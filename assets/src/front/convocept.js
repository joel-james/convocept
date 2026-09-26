/**
 * Convocept front-end entry.
 *
 * Progressive enhancement for the server-rendered thread: every feature
 * here upgrades markup that already works without JavaScript.
 */
import { initComposer } from './composer';
import { initEdit } from './edit';

/**
 * Initialise every thread on the page.
 */
function init() {
	document.querySelectorAll( '.convocept' ).forEach( ( root ) => {
		if ( root.dataset.convoceptReady ) {
			return;
		}

		root.dataset.convoceptReady = '1';
		initComposer( root );
		initEdit( root );
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
