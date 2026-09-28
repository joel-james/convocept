/**
 * Client-side checks of setting values.
 *
 * Mirrors Settings::validate() on the server, with the same messages, so a
 * mistake shows under its field as soon as it is made. The server checks
 * again on save.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Check one value against its schema.
 *
 * @param {string}                         key       Setting key.
 * @param {boolean|number|string|string[]} value     Value.
 * @param {Object}                         schema    The key's JSON schema.
 * @param {Array}                          postTypes Known post types ([{ value }]).
 * @return {string} Error message, or '' when valid.
 */
export function validateValue( key, value, schema = {}, postTypes = [] ) {
	switch ( schema.type ) {
		case 'boolean':
			return 'boolean' === typeof value
				? ''
				: __( 'Choose on or off.', 'convocept' );

		case 'integer': {
			const min = schema.minimum ?? Number.MIN_SAFE_INTEGER;
			const max = schema.maximum ?? Number.MAX_SAFE_INTEGER;

			return Number.isInteger( value ) && value >= min && value <= max
				? ''
				: sprintf(
						/* translators: 1: Smallest allowed number, 2: Largest allowed number. */
						__( 'Enter a number from %1$d to %2$d.', 'convocept' ),
						min,
						max
					);
		}

		case 'string':
			if ( 'string' !== typeof value ) {
				return __( 'Enter text.', 'convocept' );
			}

			if ( schema.enum && ! schema.enum.includes( value ) ) {
				return __( 'Choose one of the listed options.', 'convocept' );
			}

			if (
				schema.pattern &&
				! new RegExp( schema.pattern ).test( value )
			) {
				return 'accent_color' === key
					? __(
							'Enter a colour as # and six hex digits, like #2563eb.',
							'convocept'
						)
					: __(
							'This value is not in the expected format.',
							'convocept'
						);
			}

			return '';

		case 'array':
			if ( ! Array.isArray( value ) ) {
				return __( 'Enter a list of values.', 'convocept' );
			}

			if ( 'post_types' === key ) {
				const known = postTypes.map( ( type ) => type.value );
				const unknown = value.find(
					( type ) => ! known.includes( type )
				);

				return unknown
					? sprintf(
							/* translators: %s: Post type name. */
							__(
								'There is no post type called "%s".',
								'convocept'
							),
							unknown
						)
					: '';
			}

			if ( 'trusted_proxies' === key ) {
				const bad = value.find( ( range ) => ! isIpRange( range ) );

				return bad
					? sprintf(
							/* translators: %s: The invalid line. */
							__(
								'"%s" is not an IP address or CIDR range.',
								'convocept'
							),
							bad
						)
					: '';
			}

			return '';

		default:
			return '';
	}
}

/**
 * Whether a string is an IPv4/IPv6 address, optionally with a CIDR prefix.
 *
 * Loose on IPv6 (the server has the final say); strict on IPv4.
 *
 * @param {string} range Candidate.
 * @return {boolean} Whether it looks valid.
 */
function isIpRange( range ) {
	const parts = range.split( '/' );

	if ( parts.length > 2 ) {
		return false;
	}

	const [ ip, bits ] = parts;
	const v4 =
		/^(25[0-5]|2[0-4]\d|1?\d?\d)(\.(25[0-5]|2[0-4]\d|1?\d?\d)){3}$/.test(
			ip
		);

	if ( ! v4 && ! ( /^[0-9a-f:.]+$/i.test( ip ) && ip.includes( ':' ) ) ) {
		return false;
	}

	return (
		undefined === bits ||
		( /^\d+$/.test( bits ) && Number( bits ) <= ( v4 ? 32 : 128 ) )
	);
}

/**
 * Get the contrast ratio of a hex colour against white.
 *
 * @param {string} hex Colour as #rrggbb.
 * @return {number} WCAG contrast ratio (1–21).
 */
export function contrastWithWhite( hex ) {
	const channels = [ 1, 3, 5 ].map( ( start ) => {
		const c = parseInt( hex.slice( start, start + 2 ), 16 ) / 255;

		return c <= 0.03928
			? c / 12.92
			: Math.pow( ( c + 0.055 ) / 1.055, 2.4 );
	} );
	const luminance =
		0.2126 * channels[ 0 ] +
		0.7152 * channels[ 1 ] +
		0.0722 * channels[ 2 ];

	return 1.05 / ( luminance + 0.05 );
}
