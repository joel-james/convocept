/**
 * Renders the WordPress.org plugin assets into `.wordpress-org/`.
 *
 * Run with `npm run wporg:assets` against the wp-env tests site. The icon
 * and banner use the placeholder mark until Convocept has its own logo; the
 * screenshots match the list in readme.txt (== Screenshots ==).
 */

/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );

/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const OUT = path.join( __dirname, '../../../.wordpress-org' );
const SLUG = 'convocept-fast-native-comment-system';

const MARK = `<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
	<rect width="32" height="32" rx="8" fill="#111"/>
	<path d="M8 9.5A2.5 2.5 0 0 1 10.5 7h11A2.5 2.5 0 0 1 24 9.5v8a2.5 2.5 0 0 1-2.5 2.5H15l-4.5 4v-4a2.5 2.5 0 0 1-2.5-2.5z" fill="#fff"/>
	<path d="M12 12.5h8M12 16h5" stroke="#111" stroke-width="1.5" stroke-linecap="round"/>
</svg>`;

const FONT =
	"-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif";

/**
 * Save a screenshot of a page region.
 *
 * @param {Object} locator Element to capture.
 * @param {string} name    File name.
 * @return {Promise<void>}
 */
async function capture( locator, name ) {
	// Lazy avatars below the fold would otherwise still be blank.
	await locator.page().evaluate( () =>
		Promise.all(
			Array.from( document.images ).map( ( image ) => {
				image.loading = 'eager';

				return image.decode().catch( () => {} );
			} )
		)
	);
	await locator
		.page()
		.evaluate( () =>
			Promise.all(
				document
					.getAnimations()
					.map( ( animation ) => animation.finished )
			)
		);
	await locator.screenshot( {
		path: path.join( OUT, name ),
		animations: 'disabled',
	} );
}

/**
 * Create the demo post and its discussion.
 *
 * @param {Object} requestUtils Request utilities fixture.
 * @return {Promise<Object>} The post.
 */
async function createDemo( requestUtils ) {
	const post = await requestUtils.createPost( {
		title: 'How we halved our page weight',
		content:
			'<!-- wp:paragraph --><p>We removed every script that did not earn its place. Here is what we learned.</p><!-- /wp:paragraph -->',
		status: 'publish',
	} );
	const day = ( offset, time ) =>
		new Date( Date.UTC( 2026, 8, 20 + offset, ...time ) )
			.toISOString()
			.slice( 0, 19 );
	const add = async ( data, seed = {} ) => {
		const comment = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/comments',
			data: {
				post: post.id,
				author_ip: '203.0.113.80',
				status: 'approved',
				...data,
			},
		} );

		await requestUtils.rest( {
			method: 'POST',
			path: '/convocept-e2e/v1/seed-comment',
			data: { id: comment.id, ...seed },
		} );

		return comment;
	};

	const maya = await add(
		{
			author_name: 'Maya Chen',
			author_email: 'maya@example.com',
			date: day( 0, [ 9, 12 ] ),
			content:
				'<p>The part about deferring third-party embeds until someone scrolls to them was a revelation. Our <strong>largest contentful paint dropped by a second</strong> on mobile.</p>',
		},
		{ votes: 24, pinned: 1 }
	);
	await add(
		{
			author_name: 'Daniel Okafor',
			author_email: 'daniel@example.com',
			parent: maya.id,
			date: day( 0, [ 11, 40 ] ),
			content:
				'<p>Same here. Did you keep the embeds behind a click, or load them automatically near the viewport?</p>',
		},
		{ votes: 6 }
	);
	await add(
		{
			author_name: 'Maya Chen',
			author_email: 'maya@example.com',
			parent: maya.id,
			date: day( 0, [ 12, 5 ] ),
			content:
				'<p>Near the viewport, with an <code>IntersectionObserver</code> and a 600px margin. Nobody noticed the change.</p>',
		},
		{ votes: 9 }
	);
	await add(
		{
			author_name: 'Priya Raman',
			author_email: 'priya@example.com',
			date: day( 1, [ 8, 30 ] ),
			content:
				'<p>Great write-up. One question: how do you measure the effect on real visitors, not just in Lighthouse?</p>',
		},
		{ votes: 11 }
	);
	await add(
		{
			author_name: 'Tom Lindqvist',
			author_email: 'tom@example.com',
			date: day( 2, [ 16, 2 ] ),
			content:
				'<p>Bookmarked. The before and after numbers make a strong case for our next sprint.</p>',
		},
		{ votes: 3 }
	);

	return post;
}

/**
 * Open a page as a logged-out reader with the thread's script loaded.
 *
 * @param {Object} browser Browser fixture.
 * @param {string} url     URL.
 * @param {Object} options Extra context options.
 * @return {Promise<Object>} Page and context.
 */
async function openAsGuest( browser, url, options = {} ) {
	const context = await browser.newContext( {
		storageState: { cookies: [], origins: [] },
		viewport: { width: 1280, height: 900 },
		deviceScaleFactor: 2,
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

test.describe( 'WordPress.org assets', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( SLUG );
		// WPORG_THEME picks another installed theme, e.g. WPORG_THEME=raft.
		await requestUtils.activateTheme(
			process.env.WPORG_THEME || 'twentytwentyfive'
		);
		await requestUtils.deleteAllComments();
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

	test( 'icon and banner', async ( { browser } ) => {
		const context = await browser.newContext();
		const page = await context.newPage();

		for ( const size of [ 128, 256 ] ) {
			await page.setViewportSize( { width: size, height: size } );
			await page.setContent(
				`<style>html,body{margin:0}svg{display:block;width:${ size }px;height:${ size }px}</style>${ MARK }`
			);
			await page.screenshot( {
				path: path.join( OUT, `icon-${ size }x${ size }.png` ),
				omitBackground: true,
			} );
		}

		const banner = `<style>
			html, body { margin: 0; }
			.banner { box-sizing: border-box; width: 772px; height: 250px; padding: 0 56px; display: flex; align-items: center; gap: 28px; background: #f6f7f9; font-family: ${ FONT }; color: #111; position: relative; overflow: hidden; }
			.banner svg { width: 96px; height: 96px; flex: none; }
			h1 { margin: 0; font-size: 44px; font-weight: 700; letter-spacing: -0.02em; }
			p { margin: 6px 0 0; font-size: 20px; color: #50575e; }
			.stat { position: absolute; right: 56px; bottom: 28px; font-size: 14px; color: #50575e; }
			.stat b { color: #111; }
		</style>
		<div class="banner">
			${ MARK }
			<div>
				<h1>Convocept</h1>
				<p>Fast, native comments for WordPress</p>
			</div>
			<div class="stat"><b>0 KB</b> JavaScript on page load</div>
		</div>`;

		for ( const [ scale, name ] of [
			[ 1, 'banner-772x250.png' ],
			[ 2, 'banner-1544x500.png' ],
		] ) {
			const scaled = await browser.newContext( {
				viewport: { width: 772, height: 250 },
				deviceScaleFactor: scale,
			} );
			const bannerPage = await scaled.newPage();

			await bannerPage.setContent( banner );
			await bannerPage.screenshot( { path: path.join( OUT, name ) } );
			await scaled.close();
		}

		await context.close();

		expect( fs.existsSync( path.join( OUT, 'banner-1544x500.png' ) ) ).toBe(
			true
		);
	} );

	test( 'screenshots of the thread', async ( { browser, requestUtils } ) => {
		const post = await createDemo( requestUtils );

		// 1. Following the theme's colours.
		let { page, context } = await openAsGuest( browser, post.link );
		await capture(
			page.locator( 'section.convocept' ),
			'screenshot-1.png'
		);
		await context.close();

		// 2. The dark palette.
		await requestUtils.rest( {
			method: 'POST',
			path: '/convocept/v1/settings',
			data: { settings: { color_scheme: 'dark' } },
		} );
		( { page, context } = await openAsGuest( browser, post.link ) );
		await capture(
			page.locator( 'section.convocept' ),
			'screenshot-2.png'
		);
		await context.close();

		// 3. Replying inline, previewing the formatting.
		( { page, context } = await openAsGuest( browser, post.link ) );
		await page
			.getByRole( 'link', { name: 'Reply to Priya Raman' } )
			.click();
		await page.locator( '#author' ).fill( 'Sam Weber' );
		await page.locator( '#email' ).fill( 'sam@example.com' );
		await page
			.locator( '#comment' )
			.fill(
				'We use the **Chrome UX Report** for field data, plus a small `PerformanceObserver` that samples 1% of visits.'
			);
		await page.getByRole( 'tab', { name: 'Preview' } ).click();
		await expect( page.locator( '.convocept-preview strong' ) ).toHaveText(
			'Chrome UX Report'
		);
		const reply = page
			.locator( '.convocept-comment' )
			.filter( { hasText: 'Priya Raman' } )
			.first();
		await capture( reply, 'screenshot-3.png' );
		await context.close();
	} );

	test( 'screenshots of the admin', async ( { page, requestUtils } ) => {
		// Tall enough for the whole page, so the sticky sidebar spans it.
		await page.setViewportSize( { width: 1440, height: 1300 } );

		// 4. Comments → Convocept.
		await requestUtils.rest( {
			method: 'POST',
			path: '/convocept-e2e/v1/reset-settings',
		} );
		await page.goto( '/wp-admin/edit-comments.php?page=convocept' );
		await expect(
			page.getByRole( 'heading', { level: 1, name: 'General' } )
		).toBeVisible();
		await capture( page.locator( '#wpwrap' ), 'screenshot-4.png' );

		// 5. The Disqus dry run.
		await requestUtils.rest( {
			method: 'POST',
			path: '/convocept-e2e/v1/reset-import',
		} );
		const post = await requestUtils.createPost( {
			title: 'Our favourite writing tools',
			status: 'publish',
		} );
		const names = [
			'Alex Moreau',
			'Jo Park',
			'Sofia Rossi',
			'Ben Carter',
			'Nadia Haddad',
		];
		const posts = names
			.map(
				( name, i ) => `<post dsq:id="${ 5000 + i }">
				<id />
				<message><![CDATA[<p>${
					[
						'I still draft everything in plain text first.',
						'Has anyone tried writing on an e-ink tablet?',
						'Great list. I would add a good grammar checker.',
						'Outlines first, then the words come easier.',
						'Reading it aloud catches more mistakes than any tool.',
					][ i ]
				}</p>]]></message>
				<createdAt>2019-0${ i + 1 }-14T10:00:00Z</createdAt>
				<isDeleted>false</isDeleted><isSpam>${ i === 4 ? 'true' : 'false' }</isSpam>
				<author><name>${ name }</name><isAnonymous>true</isAnonymous></author>
				<thread dsq:id="1" />
			</post>`
			)
			.join( '' );
		await page.goto(
			'/wp-admin/edit-comments.php?page=convocept&tab=import'
		);
		await page
			.getByRole( 'group', { name: 'Export file' } )
			.locator( 'input[type="file"]' )
			.setInputFiles( {
				name: 'disqus-export.xml',
				mimeType: 'text/xml',
				buffer: Buffer.from(
					`<?xml version="1.0" encoding="utf-8"?><disqus xmlns="http://disqus.com" xmlns:dsq="http://disqus.com/disqus-internals"><thread dsq:id="1"><id>${ post.id }</id><link>http://example.invalid/</link><title>Tools</title></thread>${ posts }</disqus>`
				),
			} );
		await page.getByRole( 'button', { name: 'Upload and review' } ).click();
		await expect(
			page.getByRole( 'heading', { name: 'Dry run' } )
		).toBeVisible();
		await capture( page.locator( '#wpwrap' ), 'screenshot-5.png' );
		await requestUtils.rest( {
			method: 'POST',
			path: '/convocept-e2e/v1/reset-import',
		} );
	} );

	test( 'screenshot of the subscription page', async ( {
		browser,
		requestUtils,
	} ) => {
		await requestUtils.rest( {
			method: 'DELETE',
			path: '/convocept-e2e/v1/mails',
		} );
		// Earlier runs' subscriptions would be listed too, so start clean.
		const address = 'sam@example.com';
		await requestUtils.rest( {
			method: 'POST',
			path: '/convocept-e2e/v1/reset-subscriptions',
			data: { email: address },
		} );
		const post = await requestUtils.createPost( {
			title: 'How we halved our page weight',
			status: 'publish',
		} );
		const { page, context } = await openAsGuest( browser, post.link, {
			viewport: { width: 1280, height: 900 },
		} );

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
		await page
			.getByRole( 'button', { name: 'Confirm subscription' } )
			.click();
		await expect(
			page.locator( '.convocept-subscriptions' )
		).toContainText( 'confirmed' );

		// 6. The page title and the list, with the theme's background around them.
		const box = await page.evaluate( () => {
			const title = document.querySelector( 'main h1' );
			const list = document.querySelector( '.convocept-subscriptions' );
			const top = title.getBoundingClientRect();
			const bottom = list.getBoundingClientRect();
			const left = Math.min( top.left, bottom.left );

			return {
				x: left,
				y: top.top + window.scrollY,
				width: Math.max( top.right, bottom.right ) - left,
				height: bottom.bottom - top.top,
			};
		} );
		const pad = 64;
		const width = Math.max( box.width + pad * 2, 1000 );
		const x = Math.max( 0, box.x - ( width - box.width ) / 2 );

		await page.screenshot( {
			path: path.join( OUT, 'screenshot-6.png' ),
			animations: 'disabled',
			fullPage: true,
			clip: {
				x,
				y: Math.max( 0, box.y - pad ),
				width,
				height: box.height + pad * 2,
			},
		} );
		await context.close();
	} );
} );
