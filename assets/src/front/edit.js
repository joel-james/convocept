/**
 * In-place editing of a reader's own comment during the edit window.
 */
import { request } from './api';
import { config, t } from './config';
import { announce, cloneTemplate, parseHtml } from './dom';
import { getSession } from './session';
import { readEdits } from './store';

/** @type {Set<Element>} Edit buttons with a running countdown. */
const buttons = new Set();

/** @type {number} Server clock minus browser clock, in seconds. */
let skew = 0;

/**
 * Add edit buttons to comments the visitor may still edit.
 *
 * Guests are recognised by the token stored when they posted; logged-in
 * authors by their user ID.
 *
 * @param {Element} root Thread root.
 */
export async function initEdit( root ) {
	const session = await getSession();
	const edits = readEdits();
	const windowSeconds = config.editWindow * 60;
	const userId = session.user ? String( session.user.id ) : '';

	skew = session.skew || 0;

	root.querySelectorAll( '[data-convocept-comment]' ).forEach( ( item ) => {
		const id = item.dataset.convoceptComment;
		const own = userId && item.dataset.convoceptAuthor === userId;

		if ( edits[ id ] ) {
			addEditButton( root, item, edits[ id ].until );
		} else if ( own && windowSeconds ) {
			addEditButton(
				root,
				item,
				Number( item.dataset.convoceptTime ) + windowSeconds
			);
		}
	} );

	root.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-convocept-edit]' );

		if ( button ) {
			openEditor( root, button.closest( '[data-convocept-comment]' ) );
		}
	} );
}

/**
 * Add a counting-down edit button to a comment.
 *
 * @param {Element} root  Thread root.
 * @param {Element} item  Comment list item.
 * @param {number}  until Unix time (server clock) the window closes.
 */
export function addEditButton( root, item, until ) {
	const actions = item.querySelector( '[data-convocept-actions]' );

	if (
		! until ||
		! actions ||
		actions.querySelector( '[data-convocept-edit]' )
	) {
		return;
	}

	const button = cloneTemplate( root, 'edit-button' );
	button.dataset.until = String( until );
	actions.prepend( button );
	buttons.add( button );
	tick();

	if ( buttons.size === 1 ) {
		window.setInterval( tick, 1000 );
	}
}

/**
 * Update every countdown and drop expired buttons.
 */
function tick() {
	const now = Date.now() / 1000 + skew;

	buttons.forEach( ( button ) => {
		const left = Math.floor( Number( button.dataset.until ) - now );

		if ( left <= 0 ) {
			buttons.delete( button );
			button.remove();
			return;
		}

		const seconds = String( left % 60 ).padStart( 2, '0' );
		button.textContent = t(
			'editFor',
			`${ Math.floor( left / 60 ) }:${ seconds }`
		);
	} );
}

/**
 * Replace a comment's content with an edit form.
 *
 * @param {Element} root Thread root.
 * @param {Element} item Comment list item.
 */
async function openEditor( root, item ) {
	const content = item.querySelector( '[data-convocept-content]' );

	if ( ! content || item.querySelector( '[data-convocept-edit-form]' ) ) {
		return;
	}

	const id = item.dataset.convoceptComment;
	const edit = readEdits()[ id ];
	const token = edit ? edit.token : '';
	const session = await getSession();
	const form = cloneTemplate( root, 'editor' );
	const input = form.querySelector( '[data-convocept-edit-input]' );
	const status = form.querySelector( '[data-convocept-edit-status]' );

	content.hidden = true;
	content.after( form );

	try {
		const source = await request( `comments/${ id }`, {
			query: { edit_token: token },
			nonce: session.nonce,
		} );
		input.value = source.content;
		input.focus();
	} catch ( error ) {
		announce( status, error.message, true );
	}

	const close = () => {
		form.remove();
		content.hidden = false;
	};

	form.querySelector( '[data-convocept-edit-cancel]' ).addEventListener(
		'click',
		close
	);

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();

		try {
			const result = await request( `comments/${ id }`, {
				method: 'PATCH',
				nonce: session.nonce,
				data: { content: input.value, edit_token: token },
			} );
			const body = parseHtml( result.html ).querySelector(
				'.convocept-comment__body'
			);
			const oldBody = item.querySelector(
				':scope > .convocept-comment__body'
			);
			const button = item.querySelector( '[data-convocept-edit]' );

			oldBody.replaceWith( body );

			if ( button ) {
				body.querySelector( '[data-convocept-actions]' ).prepend(
					button
				);
			}

			announce(
				body.querySelector( '[data-convocept-content]' ) && status,
				t( 'saved' )
			);
			body.querySelector( '[data-convocept-actions] button' )?.focus();
		} catch ( error ) {
			announce( status, error.message, true );
		}
	} );
}
