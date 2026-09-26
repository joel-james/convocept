/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Plugin activation', () => {
	// PHPUnit reinstalls the shared tests site, so set up state explicitly.
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activateTheme( 'twentytwentyfive' );
		await requestUtils.activatePlugin( 'convocept' );
	} );

	test( 'is active without errors', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'plugins.php' );

		const row = page.locator( 'tr[data-slug="convocept"]' );
		await expect( row ).toHaveClass( /(^|\s)active(\s|$)/ );
		await expect( page.locator( '.notice-error:visible' ) ).toHaveCount(
			0
		);
	} );

	test( 'front end renders without PHP errors', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await requestUtils.createPost( {
			title: 'Convocept smoke test',
			status: 'publish',
		} );

		const response = await page.goto( post.link );

		expect( response.status() ).toBe( 200 );
		await expect( page.locator( 'body' ) ).not.toContainText(
			/Fatal error|Warning:|Notice:|Deprecated:/
		);
	} );
} );
