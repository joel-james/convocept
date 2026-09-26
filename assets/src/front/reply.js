/**
 * Inline replies: the composer moves under the comment being answered.
 *
 * Without JavaScript the same Reply links reload the page in reply mode
 * (core's `replytocom`); with it, the one server-rendered form is moved, so
 * no second form markup exists anywhere.
 */
import { t } from './config';

/** @type {WeakMap<Element, Comment>} Where each thread's composer normally lives. */
const homes = new WeakMap();

/**
 * Handle Reply and Cancel links in a thread.
 *
 * @param {Element} root Thread root.
 */
export function initReply( root ) {
	const respond = root.querySelector( '#respond' );
	const form = root.querySelector( '[data-convocept-composer]' );

	if ( ! respond || ! form ) {
		return;
	}

	const home = document.createComment( 'convocept-composer' );
	respond.before( home );
	homes.set( root, home );

	root.addEventListener( 'click', ( event ) => {
		const reply = event.target.closest( '[data-convocept-reply]' );
		const cancel = event.target.closest( '[data-convocept-cancel-reply]' );

		if ( reply ) {
			event.preventDefault();
			startReply( root, reply.closest( '[data-convocept-comment]' ) );
		} else if ( cancel ) {
			event.preventDefault();
			cancelReply( root );
		}
	} );

	// Arrived through a no-JS reply link: put the form where it belongs.
	const parent = Number( form.elements.comment_parent.value );

	if ( parent ) {
		startReply(
			root,
			root.querySelector( `[data-convocept-comment="${ parent }"]` )
		);
	}
}

/**
 * Move the composer under a comment and switch it to reply mode.
 *
 * @param {Element}      root Thread root.
 * @param {Element|null} item Comment list item being replied to.
 */
export function startReply( root, item ) {
	const respond = root.querySelector( '#respond' );
	const form = root.querySelector( '[data-convocept-composer]' );

	if ( ! item || ! respond || ! form ) {
		return;
	}

	const author = item.querySelector( '.convocept-comment__author' );
	const replying = form.querySelector( '[data-convocept-replying]' );

	item.querySelector( ':scope > .convocept-comment__body' ).after( respond );
	form.elements.comment_parent.value = item.dataset.convoceptComment;
	form.querySelector( '[data-convocept-replying-to]' ).textContent = t(
		'replyingTo',
		author ? author.textContent.trim() : ''
	);
	replying.hidden = false;
	respond.classList.add( 'is-replying' );
	form.querySelector( '[data-convocept-input]' ).focus();
}

/**
 * Put the composer back at the top of the thread.
 *
 * @param {Element} root Thread root.
 */
export function cancelReply( root ) {
	const respond = root.querySelector( '#respond' );
	const form = root.querySelector( '[data-convocept-composer]' );
	const home = homes.get( root );

	if ( ! respond || ! form || ! home ) {
		return;
	}

	const parent = form.elements.comment_parent.value;

	home.after( respond );
	form.elements.comment_parent.value = '0';
	form.querySelector( '[data-convocept-replying]' ).hidden = true;
	respond.classList.remove( 'is-replying' );

	// Return focus to the Reply link the reader started from.
	const link = root.querySelector(
		`[data-convocept-comment="${ parent }"] [data-convocept-reply]`
	);

	if ( link ) {
		link.focus();
	}
}
