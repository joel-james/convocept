/**
 * End-to-end test for subscriptions (M6): subscribe → confirm → receive →
 * unsubscribe, plus RFC 8058 one-click unsubscribe.
 *
 * Emails are captured by the test-only mu-plugin in tests/e2e/mu-plugins.
 */

/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Get captured emails sent to an address.
 *
 * @param {Object} requestUtils Request utilities fixture.
 * @param {string} address      Recipient.
 * @return {Promise<Object[]>} Emails.
 */
async function mailsTo( requestUtils, address ) {
	const mails = await requestUtils.rest( {
		path: '/convocept-e2e/v1/mails',
	} );

	return mails.filter( ( mail ) => mail.to.includes( address ) );
}

/**
 * Pull the first link to the subscription page with an action out of an email.
 *
 * @param {Object} mail   Captured email.
 * @param {string} action 'confirm', 'manage' or 'unsubscribe'.
 * @return {string} URL.
 */
function linkIn( mail, action ) {
	const match = mail.message.match(
		new RegExp( `href="([^"]*convocept=${ action }[^"]*)"` )
	);

	return match
		? match[ 1 ].replace( /&amp;/g, '&' ).replace( /&#038;/g, '&' )
		: '';
}

test.describe( 'Subscriptions', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin(
			'convocept-fast-native-comment-system'
		);
		await requestUtils.activateTheme( 'twentytwentyfive' );
	} );

	test.beforeEach( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			method: 'DELETE',
			path: '/convocept-e2e/v1/mails',
		} );
	} );

	test( 'subscribe, confirm, receive a notification, unsubscribe', async ( {
		browser,
		requestUtils,
	} ) => {
		const address = `reader-${ Date.now() }@example.com`;
		const post = await requestUtils.createPost( {
			title: `Subscriptions ${ Date.now() }`,
			status: 'publish',
		} );
		const context = await browser.newContext( {
			storageState: { cookies: [], origins: [] },
		} );
		const page = await context.newPage();

		// 1. Subscribe without commenting.
		await page.goto( `${ post.link }#comments` );
		await page.locator( '.convocept-subscribe summary' ).click();
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

		// 2. Confirm from the email; a GET alone changes nothing.
		const [ confirmation ] = await mailsTo( requestUtils, address );
		const confirmUrl = linkIn( confirmation, 'confirm' );
		expect( confirmUrl ).not.toContain( encodeURIComponent( address ) );
		expect( confirmUrl ).not.toContain( address );

		await page.goto( confirmUrl );
		await expect( page ).toHaveTitle( /comment subscriptions/i );
		await page
			.getByRole( 'button', { name: 'Confirm subscription' } )
			.click();
		await expect( page.locator( 'body' ) ).toContainText(
			'Your subscription is confirmed'
		);

		// 3. A new comment is emailed.
		await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/comments',
			data: {
				post: post.id,
				content: 'Fresh discussion point',
				author_name: 'Writer',
				author_email: 'writer@example.com',
				author_ip: '203.0.113.50',
				status: 'approved',
			},
		} );
		await requestUtils.rest( {
			method: 'POST',
			path: '/convocept-e2e/v1/run-queue',
		} );

		const notification = ( await mailsTo( requestUtils, address ) ).find(
			( mail ) => mail.subject.includes( 'commented on' )
		);
		expect( notification.message ).toContain( 'Fresh discussion point' );
		expect(
			notification.headers.some( ( header ) =>
				header.startsWith(
					'List-Unsubscribe-Post: List-Unsubscribe=One-Click'
				)
			)
		).toBe( true );

		// 4. Unsubscribe from the email link.
		await page.goto( linkIn( notification, 'unsubscribe' ) );
		await page
			.getByRole( 'button', { name: 'Unsubscribe', exact: true } )
			.click();
		await expect( page.locator( 'body' ) ).toContainText(
			'You are unsubscribed'
		);

		await context.close();
	} );

	test( 'one-click unsubscribe works without a page', async ( {
		page,
		request,
		requestUtils,
	} ) => {
		const address = `oneclick-${ Date.now() }@example.com`;
		const post = await requestUtils.createPost( {
			title: `One click ${ Date.now() }`,
			status: 'publish',
		} );

		// A logged-in reader subscribing their own address needs no confirmation.
		const admin = await requestUtils.rest( {
			path: '/wp/v2/users/me',
			params: { context: 'edit' },
		} );
		await page.goto( `${ post.link }#comments` );
		await page.locator( '.convocept-subscribe summary' ).click();
		await page
			.locator( '.convocept-subscribe input[type="email"]' )
			.fill( admin.email );
		await page
			.locator( '.convocept-subscribe' )
			.getByRole( 'button', { name: 'Subscribe' } )
			.click();
		await expect(
			page.locator( '[data-convocept-subscribe-message]' )
		).toHaveText( 'You are subscribed.' );

		await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/comments',
			data: {
				post: post.id,
				content: 'Ping',
				author_name: address,
				author_email: address,
				author_ip: '203.0.113.51',
				status: 'approved',
			},
		} );
		await requestUtils.rest( {
			method: 'POST',
			path: '/convocept-e2e/v1/run-queue',
		} );

		const [ mail ] = await mailsTo( requestUtils, admin.email );
		const url = linkIn( mail, 'unsubscribe' );

		// What a mail app does for RFC 8058.
		const response = await request.post( url, {
			form: { 'List-Unsubscribe': 'One-Click' },
		} );
		expect( response.status() ).toBe( 200 );

		await page.goto( url );
		await expect( page.locator( 'body' ) ).toContainText( 'Unsubscribed' );
	} );
} );
