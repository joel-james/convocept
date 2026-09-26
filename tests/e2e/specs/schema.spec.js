/**
 * End-to-end tests for comment structured data (M7).
 *
 * Checks the JSON-LD a real theme page carries, on a classic theme (Twenty
 * Twenty-One) and a block theme (Twenty Twenty-Five), and that comment
 * pages are plain crawlable links.
 */

/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const THEMES = [ 'twentytwentyone', 'twentytwentyfive' ];

/**
 * Read every JSON-LD block on the current page.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<Object[]>} Parsed JSON-LD documents.
 */
async function jsonLd( page ) {
	const blocks = await page
		.locator( 'script[type="application/ld+json"]' )
		.allTextContents();

	return blocks.map( ( text ) => JSON.parse( text ) );
}

for ( const theme of THEMES ) {
	test.describe( `Structured data on ${ theme }`, () => {
		test.beforeAll( async ( { requestUtils } ) => {
			await requestUtils.activatePlugin( 'convocept' );
			await requestUtils.activateTheme( theme );
			await requestUtils.deleteAllComments();
		} );

		test( 'describes visible comments as nested Comment items', async ( {
			page,
			requestUtils,
		} ) => {
			const post = await requestUtils.createPost( {
				title: `Convocept schema ${ Date.now() }`,
				content: 'Article body',
				status: 'publish',
			} );
			const parent = await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/comments',
				data: {
					post: post.id,
					content: 'Closing tag </script> in text',
					author_name: 'Ada',
					author_email: 'ada@example.com',
					author_ip: '203.0.113.20',
					status: 'approved',
				},
			} );
			const reply = await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/comments',
				data: {
					post: post.id,
					parent: parent.id,
					content: 'A reply',
					author_name: 'Grace',
					author_email: 'grace@example.com',
					author_ip: '203.0.113.20',
					status: 'approved',
				},
			} );

			await page.goto( post.link );

			const documents = await jsonLd( page );
			const node = documents.find( ( doc ) => doc.comment );

			expect( node ).toBeTruthy();
			expect( node[ '@context' ] ).toBe( 'https://schema.org' );
			expect( node[ '@type' ] ).toBe( 'BlogPosting' );
			expect( node.commentCount ).toBe( 2 );
			expect( node.comment ).toHaveLength( 1 );

			const [ top ] = node.comment;
			expect( top[ '@type' ] ).toBe( 'Comment' );
			expect( top.author ).toEqual( { '@type': 'Person', name: 'Ada' } );
			expect( top.text ).toContain( 'Closing tag' );
			expect( top.datePublished ).toMatch( /^\d{4}-\d{2}-\d{2}T/ );
			expect( top.url ).toContain( `#comment-${ parent.id }` );
			expect( top.comment[ 0 ].url ).toContain(
				`#comment-${ reply.id }`
			);

			// Every described comment is in the server HTML.
			await expect(
				page.locator( `#comment-${ parent.id }` )
			).toHaveCount( 1 );
			await expect(
				page.locator( `#comment-${ reply.id }` )
			).toHaveCount( 1 );
		} );

		test( 'comment pages are crawlable links, also without JavaScript', async ( {
			browser,
			requestUtils,
		} ) => {
			const post = await requestUtils.createPost( {
				title: `Convocept pages ${ Date.now() }`,
				status: 'publish',
			} );

			// 21 top-level comments: two pages at the default 20 per page.
			for ( let i = 1; i <= 21; i++ ) {
				await requestUtils.rest( {
					method: 'POST',
					path: '/wp/v2/comments',
					data: {
						post: post.id,
						content: `Paged comment ${ i }`,
						author_name: `Reader ${ i }`,
						author_email: `paged${ i }@example.com`,
						author_ip: '203.0.113.21',
						status: 'approved',
					},
				} );
			}

			const context = await browser.newContext( {
				javaScriptEnabled: false,
			} );
			const page = await context.newPage();

			await page.goto( post.link );

			const next = page.locator( 'a.convocept-pagination__next' );
			await expect( next ).toHaveAttribute( 'rel', 'next' );

			const href = await next.getAttribute( 'href' );
			expect( new URL( href ).searchParams.get( 'cpage' ) ).toBe( '2' );

			await page.goto( href );
			await expect( page.locator( '.convocept-comment' ) ).toHaveCount(
				1
			);

			const node = ( await jsonLd( page ) ).find(
				( doc ) => doc.comment
			);
			expect( node.comment ).toHaveLength( 1 );
			expect( node.comment[ 0 ].text ).toBe( 'Paged comment 21' );
			expect( node.url ).toBe( post.link );

			await context.close();
		} );
	} );
}
