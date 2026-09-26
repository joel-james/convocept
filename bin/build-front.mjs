/**
 * Builds the public front-end assets with esbuild.
 *
 * Outputs to assets/dist/:
 * - loader.js     Inlined into the thread wrapper by PHP (budget < 1 KB gzipped).
 * - convocept.js  Lazy-loaded interaction bundle (budget 8 KB gzipped).
 * - convocept.css Lazy-loaded styles (budget 5 KB gzipped).
 *
 * Usage: node bin/build-front.mjs [--watch]
 */
import * as esbuild from 'esbuild';

const watch = process.argv.includes( '--watch' );

/** @type {import('esbuild').BuildOptions} */
const options = {
	entryPoints: [
		'assets/src/front/loader.js',
		'assets/src/front/convocept.js',
		'assets/src/front/convocept.css',
	],
	outdir: 'assets/dist',
	entryNames: '[name]',
	bundle: true,
	minify: true,
	format: 'iife',
	target: [ 'es2019', 'chrome80', 'firefox78', 'safari13' ],
	legalComments: 'none',
	logLevel: 'info',
};

if ( watch ) {
	const context = await esbuild.context( options );
	await context.watch();
} else {
	await esbuild.build( options );
}
