/**
 * Import progress for Tools → Import → Convocept importers.
 *
 * The importer page works without JavaScript: each "Start" / "Continue"
 * click posts the form and runs one batch. This script takes over those
 * clicks, runs the batches one after another through admin-ajax, updates
 * the progress text (and bar, when shown), and reloads the page when the
 * import is done so the server-rendered report shows.
 */

/**
 * Run batches until the import finishes or fails.
 *
 * @param {HTMLFormElement} form The Start / Continue form.
 * @return {Promise<void>}
 */
async function run( form ) {
	const button = form.querySelector( '[type="submit"]' );
	const body = new URLSearchParams( {
		action: 'convocept_import_batch',
		_ajax_nonce: form.dataset.nonce,
	} );

	if ( button ) {
		button.disabled = true;
	}

	for (;;) {
		let result;

		try {
			const response = await fetch( form.dataset.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body,
			} );

			result = await response.json();

			// Another tab is running a batch: wait and try again.
			if ( response.status === 409 ) {
				await new Promise( ( resolve ) => setTimeout( resolve, 3000 ) );
				continue;
			}
		} catch {
			result = null;
		}

		const status = document.querySelector(
			'[data-convocept-import-status]'
		);

		if ( ! result || ! result.success ) {
			if ( status ) {
				status.textContent =
					result && result.data && result.data.message
						? result.data.message
						: form.dataset.error;
			}

			if ( button ) {
				button.disabled = false;
			}

			return;
		}

		if ( result.data.done ) {
			window.location.reload();
			return;
		}

		const bar = document.querySelector( '[data-convocept-import-bar]' );

		if ( bar ) {
			bar.value = result.data.percent;
			bar.textContent = `${ result.data.percent }%`;
		}

		if ( status ) {
			status.textContent = result.data.text;
		}
	}
}

const form = document.querySelector( 'form[data-convocept-import]' );

if ( form ) {
	// "Start" / "Continue" run the batches here instead of one per page load.
	form.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		run( form );
	} );

	// A running import (page reopened, or after a no-JS batch) continues on its own.
	if ( form.dataset.running === '1' ) {
		run( form );
	}
}
