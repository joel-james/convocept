/**
 * Optional live updates: check for new comments while the tab is visible.
 *
 * Off unless the site owner enables it. Checks are conditional requests
 * (If-None-Match), so an unchanged thread costs the server one tiny 304.
 * Nothing runs while the tab is hidden.
 */
import { routeUrl } from './api';
import { config, t } from './config';
import { added, announce, cloneTemplate, parseHtml } from './dom';

/**
 * Start polling a thread, if live updates are enabled.
 *
 * @param {Element} root Thread root.
 */
export function initLive( root ) {
	if ( ! config.poll ) {
		return;
	}

	const interval = config.poll * 1000;
	let etag = '';
	let last = Date.now();

	const check = async () => {
		if ( document.visibilityState !== 'visible' ) {
			return;
		}

		last = Date.now();

		try {
			const response = await fetch(
				routeUrl( `posts/${ root.dataset.convoceptPost }/updates`, {
					after: String( newestId( root ) ),
				} ),
				{
					headers: etag ? { 'If-None-Match': etag } : {},
					cache: 'no-store',
					credentials: 'same-origin',
				}
			);

			if ( response.status !== 200 ) {
				return;
			}

			etag = response.headers.get( 'ETag' ) || '';
			insert( root, await response.json() );
		} catch {
			// Offline or server error: try again next time.
		}
	};

	window.setInterval( check, interval );
	document.addEventListener( 'visibilitychange', () => {
		if ( Date.now() - last >= interval ) {
			check();
		}
	} );
}

/**
 * Get the highest comment ID on the page.
 *
 * @param {Element} root Thread root.
 * @return {number} Comment ID, 0 when there are none.
 */
function newestId( root ) {
	let max = 0;

	root.querySelectorAll( '[data-convocept-comment]' ).forEach( ( item ) => {
		max = Math.max( max, Number( item.dataset.convoceptComment ) );
	} );

	return max;
}

/**
 * Insert new comments where the reader would expect them.
 *
 * Replies go under their parent when it is on the page. Top-level comments
 * are added only where they belong in the current view: the top of page 1
 * when sorted newest first, or the end of the last page when oldest first.
 *
 * @param {Element} root Thread root.
 * @param {Object}  data Response: items and total.
 */
function insert( root, data ) {
	const {
		convoceptSort: sort,
		convoceptPage: page,
		convoceptPages: pages,
	} = root.dataset;
	const inserted = [];

	data.items.forEach( ( item ) => {
		if ( root.querySelector( `[data-convocept-comment="${ item.id }"]` ) ) {
			return;
		}

		const comment = parseHtml( item.html );
		const parent = item.parent
			? root.querySelector(
					`[data-convocept-comment="${ item.parent }"]`
				)
			: null;

		if ( parent ) {
			let replies = parent.querySelector( ':scope > .convocept-replies' );

			if ( ! replies ) {
				replies = cloneTemplate( root, 'replies' );
				parent
					.querySelector( ':scope > .convocept-comment__body' )
					.after( replies );
			}

			replies.append( comment );
		} else if ( ! item.parent && sort === 'newest' && page === '1' ) {
			list( root ).prepend( comment );
		} else if (
			! item.parent &&
			sort === 'oldest' &&
			Number( page ) >= Number( pages ) &&
			! root.querySelector( '[data-convocept-load-more]' )
		) {
			list( root ).append( comment );
		} else {
			return;
		}

		comment.classList.add( 'convocept-comment--new' );
		inserted.push( comment );
	} );

	if ( ! inserted.length ) {
		return;
	}

	added( root, inserted );

	const title = root.querySelector( '.convocept-title' );

	if ( title ) {
		title.textContent = t(
			data.total === 1 ? 'countOne' : 'countMany',
			data.total.toLocaleString()
		);
	}

	announce(
		root.querySelector( '[data-convocept-status]' ),
		t( 'newComments', inserted.length )
	);
}

/**
 * Get the top-level list, creating it in an empty thread.
 *
 * @param {Element} root Thread root.
 * @return {Element} The list.
 */
function list( root ) {
	let element = root.querySelector( '.convocept-list' );

	if ( ! element ) {
		element = cloneTemplate( root, 'list' );
		const empty = root.querySelector( '.convocept-empty' );

		if ( empty ) {
			empty.replaceWith( element );
		} else {
			root.querySelector( '#respond' ).after( element );
		}
	}

	return element;
}
