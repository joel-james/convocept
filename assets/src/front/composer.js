/**
 * The comment composer: formatting, preview, counter and REST submission.
 *
 * Upgrades the server-rendered form in place. Without this script the same
 * form posts to wp-comments-post.php.
 */
import { request } from './api';
import { config, number, t } from './config';
import { added, announce, cloneTemplate, parseHtml, reveal } from './dom';
import { getSession, savedGuest } from './session';
import { addEditButton } from './edit';
import { cancelReply } from './reply';
import { saveEdit } from './store';

/**
 * Markdown each toolbar button inserts around the selection.
 *
 * @type {Object<string, [string, string]>}
 */
const WRAP = {
	bold: [ '**', '**' ],
	italic: [ '*', '*' ],
	code: [ '`', '`' ],
};

/**
 * Upgrade the composer inside a thread.
 *
 * @param {Element} root Thread root (.convocept).
 */
export function initComposer( root ) {
	const form = root.querySelector( '[data-convocept-composer]' );

	if ( ! form ) {
		return;
	}

	const input = form.querySelector( '[data-convocept-input]' );
	const status = form.querySelector( '[data-convocept-status]' );
	const preview = form.querySelector( '[data-convocept-preview]' );
	const counter = form.querySelector( '[data-convocept-counter]' );
	const submit = form.querySelector( '[data-convocept-submit]' );
	// The reader may have started typing before this script loaded.
	let startedAt =
		window.convoceptStartedAt ||
		( input.ownerDocument.activeElement === input || input.value
			? Date.now()
			: 0 );
	let busy = false;

	input.maxLength = config.maxLength;
	form.querySelector( '[data-convocept-toolbar]' ).hidden = false;

	input.addEventListener( 'focus', () => {
		startedAt = startedAt || Date.now();
	} );

	input.addEventListener( 'input', () => {
		autoGrow( input );
		updateCounter( input, counter );
	} );

	form.addEventListener( 'click', ( event ) => {
		const format = event.target.closest( '[data-convocept-format]' );
		const tab = event.target.closest( '[data-convocept-tab]' );

		if ( format ) {
			applyFormat( input, format.dataset.convoceptFormat );
		} else if ( tab ) {
			switchTab( form, input, preview, tab.dataset.convoceptTab );
		}
	} );

	applySession( root, form );

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();

		const content = input.value.trim();

		if ( busy || ! content ) {
			input.focus();
			return;
		}

		busy = true;
		submit.disabled = true;
		announce( status, t( 'posting' ) );

		const session = await getSession();
		const parent = Number( form.elements.comment_parent.value ) || 0;
		const placeholder = insertPlaceholder(
			root,
			parent,
			content,
			session.user ? session.user.name : form.elements.author.value
		);

		try {
			const result = await request(
				`posts/${ form.elements.comment_post_ID.value }/comments`,
				{
					method: 'POST',
					nonce: session.nonce,
					data: {
						content,
						parent,
						author: form.elements.author.value,
						email: form.elements.email.value,
						url: form.elements.url.value,
						honeypot: form.elements.convocept_hp.value,
						token: form.elements.convocept_token?.value || '',
						elapsed: startedAt
							? ( Date.now() - startedAt ) / 1000
							: 0,
						cookies_consent: checked(
							form,
							'wp-comment-cookies-consent'
						),
						subscribe_replies: checked(
							form,
							'convocept_subscribe_replies'
						),
						subscribe_post: checked(
							form,
							'convocept_subscribe_post'
						),
					},
				}
			);

			const comment = parseHtml( result.html );
			placeholder.replaceWith( comment );
			added( root, [ comment ] );

			if ( result.edit_token ) {
				saveEdit( result.id, result.edit_token, result.edit_until );
			}

			addEditButton( root, comment, result.edit_until );
			updateCount( root, result.count_text );
			reveal( comment );

			if ( parent ) {
				cancelReply( root );
			}

			input.value = '';
			autoGrow( input );
			updateCounter( input, counter );
			switchTab( form, input, preview, 'write' );
			startedAt = 0;
			announce(
				status,
				t( result.status === 'approved' ? 'posted' : 'pending' ) +
					( result.subscription === 'pending'
						? ' ' + t( 'confirmEmail' )
						: '' )
			);
		} catch ( error ) {
			placeholder.remove();
			announce( status, error.message, true );
		} finally {
			busy = false;
			submit.disabled = false;
		}
	} );
}

/**
 * Adapt the form to the visitor once the session is known.
 *
 * Logged-in visitors see who they are commenting as instead of the guest
 * fields; returning guests get their saved details filled in.
 *
 * @param {Element}         root Thread root.
 * @param {HTMLFormElement} form Composer form.
 */
async function applySession( root, form ) {
	const session = await getSession();
	const guestFields = form.querySelector( '[data-convocept-guest-fields]' );

	if ( session.logged_in && session.user ) {
		const user = form.querySelector( '[data-convocept-user]' );

		guestFields.hidden = true;
		user.textContent = t( 'commentingAs', session.user.name );
		user.hidden = false;
		form.hidden = false;

		const login = root.querySelector( '[data-convocept-login]' );

		if ( login ) {
			login.hidden = true;
		}

		return;
	}

	const saved = savedGuest();

	[ 'author', 'email', 'url' ].forEach( ( name ) => {
		if ( saved[ name ] && ! form.elements[ name ].value ) {
			form.elements[ name ].value = saved[ name ];
		}
	} );
}

/**
 * Show the comment immediately, before the server answers.
 *
 * @param {Element} root    Thread root.
 * @param {number}  parent  Parent comment ID, or 0.
 * @param {string}  content Comment text (shown as plain text).
 * @param {string}  author  Display name.
 * @return {Element} The placeholder list item.
 */
function insertPlaceholder( root, parent, content, author ) {
	const item = cloneTemplate( root, 'pending' );

	item.querySelector( '[data-convocept-slot="author"]' ).textContent = author;
	item.querySelector( '[data-convocept-slot="content"]' ).textContent =
		content;

	const parentItem = parent
		? root.querySelector( `[data-convocept-comment="${ parent }"]` )
		: null;

	if ( parentItem ) {
		let replies = parentItem.querySelector( ':scope > .convocept-replies' );

		if ( ! replies ) {
			replies = cloneTemplate( root, 'replies' );
			parentItem.append( replies );
		}

		replies.append( item );
		return item;
	}

	let list = root.querySelector( '.convocept-list' );

	if ( ! list ) {
		list = cloneTemplate( root, 'list' );
		const empty = root.querySelector( '.convocept-empty' );

		if ( empty ) {
			empty.replaceWith( list );
		} else {
			root.querySelector( '#respond' ).after( list );
		}
	}

	const newestFirst = /convocept_sort=newest/.test( window.location.search );
	list[ newestFirst ? 'prepend' : 'append' ]( item );

	return item;
}

/**
 * Update the comment count in the thread header.
 *
 * @param {Element} root Thread root.
 * @param {string}  text New heading, from the server (it knows the plural forms).
 */
function updateCount( root, text ) {
	const title = root.querySelector( '.convocept-title' );

	if ( title && text ) {
		title.textContent = text;
	}
}

/**
 * Wrap the selection in Markdown, or prefix lines for quotes.
 *
 * @param {HTMLTextAreaElement} input  Textarea.
 * @param {string}              format Format name.
 */
function applyFormat( input, format ) {
	const { selectionStart: start, selectionEnd: end, value } = input;
	const selected = value.slice( start, end );
	let text;

	if ( format === 'quote' ) {
		text = ( selected || '' )
			.split( '\n' )
			.map( ( line ) => '> ' + line )
			.join( '\n' );
	} else if ( format === 'link' ) {
		// eslint-disable-next-line no-alert
		const url = window.prompt( t( 'linkPrompt' ), 'https://' );

		if ( ! url ) {
			return;
		}

		text = `[${ selected || url }](${ url })`;
	} else if ( format === 'code' && selected.includes( '\n' ) ) {
		text = '```\n' + selected + '\n```';
	} else {
		const [ before, after ] = WRAP[ format ];
		text = before + selected + after;
	}

	input.focus();
	input.setRangeText( text, start, end, selected ? 'select' : 'end' );
	input.dispatchEvent( new Event( 'input' ) );
}

/**
 * Switch between writing and a server-rendered preview.
 *
 * @param {Element}             form    Composer form.
 * @param {HTMLTextAreaElement} input   Textarea.
 * @param {Element}             preview Preview container.
 * @param {string}              tab     "write" or "preview".
 */
async function switchTab( form, input, preview, tab ) {
	const previewing = tab === 'preview';

	form.querySelectorAll( '[data-convocept-tab]' ).forEach( ( button ) => {
		button.setAttribute(
			'aria-selected',
			String( button.dataset.convoceptTab === tab )
		);
	} );

	input.parentElement.hidden = previewing;
	preview.hidden = ! previewing;

	if ( ! previewing ) {
		return;
	}

	if ( ! input.value.trim() ) {
		preview.textContent = t( 'nothingPreview' );
		return;
	}

	try {
		const result = await request( 'preview', {
			method: 'POST',
			data: { content: input.value },
		} );
		// Safe: produced by the server-side parser and kses-filtered.
		preview.innerHTML = result.html;
	} catch ( error ) {
		preview.textContent = error.message;
	}
}

/**
 * Whether a checkbox in the form is ticked.
 *
 * @param {HTMLFormElement} form Form.
 * @param {string}          name Field name.
 * @return {boolean} Whether it exists and is checked.
 */
function checked( form, name ) {
	return !! ( form.elements[ name ] && form.elements[ name ].checked );
}

/**
 * Grow the textarea with its content.
 *
 * @param {HTMLTextAreaElement} input Textarea.
 */
function autoGrow( input ) {
	input.style.height = 'auto';
	input.style.height = input.scrollHeight + 2 + 'px';
}

/**
 * Show the remaining characters when the limit is near.
 *
 * @param {HTMLTextAreaElement} input   Textarea.
 * @param {Element}             counter Counter element.
 */
function updateCounter( input, counter ) {
	const left = config.maxLength - input.value.length;

	counter.hidden = left > 1000;
	counter.textContent = t( 'charactersLeft', number( left ) );
}
