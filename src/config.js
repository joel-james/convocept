/**
 * Access to the server-inlined configuration.
 */

const defaults = {
	postId: 0,
	perPage: 50,
	commentOrder: 'asc',
	threadDepth: 1,
	pageComments: false,
	requireNameEmail: true,
	showAvatars: true,
	cookiesOptIn: false,
	commentsOpen: false,
	mustLogIn: false,
	loginUrl: '',
	maxLength: 65525,
	settings: { editWindow: 5, liveUpdates: 'polling', pollInterval: 30 },
	user: { id: 0, name: '', avatar: '', canModerate: false },
};

/**
 * Get the merged runtime config.
 *
 * @return {Object} Config object.
 */
export function getConfig() {
	return { ...defaults, ...( window.convoceptConfig || {} ) };
}
