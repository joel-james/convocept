/**
 * Access to the static configuration printed by PHP.
 *
 * PHP prints it as a JSON data block (<script type="application/json"
 * id="convocept-config">), which the inline loader also reads. It is the
 * same for every visitor (the page may be cached); per-visitor state comes
 * from the session endpoint.
 */

/**
 * Read the JSON configuration block.
 *
 * @return {Object} Configuration, or an empty object.
 */
function readConfig() {
	const element = document.getElementById( 'convocept-config' );

	try {
		return element ? JSON.parse( element.textContent ) : {};
	} catch {
		return {};
	}
}

/**
 * @typedef {Object} ConvoceptConfig
 * @property {string}                 rest       REST base URL ending in "/".
 * @property {number}                 editWindow Edit window in minutes.
 * @property {number}                 maxLength  Maximum comment length.
 * @property {number}                 poll       Seconds between checks for new comments; 0 = off.
 * @property {string}                 locale     Site language as a BCP 47 tag, e.g. "de-DE".
 * @property {Object<string, string>} i18n       Translated strings.
 */

/** @type {ConvoceptConfig} */
export const config = Object.assign(
	{
		rest: '/wp-json/convocept/v1/',
		editWindow: 0,
		maxLength: 20000,
		poll: 0,
		locale: '',
		i18n: {},
	},
	readConfig()
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

/**
 * Format a number in the site's language.
 *
 * @param {number} value Number.
 * @return {string} Formatted number.
 */
export function number( value ) {
	try {
		return value.toLocaleString( config.locale || undefined );
	} catch {
		// An unknown language tag throws a RangeError.
		return value.toLocaleString();
	}
}
