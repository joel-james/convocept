/**
 * Access to the static configuration printed by PHP.
 *
 * The configuration is the same for every visitor (the page may be
 * cached); per-visitor state comes from the session endpoint.
 */

/**
 * @typedef {Object} ConvoceptConfig
 * @property {string}                 rest       REST base URL ending in "/".
 * @property {number}                 editWindow Edit window in minutes.
 * @property {number}                 maxLength  Maximum comment length.
 * @property {Object<string, string>} i18n       Translated strings.
 */

/** @type {ConvoceptConfig} */
export const config = Object.assign(
	{
		rest: '/wp-json/convocept/v1/',
		editWindow: 0,
		maxLength: 20000,
		i18n: {},
	},
	window.convoceptConfig || {}
);

/**
 * Get a translated string, replacing "%s" with a value.
 *
 * @param {string}        key   String key from the PHP configuration.
 * @param {string|number} [arg] Value for "%s".
 * @return {string} Translated string.
 */
export function t( key, arg ) {
	const text = config.i18n[ key ] || key;

	return arg === undefined ? text : text.replace( '%s', String( arg ) );
}
