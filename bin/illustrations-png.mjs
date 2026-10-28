/**
 * Render 1200×630 JPEG social cards from the SVG illustrations (variant 1 of each category,
 * page illustrations and the default). Open Graph / X / RSS / email clients do not accept SVG.
 *
 *   node bin/illustrations-png.mjs   (needs Playwright's Chromium: npx playwright install chromium)
 */
import { chromium } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const root = path.dirname( path.dirname( fileURLToPath( import.meta.url ) ) );
const dir = path.join( root, 'wp-content/plugins/techvenza-core/assets/illustrations' );
const out = path.join( dir, 'social' );
fs.mkdirSync( out, { recursive: true } );
const files = fs.readdirSync( dir ).filter( ( f ) => f.endsWith( '.svg' ) && ( f.endsWith( '-1.svg' ) || ! /-\d\.svg$/.test( f ) ) );
const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 1200, height: 630 } } );
for ( const f of files ) {
	const svg = fs.readFileSync( path.join( dir, f ), 'utf8' );
	await page.setContent( `<body style="margin:0"><div style="width:1200px;height:630px;overflow:hidden">${ svg.replace( '<svg ', '<svg preserveAspectRatio="xMidYMid slice" style="width:1200px;height:630px" ' ) }</div></body>` );
	await page.screenshot( { path: path.join( out, f.replace( /(-1)?\.svg$/, '.jpg' ) ), type: 'jpeg', quality: 80 } );
}
await browser.close();
console.log( `${ files.length } social cards written to ${ out }` );
