/**
 * Thread state: loading, pagination, optimistic insert/rollback, edits.
 */

import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

import { createComment, fetchComments, updateComment } from '../api';

/**
 * Manage the comment thread for the current post.
 *
 * @return {Object} Thread state and actions.
 */
export function useComments() {
	const [ comments, setComments ] = useState( [] );
	const [ page, setPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const [ status, setStatus ] = useState( 'loading' );
	const tempId = useRef( -1 );

	const load = useCallback( async ( nextPage ) => {
		setStatus( 'loading' );

		try {
			const result = await fetchComments( nextPage );
			setComments( result.comments );
			setTotalPages( result.totalPages );
			setStatus( 'ready' );
		} catch ( error ) {
			setStatus( 'error' );
		}
	}, [] );

	useEffect( () => {
		load( page );
	}, [ load, page ] );

	/**
	 * Merge server-sent comments, ignoring ones already present.
	 *
	 * @param {Array} incoming New comments.
	 */
	const merge = useCallback( ( incoming ) => {
		if ( ! incoming.length ) {
			return;
		}

		setComments( ( current ) => {
			const known = new Set( current.map( ( item ) => item.id ) );
			const added = incoming.filter( ( item ) => ! known.has( item.id ) );

			return added.length ? [ ...current, ...added ] : current;
		} );
	}, [] );

	/**
	 * Post a comment optimistically, reconciling or rolling back on response.
	 *
	 * @param {Object} data Comment fields.
	 * @return {Promise<Object>} The created comment.
	 */
	const post = useCallback( async ( data ) => {
		const placeholderId = tempId.current;
		tempId.current -= 1;

		const placeholder = {
			id: placeholderId,
			parent: data.parent || 0,
			author: 0,
			authorName: data.author_name || '',
			authorUrl: '',
			avatar: '',
			date: new Date().toISOString(),
			content: `<p>${ escapeHtml( data.content ) }</p>`,
			raw: data.content,
			approved: true,
			pending: true,
			canEdit: false,
			editUntil: 0,
			edited: 0,
		};

		setComments( ( current ) => [ ...current, placeholder ] );

		try {
			const created = await createComment( data );

			setComments( ( current ) =>
				current.map( ( item ) =>
					item.id === placeholderId ? created : item
				)
			);

			return created;
		} catch ( error ) {
			setComments( ( current ) =>
				current.filter( ( item ) => item.id !== placeholderId )
			);

			throw error;
		}
	}, [] );

	/**
	 * Edit a comment, restoring the previous content on failure.
	 *
	 * @param {number} id      Comment ID.
	 * @param {string} content New content.
	 * @return {Promise<Object>} The updated comment.
	 */
	const edit = useCallback( async ( id, content ) => {
		let previous = null;

		setComments( ( current ) =>
			current.map( ( item ) => {
				if ( item.id !== id ) {
					return item;
				}

				previous = item;

				return {
					...item,
					content: `<p>${ escapeHtml( content ) }</p>`,
					raw: content,
					pending: true,
				};
			} )
		);

		try {
			const updated = await updateComment( id, content );

			setComments( ( current ) =>
				current.map( ( item ) => ( item.id === id ? updated : item ) )
			);

			return updated;
		} catch ( error ) {
			if ( previous ) {
				setComments( ( current ) =>
					current.map( ( item ) =>
						item.id === id ? previous : item
					)
				);
			}

			throw error;
		}
	}, [] );

	return {
		comments,
		status,
		page,
		totalPages,
		setPage,
		reload: () => load( page ),
		merge,
		post,
		edit,
	};
}

/**
 * Escape text for optimistic rendering before the server responds.
 *
 * @param {string} text Raw text.
 * @return {string} Escaped HTML.
 */
function escapeHtml( text ) {
	const div = document.createElement( 'div' );
	div.textContent = text;

	return div.innerHTML;
}
