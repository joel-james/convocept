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
