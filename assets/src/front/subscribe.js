/**
 * "Subscribe without commenting" over REST.
 *
 * The form works without JavaScript (admin-post.php and a reload); this
 * submits it in place and shows the answer next to it.
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
