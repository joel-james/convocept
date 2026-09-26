/**
 * "Load more comments" and "Show N more replies".
 *
 * Both enhance plain links that work without JavaScript (numbered pages
 * and a branch-expanding URL); the script fetches the same server-rendered
 * comments and appends them in place.
 */
import { request } from './api';
import { t } from './config';
import {
	added,
	announce,
	cloneTemplate,
	focusElement,
	parseFragment,
} from './dom';

/**
 * Wire up the load-more button and more-replies links in a thread.
 *
 * @param {Element} root Thread root.
 */
export function initMore( root ) {
	const loadMore = root.querySelector( '[data-convocept-load-more]' );

	if ( loadMore ) {
		const button = loadMore.querySelector( 'button' );
		const pagination = root.querySelector( '[data-convocept-pagination]' );

		loadMore.hidden = false;

		// On the first page the button replaces the numbered links.
		if ( pagination && Number( button.dataset.convoceptNextPage ) === 2 ) {
			pagination.hidden = true;
		}

		button.addEventListener( 'click', () =>
			loadPage( root, loadMore, button )
		);
	}

	root.addEventListener( 'click', ( event ) => {
		const link = event.target.closest( '[data-convocept-more-replies]' );

		if ( link ) {
			event.preventDefault();
			loadReplies( root, link );
		}
	} );
}

/**
 * Append the next page of top-level comments.
 *
 * @param {Element}           root    Thread root.
 * @param {Element}           wrapper Button wrapper.
 * @param {HTMLButtonElement} button  Load-more button.
 */
async function loadPage( root, wrapper, button ) {
	const page = Number( button.dataset.convoceptNextPage );
	const status = root.querySelector( '[data-convocept-status]' );

	button.disabled = true;

	try {
		const result = await request(
			`posts/${ root.dataset.convoceptPost }/comments`,
			{
				query: {
					page: String( page ),
					sort: button.dataset.convoceptSort,
				},
			}
		);
		const list = root.querySelector( '.convocept-list' );
		const fragment = parseFragment( result.html );
		const first = fragment.firstElementChild;

		// Skip comments already on the page (e.g. posted since it loaded).
		Array.from( fragment.children ).forEach( ( item ) => {
			if (
				root.querySelector(
					`[data-convocept-comment="${ item.dataset.convoceptComment }"]`
				)
			) {
				item.remove();
			}
		} );

		const items = Array.from( fragment.children );
		list.append( fragment );
		added( root, items );
		focusElement( first && first.isConnected ? first : null );

		if ( page >= result.pages ) {
			wrapper.remove();
		} else {
			button.dataset.convoceptNextPage = String( page + 1 );
		}
	} catch ( error ) {
		announce( status, error.message, true );
	} finally {
		button.disabled = false;
	}
}

/**
 * Append a comment's hidden replies.
 *
 * @param {Element}           root Thread root.
 * @param {HTMLAnchorElement} link The "Show N more replies" link.
 */
async function loadReplies( root, link ) {
	if ( link.getAttribute( 'aria-busy' ) ) {
		return;
	}

	const id = link.dataset.convoceptMoreReplies;
	const offset = Number( link.dataset.convoceptOffset );
	const item = root.querySelector( `[data-convocept-comment="${ id }"]` );

	link.setAttribute( 'aria-busy', 'true' );

	try {
		const result = await request( `comments/${ id }/replies`, {
			query: { offset: String( offset ) },
		} );
		const fragment = parseFragment( result.html );
		const count = fragment.children.length;
		const first = fragment.firstElementChild;
		let replies = item.querySelector( ':scope > .convocept-replies' );

		if ( ! replies ) {
			replies = cloneTemplate( root, 'replies' );
			item.querySelector( ':scope > .convocept-comment__body' ).after(
				replies
			);
		}

		const items = Array.from( fragment.children );
		replies.append( fragment );
		added( root, items );
		focusElement( first );

		if ( result.remaining > 0 ) {
			link.dataset.convoceptOffset = String( offset + count );
			link.textContent = t(
				result.remaining === 1 ? 'moreReply' : 'moreReplies',
				result.remaining.toLocaleString()
			);
		} else {
			link.parentElement.remove();
		}
	} catch ( error ) {
		link.textContent = error.message;
	} finally {
		link.removeAttribute( 'aria-busy' );
	}
}
