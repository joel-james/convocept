/**
 * End-to-end tests for the server-rendered thread (M1).
 *
 * Runs on a classic theme (Twenty Twenty-One) and a block theme (Twenty
 * Twenty-Five). Every page here works without JavaScript, so the no-JS
 * tests use a context with JavaScript disabled.
 */

/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const THEMES = [ 'twentytwentyone', 'twentytwentyfive' ];

/**
 * Create a published post with comments through the REST API.
 *
 * @param {Object} requestUtils Request utilities fixture.
 * @param {number} count        Number of top-level comments.
 * @return {Promise<Object>} The created post.
 */
async function createPostWithComments( requestUtils, count ) {
	const post = await requestUtils.createPost( {
		title: `Convocept thread ${ Date.now() }`,
		status: 'publish',
	} );

	for ( let i = 1; i <= count; i++ ) {
		const comment = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/comments',
			data: {
				post: post.id,
				content: `Top level comment ${ i }`,
				author_name: `Reader ${ i }`,
				author_email: `reader${ i }@example.com`,
				// A different IP keeps core flood control from blocking the
				// no-JS submission test that follows.
				author_ip: '203.0.113.10',
				status: 'approved',
			},
		} );

		if ( i === 1 ) {
			await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/comments',
				data: {
					post: post.id,
					parent: comment.id,
					content: 'A nested reply',
					author_ip: '203.0.113.10',
					status: 'approved',
				},
			} );
		}
	}

	return post;
}

for ( const theme of THEMES ) {
	test.describe( `Thread on ${ theme }`, () => {
		test.beforeAll( async ( { requestUtils } ) => {
			await requestUtils.activatePlugin( 'convocept' );
			await requestUtils.activateTheme( theme );
			await requestUtils.deleteAllComments();
		} );

		test( 'renders one server-side thread without core comment markup', async ( {
			page,
			requestUtils,
		} ) => {
			const post = await createPostWithComments( requestUtils, 3 );

			await page.goto( post.link );

			const thread = page.locator( 'section.convocept' );
			await expect( thread ).toHaveCount( 1 );
			await expect( thread.locator( '.convocept-title' ) ).toHaveText(
				'4 comments'
			);
			await expect(
				thread.locator( '.convocept-replies .convocept-comment' )
			).toHaveCount( 1 );
			await expect( page.locator( '#commentform' ) ).toHaveCount( 1 );
			await expect(
				page.locator( '.wp-block-comments, .comment-list' )
			).toHaveCount( 0 );
			await expect(
				page.locator( 'script[src*="comment-reply"]' )
			).toHaveCount( 0 );
		} );

		test( 'sort links reorder comments', async ( {
			page,
			requestUtils,
		} ) => {
			const post = await createPostWithComments( requestUtils, 3 );

			await page.goto( post.link );
			await page.getByRole( 'link', { name: 'Newest' } ).click();

			await expect( page ).toHaveURL( /convocept_sort=newest/ );
			await expect(
				page.locator( '.convocept-list > .convocept-comment' ).first()
			).toContainText( 'Top level comment 3' );
		} );
	} );
}

test.describe( 'Without JavaScript', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'convocept' );
		await requestUtils.activateTheme( 'twentytwentyfive' );
	} );

	test( 'a guest can post a comment through the plain form', async ( {
		browser,
		requestUtils,
	} ) => {
		const post = await createPostWithComments( requestUtils, 1 );
		const context = await browser.newContext( {
			javaScriptEnabled: false,
			storageState: { cookies: [], origins: [] },
		} );
		const page = await context.newPage();

		await page.goto( post.link );
		await page.locator( '#comment' ).fill( 'Posted without JavaScript' );
		await page.locator( '#author' ).fill( 'No JS Guest' );
		await page.locator( '#email' ).fill( 'nojs@example.com' );
		await page.locator( '#commentform button[type="submit"]' ).click();
		await page.waitForLoadState();

		const held = await requestUtils.rest( {
			path: '/wp/v2/comments',
			params: {
				post: post.id,
				status: 'all',
				search: 'without JavaScript',
			},
		} );

		expect( held ).toHaveLength( 1 );
		await context.close();
	} );

	test( 'a logged-in user can post and sees the comment', async ( {
		browser,
		requestUtils,
	} ) => {
		const post = await createPostWithComments( requestUtils, 1 );
		const context = await browser.newContext( {
			javaScriptEnabled: false,
			storageState: process.env.STORAGE_STATE_PATH,
		} );
		const page = await context.newPage();

		await page.goto( post.link );
		await page
			.locator( '#comment' )
			.fill( 'Admin reply without JavaScript' );
		await page.locator( '#commentform button[type="submit"]' ).click();
		await page.waitForLoadState();

		await expect( page.locator( 'section.convocept' ) ).toContainText(
			'Admin reply without JavaScript'
		);
		await context.close();
	} );
} );
