/**
 * Live updates via polling (paused when the tab is hidden) or Heartbeat.
 */

import { useEffect, useRef } from '@wordpress/element';

import { fetchUpdates } from '../api';
import { getConfig } from '../config';

/**
 * Subscribe to new comments and hand them to a merge callback.
 *
 * @param {Function} onNew    Receives an array of new comments.
 * @param {boolean}  isActive Whether updates should run.
 */
export function useLiveUpdates( onNew, isActive = true ) {
	const { settings } = getConfig();
	const since = useRef( new Date().toISOString().replace( /\.\d+Z$/, 'Z' ) );
	const onNewRef = useRef( onNew );

	useEffect( () => {
		onNewRef.current = onNew;
	}, [ onNew ] );

	useEffect( () => {
		const mode = settings.liveUpdates;

		if ( ! isActive || mode === 'off' ) {
			return undefined;
		}

		let cancelled = false;

		const check = async () => {
			if ( cancelled || document.hidden ) {
				return;
			}

			try {
				const result = await fetchUpdates( since.current );

				if ( cancelled ) {
					return;
				}

				if ( result.now ) {
					since.current = result.now;
				}

				if ( result.comments && result.comments.length ) {
					onNewRef.current( result.comments );
				}
			} catch ( error ) {
				// Transient failures are ignored; the next tick retries.
			}
		};

		if ( mode === 'heartbeat' ) {
			// Heartbeat only signals cadence; the data still comes from REST,
			// which keeps permission handling in one place.
			const onTick = () => check();
			document.addEventListener( 'heartbeat-tick', onTick );

			return () => {
				cancelled = true;
				document.removeEventListener( 'heartbeat-tick', onTick );
			};
		}

		const interval = Math.max( 10, settings.pollInterval ) * 1000;
		const timer = setInterval( check, interval );
		const onVisible = () => {
			if ( ! document.hidden ) {
				check();
			}
		};

		document.addEventListener( 'visibilitychange', onVisible );

		return () => {
			cancelled = true;
			clearInterval( timer );
			document.removeEventListener( 'visibilitychange', onVisible );
		};
	}, [ isActive, settings.liveUpdates, settings.pollInterval ] );
}
