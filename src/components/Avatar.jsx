/**
 * Avatar: Gravatar image, or the author's initials when unavailable.
 */

import { useState } from '@wordpress/element';

/**
 * Render an avatar.
 *
 * @param {Object} props        Component props.
 * @param {string} props.src    Avatar image URL.
 * @param {string} props.name   Author display name (for initials).
 * @return {JSX.Element} Avatar element.
 */
export default function Avatar( { src, name } ) {
	const [ failed, setFailed ] = useState( false );

	if ( src && ! failed ) {
		return (
			<img
				className="convocept-avatar"
				src={ src }
				alt=""
				width="36"
				height="36"
				loading="lazy"
				onError={ () => setFailed( true ) }
			/>
		);
	}

	return (
		<span className="convocept-avatar convocept-avatar--initials" aria-hidden="true">
			{ initials( name ) }
		</span>
	);
}

/**
 * Derive up to two initials from a display name.
 *
 * @param {string} name Display name.
 * @return {string} Initials.
 */
function initials( name ) {
	const parts = ( name || '' ).trim().split( /\s+/ ).filter( Boolean );

	if ( ! parts.length ) {
		return '•';
	}

	const first = parts[ 0 ].charAt( 0 );
	const last = parts.length > 1 ? parts[ parts.length - 1 ].charAt( 0 ) : '';

	return ( first + last ).toUpperCase();
}
