/**
 * "Subscribe without commenting" over REST.
 *
 * The form works without JavaScript (admin-post.php and a reload); this
 * submits it in place and shows the answer next to it. The header's Follow
 * link opens the in-thread form through :target without JavaScript; this
 * turns it into a toggle button.
 */
import { request } from './api';
import { announce } from './dom';
import { getSession } from './session';

/**
 * Enhance subscribe forms in a thread.
 *
 * @param {Element} root Thread root.
 */
export function initSubscribe( root ) {
	initFollow( root );

	root.querySelectorAll( '[data-convocept-subscribe-form]' ).forEach(
		( form ) => {
			const message = form.parentElement.querySelector(
				'[data-convocept-subscribe-message]'
			);

			form.addEventListener( 'submit', async ( event ) => {
				event.preventDefault();

				const button = form.querySelector( 'button' );
				button.disabled = true;

				try {
					const session = await getSession();
					const result = await request( 'subscriptions', {
						method: 'POST',
						nonce: session.nonce,
						data: {
							email: form.elements.email.value,
							post_id: Number( form.elements.post_id.value ),
							honeypot: form.elements.convocept_hp.value,
						},
					} );
					announce( message, result.message );
					form.reset();
				} catch ( error ) {
					announce( message, error.message, true );
				} finally {
					button.disabled = false;
				}
			} );
		}
	);
}

/**
 * Make the header's Follow link open and close the in-thread form.
 *
 * @param {Element} root Thread root.
 */
function initFollow( root ) {
	const follow = root.querySelector( '[data-convocept-follow]' );
	const panel = root.querySelector( '[data-convocept-subscribe]' );

	if ( ! follow || ! panel ) {
		return;
	}

	const hash = `#${ panel.id }`;

	follow.setAttribute( 'role', 'button' );
	follow.setAttribute( 'aria-controls', panel.id );
	follow.setAttribute(
		'aria-expanded',
		String(
			panel.classList.contains( 'is-open' ) ||
				window.location.hash === hash
		)
	);

	follow.addEventListener( 'click', ( event ) => {
		event.preventDefault();

		const open = follow.getAttribute( 'aria-expanded' ) !== 'true';

		follow.setAttribute( 'aria-expanded', String( open ) );
		panel.classList.toggle( 'is-open', open );
		// Also closes a form that :target (a #convocept-subscribe URL) keeps open.
		panel.classList.toggle( 'is-closed', ! open );

		if ( open ) {
			panel.querySelector( 'input[type="email"]' ).focus();
		}
	} );

	// Space activates buttons; links only answer Enter.
	follow.addEventListener( 'keydown', ( event ) => {
		if ( event.key === ' ' ) {
			event.preventDefault();
			follow.click();
		}
	} );
}
