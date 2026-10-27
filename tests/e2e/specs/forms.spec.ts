import { test, expect, Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const PASSWORD = process.env.E2E_PASSWORD || 'e2e-Techvenza-2026';
const ADMIN = 'admin-demo@example.com';
const stamp = Date.now();

async function expectAccessible( page: Page ) {
	const axe = await new AxeBuilder( { page } ).withTags( [ 'wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa' ] ).analyze();
	const serious = axe.violations.filter( ( v ) => v.impact === 'serious' || v.impact === 'critical' );
	const found = serious.map( ( v ) => `${ v.id }: ${ v.nodes.slice( 0, 3 ).map( ( n ) => n.target.join( ' ' ) + ' — ' + ( n.any[ 0 ]?.message || '' ).slice( 0, 120 ) ).join( ' | ' ) }` );
	expect( found, `axe: ${ found.join( ' || ' ) }` ).toEqual( [] );
}

async function login( page: Page, email: string ) {
	await page.goto( '/login/' );
	await page.fill( '#liEmail', email );
	await page.fill( '#liPw', PASSWORD );
	await page.locator( 'form[data-endpoint="auth/login"] button[type=submit]' ).click();
	await page.waitForURL( ( u ) => ! u.pathname.startsWith( '/login' ) );
}

for ( const path of [ '/newsletter/', '/contact/', '/apply/' ] ) {
	test( `${ path } passes axe`, async ( { page } ) => {
		await page.goto( path );
		await expect( page.locator( 'form[data-tv-form]' ).first() ).toBeVisible();
		await expectAccessible( page );
	} );
}

test( 'contact form validates, sends and lands in the admin inbox', async ( { page } ) => {
	await page.goto( '/contact/' );
	await page.locator( 'form[data-endpoint="forms/contact"] button[type=submit]' ).click();
	await expect( page.locator( '[data-field="type"]' ) ).toHaveClass( /invalid/ );
	await expect( page.locator( '#ctType' ) ).toBeFocused();

	const subject = `E2E question ${ stamp }`;
	await page.selectOption( '#ctType', 'editorial' );
	await page.fill( '#ctName', 'Erin Tester' );
	await page.fill( '#ctEmail', `erin.${ stamp }@example.com` );
	await page.fill( '#ctSubject', subject );
	await page.fill( '#ctMsg', 'This is an end-to-end test message with enough characters.' );
	await page.locator( '[data-field="consent"] input' ).check();
	await page.locator( 'form[data-endpoint="forms/contact"] button[type=submit]' ).click();
	await expect( page.locator( '.success-state' ) ).toContainText( 'Message sent' );

	await page.context().clearCookies();
	await login( page, ADMIN );
	await page.goto( '/wp-admin/admin.php?page=techvenza-messages' );
	await page.getByRole( 'link', { name: subject } ).click();
	await expect( page.locator( '.tv-admin' ) ).toContainText( 'Erin Tester' );
	await page.getByRole( 'button', { name: 'Mark as handled' } ).click();
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
} );

test( 'author application is stored with a reference and listed for editors', async ( { page } ) => {
	await page.goto( '/apply/' );
	await page.fill( '#apName', 'Avery Applicant' );
	await page.fill( '#apEmail', `avery.${ stamp }@example.com` );
	await page.fill( '#apTitle', 'Staff Engineer' );
	await page.fill( '#apExp', 'Platform engineering, Kubernetes' );
	await page.locator( '[data-field="cats"] input' ).first().check();
	await page.fill( '#apBio', 'Avery is a staff engineer who has spent a decade building internal developer platforms at scale.' );
	await page.fill( '#apLink', 'https://example.com/avery' );
	await page.fill( '#apSamples', 'https://example.com/post-1' );
	await page.fill( '#apReason', 'I want to share practical lessons about platform teams with other practitioners.' );
	await page.locator( '[data-field="agree"] input' ).check();
	await page.locator( '[data-field="privacy"] input' ).check();
	await page.locator( 'form[data-endpoint="forms/apply"] button[type=submit]' ).click();
	await expect( page.locator( '.success-state' ) ).toContainText( /TV-[A-Z0-9]{6}/ );

	await page.context().clearCookies();
	await login( page, ADMIN );
	await page.goto( '/wp-admin/admin.php?page=techvenza-applications' );
	await expect( page.locator( '.tv-admin' ) ).toContainText( 'Avery Applicant' );
} );

test( 'newsletter sign-up asks for confirmation and appears as awaiting confirmation', async ( { page } ) => {
	const email = `nora.${ stamp }@example.com`;
	await page.goto( '/newsletter/' );
	await page.fill( '#nlEmail', email );
	await page.locator( '[data-field="consent"] input' ).check();
	await page.locator( 'form[data-endpoint="newsletter/subscribe"] button[type=submit]' ).click();
	await expect( page.locator( '.success-state' ) ).toContainText( 'Check your inbox' );

	await page.context().clearCookies();
	await login( page, ADMIN );
	await page.goto( `/wp-admin/admin.php?page=techvenza-newsletter&q=${ encodeURIComponent( email ) }` );
	const row = page.locator( 'tr', { hasText: email } );
	await expect( row ).toContainText( 'Pending' );
} );

test( 'campaign drafts can be saved and previewed', async ( { page } ) => {
	await login( page, ADMIN );
	await page.goto( '/wp-admin/admin.php?page=techvenza-newsletter&tab=compose' );
	await page.fill( '#cpName', `E2E campaign ${ stamp }` );
	await page.fill( '#cpSubj', 'Three stories worth your time' );
	await page.locator( 'input[name="posts[]"]' ).first().check();
	await page.getByRole( 'button', { name: 'Save draft' } ).click();
	await expect( page.locator( '.notice-success' ) ).toContainText( 'Draft saved' );
	const preview = page.locator( 'a', { hasText: 'Preview' } ).first();
	const href = await preview.getAttribute( 'href' );
	const res = await page.request.get( href || '' );
	expect( res.status() ).toBe( 200 );
	expect( await res.text() ).toContain( 'Three stories worth your time' );
} );
