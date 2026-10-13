/**
 * Renders the WordPress.org plugin assets into `.wordpress-org/`.
 *
 * Run with `npm run wporg:assets` against the wp-env tests site. The icon and
 * banner use the convocept.com palette and fonts, in the style of the big
 * directory listings (floating UI cards on a solid ground); the fonts load from
 * Google Fonts, so generating needs network access. The
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

// The convocept.com palette: black and white grounds with four hues, which
// always carry black text.
const HUES = {
	emerald: '#10c877',
	amber: '#f5a30b',
	purple: '#9a5cf5',
	blue: '#4d6bfa',
};

// The mark's speech bubble (a rounded square with a square bottom-left corner).
const BUBBLE =
	'M34 2h32a32 32 0 0 1 32 32v32a32 32 0 0 1-32 32H2V34A32 32 0 0 1 34 2z';

// Three bubbles on a black rounded tile, as in the convocept.com launch post
// image: a comment, a reply and someone typing. The corners stay transparent.
const ICON = `<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256">
	<rect width="256" height="256" rx="56" fill="#000"/>
	<g transform="translate(128 128) scale(0.9) translate(-128 -128)">
		<path d="M56 44h92a20 20 0 0 1 20 20v12a20 20 0 0 1-20 20H36V64a20 20 0 0 1 20-20z" fill="${ HUES.amber }"/>
		<path d="M58 63h70M58 78h44" stroke="#000" stroke-opacity=".55" stroke-width="8" stroke-linecap="round"/>
		<path d="M108 108h92a20 20 0 0 1 20 20v8a20 20 0 0 1-20 20H88v-28a20 20 0 0 1 20-20z" fill="${ HUES.purple }"/>
		<path d="M110 132h76" stroke="#000" stroke-opacity=".55" stroke-width="8" stroke-linecap="round"/>
		<path d="M72 168h64a20 20 0 0 1 20 20v6a20 20 0 0 1-20 20H52v-26a20 20 0 0 1 20-20z" fill="${ HUES.emerald }"/>
		<circle cx="82" cy="191" r="7"/><circle cx="104" cy="191" r="7"/><circle cx="126" cy="191" r="7"/>
	</g>
</svg>
`;

const FONTS =
	'<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600..800&family=Figtree:wght@400..800&display=block" rel="stylesheet">';

// Lucide-style feature icons, drawn on a 24px grid.
const FEATURE_ICONS = {
	composer: '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
	threads:
		'<path d="M6 3v12M18 9a9 9 0 0 1-9 9"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/>',
	upvotes: '<path d="M12 19V5M5 12l7-7 7 7"/>',
	subscriptions:
		'<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
	moderation:
		'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
	import: '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
};

// Every claim on the banner is a 1.0 feature.
const FEATURES = [
	[ 'emerald', 'composer', 'Composer' ],
	[ 'amber', 'threads', 'Threads' ],
	[ 'purple', 'upvotes', 'Upvotes &amp; pins' ],
	[ 'blue', 'subscriptions', 'Subscriptions' ],
	[ 'emerald', 'moderation', 'Moderation' ],
	[ 'amber', 'import', 'Disqus import' ],
];

/**
 * Build an outline of the mark's bubble, drawn as a background loop.
 *
 * @param {string} style Inline position, size and rotation.
 * @return {string} SVG markup.
 */
function loop( style ) {
	return `<svg class="loop" style="${ style }" viewBox="0 0 100 100"><path d="${ BUBBLE }" vector-effect="non-scaling-stroke"/></svg>`;
}

// Three columns of equal height with equal gutters, so no card touches
// another; the comments mirror the convocept.com hero.
const BANNER = `${ FONTS }
<style>
	html, body { margin: 0; }
	* { box-sizing: border-box; }
	.banner { position: relative; width: 772px; height: 250px; overflow: hidden; padding: 26px 40px; display: flex; align-items: stretch; gap: 24px; background: #000; font-family: 'Figtree', sans-serif; color: #fff; }
	.banner > * { position: relative; }
	.loop { position: absolute !important; fill: none; stroke: #161616; stroke-width: 5; }
	.card { border-radius: 14px; background: #121212; border: 1px solid #2b2b2b; }
	.list { flex: none; width: 184px; padding: 14px 16px; display: flex; flex-direction: column; justify-content: center; gap: 5px; }
	.feature { display: flex; align-items: center; gap: 10px; font: 700 14.5px/1 'Bricolage Grotesque'; letter-spacing: -0.01em; }
	.feature i { flex: none; width: 22px; height: 22px; border-radius: 7px 7px 7px 2px; display: grid; place-items: center; }
	.feature svg { width: 13px; height: 13px; fill: none; stroke: #000; stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; }
	.panel { flex: none; width: 300px; padding: 16px; display: flex; flex-direction: column; justify-content: center; gap: 10px; }
	.bubble { display: flex; gap: 10px; padding: 12px 14px 11px; border-radius: 18px 18px 18px 5px; color: #000; }
	.bubble.reply { margin-left: 36px; }
	.avatar { flex: none; width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center; font: 700 11px/1 'Figtree'; color: #fff; background: #000; }
	.who { display: flex; align-items: center; gap: 6px; font: 700 12px/1.2 'Figtree'; }
	.who span { font-size: 10.5px; font-weight: 600; opacity: 0.7; }
	.who em { padding: 2px 6px; border-radius: 99px; font-size: 9.5px; font-style: normal; color: #fff; background: #000; }
	.text { margin-top: 4px; font: 500 12.5px/1.35 'Figtree'; }
	.meta { margin-top: 7px; font: 700 10.5px/1 'Figtree'; }
	.side { flex: none; width: 160px; display: flex; flex-direction: column; gap: 14px; }
	.stat { flex: 1; display: flex; flex-direction: column; justify-content: center; padding: 14px 16px 15px; border-radius: 14px; color: #000; background: ${ HUES.blue }; }
	.stat b { display: block; font: 800 38px/1 'Bricolage Grotesque'; letter-spacing: -0.03em; }
	.stat span { display: block; margin-top: 6px; font: 600 11.5px/1.25 'Figtree'; }
	.toolbar { height: 44px; padding: 0 6px 0 14px; display: flex; align-items: center; gap: 13px; font: 800 14px/1 'Figtree'; }
	.toolbar svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; stroke-linejoin: round; }
	.toolbar .post { margin-left: auto; padding: 9px 14px; border-radius: 99px; font-size: 11.5px; font-weight: 700; color: #000; background: ${ HUES.emerald }; }
</style>
<div class="banner">
	${ loop( 'left: -120px; top: -170px; width: 330px; height: 330px; transform: rotate(-12deg)' ) }
	${ loop( 'left: 520px; top: 130px; width: 360px; height: 360px; transform: rotate(8deg)' ) }
	<div class="list card">
		${ FEATURES.map(
			( [ hue, icon, label ] ) =>
				`<div class="feature"><i style="background: ${ HUES[ hue ] }"><svg viewBox="0 0 24 24">${ FEATURE_ICONS[ icon ] }</svg></i>${ label }</div>`
		).join( '' ) }
	</div>
	<div class="panel card">
		<div class="bubble" style="background: ${ HUES.amber }">
			<div class="avatar">KE</div>
			<div>
				<div class="who">Kripa E. <span>2 h ago</span> <em>Pinned</em></div>
				<div class="text">Moved off Disqus. Pages feel instant again.</div>
				<div class="meta">↑ 24 · Reply</div>
			</div>
		</div>
		<div class="bubble reply" style="background: ${ HUES.purple }">
			<div class="avatar">JJ</div>
			<div>
				<div class="who">Jake J. <span>35 min ago</span></div>
				<div class="text">Subscribed to replies.</div>
			</div>
		</div>
	</div>
	<div class="side">
		<div class="stat"><b>0 KB</b><span>JavaScript on<br>page load</span></div>
		<div class="toolbar card">
			<span>B</span><span style="font-style: italic; font-weight: 700">I</span>
			<svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>
			<span class="post">Post</span>
		</div>
	</div>
</div>`;

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

		// The directory prefers icon.svg and falls back to the PNGs.
		fs.writeFileSync( path.join( OUT, 'icon.svg' ), ICON );

		for ( const size of [ 128, 256 ] ) {
			await page.setViewportSize( { width: size, height: size } );
			await page.setContent(
				`<style>html,body{margin:0}svg{display:block;width:${ size }px;height:${ size }px}</style>${ ICON }`
			);
			await page.screenshot( {
				path: path.join( OUT, `icon-${ size }x${ size }.png` ),
				omitBackground: true,
			} );
		}

		for ( const [ scale, name ] of [
			[ 1, 'banner-772x250.png' ],
			[ 2, 'banner-1544x500.png' ],
		] ) {
			const scaled = await browser.newContext( {
				viewport: { width: 772, height: 250 },
				deviceScaleFactor: scale,
			} );
			const bannerPage = await scaled.newPage();

			await bannerPage.setContent( BANNER );
			await bannerPage.evaluate( () => document.fonts.ready );
			expect(
				await bannerPage.evaluate( () =>
					document.fonts.check( "700 17px 'Bricolage Grotesque'" )
				)
			).toBe( true );
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
