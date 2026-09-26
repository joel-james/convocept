/**
 * End-to-end tests for votes and front-end moderation (M5).
 */

/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Create a comment through core's REST API.
 *
 * @param {Object} requestUtils Request utilities fixture.
 * @param {number} post         Post ID.
 * @param {string} content      Text.
 * @param {string} [status]     'approved' or 'hold'.
 * @return {Promise<Object>} The comment.
 */
function addComment( requestUtils, post, content, status = 'approved' ) {
	return requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/comments',
		data: {
			post,
			content,
			author_name: 'Reader',
			author_email: 'reader@example.com',
			author_ip: '203.0.113.40',
			status,
		},
	} );
}

/**
 * Create a published post.
 *
 * @param {Object} requestUtils Request utilities fixture.
 * @return {Promise<Object>} The post.
 */
function createPost( requestUtils ) {
	return requestUtils.createPost( {
		title: `Moderation ${ Date.now() }`,
		status: 'publish',
	} );
}

test.describe( 'Votes and moderation', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'convocept' );
		await requestUtils.activateTheme( 'twentytwentyfive' );
	} );

	test( 'a guest upvotes, and the vote is remembered after reload', async ( {
		browser,
		requestUtils,
	} ) => {
		const post = await createPost( requestUtils );
		const comment = await addComment(
			requestUtils,
			post.id,
			'Vote for me'
		);
		const context = await browser.newContext( {
			storageState: { cookies: [], origins: [] },
		} );
		const page = await context.newPage();

		await page.goto( `${ post.link }#comments` );
		const button = page.locator(
			`#comment-${ comment.id } [data-convocept-vote]`
		);

		await button.click();
		await expect( button ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( button ).toContainText( '1' );

		await page.reload();
		await expect( button ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( button ).toContainText( '1' );

		await button.click();
		await expect( button ).toHaveAttribute( 'aria-pressed', 'false' );
		await expect( button ).toContainText( '0' );
		await context.close();
	} );

	test( 'guests never see moderator tools or pending comments', async ( {
		browser,
		requestUtils,
	} ) => {
		const post = await createPost( requestUtils );
		await addComment( requestUtils, post.id, 'Visible' );
		await addComment( requestUtils, post.id, 'Secret pending', 'hold' );
		const context = await browser.newContext( {
			storageState: { cookies: [], origins: [] },
		} );
		const page = await context.newPage();

		await page.goto( `${ post.link }#comments` );
		await expect(
			page.locator( '[data-convocept-toolbar]' )
		).toBeVisible();

		await expect( page.locator( '[data-convocept-mod-menu]' ) ).toHaveCount(
			0
		);
		await expect( page.locator( 'body' ) ).not.toContainText(
			'Secret pending'
		);
		await context.close();
	} );

	test( 'a moderator approves, pins and spams in place', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPost( requestUtils );
		const first = await addComment( requestUtils, post.id, 'First words' );
		const second = await addComment(
			requestUtils,
			post.id,
			'Second words'
		);
		const held = await addComment(
			requestUtils,
			post.id,
			'Waiting words',
			'hold'
		);

		await page.goto( `${ post.link }#comments` );

		const pending = page.locator( `#comment-${ held.id }` );
		await expect( pending ).toContainText( /awaiting moderation/i );
		await pending.getByRole( 'button', { name: 'Approve' } ).click();
		await expect( pending ).not.toHaveClass( /convocept-comment--pending/ );

		const target = page.locator( `#comment-${ second.id }` );
		await target
			.getByRole( 'button', { name: 'Pin', exact: true } )
			.click();
		await expect(
			target.locator( '.convocept-badge--pinned' )
		).toBeVisible();

		await page.reload();
		await expect(
			page.locator( '.convocept-list > .convocept-comment' ).first()
		).toHaveId( `comment-${ second.id }` );

		await page
			.locator( `#comment-${ first.id }` )
			.getByRole( 'button', { name: 'Spam' } )
			.click();
		await expect( page.locator( `#comment-${ first.id }` ) ).toHaveCount(
			0
		);

		const spam = await requestUtils.rest( {
			path: `/wp/v2/comments/${ first.id }`,
			params: { context: 'edit' },
		} );
		expect( spam.status ).toBe( 'spam' );
	} );
} );
