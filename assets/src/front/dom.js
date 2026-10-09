/**
 * DOM helpers shared by the modules.
 */

/**
 * Clone a server-rendered <template> from the thread.
 *
 * @param {Element} root Thread root.
 * @param {string}  name Template name (data-convocept-template).
 * @return {Element|null} The template's first element, cloned.
 */
export function cloneTemplate( root, name ) {
	const template = root.querySelector(
		`template[data-convocept-template="${ name }"]`
	);

	return template
		? template.content.firstElementChild.cloneNode( true )
		: null;
}

/**
 * Parse an HTML fragment returned by the server into its first element.
 *
 * @param {string} html Server-rendered HTML.
 * @return {Element|null} Parsed element.
 */
export function parseHtml( html ) {
	const template = document.createElement( 'template' );
	template.innerHTML = html.trim();

	return template.content.firstElementChild;
}

/**
 * Announce that comments were added to the thread after the page loaded.
 *
 * Modules that decorate comments (votes, moderator tools) listen for the
 * `convocept:added` event instead of each insertion point knowing them.
 *
 * @param {Element}   root     Thread root.
 * @param {Element[]} elements Added comment list items.
 */
export function added( root, elements ) {
	root.dispatchEvent(
		new CustomEvent( 'convocept:added', { detail: elements } )
	);
}

/**
 * Parse server-rendered HTML holding several elements (e.g. list items).
 *
 * @param {string} html Server-rendered HTML.
 * @return {DocumentFragment} Parsed nodes.
 */
export function parseFragment( html ) {
	const template = document.createElement( 'template' );
	template.innerHTML = html.trim();

	return template.content;
}

/**
 * Move keyboard focus to a newly inserted element, e.g. loaded comments.
 *
 * @param {Element|null} element Element to focus.
 */
export function focusElement( element ) {
	if ( ! element ) {
		return;
	}

	element.setAttribute( 'tabindex', '-1' );
	element.focus( { preventScroll: true } );
}

/**
 * Show a message in a live region so screen readers announce it.
 *
 * @param {Element|null} region  Element with role="status".
 * @param {string}       message Message.
 * @param {boolean}      [error] Whether it is an error.
 */
export function announce( region, message, error = false ) {
	if ( ! region ) {
		return;
	}

	region.textContent = message;
	region.classList.toggle( 'is-error', error );
}

/**
 * Scroll an element into view, respecting reduced-motion preferences.
 *
 * @param {Element} element Element.
 */
export function reveal( element ) {
	const smooth = ! window.matchMedia( '(prefers-reduced-motion: reduce)' )
		.matches;

	element.scrollIntoView( {
		behavior: smooth ? 'smooth' : 'auto',
		block: 'nearest',
	} );
}

/**
 * Put a new top-level comment first in the list, below any pinned ones.
 *
 * @param {Element} list Comment list.
 * @param {Element} item Comment list item.
 */
export function prependComment( list, item ) {
	const pinned = list.querySelectorAll( ':scope > [data-convocept-pinned]' );

	if ( pinned.length ) {
		pinned[ pinned.length - 1 ].after( item );
	} else {
		list.prepend( item );
	}
}
