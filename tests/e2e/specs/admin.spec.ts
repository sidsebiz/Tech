import { test, expect, Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const PASSWORD = process.env.E2E_PASSWORD || 'e2e-Techvenza-2026';
const ADMIN = 'admin-demo@example.com';
const EDITOR = 'ananya-rao@example.com';

async function login( page: Page, email: string ) {
	await page.goto( '/login/' );
	await page.fill( '#liEmail', email );
	await page.fill( '#liPw', PASSWORD );
	await page.locator( 'form[data-endpoint="auth/login"] button[type=submit]' ).click();
	await page.waitForURL( ( u ) => ! u.pathname.startsWith( '/login' ) );
}

async function expectAccessible( page: Page ) {
	const axe = await new AxeBuilder( { page } ).include( '.tv-admin' ).withTags( [ 'wcag2a', 'wcag2aa' ] ).analyze();
	const serious = axe.violations.filter( ( v ) => v.impact === 'serious' || v.impact === 'critical' );
	const found = serious.map( ( v ) => `${ v.id }: ${ v.nodes.slice( 0, 3 ).map( ( n ) => n.target.join( ' ' ) ).join( ' | ' ) }` );
	expect( found, `axe: ${ found.join( ' || ' ) }` ).toEqual( [] );
}

const SCREENS: Array< [ string, string ] > = [
	[ 'techvenza', 'Editorial dashboard' ],
	[ 'techvenza-review', '' ],
	[ 'techvenza-calendar', '' ],
	[ 'techvenza-applications', '' ],
	[ 'techvenza-authors', 'Authors' ],
	[ 'techvenza-analytics', 'Analytics' ],
	[ 'techvenza-roles', 'Roles & permissions' ],
	[ 'techvenza-newsletter', 'Newsletter' ],
	[ 'techvenza-messages', 'Contact messages' ],
	[ 'techvenza-settings', 'Site settings' ],
];

test( 'editorial admin screens render without errors and pass axe', async ( { page } ) => {
	await login( page, ADMIN );
	for ( const [ slug, title ] of SCREENS ) {
		await page.goto( `/wp-admin/admin.php?page=${ slug }` );
		await expect( page.locator( '.tv-admin h1' ) ).toBeVisible();
		if ( title ) {
			await expect( page.locator( '.tv-admin h1' ) ).toHaveText( title );
		}
		await expect( page.locator( 'body' ) ).not.toContainText( /Fatal error|Warning:|Notice:/ );
		await expectAccessible( page );
	}
} );

test( 'posts list shows workflow, editor and views columns', async ( { page } ) => {
	await login( page, ADMIN );
	await page.goto( '/wp-admin/edit.php' );
	await expect( page.locator( 'th#tv_status' ) ).toHaveText( 'Workflow' );
	await expect( page.locator( 'th#tv_views' ) ).toContainText( 'Views' );
	await expect( page.locator( '.subsubsub' ) ).toContainText( 'New submission' );
} );

test( 'requesting changes requires a note', async ( { page } ) => {
	await login( page, EDITOR );
	await page.goto( '/wp-admin/admin.php?page=techvenza-review' );
	await page.locator( '.queue a[href*="&id="]' ).first().click();
	const request = page.locator( 'button[name="transition"][data-needs-note]' ).first();
	await expect( request ).toBeVisible();
	const before = page.url();
	await request.click();
	await expect( page.locator( '#tvNote' ) ).toHaveAttribute( 'aria-invalid', 'true' );
	expect( page.url() ).toBe( before );
} );

test( 'editors cannot open the roles screen', async ( { page } ) => {
	await login( page, EDITOR );
	const res = await page.goto( '/wp-admin/admin.php?page=techvenza-roles' );
	expect( res?.status() ).toBe( 403 );
} );

test( 'approved articles can be scheduled from the calendar endpoint', async ( { page } ) => {
	await login( page, ADMIN );
	await page.goto( '/wp-admin/admin.php?page=techvenza-calendar' );
	const item = page.locator( '[data-tray] .cal-item' ).first();
	test.skip( ( await item.count() ) === 0, 'No approved article to schedule.' );
	const id = Number( await item.getAttribute( 'data-id' ) );
	const date = new Date( Date.now() + 5 * 86400000 ).toISOString().slice( 0, 10 );
	const res = await page.evaluate(
		( args ) =>
			( window as any ).wp.apiFetch( { path: '/techvenza/v1/calendar/move', method: 'POST', data: args } ),
		{ id, date }
	);
	expect( res.id ).toBe( id );
	// Open the month that contains the new date (it may be next month near month end).
	await page.goto( `/wp-admin/admin.php?page=techvenza-calendar&m=${ date.slice( 0, 7 ) }` );
	await expect( page.locator( `.day[data-date="${ date }"] .cal-item[data-id="${ id }"]` ) ).toHaveCount( 1 );
	await expect( page.locator( `[data-tray] .cal-item[data-id="${ id }"]` ) ).toHaveCount( 0 );
} );

test( 'author flags toggle and persist', async ( { page } ) => {
	await login( page, ADMIN );
	await page.goto( '/wp-admin/admin.php?page=techvenza-authors' );
	const row = page.locator( 'tr', { hasText: 'Hana Kim' } );
	const flag = row.locator( 'button[aria-label^="Toggle Featured"]' );
	const before = ( await flag.textContent() )?.trim();
	await flag.click();
	await expect( page.locator( '.notice-success' ) ).toContainText( 'Author updated' );
	const after = ( await page.locator( 'tr', { hasText: 'Hana Kim' } ).locator( 'button[aria-label^="Toggle Featured"]' ).textContent() )?.trim();
	expect( after ).not.toBe( before );
	await page.locator( 'tr', { hasText: 'Hana Kim' } ).locator( 'button[aria-label^="Toggle Featured"]' ).click();
	await expect( page.locator( 'tr', { hasText: 'Hana Kim' } ).locator( 'button[aria-label^="Toggle Featured"]' ) ).toHaveText( before || '' );
} );

test( 'settings save, keep secrets write-only and stay accessible', async ( { page } ) => {
	await login( page, ADMIN );
	await page.goto( '/wp-admin/admin.php?page=techvenza-settings&tab=brand' );
	await expectAccessible( page );
	const field = page.locator( '#tvs-tagline' );
	const original = await field.inputValue();
	await field.fill( 'E2E tagline' );
	await page.getByRole( 'button', { name: 'Save changes' } ).click();
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
	await expect( page.locator( '#tvs-tagline' ) ).toHaveValue( 'E2E tagline' );
	await page.locator( '#tvs-tagline' ).fill( original );
	await page.getByRole( 'button', { name: 'Save changes' } ).click();

	await page.goto( '/wp-admin/admin.php?page=techvenza-settings&tab=newsletter' );
	await expect( page.locator( '#tvs-brevo_api_key' ) ).toHaveAttribute( 'type', 'password' );
	await expect( page.locator( '#tvs-brevo_api_key' ) ).toHaveValue( '' );
} );
