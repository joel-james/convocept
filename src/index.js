/**
 * Entry point: mount the app over the native comment markup.
 *
 * The native markup is hidden only after a successful mount, and revealed
 * again if the app fails, so comments always work without JavaScript.
 */

import { createRoot } from '@wordpress/element';

import App from './App';
import './style.scss';

/**
 * Simple registry so frontend addons can hook in later.
 */
window.convocept = window.convocept || {
	plugins: {},
	registerPlugin( name, plugin ) {
		this.plugins[ name ] = plugin;
	},
};

/**
 * Mount the app into every comments container on the page.
 *
 * @return {void}
 */
function mount() {
	const containers = document.querySelectorAll( '[data-convocept-app]' );

	containers.forEach( ( container ) => {
		const target = container.querySelector( '[data-convocept-mount]' );
		const native = container.querySelector( '[data-convocept-native]' );

		if ( ! target || target.dataset.convoceptMounted ) {
			return;
		}

		const reveal = () => {
			if ( native ) {
				native.hidden = false;
			}
		};

		try {
			// The theme's own comments container knows where comments belong
			// on this theme — mirror its outer margins onto the app so it
			// occupies the same slot (e.g. Twenty Fifteen's inset box).
			// Read while the native markup is still visible so percentage
			// margins resolve to real values.
			const nativeRoot = native && native.firstElementChild;

			if ( nativeRoot ) {
				const margin = window.getComputedStyle( nativeRoot ).margin;

				if ( margin && '0px' !== margin ) {
					target.style.margin = margin;
				}
			}

			createRoot( target ).render( <App onFailure={ reveal } /> );
			target.dataset.convoceptMounted = '1';

			if ( native ) {
				native.hidden = true;
			}
		} catch ( error ) {
			reveal();
		}
	} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
