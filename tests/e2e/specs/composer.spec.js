/**
 * End-to-end tests for the JavaScript composer (M2).
 */

/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Create a published post.
 *
 * @param {Object} requestUtils Request utilities fixture.
 * @return {Promise<Object>} The post.
 */
function createPost( requestUtils ) {
	return requestUtils.createPost( {
		title: `Composer ${ Date.now() }`,
		status: 'publish',
	} );
}

/**
 * Open a post as a logged-out visitor.
 *
 * @param {Object} browser Browser fixture.
 * @param {string} url     Post URL.
 * @return {Promise<Object>} Page and context.
 */
async function openAsGuest( browser, url ) {
	const context = await browser.newContext( {
		storageState: { cookies: [], origins: [] },
	} );
	const page = await context.newPage();
	await page.goto( url );

	return { page, context };
}

test.describe( 'JavaScript composer', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin(
			'convocept-fast-native-comment-system'
		);
		await requestUtils.activateTheme( 'twentytwentyfive' );
	} );

	test( 'logged-in user posts, sees it formatted, and edits it', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPost( requestUtils );
		await page.goto( post.link );

		const form = page.locator( '#commentform' );
		await expect( form.locator( '[data-convocept-user]' ) ).toContainText(
			'Commenting as'
		);
		await expect(
			form.locator( '[data-convocept-guest-fields]' )
		).toBeHidden();

		await form.locator( '#comment' ).fill( 'Hello **bold** world' );
		await form.getByRole( 'button', { name: 'Post comment' } ).click();

		const comment = page.locator( '.convocept-list > .convocept-comment' );
		await expect( comment ).toHaveCount( 1 );
		await expect( comment.locator( 'strong' ) ).toHaveText( 'bold' );
		await expect( form.locator( '[data-convocept-status]' ) ).toHaveText(
			'Your comment was posted.'
		);
		await expect( page.locator( '.convocept-title' ) ).toHaveText(
			'1 comment'
		);

		await comment.locator( '[data-convocept-edit]' ).click();
		const editor = comment.locator( '[data-convocept-edit-input]' );
		await expect( editor ).toHaveValue( 'Hello **bold** world' );
		await editor.fill( 'Hello *edited* world' );
		await comment.getByRole( 'button', { name: 'Save' } ).click();

		await expect( comment.locator( 'em' ) ).toHaveText( 'edited' );
		await expect(
			comment.locator( '.convocept-comment__edited' )
		).toBeVisible();
	} );

	test( 'preview renders Markdown on the server', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPost( requestUtils );
		await page.goto( post.link );

		await page.locator( '#comment' ).fill( '> quoted `code`' );
		await page.getByRole( 'tab', { name: 'Preview' } ).click();

		const preview = page.locator( '[data-convocept-preview]' );
		await expect( preview.locator( 'blockquote code' ) ).toHaveText(
			'code'
		);
		await expect( page.locator( '#comment' ) ).toBeHidden();
	} );

	test( 'core errors such as duplicates are shown inline', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPost( requestUtils );
		await page.goto( post.link );

		const input = page.locator( '#comment' );
		const submit = page.getByRole( 'button', { name: 'Post comment' } );
		const status = page.locator( '[data-convocept-status]' );

		await input.fill( 'Exactly the same words' );
		await submit.click();
		await expect( status ).toHaveText( 'Your comment was posted.' );

		await input.fill( 'Exactly the same words' );
		await submit.click();

		await expect( status ).toHaveClass( /is-error/ );
		await expect( status ).toContainText( 'Duplicate comment' );
		await expect( input ).toHaveValue( 'Exactly the same words' );
		await expect(
			page.locator( '.convocept-list > .convocept-comment' )
		).toHaveCount( 1 );
	} );

	test( 'a guest comment held by moderation shows only to its author', async ( {
		browser,
		requestUtils,
	} ) => {
		const post = await createPost( requestUtils );
		const { page, context } = await openAsGuest( browser, post.link );

		await page.locator( '#comment' ).fill( 'Guest words' );
		await page.locator( '#author' ).fill( 'Guest Person' );
		await page.locator( '#email' ).fill( 'guest-person@example.com' );
		await page.getByRole( 'button', { name: 'Post comment' } ).click();

		const comment = page.locator( '.convocept-comment--pending' );
		await expect( comment ).toContainText( /awaiting moderation/i );
		await expect(
			comment.locator( '[data-convocept-edit]' )
		).toBeVisible();

		// Pending comments never reach the shared HTML, but the edit token survives a reload.
		await page.reload();
		await expect(
			page.locator( '.convocept-comment--pending' )
		).toHaveCount( 0 );
		await context.close();
	} );

	test( 'spam verdicts from anti-spam plugins stand and look pending', async ( {
		browser,
		requestUtils,
	} ) => {
		const post = await createPost( requestUtils );
		const { page, context } = await openAsGuest( browser, post.link );

		await page.locator( '#comment' ).fill( 'buy now convocept-spam-test' );
		await page.locator( '#author' ).fill( 'Spammer' );
		await page.locator( '#email' ).fill( 'spam@example.com' );
		await page.getByRole( 'button', { name: 'Post comment' } ).click();

		await expect( page.locator( '[data-convocept-status]' ) ).toHaveText(
			'Your comment is awaiting moderation.'
		);

		const spam = await requestUtils.rest( {
			path: '/wp/v2/comments',
			params: { post: post.id, status: 'spam' },
		} );
		expect( spam ).toHaveLength( 1 );
		await context.close();
	} );
} );
