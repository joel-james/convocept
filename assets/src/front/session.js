/**
 * Per-visitor state, fetched once after the script loads.
 */
import { request } from './api';

/**
 * @typedef {Object} Session
 * @property {boolean}     logged_in    Whether the visitor is logged in.
 * @property {Object|null} user         Logged-in user: id, name, avatar.
 * @property {string}      nonce        REST nonce, empty for guests.
 * @property {boolean}     can_moderate Whether the visitor moderates comments.
 * @property {number}      edit_window  Edit window in minutes.
 * @property {number}      skew         Server clock minus browser clock, in seconds.
 */

/** @type {Promise<Session>|null} */
let pending = null;

/**
 * Get the session, fetching it on first call.
 *
 * Failure is not fatal: the visitor is treated as a guest.
 *
 * @return {Promise<Session>} Session.
 */
export function getSession() {
	if ( ! pending ) {
		pending = request( 'session' )
			.then( ( data ) =>
				Object.assign( data, {
					skew: ( data.now || 0 ) - Date.now() / 1000,
				} )
			)
			.catch( () => ( {
				logged_in: false,
				user: null,
				nonce: '',
				can_moderate: false,
				edit_window: 0,
				skew: 0,
			} ) );
	}

	return pending;
}

/**
 * Read the name, email and website core remembers for returning guests.
 *
 * Core stores them in cookies named comment_author_*_{hash} when the guest
 * ticked "save my details". Reading them here keeps them out of the
 * cached HTML.
 *
 * @return {{author: string, email: string, url: string}} Saved details.
 */
export function savedGuest() {
	const saved = { author: '', email: '', url: '' };

	document.cookie.split( '; ' ).forEach( ( pair ) => {
		const index = pair.indexOf( '=' );
		let value;

		try {
			value = decodeURIComponent(
				pair.slice( index + 1 ).replace( /\+/g, ' ' )
			);
		} catch {
			return;
		}

		const name = pair.slice( 0, index );

		if ( name.startsWith( 'comment_author_email_' ) ) {
			saved.email = value;
		} else if ( name.startsWith( 'comment_author_url_' ) ) {
			saved.url = value;
		} else if ( name.startsWith( 'comment_author_' ) ) {
			saved.author = value;
		}
	} );

	return saved;
}
