/**
 * Comment thread list.
 */

import { __ } from '@wordpress/i18n';

import Comment from './Comment';

/**
 * Render the nested thread.
 *
 * @param {Object}   props          Component props.
 * @param {Array}    props.comments Flat comment list.
 * @param {Function} props.onReply  Reply handler.
 * @param {Function} props.onEdit   Edit handler.
 * @return {JSX.Element} Thread element.
 */
export default function Thread( { comments, onReply, onEdit } ) {
	if ( ! comments.length ) {
		return (
			<p className="convocept-thread__empty">
				{ __( 'No comments yet. Start the discussion!', 'convocept' ) }
			</p>
		);
	}

	const tree = buildTree( comments );

	return (
		<ol className="convocept-thread">
			{ tree.map( ( comment ) => (
				<Comment
					key={ comment.id }
					comment={ comment }
					replies={ comment.children }
					depth={ 1 }
					onReply={ onReply }
					onEdit={ onEdit }
				/>
			) ) }
		</ol>
	);
}

/**
 * Nest a flat comment list by parent ID.
 *
 * Comments whose parent is not on this page are promoted to the top level
 * so nothing is silently dropped.
 *
 * @param {Array} comments Flat list.
 * @return {Array} Nested list.
 */
function buildTree( comments ) {
	const byId = new Map();
	const roots = [];

	comments.forEach( ( comment ) => {
		byId.set( comment.id, { ...comment, children: [] } );
	} );

	byId.forEach( ( comment ) => {
		const parent = comment.parent ? byId.get( comment.parent ) : null;

		if ( parent ) {
			parent.children.push( comment );
		} else {
			roots.push( comment );
		}
	} );

	return roots;
}
