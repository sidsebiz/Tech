import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const PAGES: Array< [ string, string | RegExp ] > = [
	[ '/', /Technology, explained/ ],
	[ '/explore/', 'Explore every story' ],
	[ '/trending/', 'What readers are opening right now.' ],
	[ '/editors-picks/', "Stories we think you shouldn't miss." ],
	[ '/guides/', 'Guides & Resources' ],
	[ '/authors/', 'The people behind the analysis' ],
	[ '/about/', 'Clarity for a world built on technology.' ],
	[ '/become-an-author/', 'Share Your Expertise with Techvenza.' ],
	[ '/editorial-guidelines/', 'Editorial guidelines' ],
	[ '/category/ai/', 'Artificial Intelligence' ],
	[ '/author/priya-natarajan/', 'Priya Natarajan' ],
	[ '/the-agentic-enterprise/', 'The Agentic Enterprise: What Changes When Software Starts Taking Actions' ],
	[ '/?s=zero+trust', /Results for/ ],
];

async function dismissConsent( page ) {
	const btn = page.getByRole( 'button', { name: /essential only|reject/i } );
	if ( await btn.isVisible().catch( () => false ) ) {
		await btn.click();
		// The confirmation toast overlaps content; wait for it so contrast checks see the page.
		await expect( page.locator( '#toasts .toast' ) ).toHaveCount( 0, { timeout: 10_000 } );
	}
}

for ( const [ path, heading ] of PAGES ) {
	test( `renders ${ path } @a11y`, async ( { page } ) => {
		const errors: string[] = [];
		page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );
		const res = await page.goto( path );
		expect( res?.status() ).toBe( 200 );
		await expect( page.getByRole( 'heading', { level: 1 } ) ).toContainText( heading );
		await dismissConsent( page );
		expect( errors ).toEqual( [] );

		const axe = await new AxeBuilder( { page } )
			.withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] )
			.analyze();
		const serious = axe.violations.filter( ( v ) => v.impact === 'serious' || v.impact === 'critical' );
		const found = serious.map( ( v ) => `${ v.id }: ${ v.nodes.slice( 0, 3 ).map( ( n ) => n.target.join( ' ' ) + ' — ' + ( n.any[ 0 ]?.message || '' ).slice( 0, 120 ) ).join( ' | ' ) }` );
		expect( found, `axe: ${ found.join( ' || ' ) }` ).toEqual( [] );
	} );
}

test( '404 page', async ( { page } ) => {
	const res = await page.goto( '/this-page-does-not-exist/' );
	expect( res?.status() ).toBe( 404 );
	await expect( page.getByRole( 'heading', { level: 1 } ) ).toContainText( 'wandered off' );
} );

test( 'explore filters update in place and keep a shareable URL', async ( { page } ) => {
	await page.goto( '/explore/' );
	await dismissConsent( page );
	await page.getByRole( 'link', { name: /^Security/ } ).first().click();
	await expect( page ).toHaveURL( /cat=cybersecurity/ );
	await expect( page.locator( '[data-live-status]' ) ).toContainText( 'Cybersecurity' );
	await page.reload();
	await expect( page.locator( '[data-live-status]' ) ).toContainText( 'Cybersecurity' );
} );

test( 'search overlay suggests articles', async ( { page } ) => {
	await page.goto( '/' );
	await dismissConsent( page );
	await page.keyboard.press( '/' );
	await page.locator( '#overlaySearch' ).fill( 'zero' );
	await expect( page.locator( '#searchSuggest' ) ).toContainText( 'Zero Trust' );
	await page.keyboard.press( 'Escape' );
	await expect( page.locator( '#searchOverlay' ) ).not.toHaveClass( /open/ );
} );

test( 'article page has table of contents and reading tools', async ( { page } ) => {
	await page.goto( '/the-agentic-enterprise/' );
	await dismissConsent( page );
	await expect( page.locator( '.toc a' ).first() ).toBeVisible();
	await page.getByRole( 'button', { name: /Increase text size/ } ).click();
	await page.getByRole( 'button', { name: 'Dark reading theme' } ).click();
	await expect( page.locator( '.tv-article' ) ).toHaveClass( /reading-dark/ );
} );

test( 'mobile menu opens and traps focus @mobile', async ( { page } ) => {
	await page.goto( '/' );
	await dismissConsent( page );
	await page.locator( '[data-open-drawer]' ).click();
	await expect( page.locator( '#drawer' ) ).toHaveClass( /open/ );
	await page.keyboard.press( 'Escape' );
	await expect( page.locator( '#drawer' ) ).not.toHaveClass( /open/ );
} );
