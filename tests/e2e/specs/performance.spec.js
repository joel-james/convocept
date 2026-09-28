/**
 * End-to-end tests for lazy loading and live updates (M4).
 */

/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Create a long post so the thread starts well below the fold.
 *
 * @param {Object} requestUtils Request utilities fixture.
 * @return {Promise<Object>} The post.
 */
function longPost( requestUtils ) {
	return requestUtils.createPost( {
		title: `Long ${ Date.now() }`,
		status: 'publish',
		content:
			'<!-- wp:paragraph --><p>Paragraph of a long article.</p><!-- /wp:paragraph -->'.repeat(
				120
			),
	} );
}

test.describe( 'Lazy loading', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin(
			'convocept-fast-native-comment-system'
		);
		await requestUtils.activateTheme( 'twentytwentyfive' );
	} );

	test( 'no Convocept request is made until the reader nears the comments', async ( {
		browser,
		requestUtils,
	} ) => {
		const post = await longPost( requestUtils );
		const context = await browser.newContext( {
			storageState: { cookies: [], origins: [] },
		} );
		const page = await context.newPage();
		const requests = [];

		page.on( 'request', ( request ) => {
			if ( /convocept/.test( request.url() ) ) {
				requests.push( request.url() );
			}
		} );

		await page.goto( post.link );
		// Give idle callbacks and timers a moment: nothing may load on its own.
		await page.evaluate(
			() => new Promise( ( resolve ) => setTimeout( resolve, 1500 ) )
		);

		expect( requests ).toEqual( [] );
		await expect( page.locator( 'head #convocept-css' ) ).toHaveCount( 0 );
		await expect(
			page.locator( 'script[src*="comment-reply"]' )
		).toHaveCount( 0 );
		await expect( page.locator( 'script[src*="jquery"]' ) ).toHaveCount(
			0
		);

		await page.locator( '#comments' ).scrollIntoViewIfNeeded();
		await expect(
			page.locator( '[data-convocept-toolbar]' )
		).toBeVisible();
		expect(
			requests.some( ( url ) => url.includes( 'convocept.js' ) )
		).toBe( true );
		await context.close();
	} );

	test( 'a link to the comments loads the script immediately', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await longPost( requestUtils );
		await page.goto( `${ post.link }#comments` );

		await expect(
			page.locator( '[data-convocept-toolbar]' )
		).toBeVisible();
	} );
} );

test.describe( 'Live updates', () => {
	test( 'new comments appear, and unchanged checks are 304s', async ( {
		page,
		requestUtils,
	} ) => {
		await requestUtils.activatePlugin(
			'convocept-fast-native-comment-system'
		);
		const post = await requestUtils.createPost( {
			title: `Live ${ Date.now() }`,
			status: 'publish',
		} );
		const statuses = [];

		page.on( 'response', ( response ) => {
			if ( decodeURIComponent( response.url() ).includes( '/updates' ) ) {
				statuses.push( response.status() );
			}
		} );

		// Test-only switch (see tests/e2e/mu-plugins) for a 1-second interval.
		const url = new URL( post.link );
		url.searchParams.set( 'convocept_e2e_poll', '1' );
		url.hash = 'comments';
		await page.goto( url.href );
		await expect(
			page.locator( '[data-convocept-toolbar]' )
		).toBeVisible();

		await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/comments',
			data: {
				post: post.id,
				content: 'Arrived while reading',
				author_name: 'Someone',
				author_email: 'someone@example.com',
				author_ip: '203.0.113.30',
				status: 'approved',
			},
		} );

		await expect( page.locator( '.convocept-comment--new' ) ).toContainText(
			'Arrived while reading',
			{ timeout: 10000 }
		);
		await expect( page.locator( '.convocept-title' ) ).toHaveText(
			'1 comment'
		);

		await expect
			.poll(
				() => statuses.filter( ( status ) => status === 304 ).length,
				{
					timeout: 10000,
				}
			)
			.toBeGreaterThan( 0 );
	} );
} );
