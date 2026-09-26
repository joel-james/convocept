/**
 * Convocept front-end entry.
 *
 * Progressive enhancement for the server-rendered thread: every feature
 * here upgrades markup that already works without JavaScript.
 */
import { initComposer } from './composer';
import { initDeepLink } from './deeplink';
import { initEdit } from './edit';
import { initLive } from './live';
import { initModerate } from './moderate';
import { initMore } from './more';
import { initReply } from './reply';
import { initSubscribe } from './subscribe';
import { initVotes } from './votes';

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
		initLive( root );
		initVotes( root );
		initModerate( root );
		initSubscribe( root );
	} );

	// Replay a click the inline loader caught before this script was ready.
	const queued = window.convoceptQueued;

	if ( queued ) {
		window.convoceptQueued = null;
		queued.click();
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
