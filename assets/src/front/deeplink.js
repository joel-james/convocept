/**
 * Follow #comment-{ID} links to comments that are not on this page.
 *
 * Links built by WordPress already point at the right page (Convocept
 * filters get_comment_link()), so this only matters for old or hand-made
 * links. The browser's :target styling highlights the comment.
 */
import { request } from './api';

/**
 * Check the URL fragment and go to the page holding the comment if needed.
 */
export async function initDeepLink() {
	const match = window.location.hash.match( /^#comment-(\d+)$/ );

	if ( ! match ) {
		return;
	}

	// Never bounce between pages if the target still isn't there after one hop.
	const tried = `convocept-deeplink-${ match[ 1 ] }`;

	try {
		if ( document.getElementById( `comment-${ match[ 1 ] }` ) ) {
			window.sessionStorage.removeItem( tried );
			return;
		}

		if ( window.sessionStorage.getItem( tried ) ) {
			return;
		}

		window.sessionStorage.setItem( tried, '1' );
	} catch {
		return;
	}

	try {
		const result = await request( `comments/${ match[ 1 ] }/context` );

		if ( result.url && result.url !== window.location.href ) {
			window.location.replace( result.url );
		}
	} catch {
		// Unknown or unapproved comment: stay on the page.
	}
}
