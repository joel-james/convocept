/**
 * Comment composer / reply form (soft-card style, plugin-owned markup).
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { getConfig } from '../config';

/**
 * Composer form.
 *
 * @param {Object}   props            Component props.
 * @param {number}   props.parent     Parent comment ID (0 = top level).
 * @param {Function} props.onSubmit   Async submit handler.
 * @param {Function} [props.onCancel] Cancel handler for reply forms.
 * @return {JSX.Element} Composer.
 */
export default function Composer( { parent = 0, onSubmit, onCancel } ) {
	const config = getConfig();
	const [ content, setContent ] = useState( '' );
	const [ authorName, setAuthorName ] = useState( '' );
	const [ authorEmail, setAuthorEmail ] = useState( '' );
	const [ authorUrl, setAuthorUrl ] = useState( '' );
	const [ consent, setConsent ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const isGuest = ! config.user.id;

	if ( config.mustLogIn && isGuest ) {
		return (
			<p className="convocept-notice">
				<a href={ config.loginUrl }>
					{ __( 'You must be logged in to post a comment.', 'convocept' ) }
				</a>
			</p>
		);
	}

	const handleSubmit = async ( event ) => {
		event.preventDefault();
		setError( '' );

		if ( ! content.trim() ) {
			setError( __( 'Please enter a comment.', 'convocept' ) );
			return;
		}

		if ( isGuest && config.requireNameEmail && ( ! authorName.trim() || ! authorEmail.trim() ) ) {
			setError( __( 'Name and email are required.', 'convocept' ) );
			return;
		}

		setBusy( true );

		const data = { content, parent };

		if ( isGuest ) {
			data.author_name = authorName;
			data.author_email = authorEmail;

			if ( authorUrl ) {
				data.author_url = authorUrl;
			}
		}

		try {
			await onSubmit( data );
			setContent( '' );
			setAuthorUrl( '' );
		} catch ( submitError ) {
			setError(
				submitError.message ||
					__( 'Your comment could not be posted. Please try again.', 'convocept' )
			);
		} finally {
			setBusy( false );
		}
	};

	return (
		<form className="convocept-composer" onSubmit={ handleSubmit }>
			{ error && (
				<p className="convocept-error" role="alert">
					{ error }
				</p>
			) }

			<textarea
				className="convocept-field convocept-field--area convocept-field--soft"
				value={ content }
				maxLength={ config.maxLength }
				onChange={ ( event ) => setContent( event.target.value ) }
				placeholder={
					parent
						? __( 'Write a reply…', 'convocept' )
						: __( 'Join the discussion…', 'convocept' )
				}
				rows={ parent ? 3 : 4 }
				aria-label={ __( 'Comment', 'convocept' ) }
				required
			/>

			{ isGuest && (
				<div className="convocept-composer__identity">
					<input
						type="text"
						className="convocept-field"
						value={ authorName }
						onChange={ ( event ) => setAuthorName( event.target.value ) }
						placeholder={ __( 'Name', 'convocept' ) }
						aria-label={ __( 'Name', 'convocept' ) }
						required={ config.requireNameEmail }
					/>
					<input
						type="email"
						className="convocept-field"
						value={ authorEmail }
						onChange={ ( event ) => setAuthorEmail( event.target.value ) }
						placeholder={ __( 'Email', 'convocept' ) }
						aria-label={ __( 'Email', 'convocept' ) }
						required={ config.requireNameEmail }
					/>
					<input
						type="url"
						className="convocept-field"
						value={ authorUrl }
						onChange={ ( event ) => setAuthorUrl( event.target.value ) }
						placeholder={ __( 'Website (optional)', 'convocept' ) }
						aria-label={ __( 'Website', 'convocept' ) }
					/>
				</div>
			) }

			{ isGuest && config.cookiesOptIn && (
				<label className="convocept-composer__consent">
					<input
						type="checkbox"
						checked={ consent }
						onChange={ ( event ) => setConsent( event.target.checked ) }
					/>
					<span>
						{ __(
							'Save my name, email, and website in this browser for the next time I comment.',
							'convocept'
						) }
					</span>
				</label>
			) }

			<div className="convocept-actions convocept-actions--end">
				{ onCancel && (
					<button
						type="button"
						className="convocept-button convocept-button--ghost"
						onClick={ onCancel }
					>
						{ __( 'Cancel', 'convocept' ) }
					</button>
				) }

				<button
					type="submit"
					className="convocept-button convocept-button--primary"
					disabled={ busy }
				>
					{ busy
						? __( 'Posting…', 'convocept' )
						: __( 'Post comment', 'convocept' ) }
				</button>
			</div>
		</form>
	);
}
