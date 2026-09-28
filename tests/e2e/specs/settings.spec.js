/**
 * End-to-end tests for the settings screen (M9).
 *
 * Settings → Convocept: sidebar navigation, the save bar, validation,
 * saving and the effect on the front end, and the narrow-screen dropdown.
 */

/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const SCREEN = 'options-general.php';
const QUERY = 'page=convocept';

test.describe( 'Settings screen', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'convocept' );
		await requestUtils.activateTheme( 'twentytwentyfive' );
	} );

	test.beforeEach( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			method: 'POST',
			path: '/convocept-e2e/v1/reset-settings',
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			method: 'POST',
			path: '/convocept-e2e/v1/reset-settings',
		} );
	} );

	test( 'changes on several pages save together and reach the thread', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await admin.visitAdminPage( SCREEN, QUERY );

		const bar = page.getByRole( 'region', { name: 'Unsaved changes' } );
		await expect(
			page.getByRole( 'heading', { level: 1, name: 'General' } )
		).toBeVisible();
		await expect( bar ).toHaveCount( 0 );

		await page.getByLabel( 'Comments per page' ).fill( '12' );
		await page.getByLabel( 'Readers can upvote comments' ).click();
		await expect( bar ).toContainText( '2 unsaved changes' );

		await page
			.getByRole( 'navigation', { name: 'Convocept sections' } )
			.getByRole( 'link', { name: 'Appearance' } )
			.click();
		await expect(
			page.getByRole( 'heading', { level: 1, name: 'Appearance' } )
		).toBeFocused();
		await page.getByLabel( 'Always dark' ).check();
		await page.getByLabel( 'Hex value' ).fill( '#123456' );
		await expect( bar ).toContainText( '4 unsaved changes' );

		await bar.getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( page.locator( '.components-snackbar' ) ).toHaveText(
			'Settings saved.'
		);
		await expect( bar ).toHaveCount( 0 );

		await page.reload();
		await expect( page.getByLabel( 'Always dark' ) ).toBeChecked();
		await page.goto( page.url().replace( /#.*$/, '#general' ) );
		await expect( page.getByLabel( 'Comments per page' ) ).toHaveValue(
			'12'
		);
		await expect(
			page.getByLabel( 'Readers can upvote comments' )
		).not.toBeChecked();

		const post = await requestUtils.createPost( {
			title: `Convocept settings ${ Date.now() }`,
			status: 'publish',
		} );
		await page.goto( post.link );
		const thread = page.locator( 'section.convocept' );
		await expect( thread ).toHaveAttribute(
			'data-convocept-theme',
			'dark'
		);
		await expect( thread ).toHaveAttribute( 'style', /#123456/ );
	} );

	test( 'invalid values are explained and block saving until fixed or discarded', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( SCREEN, QUERY );

		const field = page.getByLabel( 'Comments per page' );
		const bar = page.getByRole( 'region', { name: 'Unsaved changes' } );

		await field.fill( '0' );
		await expect(
			page.getByText( 'Enter a number from 1 to 100.' )
		).toBeVisible();
		await expect( field ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( bar ).toContainText( '1 setting needs fixing' );
		await expect(
			bar.getByRole( 'button', { name: 'Save changes' } )
		).toHaveAttribute( 'aria-disabled', 'true' );
		await expect(
			page
				.getByRole( 'link', { name: /General/ } )
				.getByText( 'Needs fixing' )
		).toBeAttached();

		await bar.getByRole( 'button', { name: 'Discard' } ).click();
		await expect( field ).toHaveValue( '20' );
		await expect( bar ).toHaveCount( 0 );
	} );

	test( 'the app loads on its own screen only', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'index.php' );
		await expect( page.locator( '#convocept-admin-js' ) ).toHaveCount( 0 );

		await admin.visitAdminPage( SCREEN, QUERY );
		await expect( page.locator( '#convocept-admin-js' ) ).toHaveCount( 1 );
	} );

	test( 'narrow screens get a section dropdown', async ( {
		admin,
		page,
	} ) => {
		await page.setViewportSize( { width: 600, height: 900 } );
		await admin.visitAdminPage( SCREEN, QUERY );

		await expect(
			page.getByRole( 'navigation', { name: 'Convocept sections' } )
		).toBeHidden();
		await page
			.getByLabel( 'Section', { exact: true } )
			.selectOption( 'advanced' );
		await expect(
			page.getByRole( 'heading', { level: 1, name: 'Advanced' } )
		).toBeVisible();
	} );
} );
