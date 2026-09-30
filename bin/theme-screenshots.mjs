/**
 * Screenshots the comment thread in popular WordPress.org themes.
 *
 * The thread draws its own design, so it should look the same in every
 * theme. Run this after changing the thread's CSS or markup and compare the
 * sheets in artifacts/themes/ (one PNG per theme and viewport, plus
 * sheet-*.png overviews). It also reports themes that print their own
 * comment list next to ours.
 *
 * Uses the wp-env development site (localhost:8888): installs missing
 * themes, switches between them and restores the active theme at the end.
 *
 * Usage: node bin/theme-screenshots.mjs [post-url]
 */
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync } from 'node:fs';
import { chromium } from '@playwright/test';

const THEMES = [
	'twentytwentyfive',
	'twentytwentyone',
	'astra',
	'kadence',
	'generatepress',
	'oceanwp',
	'neve',
	'blocksy',
	'hello-elementor',
	'sydney',
	'go',
	'raft',
];
const VIEWPORTS = { desktop: 1280, phone: 390 };
const OUT = 'artifacts/themes';

/**
 * Run WP-CLI on the development site.
 *
 * @param {...string} args WP-CLI arguments.
 * @return {string} Output.
 */
function wp( ...args ) {
	return execFileSync( 'npx', [ 'wp-env', 'run', 'cli', 'wp', ...args ], {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'ignore' ],
	} ).trim();
}

/**
 * Find a post with comments to show.
 *
 * @return {string} Post URL.
 */
function busiestPost() {
	const id = wp(
		'post',
		'list',
		'--post_type=post',
		'--orderby=comment_count',
		'--order=desc',
		'--posts_per_page=1',
		'--field=ID'
	);

	return wp( 'post', 'url', id );
}

/**
 * Screenshot the thread on the current theme.
 *
 * @param {Object} browser Playwright browser.
 * @param {string} url     Post URL.
 * @param {string} name    File name stem.
 * @param {number} width   Viewport width.
 * @return {Promise<Object>} What the page showed.
 */
async function shoot( browser, url, name, width ) {
	const page = await browser.newPage( { viewport: { width, height: 900 } } );

	await page.goto( url );

	// Scroll through the page so lazy avatars load.
	await page.evaluate( async () => {
		for ( let y = 0; y < document.body.scrollHeight; y += 400 ) {
			window.scrollTo( 0, y );
			await new Promise( ( resolve ) => setTimeout( resolve, 60 ) );
		}
	} );
	await page.waitForLoadState( 'networkidle' );

	const info = await page.evaluate( () => ( {
		threads: document.querySelectorAll( '.convocept' ).length,
		coreLists: document.querySelectorAll(
			'ol.comment-list, ol.commentlist, .wp-block-comment-template'
		).length,
	} ) );
	const box = await page.locator( '.convocept' ).first().boundingBox();

	if ( box ) {
		await page.screenshot( {
			path: `${ OUT }/${ name }.png`,
			fullPage: true,
			clip: {
				x: 0,
				y: Math.max( 0, box.y - 200 ),
				width,
				height: 1300,
			},
		} );
	}

	await page.close();

	return info;
}

/**
 * Put several screenshots on one sheet.
 *
 * @param {Object}   browser Playwright browser.
 * @param {string[]} names   File name stems.
 * @param {string}   file    Sheet path.
 * @return {Promise<void>}
 */
async function sheet( browser, names, file ) {
	const page = await browser.newPage( {
		viewport: { width: 1500, height: 800 },
	} );
	const cells = names
		.map(
			( name ) =>
				`<figure><figcaption>${ name }</figcaption><img src="data:image/png;base64,${ readFileSync(
					`${ OUT }/${ name }.png`
				).toString( 'base64' ) }"></figure>`
		)
		.join( '' );

	await page.setContent(
		`<style>body{margin:0;padding:8px;display:grid;grid-template-columns:repeat(3,1fr);gap:8px;font:14px sans-serif}figure{margin:0}img{width:100%;border:1px solid #ccc}</style>${ cells }`
	);
	await page.screenshot( { path: file, fullPage: true } );
	await page.close();
}

mkdirSync( OUT, { recursive: true } );

const url = process.argv[ 2 ] || busiestPost();
const original = wp( 'theme', 'list', '--status=active', '--field=name' );
const installed = wp( 'theme', 'list', '--field=name' ).split( /\s+/ );
const missing = THEMES.filter( ( theme ) => ! installed.includes( theme ) );

if ( missing.length ) {
	wp( 'theme', 'install', ...missing );
}

const browser = await chromium.launch();

try {
	for ( const theme of THEMES ) {
		wp( 'theme', 'activate', theme );

		for ( const [ viewport, width ] of Object.entries( VIEWPORTS ) ) {
			const info = await shoot(
				browser,
				url,
				`${ theme }-${ viewport }`,
				width
			);
			const problem =
				info.threads !== 1 || info.coreLists
					? '  <- check: missing thread or core comments shown too'
					: '';

			// eslint-disable-next-line no-console
			console.log(
				`${ theme } ${ viewport }: ${ JSON.stringify( info ) }${ problem }`
			);
		}
	}

	const desktop = THEMES.map( ( theme ) => `${ theme }-desktop` );

	await sheet( browser, desktop.slice( 0, 6 ), `${ OUT }/sheet-1.png` );
	await sheet( browser, desktop.slice( 6 ), `${ OUT }/sheet-2.png` );
} finally {
	await browser.close();
	wp( 'theme', 'activate', original );
}
