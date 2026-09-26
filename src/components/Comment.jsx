/**
 * A single comment, its edit form, and its replies.
 *
 * Markup is fully plugin-owned (`convocept-` classes only) so no theme CSS can
 * reach in and alter the layout. Visual identity comes from the token
 * system in style.scss.
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import Avatar from './Avatar';
import Composer from './Composer';
import { getConfig } from '../config';

/**
 * Render one comment and its children.
 *
 * @param {Object}   props         Component props.
 * @param {Object}   props.comment Comment object.
 * @param {Array}    props.replies Child comments (already nested).
 * @param {number}   props.depth   Current nesting depth.
 * @param {Function} props.onReply Reply submit handler.
 * @param {Function} props.onEdit  Edit submit handler.
 * @return {JSX.Element} Comment element.
 */
export default function Comment( {
	comment,
	replies = [],
	depth = 1,
	onReply,
	onEdit,
} ) {
	const config = getConfig();
	const [ replying, setReplying ] = useState( false );
	const [ editing, setEditing ] = useState( false );
	const [ draft, setDraft ] = useState( comment.raw || '' );
	const [ editError, setEditError ] = useState( '' );

	const canNest = depth < config.threadDepth;

	const handleEdit = async ( event ) => {
		event.preventDefault();
		setEditError( '' );

		try {
			await onEdit( comment.id, draft );
			setEditing( false );
		} catch ( error ) {
			setEditError(
				error.message ||
					__( 'Your edit could not be saved.', 'convocept' )
			);
		}
	};

	const classes = [
		'convocept-comment',
		comment.pending ? 'convocept-comment--pending' : '',
		! comment.approved ? 'convocept-comment--unapproved' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<li className={ classes } id={ `convocept-comment-${ comment.id }` }>
			<div className="convocept-comment__row">
				{ config.showAvatars && (
					<Avatar src={ comment.avatar } name={ comment.authorName } />
				) }

				<article className="convocept-comment__bubble">
					<header className="convocept-comment__header">
						<span className="convocept-comment__author">
							{ comment.authorUrl ? (
								<a
									href={ comment.authorUrl }
									rel="nofollow ugc external"
								>
									{ comment.authorName }
								</a>
							) : (
								comment.authorName
							) }
						</span>

						<time
							className="convocept-comment__date"
							dateTime={ comment.date }
						>
							{ formatDate( comment.date ) }
						</time>

						{ !! comment.edited && (
							<span className="convocept-comment__edited">
								{ __( '(edited)', 'convocept' ) }
							</span>
						) }
					</header>

					{ editing ? (
						<form
							className="convocept-comment__edit"
							onSubmit={ handleEdit }
						>
							{ editError && (
								<p className="convocept-error" role="alert">
									{ editError }
								</p>
							) }
							<textarea
								className="convocept-field convocept-field--area"
								value={ draft }
								onChange={ ( event ) =>
									setDraft( event.target.value )
								}
								rows={ 4 }
								aria-label={ __( 'Edit comment', 'convocept' ) }
							/>
							<div className="convocept-actions">
								<button
									type="submit"
									className="convocept-button convocept-button--primary"
								>
									{ __( 'Save', 'convocept' ) }
								</button>
								<button
									type="button"
									className="convocept-button convocept-button--ghost"
									onClick={ () => setEditing( false ) }
								>
									{ __( 'Cancel', 'convocept' ) }
								</button>
							</div>
						</form>
					) : (
						<div
							className="convocept-comment__content"
							// Server-sanitized through core's comment_text filter.
							dangerouslySetInnerHTML={ {
								__html: comment.content,
							} }
						/>
					) }

					{ ! comment.approved && (
						<p className="convocept-comment__status">
							{ __( 'Your comment is awaiting moderation.', 'convocept' ) }
						</p>
					) }

					{ ! editing && ! comment.pending && (
						<footer className="convocept-comment__links">
							{ config.commentsOpen && (
								<button
									type="button"
									className="convocept-link"
									onClick={ () => setReplying( ! replying ) }
								>
									{ replying
										? __( 'Cancel reply', 'convocept' )
										: __( 'Reply', 'convocept' ) }
								</button>
							) }

							{ comment.canEdit && (
								<button
									type="button"
									className="convocept-link"
									onClick={ () => {
										setDraft( comment.raw || '' );
										setEditing( true );
									} }
								>
									{ __( 'Edit', 'convocept' ) }
								</button>
							) }
						</footer>
					) }
				</article>
			</div>

			{ replying && (
				<div className="convocept-comment__reply-form">
					<Composer
						parent={ comment.id }
						onSubmit={ async ( data ) => {
							await onReply( data );
							setReplying( false );
						} }
						onCancel={ () => setReplying( false ) }
					/>
				</div>
			) }

			{ replies.length > 0 && (
				<ol className="convocept-comment__children">
					{ replies.map( ( child ) => (
						<Comment
							key={ child.id }
							comment={ child }
							replies={ child.children || [] }
							depth={ canNest ? depth + 1 : depth }
							onReply={ onReply }
							onEdit={ onEdit }
						/>
					) ) }
				</ol>
			) }
		</li>
	);
}

/**
 * Format a date for display in the site locale.
 *
 * @param {string} value ISO date string.
 * @return {string} Localized date.
 */
function formatDate( value ) {
	const date = new Date( value );

	if ( isNaN( date.getTime() ) ) {
		return '';
	}

	return date.toLocaleString();
}
