/**
 * Inline loader, printed after the thread.
 *
 * Keeps the page free of Convocept requests until the reader nears the
 * comments: the interaction script is injected when the thread comes
 * within 600px of the viewport, or as soon as the reader focuses, taps or
 * types inside it. Links straight to a comment load it immediately.
 *
 * Everything the thread needs already works without this script.
 */
( () => {
	const config = document.getElementById( 'convocept-config' );

	if ( ! config || window.convoceptLoader ) {
		return;
	}

	window.convoceptLoader = true;

	const observer =
		'IntersectionObserver' in window
			? new IntersectionObserver(
					( entries ) => {
						if (
							entries.some( ( entry ) => entry.isIntersecting )
						) {
							load();
						}
					},
					{ rootMargin: '600px 0px' }
				)
			: null;
	let loaded = false;

	/**
	 * Inject the interaction script and any add-on modules, in order.
	 */
	function load() {
		if ( loaded ) {
			return;
		}

		loaded = true;

		if ( observer ) {
			observer.disconnect();
		}

		JSON.parse( config.textContent ).scripts.forEach( ( src ) => {
			const script = document.createElement( 'script' );
			script.src = src;
			script.async = false;
			document.head.appendChild( script );
		} );
	}

	if (
		! observer ||
		/^#(comments|respond|comment-\d+)$/.test( window.location.hash )
	) {
		load();
		return;
	}

	document.querySelectorAll( '.convocept' ).forEach( ( root ) => {
		observer.observe( root );
		[ 'focusin', 'pointerdown', 'keydown' ].forEach( ( type ) =>
			root.addEventListener( type, load, { once: true, passive: true } )
		);
	} );
} )();
