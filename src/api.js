/**
 * REST wrappers around @wordpress/api-fetch.
 *
 * Comment creation goes through core /wp/v2/comments so all native
 * moderation, flood, and spam checks apply.
 */

import apiFetch from '@wordpress/api-fetch';

import { getConfig } from './config';

/**
 * Fetch a page of comments for the current post.
 *
 * @param {number} page Page number.
 * @return {Promise<{comments: Array, totalPages: number}>} Thread page.
 */
export async function fetchComments( page = 1 ) {
	const { postId, perPage, commentOrder } = getConfig();

	const response = await apiFetch( {
		path: `/wp/v2/comments?post=${ postId }&per_page=${ perPage }&page=${ page }&order=${ commentOrder }&orderby=date_gmt`,
		parse: false,
	} );

	const totalPages = parseInt(
		response.headers.get( 'X-WP-TotalPages' ) || '1',
		10
	);
	const body = await response.json();

	return { comments: body.map( normalizeCore ), totalPages };
}

/**
 * Create a comment via the core endpoint.
 *
 * @param {Object} data Comment fields.
 * @return {Promise<Object>} Created comment.
 */
export async function createComment( data ) {
	const { postId } = getConfig();

	const created = await apiFetch( {
		path: '/wp/v2/comments',
		method: 'POST',
		data: { ...data, post: postId },
	} );

	return normalizeCore( created );
}

/**
 * Edit a comment through the plugin endpoint.
 *
 * @param {number} id      Comment ID.
 * @param {string} content New content.
 * @return {Promise<Object>} Updated comment.
 */
export function updateComment( id, content ) {
	return apiFetch( {
		path: `/convocept/v1/comments/${ id }`,
		method: 'PATCH',
		data: { content },
	} );
}

/**
 * Poll for comments newer than a timestamp.
 *
 * @param {string} after ISO timestamp.
 * @return {Promise<{comments: Array, now: string}>} New comments.
 */
export function fetchUpdates( after ) {
	const { postId } = getConfig();
	const query = after
		? `?post=${ postId }&after=${ encodeURIComponent( after ) }`
		: `?post=${ postId }`;

	return apiFetch( { path: `/convocept/v1/updates${ query }` } );
}

/**
 * Map a core REST comment onto the plugin's comment shape.
 *
 * @param {Object} comment Core comment object.
 * @return {Object} Normalized comment.
 */
function normalizeCore( comment ) {
	const { user, settings } = getConfig();
	const date = comment.date_gmt ? `${ comment.date_gmt }Z` : comment.date;

	/*
	 * Editability is computed here only to decide whether to show the Edit
	 * control. The PATCH endpoint re-checks ownership and the window on the
	 * server, which is the authoritative decision.
	 */
	const age = ( Date.now() - new Date( date ).getTime() ) / 1000;
	const owned = !! user.id && comment.author === user.id;
	const canEdit =
		user.canModerate ||
		( owned && settings.editWindow > 0 && age < settings.editWindow * 60 );

	const rendered = comment.content ? comment.content.rendered : '';

	return {
		id: comment.id,
		parent: comment.parent,
		post: comment.post,
		author: comment.author,
		authorName: comment.author_name,
		authorUrl: comment.author_url,
		avatar: comment.author_avatar_urls
			? comment.author_avatar_urls[ 48 ]
			: '',
		date,
		content: rendered,
		raw:
			comment.content && comment.content.raw
				? comment.content.raw
				: htmlToText( rendered ),
		approved: comment.status ? comment.status === 'approved' : true,
		canEdit,
		editUntil: canEdit
			? Math.max( 0, settings.editWindow * 60 - age )
			: 0,
		edited: 0,
	};
}

/**
 * Convert rendered comment HTML back to plain text for the edit field.
 *
 * Core only returns raw content to users who can moderate, so authors
 * editing their own comment get the rendered text unwrapped instead.
 *
 * @param {string} html Rendered HTML.
 * @return {string} Plain text.
 */
function htmlToText( html ) {
	const div = document.createElement( 'div' );
	div.innerHTML = html;

	return ( div.textContent || '' ).trim();
}
