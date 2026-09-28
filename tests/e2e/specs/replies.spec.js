/**
 * End-to-end tests for replies, load more and deep links (M3).
 */

/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Create an approved comment through core's REST API.
 *
 * @param {Object} requestUtils Request utilities fixture.
 * @param {number} post         Post ID.
 * @param {string} content      Comment text.
 * @param {number} [parent]     Parent comment ID.
 * @return {Promise<Object>} The comment.
 */
function addComment( requestUtils, post, content, parent = 0 ) {
	return requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/comments',
		data: {
			post,
			parent,
			content,
			author_name: 'Reader',
			author_email: 'reader@example.com',
			author_ip: '203.0.113.20',
			status: 'approved',
		},
	} );
}

/**
 * Create a post with top-level comments, returning the post and comment IDs.
 *
 * @param {Object} requestUtils Request utilities fixture.
 * @param {number} count        Top-level comments to create.
 * @return {Promise<{post: Object, ids: number[]}>} Post and comment IDs.
 */
async function postWithComments( requestUtils, count ) {
	const post = await requestUtils.createPost( {
		title: `Replies ${ Date.now() }`,
		status: 'publish',
	} );
	const ids = [];

	for ( let i = 1; i <= count; i++ ) {
		ids.push(
			( await addComment( requestUtils, post.id, `Top ${ i }` ) ).id
		);
	}

	return { post, ids };
}

test.describe( 'Replies and navigation', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin(
			'convocept-fast-native-comment-system'
		);
		await requestUtils.activateTheme( 'twentytwentyfive' );
	} );

	test( 'the composer moves under a comment to reply inline', async ( {
		page,
		requestUtils,
	} ) => {
		const { post, ids } = await postWithComments( requestUtils, 2 );
		await page.goto( post.link );

		const target = page.locator( `#comment-${ ids[ 0 ] }` );
		await target
			.getByRole( 'link', { name: /Reply to/ } )
			.first()
			.click();

		await expect( target.locator( '#respond' ) ).toBeVisible();
		await expect( page.locator( '#comment' ) ).toBeFocused();
		await expect(
			page.locator( '[data-convocept-replying]' )
		).toContainText( 'Replying to Reader' );

		await page.locator( '#comment' ).fill( 'An inline reply' );
		await page.getByRole( 'button', { name: 'Post comment' } ).click();

		await expect(
			target.locator( '.convocept-replies .convocept-comment' )
		).toContainText( 'An inline reply' );
		await expect( target.locator( '#respond' ) ).toHaveCount( 0 );
		await expect(
			page.locator( '[data-convocept-replying]' )
		).toBeHidden();
	} );

	test( 'cancel returns the composer to the top', async ( {
		page,
		requestUtils,
	} ) => {
		const { post, ids } = await postWithComments( requestUtils, 1 );
		await page.goto( post.link );

		const target = page.locator( `#comment-${ ids[ 0 ] }` );
		await target.getByRole( 'link', { name: /Reply to/ } ).click();
		await page.locator( '[data-convocept-cancel-reply]' ).click();

		await expect( target.locator( '#respond' ) ).toHaveCount( 0 );
		await expect(
			page.locator( '.convocept > #respond, .convocept > * #respond' )
		).toHaveCount( 1 );
	} );

	test( 'load more appends the next page', async ( {
		page,
		requestUtils,
	} ) => {
		const { post } = await postWithComments( requestUtils, 22 );
		await page.goto( post.link );

		const items = page.locator( '.convocept-list > .convocept-comment' );
		await expect( items ).toHaveCount( 20 );
		await expect(
			page.locator( '[data-convocept-pagination]' )
		).toBeHidden();

		await page
			.getByRole( 'button', { name: 'Load more comments' } )
			.click();

		await expect( items ).toHaveCount( 22 );
		await expect(
			page.getByRole( 'button', { name: 'Load more comments' } )
		).toHaveCount( 0 );
	} );

	test( 'show more replies loads the rest of a branch', async ( {
		page,
		requestUtils,
	} ) => {
		const { post, ids } = await postWithComments( requestUtils, 1 );

		for ( let i = 1; i <= 7; i++ ) {
			await addComment( requestUtils, post.id, `Reply ${ i }`, ids[ 0 ] );
		}

		await page.goto( post.link );

		const replies = page.locator(
			`#comment-${ ids[ 0 ] } > .convocept-replies > .convocept-comment`
		);
		await expect( replies ).toHaveCount( 5 );

		await page.getByRole( 'link', { name: 'Show 2 more replies' } ).click();

		await expect( replies ).toHaveCount( 7 );
		await expect( page.locator( '.convocept-more-replies' ) ).toHaveCount(
			0
		);
	} );

	test( 'a link to a comment on another page follows it', async ( {
		page,
		requestUtils,
	} ) => {
		const { post, ids } = await postWithComments( requestUtils, 21 );

		await page.goto( `${ post.link }#comment-${ ids[ 20 ] }` );

		await expect( page ).toHaveURL( /comment-page-2|cpage=2/ );
		await expect( page.locator( `#comment-${ ids[ 20 ] }` ) ).toBeVisible();
	} );

	test( 'without JavaScript, reply links and expand links still work', async ( {
		browser,
		requestUtils,
	} ) => {
		const { post, ids } = await postWithComments( requestUtils, 1 );

		for ( let i = 1; i <= 6; i++ ) {
			await addComment( requestUtils, post.id, `Reply ${ i }`, ids[ 0 ] );
		}

		const context = await browser.newContext( {
			javaScriptEnabled: false,
		} );
		const page = await context.newPage();
		await page.goto( post.link );

		await page.getByRole( 'link', { name: 'Show 1 more reply' } ).click();
		await expect(
			page.locator(
				`#comment-${ ids[ 0 ] } > .convocept-replies > .convocept-comment`
			)
		).toHaveCount( 6 );

		await page
			.locator( `#comment-${ ids[ 0 ] } > .convocept-comment__body` )
			.getByRole( 'link', { name: /Reply to/ } )
			.click();
		await expect( page ).toHaveURL( /replytocom=/ );
		await expect(
			page.locator( 'input[name="comment_parent"]' )
		).toHaveValue( String( ids[ 0 ] ) );
		await context.close();
	} );
} );
