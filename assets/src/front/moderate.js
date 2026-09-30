/**
 * Front-end moderator tools.
 *
 * Only for visitors the session reports as moderators. Pending comments
 * are fetched over an authenticated request and never appear in the
 * cached page. Every action is re-checked on the server.
 */
import { request } from './api';
import { config } from './config';
import { added, cloneTemplate, parseHtml } from './dom';
import { getSession } from './session';

/**
 * Start moderator tools for a thread when the visitor may moderate.
 *
 * @param {Element} root Thread root.
 */
export async function initModerate( root ) {
	const session = await getSession();

	if ( ! session.can_moderate ) {
		return;
	}

	const decorate = ( items ) =>
		items.forEach( ( item ) => addMenu( root, item ) );

	decorate( root.querySelectorAll( '[data-convocept-comment]' ) );
	root.addEventListener( 'convocept:added', ( event ) =>
		event.detail.forEach( ( item ) =>
			decorate( [
				item,
				...item.querySelectorAll( '[data-convocept-comment]' ),
			] )
		)
	);

	root.addEventListener( 'click', ( event ) => {
		const toggle = event.target.closest( '[data-convocept-mod-toggle]' );
		const button = event.target.closest( '[data-convocept-mod]' );

		closeMenus( root, toggle );

		if ( toggle ) {
			setOpen(
				toggle,
				toggle.getAttribute( 'aria-expanded' ) !== 'true'
			);
		} else if ( button ) {
			act( root, session, button );
		}
	} );

	// Escape closes the open menu and returns focus to its toggle.
	root.addEventListener( 'keydown', ( event ) => {
		const menu = event.target.closest( '[data-convocept-mod-menu]' );

		if ( event.key === 'Escape' && menu ) {
			const toggle = menu.querySelector( '[data-convocept-mod-toggle]' );

			setOpen( toggle, false );
			toggle.focus();
		}
	} );

	document.addEventListener( 'click', ( event ) => {
		if ( ! root.contains( event.target ) ) {
			closeMenus( root );
		}
	} );

	loadPending( root, session );
}

/**
 * Open or close one comment's moderator menu.
 *
 * @param {HTMLButtonElement} toggle The menu's toggle button.
 * @param {boolean}           open   Whether to open it.
 */
function setOpen( toggle, open ) {
	toggle.setAttribute( 'aria-expanded', String( open ) );
	toggle.nextElementSibling.hidden = ! open;
}

/**
 * Close every open moderator menu in the thread.
 *
 * @param {Element}      root   Thread root.
 * @param {Element|null} [keep] Toggle whose menu stays as it is.
 */
function closeMenus( root, keep = null ) {
	root.querySelectorAll(
		'[data-convocept-mod-toggle][aria-expanded="true"]'
	).forEach( ( toggle ) => toggle !== keep && setOpen( toggle, false ) );
}

/**
 * Add the moderator menu to one comment, with the actions that apply.
 *
 * @param {Element} root Thread root.
 * @param {Element} item Comment list item.
 */
function addMenu( root, item ) {
	const actions = item.querySelector( ':scope > * [data-convocept-actions]' );

	if ( ! actions ) {
		return;
	}

	const old = actions.querySelector( '[data-convocept-mod-menu]' );

	if ( old ) {
		old.remove();
	}

	const menu = cloneTemplate( root, 'moderate' );
	const pending = item.classList.contains( 'convocept-comment--pending' );
	const topLevel = ! item.parentElement.closest( '[data-convocept-comment]' );
	const pinned = item.hasAttribute( 'data-convocept-pinned' );
	const keep = {
		approve: pending,
		pin: ! pending && topLevel && ! pinned,
		unpin: ! pending && topLevel && pinned,
		spam: true,
		trash: true,
	};

	menu.querySelectorAll( '[data-convocept-mod]' ).forEach( ( button ) => {
		if ( ! keep[ button.dataset.convoceptMod ] ) {
			button.remove();
		}
	} );

	menu.querySelector( '[data-convocept-mod-edit]' ).href = `${
		config.adminUrl
	}comment.php?action=editcomment&c=${ item.dataset.convoceptComment }`;

	actions.append( menu );
}

/**
 * Run a moderation action and update the thread in place.
 *
 * @param {Element}           root    Thread root.
 * @param {Object}            session Session.
 * @param {HTMLButtonElement} button  Clicked action.
 */
async function act( root, session, button ) {
	const item = button.closest( '[data-convocept-comment]' );
	const id = item.dataset.convoceptComment;
	const action = button.dataset.convoceptMod;
	const pin = action === 'pin' || action === 'unpin';

	button.disabled = true;

	try {
		const result = await request(
			pin ? `comments/${ id }/pin` : `comments/${ id }/moderate`,
			{
				method: 'POST',
				nonce: session.nonce,
				data: pin ? { pinned: action === 'pin' } : { action },
			}
		);

		if ( ! result.html ) {
			item.remove();
			return;
		}

		// Swap only the comment's own body so its replies stay in place.
		const fresh = parseHtml( result.html );
		item.className = fresh.className;
		item.toggleAttribute(
			'data-convocept-pinned',
			fresh.hasAttribute( 'data-convocept-pinned' )
		);
		item.querySelector( ':scope > .convocept-comment__body' ).replaceWith(
			fresh.querySelector( '.convocept-comment__body' )
		);
		added( root, [ item ] );
	} catch ( error ) {
		button.disabled = false;
		button.title = error.message;
	}
}

/**
 * Show comments awaiting moderation where they belong in the thread.
 *
 * @param {Element} root    Thread root.
 * @param {Object}  session Session.
 */
async function loadPending( root, session ) {
	try {
		const result = await request(
			`posts/${ root.dataset.convoceptPost }/pending`,
			{ nonce: session.nonce }
		);
		const inserted = [];

		result.items.forEach( ( entry ) => {
			if (
				root.querySelector( `[data-convocept-comment="${ entry.id }"]` )
			) {
				return;
			}

			const comment = parseHtml( entry.html );
			const parent = entry.parent
				? root.querySelector(
						`[data-convocept-comment="${ entry.parent }"]`
					)
				: null;
			const list = root.querySelector( '.convocept-list' );

			if ( parent ) {
				let replies = parent.querySelector(
					':scope > .convocept-replies'
				);

				if ( ! replies ) {
					replies = cloneTemplate( root, 'replies' );
					parent
						.querySelector( ':scope > .convocept-comment__body' )
						.after( replies );
				}

				replies.append( comment );
			} else if ( list ) {
				list.prepend( comment );
			} else {
				return;
			}

			inserted.push( comment );
		} );

		added( root, inserted );
	} catch {
		// Not fatal: moderators can still use the dashboard.
	}
}
