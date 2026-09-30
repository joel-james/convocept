/**
 * End-to-end tests for the settings screen (M9).
 *
 * Comments → Convocept: sidebar navigation, Save in the page header,
 * validation, saving and the effect on the front end, and the narrow-screen
 * dropdown.
 */

/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const SCREEN = 'edit-comments.php';
const QUERY = 'page=convocept';

test.describe( 'Settings screen', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin(
			'convocept-fast-native-comment-system'
		);
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

		const header = page.locator( '.convocept-header' );
		const save = header.getByRole( 'button', { name: 'Save' } );
		await expect(
			page.getByRole( 'heading', { level: 1, name: 'General' } )
		).toBeVisible();
		await expect( save ).toHaveAttribute( 'aria-disabled', 'true' );

		await page.getByLabel( 'Comments per page' ).fill( '12' );
		await page.getByLabel( 'Readers can upvote comments' ).click();
		await expect( header ).toContainText( '2 unsaved changes' );

		await page
			.getByRole( 'navigation', { name: 'Convocept sections' } )
			.getByRole( 'link', { name: 'Appearance' } )
			.click();
		await expect(
			page.getByRole( 'heading', { level: 1, name: 'Appearance' } )
		).toBeFocused();
		await page.getByLabel( 'Always dark' ).check();
		await page.locator( '.convocept-color' ).click();
		await page
			.locator( '.convocept-color-panel' )
			.getByRole( 'option', { name: /Teal/ } )
			.click();
		await expect( page.locator( '.convocept-color__hex' ) ).toHaveText(
			'#0f766e'
		);
		await expect( header ).toContainText( '4 unsaved changes' );

		await save.click();
		await expect( page.locator( '.components-snackbar' ) ).toHaveText(
			'Settings saved.'
		);
		await expect(
			header.getByRole( 'button', { name: 'Saved' } )
		).toHaveAttribute( 'aria-disabled', 'true' );
		await expect( header ).not.toContainText( 'unsaved' );

		// The page is in the address, so a reload stays on it.
		expect( new URL( page.url() ).searchParams.get( 'tab' ) ).toBe(
			'appearance'
		);
		await page.reload();
		await expect(
			page.getByRole( 'heading', { level: 1, name: 'Appearance' } )
		).toBeVisible();
		await expect( page.getByLabel( 'Always dark' ) ).toBeChecked();

		// Back returns to the previous page without a reload.
		await page.goBack();
		await expect(
			page.getByRole( 'heading', { level: 1, name: 'General' } )
		).toBeVisible();
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
		await expect( thread ).toHaveAttribute( 'style', /#0f766e/ );
	} );

	test( 'invalid values are explained and block saving until fixed or discarded', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( SCREEN, QUERY );

		const field = page.getByLabel( 'Comments per page' );
		const header = page.locator( '.convocept-header' );

		await field.fill( '0' );
		await expect(
			page.getByText( 'Enter a number from 1 to 100.' )
		).toBeVisible();
		await expect( field ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( header ).toContainText( '1 setting needs fixing' );
		await expect(
			header.getByRole( 'button', { name: 'Save' } )
		).toHaveAttribute( 'aria-disabled', 'true' );
		await expect(
			page
				.getByRole( 'link', { name: /General/ } )
				.getByText( 'Needs fixing' )
		).toBeAttached();

		await header.getByRole( 'button', { name: 'Discard' } ).click();
		await expect( field ).toHaveValue( '20' );
		await expect( header ).not.toContainText( 'needs fixing' );
		await expect(
			header.getByRole( 'button', { name: 'Discard' } )
		).toHaveCount( 0 );
	} );

	// WordPress warns in the console (SCRIPT_DEBUG is on in wp-env) before it
	// changes or removes a component prop, so this catches a coming break in
	// the admin UI while it is still a warning. Run it against trunk to see
	// the next release early.
	test( 'no page uses a deprecated WordPress component or prop', async ( {
		admin,
		page,
	} ) => {
		const warnings = [];
		page.on( 'console', ( message ) => {
			if (
				[ 'warning', 'error' ].includes( message.type() ) &&
				/deprecated|is not a valid prop|unknown prop/i.test(
					message.text()
				)
			) {
				warnings.push( message.text() );
			}
		} );

		await admin.visitAdminPage( SCREEN, QUERY );

		const nav = page.getByRole( 'navigation', {
			name: 'Convocept sections',
		} );
		await expect( nav ).toBeVisible();
		const links = await nav
			.locator( '.convocept-nav-item__label' )
			.allTextContents();

		for ( const name of links ) {
			await nav.getByRole( 'link', { name } ).click();
			await expect(
				page.getByRole( 'heading', { level: 1, name } )
			).toBeVisible();
		}

		expect( warnings ).toEqual( [] );
	} );

	test( 'the app loads on its own screen only', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'index.php' );
		await expect( page.locator( '#convocept-admin-js' ) ).toHaveCount( 0 );

		await admin.visitAdminPage( SCREEN, QUERY );
		await expect( page.locator( '#convocept-admin-js' ) ).toHaveCount( 1 );
	} );

	test( 'narrow screens get a section dropdown beside Save', async ( {
		admin,
		page,
	} ) => {
		await page.setViewportSize( { width: 600, height: 900 } );
		await admin.visitAdminPage( SCREEN, QUERY );

		await expect(
			page.getByRole( 'navigation', { name: 'Convocept sections' } )
		).toBeHidden();
		await expect(
			page
				.locator( '.convocept-header' )
				.getByRole( 'button', { name: 'Save' } )
		).toBeVisible();
		await page
			.getByLabel( 'Section', { exact: true } )
			.selectOption( 'advanced' );
		await expect(
			page.getByRole( 'heading', { level: 1, name: 'Advanced' } )
		).toBeVisible();
	} );

	// These check the layout against WordPress itself, so a change to core's
	// admin or component styles that breaks the screen fails here. Run it on
	// the oldest supported WordPress and on trunk (.wp-env.override.json).
	test( 'layout holds on this WordPress version', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			SCREEN,
			`${ QUERY }&tab=general&convocept_e2e_notice=1`
		);

		const header = page.locator( '.convocept-header' );
		const content = page.locator( '.convocept-content' );

		// Notices from other plugins sit in the content column, above the cards.
		await expect(
			content
				.locator( '.convocept-wp-notices' )
				.getByText( 'Convocept e2e notice' )
		).toBeVisible();

		// Nothing is wider than the window.
		const overflow = await page.evaluate(
			() =>
				document.documentElement.scrollWidth -
				document.documentElement.clientWidth
		);
		expect( overflow ).toBeLessThanOrEqual( 0 );

		// The unit sits inside the number field's frame, after the input.
		const frame = await page
			.locator( '.convocept-affix' )
			.first()
			.boundingBox();
		const unit = await page
			.locator( '.convocept-affix__unit' )
			.first()
			.boundingBox();
		expect( unit.x ).toBeGreaterThan( frame.x );
		expect( unit.x + unit.width ).toBeLessThanOrEqual(
			frame.x + frame.width
		);
		expect( frame.height ).toBeGreaterThanOrEqual( 36 );

		// Save stays in view while scrolling a long page.
		await page.locator( '.convocept-footer' ).scrollIntoViewIfNeeded();
		await expect(
			header.getByRole( 'button', { name: 'Save' } )
		).toBeInViewport();

		// Cards keep their frame; the accent is the admin theme colour.
		const card = await page
			.locator( '.convocept-card' )
			.first()
			.evaluate( ( element ) => {
				const style = window.getComputedStyle( element );

				return {
					border: style.borderTopWidth,
					radius: style.borderTopLeftRadius,
				};
			} );
		expect( card ).toEqual( { border: '1px', radius: '8px' } );

		const colours = await page.evaluate( () => {
			const probe = document.createElement( 'span' );
			probe.style.color = 'var(--wp-admin-theme-color, #3858e9)';
			document.body.appendChild( probe );
			const theme = window.getComputedStyle( probe ).color;
			probe.remove();

			const active = document.querySelector(
				'.convocept-nav-item[aria-current="page"]'
			);
			const other = document.querySelector(
				'.convocept-nav-item:not([aria-current])'
			);

			return {
				theme,
				active: window.getComputedStyle( active ).color,
				other: window.getComputedStyle( other ).color,
			};
		} );
		// Only the current page is in the theme colour; the rest are text colour.
		expect( colours.active ).toBe( colours.theme );
		expect( colours.other ).toBe( 'rgb(30, 30, 30)' );

		// A checked tile shows its dot, whatever core does to radios.
		await page
			.getByRole( 'navigation', { name: 'Convocept sections' } )
			.getByRole( 'link', { name: 'Appearance' } )
			.click();
		const dot = await page
			.locator( '.convocept-tile--on input[type="radio"]' )
			.evaluate( ( element ) => {
				const style = window.getComputedStyle( element, '::after' );

				return { width: style.width, content: style.content };
			} );
		expect( dot ).toEqual( { width: '6px', content: '""' } );

		// Phones: no sideways scroll, and Save beside the section dropdown.
		await page.setViewportSize( { width: 375, height: 800 } );
		await expect(
			page.getByLabel( 'Section', { exact: true } )
		).toBeVisible();
		await expect(
			header.getByRole( 'button', { name: 'Save' } )
		).toBeInViewport();
		expect(
			await page.evaluate(
				() =>
					document.documentElement.scrollWidth -
					document.documentElement.clientWidth
			)
		).toBeLessThanOrEqual( 0 );
	} );

	test( 'our footer replaces the WordPress footer on this screen only', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( SCREEN, QUERY );

		const footer = page.getByRole( 'navigation', {
			name: 'About Convocept',
		} );
		await expect(
			footer.getByRole( 'link', { name: /Documentation/ } )
		).toHaveAttribute(
			'href',
			'https://docs.foxelabs.com/software/convocept/getting-started'
		);
		await expect(
			footer.getByRole( 'link', { name: /Homepage/ } )
		).toHaveAttribute( 'href', 'https://convocept.com/' );
		await expect(
			footer.getByRole( 'link', { name: /Leave a five-star review/ } )
		).toHaveAttribute(
			'href',
			'https://wordpress.org/support/plugin/convocept/reviews/#new-post'
		);
		await expect(
			page
				.locator( '.convocept-footer' )
				.getByRole( 'link', { name: /Foxe Labs/ } )
		).toHaveAttribute( 'href', 'https://foxelabs.com/' );
		await expect( page.locator( '#wpfooter' ) ).toBeHidden();

		await admin.visitAdminPage( 'index.php' );
		await expect( page.locator( '#wpfooter' ) ).toBeVisible();
	} );
} );
