/**
 * Convocept front-end entry.
 *
 * Progressive enhancement for the server-rendered thread: every feature
 * here upgrades markup that already works without JavaScript.
 */
import { initComposer } from './composer';
import { initDeepLink } from './deeplink';
import { initEdit } from './edit';
import { initMore } from './more';
import { initReply } from './reply';

/**
 * Initialise every thread on the page.
 */
function init() {
	initDeepLink();

	document.querySelectorAll( '.convocept' ).forEach( ( root ) => {
		if ( root.dataset.convoceptReady ) {
			return;
		}

		root.dataset.convoceptReady = '1';
		initComposer( root );
		initReply( root );
		initMore( root );
		initEdit( root );
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
