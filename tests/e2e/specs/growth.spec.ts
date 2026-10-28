import { test, expect, Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import path from 'path';

const PASSWORD = process.env.E2E_PASSWORD || 'e2e-Techvenza-2026';
const ADMIN = 'admin-demo@example.com';
const FIXTURES = path.join( __dirname, '..', 'fixtures' );

async function login( page: Page, email: string ) {
	await page.goto( '/login/' );
	await page.fill( '#liEmail', email );
	await page.fill( '#liPw', PASSWORD );
	await page.locator( 'form[data-endpoint="auth/login"] button[type=submit]' ).click();
	await page.waitForURL( ( u ) => ! u.pathname.startsWith( '/login' ) );
}

test.describe.configure( { mode: 'serial' } );

test( 'articles show an answer-first summary, FAQ, sources and rich structured data', async ( { page } ) => {
	await page.goto( '/the-agentic-enterprise/' );
	await expect( page.locator( '.tv-summary' ) ).toContainText( 'In short' );
	await expect( page.locator( '.tv-faq summary' ) ).toHaveCount( 3 );
	await expect( page.locator( '.tv-sources a' ).first() ).toHaveAttribute( 'href', /^https:\/\// );
	const ld = ( await page.locator( 'script[type="application/ld+json"]' ).allTextContents() ).join( '' );
	for ( const type of [ '"NewsArticle"', '"FAQPage"', '"BreadcrumbList"', '"citation"', '"abstract"', '"speakable"' ] ) {
		expect( ld ).toContain( type );
	}
	await expect( page.locator( 'link[rel="alternate"][type="text/markdown"]' ) ).toHaveCount( 1 );
	await expect( page.locator( 'meta[property="og:image"]' ) ).toHaveAttribute( 'content', /\.(jpe?g|png|webp)$/ );
	const axe = await new AxeBuilder( { page } ).include( '.tv-article-body' ).withTags( [ 'wcag2a', 'wcag2aa' ] ).analyze();
	expect( axe.violations.map( ( v ) => v.id ) ).toEqual( [] );
} );

test( 'llms.txt, Markdown versions and robots.txt serve AI crawlers', async ( { request } ) => {
	const llms = await request.get( '/llms.txt' );
	expect( llms.status() ).toBe( 200 );
	const body = await llms.text();
	expect( body ).toMatch( /^# / );
	expect( body ).toContain( '## Topics' );
	expect( body ).toContain( '/the-agentic-enterprise/md/' );

	const md = await request.get( '/the-agentic-enterprise/md/' );
	expect( md.headers()[ 'content-type' ] ).toContain( 'text/markdown' );
	expect( await md.text() ).toContain( '## Frequently asked questions' );

	const robots = await ( await request.get( '/robots.txt' ) ).text();
	expect( robots ).toContain( 'User-agent: OAI-SearchBot' );
	expect( robots ).toContain( 'Disallow: /dashboard/' );
} );

test( 'articles without a photo get a manageable illustration', async ( { page } ) => {
	await page.goto( '/' );
	const img = page.locator( '.media img' ).first();
	await expect( img ).toBeVisible();
	expect( await img.getAttribute( 'src' ) ).toMatch( /\.(svg|jpe?g|png|webp)/ );
	await expect( page.locator( '.media.is-empty' ) ).toHaveCount( 0 );
} );

test( 'Bulk Blog validates a CSV, imports articles with images and reports results', async ( { page } ) => {
	await login( page, ADMIN );
	await page.goto( '/wp-admin/admin.php?page=techvenza-bulk' );
	await expect( page.locator( '.tv-admin h1' ) ).toHaveText( 'Bulk Blog' );
	await page.setInputFiles( '#tvBulkCsv', path.join( FIXTURES, 'bulk-blog-sample.csv' ) );
	await page.setInputFiles( '#tvBulkZip', path.join( FIXTURES, 'bulk-blog-sample-images.zip' ) );
	await page.selectOption( '#tvBulkDup', 'update' );
	await page.getByRole( 'button', { name: 'Upload and check' } ).click();

	await expect( page.locator( '.tv-admin' ) ).toContainText( '3 rows: 3 ready' );
	await expect( page.locator( 'tbody tr' ) ).toHaveCount( 3 );
	await page.getByRole( 'button', { name: /Import 3 articles/ } ).click();
	await expect( page.locator( '.panel-title' ).first() ).toHaveText( 'Import finished', { timeout: 60_000 } );
	await expect( page.locator( 'tbody tr' ) ).toHaveCount( 3 );
	await expect( page.locator( 'tbody' ) ).not.toContainText( 'failed' );

	await page.goto( '/platform-teams-cut-lead-time/' );
	await expect( page.locator( 'h1' ) ).toHaveText( 'How Platform Teams Cut Lead Time in Half' );
	await expect( page.locator( '.tv-summary' ) ).toBeVisible();
	await expect( page.locator( '.tv-article-body table' ) ).toBeVisible();
	await expect( page.locator( '.tv-article-body img[src*="/wp-content/uploads/"]' ) ).toHaveCount( 1 );
} );

test( 'partners are auto-linked with the chosen link type', async ( { page } ) => {
	await login( page, ADMIN );
	await page.goto( '/wp-admin/post-new.php?post_type=tv_partner' );
	await page.fill( '#title', 'DORA Research' );
	await page.fill( '#tvPUrl', 'https://dora.dev/' );
	await page.selectOption( '#tvPRel', 'sponsored' );
	await page.fill( '#tvPKw', 'DORA research' );
	await page.locator( '#publish' ).click();
	await expect( page.locator( '#message' ) ).toBeVisible();

	await page.goto( '/platform-teams-cut-lead-time/' );
	const link = page.locator( '.tv-article-body a.tv-partner-link' ).first();
	await expect( link ).toHaveAttribute( 'href', 'https://dora.dev/' );
	await expect( link ).toHaveAttribute( 'rel', /sponsored/ );

	await page.goto( '/wp-admin/admin.php?page=techvenza-settings&tab=ai' );
	await expect( page.locator( '#tvs-llms_enabled' ) ).toBeChecked();
} );
