/**
 * Upvotes.
 *
 * The count is in the page HTML; the script makes the button work and
 * marks the comments the visitor already upvoted (from the session).
 */
import { request } from './api';
import { getSession } from './session';

/**
 * Wire up vote buttons in a thread.
 *
 * @param {Element} root Thread root.
 */
export function initVotes( root ) {
	const voted = new Set();

	const mark = ( items ) =>
		items.forEach( ( item ) => {
			const button = item.querySelector(
				':scope > * [data-convocept-vote]'
			);

			if ( button ) {
				button.setAttribute(
					'aria-pressed',
					String( voted.has( item.dataset.convoceptComment ) )
				);
			}
		} );

	// Attach the click handler now (a queued click may be replayed at once);
	// mark earlier votes when the session arrives.
	getSession().then( ( session ) => {
		( session.votes || [] ).forEach( ( id ) => voted.add( String( id ) ) );
		mark( root.querySelectorAll( '[data-convocept-comment]' ) );
	} );

	root.addEventListener( 'convocept:added', ( event ) =>
		event.detail.forEach( ( item ) =>
			mark( [
				item,
				...item.querySelectorAll( '[data-convocept-comment]' ),
			] )
		)
	);

	root.addEventListener( 'click', async ( event ) => {
		const button = event.target.closest( '[data-convocept-vote]' );

		if ( ! button || button.getAttribute( 'aria-busy' ) ) {
			return;
		}

		button.setAttribute( 'aria-busy', 'true' );

		// Waiting for the session also lets it mark earlier votes first, so
		// a click replayed straight after loading reads the right state.
		const session = await getSession();
		const id = button.closest( '[data-convocept-comment]' ).dataset
			.convoceptComment;
		const count = button.querySelector( '[data-convocept-vote-count]' );
		const active = button.getAttribute( 'aria-pressed' ) !== 'true';
		const before = Number( count.textContent.replace( /\D/g, '' ) ) || 0;

		// Optimistic: update now, correct from the server's answer.
		button.setAttribute( 'aria-pressed', String( active ) );
		count.textContent = ( before + ( active ? 1 : -1 ) ).toLocaleString();

		try {
			const result = await request( `comments/${ id }/reactions`, {
				method: 'POST',
				nonce: session.nonce,
				data: { reaction: 'up', active },
			} );
			count.textContent = result.count.toLocaleString();
			voted[ active ? 'add' : 'delete' ]( id );
		} catch {
			button.setAttribute( 'aria-pressed', String( ! active ) );
			count.textContent = before.toLocaleString();
		} finally {
			button.removeAttribute( 'aria-busy' );
		}
	} );
}
