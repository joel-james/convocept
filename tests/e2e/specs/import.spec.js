/**
 * End-to-end tests for importing from Disqus (M8, on the M9 screen).
 *
 * Covers Manage → Import on the settings screen, the Tools → Import entry
 * that leads there, and that importer code never loads on front-end pages.
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
		await requestUtils.activatePlugin(
			'convocept-fast-native-comment-system'
		);
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
					name.startsWith( 'Convocept\\Admin\\' )
			)
		).toEqual( [] );

		await context.close();
	} );

	test( 'Tools → Import leads to the Import page', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( 'import.php' );
		await page.getByRole( 'link', { name: 'Disqus (Convocept)' } ).click();

		await expect(
			page.getByRole( 'heading', { level: 1, name: 'Import' } )
		).toBeVisible();
		expect( new URL( page.url() ).searchParams.get( 'tab' ) ).toBe(
			'import'
		);
	} );

	test( 'dry run, then import with progress, then report', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const tag = `run-${ Date.now() }`;
		const post = await requestUtils.createPost( {
			title: `Convocept import ${ tag }`,
			status: 'publish',
		} );

		await admin.visitAdminPage( 'edit-comments.php', 'page=convocept' );
		await page
			.getByRole( 'navigation', { name: 'Convocept sections' } )
			.getByRole( 'link', { name: 'Import' } )
			.click();

		const upload = page.getByRole( 'button', {
			name: 'Upload and review',
		} );
		await expect( upload ).toBeDisabled();

		await page.getByLabel( 'Export file' ).setInputFiles( {
			name: 'export.xml',
			mimeType: 'text/xml',
			buffer: disqusExport( post.id, tag ),
		} );
		await expect( page.locator( '.convocept-drop__title' ) ).toHaveText(
			'export.xml'
		);
		await upload.click();

		await expect(
			page.getByRole( 'heading', { name: 'Dry run' } )
		).toBeVisible();
		await expect( page.locator( '.convocept-stats' ) ).toContainText(
			'Comments to import2'
		);
		await expect( page.locator( '.convocept-status-list' ) ).toContainText(
			'Spam (skipped)1'
		);
		await expect(
			page.getByRole( 'list', { name: 'Import steps' } )
		).toContainText( 'Review' );
		await expect(
			page.getByRole( 'cell', { name: `Imported top ${ tag }` } )
		).toBeVisible();

		await page.getByRole( 'button', { name: 'Start import' } ).click();

		await expect(
			page.getByRole( 'heading', { name: 'Import complete' } )
		).toBeVisible();
		await expect( page.locator( '.convocept-stats' ) ).toContainText(
			'Imported2'
		);

		await page
			.getByRole( 'button', { name: 'Start another import' } )
			.click();
		await expect( upload ).toBeVisible();

		await page.goto( post.link );
		const thread = page.locator( 'section.convocept' );
		await expect( thread ).toContainText( `Imported top ${ tag }` );
		await expect(
			thread.locator( '.convocept-comment .convocept-comment' )
		).toContainText( `Imported reply ${ tag }` );
		await expect( thread ).not.toContainText( `Imported spam ${ tag }` );
	} );

	test( 'a bad file is explained and nothing starts', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			'edit-comments.php',
			'page=convocept&tab=import'
		);
		await page.getByLabel( 'Export file' ).setInputFiles( {
			name: 'export.xml',
			mimeType: 'text/xml',
			buffer: Buffer.from( '<?xml version="1.0"?><rss/>' ),
		} );
		await page.getByRole( 'button', { name: 'Upload and review' } ).click();

		await expect(
			page
				.getByRole( 'alert' )
				.getByText( 'This file is not a valid Disqus XML export.' )
		).toBeVisible();
		await expect(
			page.getByRole( 'heading', { name: 'Import from Disqus' } )
		).toBeVisible();
	} );
} );
