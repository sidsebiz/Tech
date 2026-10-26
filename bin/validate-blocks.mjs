#!/usr/bin/env node
// Sanity-check every block.json: valid JSON, apiVersion 3, name namespace, render file present.
import { readdirSync, readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
const dir = 'wp-content/plugins/techvenza-core/blocks';
let errors = 0;
for ( const name of readdirSync( dir, { withFileTypes: true } ).filter( ( d ) => d.isDirectory() ).map( ( d ) => d.name ) ) {
	const f = join( dir, name, 'block.json' );
	if ( ! existsSync( f ) ) continue;
	try {
		const b = JSON.parse( readFileSync( f, 'utf8' ) );
		if ( b.apiVersion !== 3 ) throw new Error( 'apiVersion must be 3' );
		if ( ! b.name?.startsWith( 'techvenza/' ) ) throw new Error( 'name must be techvenza/*' );
		if ( b.render && ! existsSync( join( dir, name, b.render.replace( 'file:./', '' ) ) ) ) throw new Error( 'render file missing' );
	} catch ( e ) {
		errors++;
		console.error( `✖ ${ f }: ${ e.message }` );
	}
}
if ( errors ) process.exit( 1 );
console.log( 'All block.json files valid.' );
