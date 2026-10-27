import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

// Start without the pre-set privacy choice from playwright.config.ts.
test.use( { storageState: { cookies: [], origins: [] } } );

test( 'consent banner records the choice and can be reopened', async ( { page, context } ) => {
	await page.goto( '/' );
	const banner = page.locator( '#cookieBanner' );
	await expect( banner ).toBeVisible();
	await expect( page.locator( '#cookieBanner button' ).first() ).not.toBeFocused();

	const axe = await new AxeBuilder( { page } ).include( '#cookieBanner' ).withTags( [ 'wcag2a', 'wcag2aa' ] ).analyze();
	expect( axe.violations.map( ( v ) => v.id ) ).toEqual( [] );

	await banner.getByRole( 'button', { name: 'Essential only' } ).click();
	await expect( banner ).toBeHidden();
	const cookie = ( await context.cookies() ).find( ( c ) => c.name === 'tv_consent' );
	expect( cookie?.value ).toBe( 'essential' );

	await page.reload();
	await expect( banner ).toBeHidden();

	await page.locator( '[data-cookie-prefs]' ).first().click();
	await expect( banner ).toBeVisible();
	await expect( banner.getByRole( 'button', { name: 'Essential only' } ) ).toBeFocused();
	await banner.getByRole( 'button', { name: 'Allow all' } ).click();
	expect( ( await context.cookies() ).find( ( c ) => c.name === 'tv_consent' )?.value ).toBe( 'all' );
} );

test( 'pages expose canonical, description and structured data', async ( { page } ) => {
	await page.goto( '/' );
	await expect( page.locator( 'link[rel="canonical"]' ) ).toHaveCount( 1 );
	await expect( page.locator( 'meta[name="description"]' ) ).toHaveCount( 1 );
	const ld = await page.locator( 'script[type="application/ld+json"]' ).allTextContents();
	expect( ld.join( '' ) ).toContain( '"WebSite"' );

	await page.goto( '/login/' );
	await expect( page.locator( 'meta[name="robots"]' ) ).toHaveAttribute( 'content', /noindex/ );
} );
