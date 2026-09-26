/**
 * End-to-end tests for the Disqus importer (M8).
 *
 * Covers the Tools → Import screen with and without JavaScript, and checks
 * that importer code never loads on front-end requests.
 */

/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Build a small Disqus export for a post.
 *
 * @param {number} postId Post the thread belongs to.
 * @param {string} tag    Text that makes this run's comments unique.
 * @return {Buffer} Export XML.
 */
function disqusExport( postId, tag ) {
	const post = ( id, message, extra = '' ) => `
	<post dsq:id="${ id }">
		<id />
		<message><![CDATA[<p>${ message }</p>]]></message>
		<createdAt>2015-03-01T12:00:00Z</createdAt>
		<isDeleted>false</isDeleted>
		<isSpam>${ extra === 'spam' ? 'true' : 'false' }</isSpam>
		<author><name>Reader ${ id }</name><isAnonymous>true</isAnonymous></author>
		<thread dsq:id="1" />
		${ extra.startsWith( '<parent' ) ? extra : '' }
	</post>`;

	const base = Date.now();

	return Buffer.from(
		`<?xml version="1.0" encoding="utf-8"?>
<disqus xmlns="http://disqus.com" xmlns:dsq="http://disqus.com/disqus-internals">
	<thread dsq:id="1"><id>${ postId }</id><link>http://example.invalid/</link><title>Imported</title></thread>
	${ post( base + 1, `Imported top ${ tag }` ) }
	${ post(
		base + 2,
		`Imported reply ${ tag }`,
		`<parent dsq:id="${ base + 1 }" />`
	) }
	${ post( base + 3, `Imported spam ${ tag }`, 'spam' ) }
</disqus>`
	);
}

test.describe( 'Disqus importer', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'convocept' );
		await requestUtils.activateTheme( 'twentytwentyfive' );
	} );

	// Cancel any import left over from an earlier failed run.
	test.beforeEach( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			method: 'POST',
			path: '/convocept-e2e/v1/reset-import',
		} );
	} );

	test( 'importer code never loads on the front end', async ( {
		browser,
		requestUtils,
	} ) => {
		const post = await requestUtils.createPost( {
			title: `Convocept classes ${ Date.now() }`,
			status: 'publish',
		} );
		const context = await browser.newContext( {
			storageState: { cookies: [], origins: [] },
		} );
		const page = await context.newPage();
		const url = new URL( post.link );
		url.searchParams.set( 'convocept_e2e_classes', '1' );

		await page.goto( url.href );

		const classes = JSON.parse(
			await page.locator( '#convocept-e2e-classes' ).textContent()
		);

		expect( classes ).toContain( 'Convocept\\Plugin' );
		expect(
			classes.filter(
				( name ) =>
					name.startsWith( 'Convocept\\Import\\' ) ||
					name.startsWith( 'Convocept\\Cli\\' ) ||
					name === 'Convocept\\Admin\\ImportScreen'
			)
		).toEqual( [] );

		await context.close();
	} );

	test( 'dry run, then import with progress, then report', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const tag = `js-${ Date.now() }`;
		const post = await requestUtils.createPost( {
			title: `Convocept import ${ tag }`,
			status: 'publish',
		} );

		await admin.visitAdminPage( 'admin.php', 'import=convocept-disqus' );

		await page.getByLabel( 'Export file' ).setInputFiles( {
			name: 'export.xml',
			mimeType: 'text/xml',
			buffer: disqusExport( post.id, tag ),
		} );
		await page
			.getByRole( 'button', { name: 'Upload and analyse' } )
			.click();

		await expect(
			page.getByRole( 'heading', { name: 'Dry run' } )
		).toBeVisible();
		await expect(
			page.locator( '[data-convocept-count="found"] td' )
		).toHaveText( '2' );
		await expect(
			page.locator( '[data-convocept-count="spam"] td' )
		).toHaveText( '1' );
		await expect(
			page.getByRole( 'cell', { name: `Imported top ${ tag }` } )
		).toBeVisible();

		await page.getByRole( 'button', { name: 'Start import' } ).click();

		await expect(
			page.getByRole( 'heading', { name: 'Import complete' } )
		).toBeVisible();
		await expect(
			page.locator( '[data-convocept-count="imported"] td' )
		).toHaveText( '2' );

		await page.goto( post.link );
		const thread = page.locator( 'section.convocept' );
		await expect( thread ).toContainText( `Imported top ${ tag }` );
		await expect(
			thread.locator( '.convocept-comment .convocept-comment' )
		).toContainText( `Imported reply ${ tag }` );
		await expect( thread ).not.toContainText( `Imported spam ${ tag }` );

		await admin.visitAdminPage( 'admin.php', 'import=convocept-disqus' );
		await page
			.getByRole( 'button', { name: 'Start another import' } )
			.click();
		await expect(
			page.getByRole( 'button', { name: 'Upload and analyse' } )
		).toBeVisible();
	} );

	test( 'works without JavaScript, one batch per click', async ( {
		browser,
		requestUtils,
	} ) => {
		const tag = `nojs-${ Date.now() }`;
		const post = await requestUtils.createPost( {
			title: `Convocept import ${ tag }`,
			status: 'publish',
		} );
		const context = await browser.newContext( {
			javaScriptEnabled: false,
			storageState: process.env.STORAGE_STATE_PATH,
		} );
		const page = await context.newPage();
		const importUrl = new URL(
			'/wp-admin/admin.php?import=convocept-disqus',
			post.link
		);

		await page.goto( importUrl.href );

		await page.getByLabel( 'Export file' ).setInputFiles( {
			name: 'export.xml',
			mimeType: 'text/xml',
			buffer: disqusExport( post.id, tag ),
		} );
		await page
			.getByRole( 'button', { name: 'Upload and analyse' } )
			.click();
		await page.getByRole( 'button', { name: 'Start import' } ).click();

		// Two comments fit in the one batch the Start click runs.
		await expect(
			page.getByRole( 'heading', { name: 'Import complete' } )
		).toBeVisible();
		await expect(
			page.locator( '[data-convocept-count="imported"] td' )
		).toHaveText( '2' );

		await page
			.getByRole( 'button', { name: 'Start another import' } )
			.click();
		await context.close();
	} );
} );
