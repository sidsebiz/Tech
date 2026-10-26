#!/usr/bin/env node
/**
 * Minifies theme/plugin CSS and JS into *.min.* siblings.
 * Source files are authored as plain, dependency-free browser code (no JSX / bundling),
 * so WordPress can also load the unminified files directly when SCRIPT_DEBUG is true.
 */
import { build, context } from 'esbuild';
import { readdirSync, statSync } from 'node:fs';
import { join, extname } from 'node:path';

const roots = [
	'wp-content/themes/techvenza/assets',
	'wp-content/plugins/techvenza-core/assets',
	'wp-content/plugins/techvenza-core/blocks',
];
const files = [];
const walk = ( dir ) => {
	for ( const name of readdirSync( dir ) ) {
		const p = join( dir, name );
		if ( statSync( p ).isDirectory() ) {
			if ( name !== 'node_modules' && name !== 'fonts' ) walk( p );
		} else if ( [ '.js', '.css' ].includes( extname( p ) ) && ! p.includes( '.min.' ) ) {
			files.push( p );
		}
	}
};
roots.forEach( ( r ) => { try { walk( r ); } catch ( e ) { /* root may not exist yet */ } } );

const opts = ( file ) => ( {
	entryPoints: [ file ],
	outfile: file.replace( /\.(js|css)$/, '.min.$1' ),
	minify: true,
	bundle: false,
	target: [ 'es2019', 'chrome90', 'safari14', 'firefox90' ],
	legalComments: 'none',
	logLevel: 'error',
} );

if ( process.argv.includes( '--watch' ) ) {
	for ( const f of files ) ( await context( opts( f ) ) ).watch();
	console.log( `Watching ${ files.length } files…` );
} else {
	await Promise.all( files.map( ( f ) => build( opts( f ) ) ) );
	console.log( `Minified ${ files.length } files.` );
}
