import { test, expect, Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

// Demo accounts are seeded with a fixed password in CI (`wp techvenza seed --demo-password=…`).
const PASSWORD = process.env.E2E_PASSWORD || 'e2e-Techvenza-2026';

async function expectAccessible( page: Page ) {
	const axe = await new AxeBuilder( { page } ).withTags( [ 'wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa' ] ).analyze();
	const serious = axe.violations.filter( ( v ) => v.impact === 'serious' || v.impact === 'critical' );
	const found = serious.map( ( v ) => `${ v.id }: ${ v.nodes.slice( 0, 3 ).map( ( n ) => n.target.join( ' ' ) + ' — ' + ( n.any[ 0 ]?.message || '' ).slice( 0, 120 ) ).join( ' | ' ) }` );
		expect( found, `axe: ${ found.join( ' || ' ) }` ).toEqual( [] );
}

async function login( page: Page, email: string, password = PASSWORD ) {
	await page.goto( '/login/' );
	await page.fill( '#liEmail', email );
	await page.fill( '#liPw', password );
	await page.locator( 'form[data-endpoint="auth/login"] button[type=submit]' ).click();
}

test( 'logged-out visitors are sent to the login page with a return address', async ( { page } ) => {
	await page.goto( '/dashboard/' );
	await expect( page ).toHaveURL( /\/login\/\?next=/ );
	await expect( page.getByRole( 'heading', { level: 1 } ) ).toHaveText( 'Welcome back' );
} );

test( 'wrong password shows an inline error', async ( { page } ) => {
	await login( page, 'reader@example.com', 'not-the-password-1' );
	await expect( page.locator( '[data-field="password"]' ) ).toHaveClass( /invalid/ );
	await expect( page ).toHaveURL( /\/login\// );
} );

test( 'reader signs in, sees the dashboard and saves topics', async ( { page } ) => {
	await login( page, 'reader@example.com' );
	await expect( page ).toHaveURL( /\/dashboard\// );
	await expect( page.getByRole( 'heading', { level: 1 } ) ).toContainText( 'Welcome back' );
	await expectAccessible( page );
	await page.getByRole( 'link', { name: 'Preferences & profile' } ).click();
	await expectAccessible( page );
	await page.getByLabel( 'Cloud Computing' ).check();
	await page.getByRole( 'button', { name: 'Save topics' } ).click();
	await expect( page.locator( '#toasts' ) ).toContainText( 'Saved' );
	await page.reload();
	await expect( page.getByLabel( 'Cloud Computing' ) ).toBeChecked();
} );

test( 'readers cannot open the author portal', async ( { page } ) => {
	await login( page, 'reader@example.com' );
	await page.waitForURL( /\/dashboard\// );
	await page.goto( '/author-portal/' );
	await expect( page.getByRole( 'heading', { level: 1 } ) ).toHaveText( 'Access restricted' );
} );

test( 'author portal lists articles and submission history', async ( { page } ) => {
	await login( page, 'priya-natarajan@example.com' );
	await expect( page ).toHaveURL( /\/author-portal\// );
	await expectAccessible( page );
	await page.getByRole( 'link', { name: 'My articles' } ).first().click();
	await expectAccessible( page );
	await expect( page.locator( 'table.dt tbody tr' ).first() ).toBeVisible();
	await page.getByRole( 'link', { name: /Submission details for Observability Budgets/ } ).click();
	await expect( page.getByRole( 'heading', { name: 'History' } ) ).toBeVisible();
	await expect( page.locator( '.timeline li' ) ).not.toHaveCount( 0 );
} );

test( 'registration asks for email confirmation', async ( { page } ) => {
	const email = `e2e-${ Date.now() }@example.com`;
	await page.goto( '/register/' );
	await page.fill( '#rgName', 'E2E Reader' );
	await page.fill( '#rgEmail', email );
	await page.fill( '#rgPw', 'a-strong-password-42' );
	await page.fill( '#rgPw2', 'a-strong-password-42' );
	await page.locator( 'input[name="terms"]' ).check();
	await page.getByRole( 'button', { name: 'Create account' } ).click();
	await expect( page.getByRole( 'heading', { name: 'Check your email' } ) ).toBeVisible();

	// Unverified accounts cannot sign in yet.
	await login( page, email, 'a-strong-password-42' );
	await expect( page.locator( '[data-form-error]' ) ).toContainText( 'confirm your email' );
} );

test( 'forgot password never reveals whether an account exists', async ( { page } ) => {
	await page.goto( '/forgot-password/' );
	await page.fill( '#fpEmail', 'nobody-here@example.com' );
	await page.getByRole( 'button', { name: 'Send reset link' } ).click();
	await expect( page.getByRole( 'heading', { name: 'Check your email' } ) ).toBeVisible();
} );

test( 'an invalid reset link shows the expired state', async ( { page } ) => {
	await page.goto( '/reset-password/?key=bad&login=reader' );
	await expect( page.getByRole( 'heading', { level: 1 } ) ).toHaveText( 'This reset link has expired' );
} );
