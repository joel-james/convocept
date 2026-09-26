/**
 * Edit tokens kept in localStorage.
 *
 * Guests prove they wrote a comment with a token issued when they posted.
 * Storage can be unavailable (private mode, blocked storage), so every
 * access is guarded and failure just means no edit button.
 */

const KEY = 'convoceptEdits';

/**
 * Read all unexpired entries.
 *
 * @return {Object<string, {token: string, until: number}>} Entries by comment ID.
 */
export function readEdits() {
	try {
		const all = JSON.parse( window.localStorage.getItem( KEY ) || '{}' );
		const now = Date.now() / 1000;

		Object.keys( all ).forEach( ( id ) => {
			if ( ! all[ id ] || all[ id ].until < now ) {
				delete all[ id ];
			}
		} );

		return all;
	} catch {
		return {};
	}
}

/**
 * Remember the edit token for a comment.
 *
 * @param {number} id    Comment ID.
 * @param {string} token Edit token.
 * @param {number} until Unix time the window closes.
 */
export function saveEdit( id, token, until ) {
	try {
		const all = readEdits();
		all[ id ] = { token, until };
		window.localStorage.setItem( KEY, JSON.stringify( all ) );
	} catch {
		// Storage unavailable: editing after a reload won't be offered.
	}
}
