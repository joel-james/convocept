/**
 * Accessibility checks (M11).
 *
 * Runs axe (WCAG 2.2 A and AA rules) on everything Convocept renders: the
 * thread on a classic and a block theme in each colour scheme, the thread
 * while replying and previewing, the moderator view, the subscription page
 * and every page of the settings screen. Only serious and critical issues
 * inside Convocept's own markup fail a test; the theme around it is not
 * ours to fix. Also checks keyboard focus and reduced motion.
 */

/**
 * External dependencies
 */
const AxeBuilder = require( '@axe-core/playwright' ).default;

/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const THEMES = [ 'twentytwentyone', 'twentytwentyfive' ];
const SCHEMES = [ 'light', 'dark', 'system' ];

/**
 * Run axe on part of a page.
 *
 * @param {Object} page     Playwright page.
 * @param {string} selector Region to check.
 * @return {Promise<string[]>} One line per serious or critical violation.
 */
async function violations( page, selector ) {
	// Let hover and state transitions finish, or axe samples colours halfway.
	await page.evaluate( () =>
		Promise.all(
			document.getAnimations().map( ( animation ) => animation.finished )
		)
	);

	const results = await new AxeBuilder( { page } )
		.include( selector )
		.withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] )
		.analyze();

	return results.violations
		.filter( ( item ) => [ 'serious', 'critical' ].includes( item.impact ) )
		.map(
			( item ) =>
				`${ item.id }: ${ item.help } — ${ item.nodes
					.map( ( node ) => node.target.join( ' ' ) )
					.join( ', ' ) }`
		);
}

/**
 * Save settings through the REST route.
 *
 * @param {Object} requestUtils Request utilities fixture.
 * @param {Object} settings     Changed settings.
 * @return {Promise<void>}
 */
async function saveSettings( requestUtils, settings ) {
	await requestUtils.rest( {
		method: 'POST',
		path: '/convocept/v1/settings',
		data: { settings },
	} );
}

/**
 * Create a post with a comment, a reply and a pinned comment.
 *
 * @param {Object} requestUtils Request utilities fixture.
 * @return {Promise<Object>} The post.
 */
async function createThread( requestUtils ) {
	const post = await requestUtils.createPost( {
		title: `Accessibility ${ Date.now() }`,
		status: 'publish',
	} );
	const comment = ( data ) =>
		requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/comments',
			data: {
				post: post.id,
				author_name: 'Reader',
				author_email: 'reader@example.com',
				author_ip: '203.0.113.70',
				status: 'approved',
				...data,
			},
		} );

	const first = await comment( {
		content: 'A first comment with **bold** text and `code`.',
	} );
	await comment( { content: 'A reply to it.', parent: first.id } );
	await comment( { content: 'A second comment.' } );

	return post;
}

/**
 * Open a post as a logged-out visitor, with the thread's script loaded.
 *
 * @param {Object} browser Browser fixture.
 * @param {string} url     Post URL.
 * @param {Object} options Extra context options.
 * @return {Promise<Object>} Page and context.
 */
async function openAsGuest( browser, url, options = {} ) {
	const context = await browser.newContext( {
		storageState: { cookies: [], origins: [] },
		...options,
	} );
	const page = await context.newPage();

	await page.goto( url );
	await page.locator( 'section.convocept' ).scrollIntoViewIfNeeded();
	await expect( page.locator( 'section.convocept' ) ).toHaveAttribute(
		'data-convocept-ready',
		'1'
	);

	return { page, context };
}

test.describe( 'Accessibility', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin(
			'convocept-fast-native-comment-system'
		);
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
		await requestUtils.activateTheme( 'twentytwentyfive' );
	} );

	for ( const theme of THEMES ) {
		test( `thread on ${ theme } in every colour scheme`, async ( {
			browser,
			requestUtils,
		} ) => {
			await requestUtils.activateTheme( theme );
			const post = await createThread( requestUtils );

			for ( const scheme of SCHEMES ) {
				await saveSettings( requestUtils, { color_scheme: scheme } );

				for ( const colorScheme of [ 'light', 'dark' ] ) {
					// "light" and "dark" ignore the visitor's preference; check it anyway.
					const { page, context } = await openAsGuest(
						browser,
						post.link,
						{ colorScheme }
					);

					expect(
						await violations( page, 'section.convocept' ),
						`${ scheme } scheme, ${ colorScheme } preference`
					).toEqual( [] );

					await context.close();
				}
			}
		} );
	}

	test( 'replying and previewing', async ( { browser, requestUtils } ) => {
		await requestUtils.activateTheme( 'twentytwentyfive' );
		const post = await createThread( requestUtils );
		const { page, context } = await openAsGuest( browser, post.link );

		await page
			.locator( '.convocept-comment' )
			.first()
			.getByRole( 'link', { name: /Reply/ } )
			.first()
			.click();
		await expect( page.locator( '#comment' ) ).toBeFocused();
		await page.locator( '#comment' ).fill( 'A **preview** of my reply' );
		await page.getByRole( 'tab', { name: 'Preview' } ).click();

		expect( await violations( page, 'section.convocept' ) ).toEqual( [] );

		await context.close();
	} );

	test( 'moderator view in every colour scheme', async ( {
		page,
		requestUtils,
	} ) => {
		await requestUtils.activateTheme( 'twentytwentyfive' );
		const post = await createThread( requestUtils );

		for ( const scheme of SCHEMES ) {
			await saveSettings( requestUtils, { color_scheme: scheme } );
			await page.goto( post.link );
			await page.locator( 'section.convocept' ).scrollIntoViewIfNeeded();
			await expect(
				page.locator( '[data-convocept-mod-menu]' ).first()
			).toBeAttached();

			expect(
				await violations( page, 'section.convocept' ),
				scheme
			).toEqual( [] );
		}

		await saveSettings( requestUtils, { color_scheme: 'light' } );
	} );

	test( 'subscription page', async ( { browser, requestUtils } ) => {
		await requestUtils.activateTheme( 'twentytwentyfive' );
		await requestUtils.rest( {
			method: 'DELETE',
			path: '/convocept-e2e/v1/mails',
		} );
		const address = `a11y-${ Date.now() }@example.com`;
		const post = await createThread( requestUtils );
		const { page, context } = await openAsGuest( browser, post.link );

		await page.getByRole( 'button', { name: 'Follow' } ).click();
		await page
			.locator( '.convocept-subscribe input[type="email"]' )
			.fill( address );
		await page
			.locator( '.convocept-subscribe' )
			.getByRole( 'button', { name: 'Subscribe' } )
			.click();
		await expect(
			page.locator( '[data-convocept-subscribe-message]' )
		).toContainText( 'Check your inbox' );

		const mails = await requestUtils.rest( {
			path: '/convocept-e2e/v1/mails',
		} );
		const mail = mails.find( ( item ) => item.to.includes( address ) );
		const link = mail.message.match(
			/href="([^"]*convocept=confirm[^"]*)"/
		)[ 1 ];

		await page.goto( link.replace( /&amp;|&#038;/g, '&' ) );

		expect( await violations( page, '.convocept-subscriptions' ) ).toEqual(
			[]
		);

		await context.close();
	} );

	test( 'settings screen, every page', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'edit-comments.php', 'page=convocept' );
		const nav = page.getByRole( 'navigation', {
			name: 'Convocept sections',
		} );
		await expect( nav ).toBeVisible();
		const names = (
			await nav.locator( '.convocept-nav-item__label' ).allTextContents()
		).map( ( name ) => name.trim() );

		expect( names.length ).toBeGreaterThan( 5 );
		expect( await violations( page, '.convocept-admin' ) ).toEqual( [] );

		// From the first page, visit every other one; moving moves focus to the heading.
		for ( const name of names.slice( 1 ) ) {
			await nav
				.locator( '.convocept-nav-item', { hasText: name } )
				.click();
			await expect(
				page.getByRole( 'heading', { level: 1, name } )
			).toBeFocused();

			expect(
				await violations( page, '.convocept-admin' ),
				name
			).toEqual( [] );
		}

		// A page with an invalid value and the save status showing.
		await page
			.getByRole( 'navigation', { name: 'Convocept sections' } )
			.getByRole( 'link', { name: 'General' } )
			.click();
		await page.getByLabel( 'Comments per page' ).fill( '0' );
		await expect( page.locator( '.convocept-header' ) ).toContainText(
			'1 setting needs fixing'
		);

		expect( await violations( page, '.convocept-admin' ) ).toEqual( [] );
	} );

	test( 'keyboard: every control in the thread shows focus', async ( {
		browser,
		requestUtils,
	} ) => {
		await requestUtils.activateTheme( 'twentytwentyfive' );
		const post = await createThread( requestUtils );
		const { page, context } = await openAsGuest( browser, post.link );

		const controls = page.locator(
			// The honeypot (tabindex -1) is hidden from everyone on purpose.
			'section.convocept :is(a[href], button, input:not([type="hidden"]), textarea, select, summary):not([tabindex="-1"]):visible'
		);
		const count = await controls.count();

		expect( count ).toBeGreaterThan( 5 );

		for ( let i = 0; i < count; i++ ) {
			const control = controls.nth( i );

			await control.focus();
			await page.keyboard.press( 'Shift+Tab' );
			await page.keyboard.press( 'Tab' );

			const outline = await control.evaluate( ( node ) => {
				/**
				 * Whether an element draws a focus ring.
				 *
				 * @param {Element} element Element.
				 * @return {boolean} True with an outline or a shadow ring.
				 */
				const ring = ( element ) => {
					const style = window.getComputedStyle( element );

					return (
						( style.outlineStyle !== 'none' &&
							parseFloat( style.outlineWidth ) > 0 ) ||
						style.boxShadow !== 'none'
					);
				};
				// The comment field's ring is drawn by the editor around it and its toolbar.
				const editor = node.closest( '.convocept-editor' );

				return {
					focused: node.matches( ':focus-visible' ),
					visible: ring( node ) || ( !! editor && ring( editor ) ),
					html: node.outerHTML.slice( 0, 80 ),
				};
			} );

			expect( outline.focused, outline.html ).toBe( true );
			expect( outline.visible, outline.html ).toBe( true );
		}

		await context.close();
	} );

	test( 'right-to-left pages mirror the thread', async ( {
		browser,
		requestUtils,
	} ) => {
		await requestUtils.activateTheme( 'twentytwentyfive' );
		const post = await createThread( requestUtils );
		const { page, context } = await openAsGuest( browser, post.link );

		// An RTL language sets dir="rtl" on <html>; the CSS uses logical properties only.
		await page.evaluate( () => ( document.documentElement.dir = 'rtl' ) );

		const replies = page.locator( '.convocept-replies' ).first();
		const sides = await replies.evaluate( ( node ) => {
			const style = window.getComputedStyle( node );

			return {
				right: style.borderRightWidth,
				left: style.borderLeftWidth,
			};
		} );

		expect( sides ).toEqual( { right: '1px', left: '0px' } );

		await context.close();
	} );

	test( 'reduced motion turns off the highlight animation', async ( {
		browser,
		requestUtils,
	} ) => {
		await requestUtils.activateTheme( 'twentytwentyfive' );
		const post = await createThread( requestUtils );
		const context = await browser.newContext( {
			storageState: { cookies: [], origins: [] },
			reducedMotion: 'reduce',
		} );
		const page = await context.newPage();
		await page.goto( post.link );

		const id = await page
			.locator( '.convocept-comment' )
			.first()
			.getAttribute( 'id' );
		await page.goto( `${ post.link }#${ id }` );

		const animation = await page
			.locator( `#${ id } > .convocept-comment__body` )
			.evaluate(
				( node ) => window.getComputedStyle( node ).animationName
			);

		expect( animation ).toBe( 'none' );

		await context.close();
	} );
} );
