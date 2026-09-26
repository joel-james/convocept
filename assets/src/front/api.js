/**
 * Minimal REST client for the convocept/v1 routes.
 */
import { config, t } from './config';

/**
 * Error thrown for failed requests, carrying a message safe to show.
 */
export class ApiError extends Error {
	/**
	 * @param {string} message Message for the reader.
	 * @param {number} status  HTTP status, 0 for network failures.
	 */
	constructor( message, status ) {
		super( message );
		this.status = status;
	}
}

/**
 * Send a request to a Convocept REST route.
 *
 * The REST base may be pretty (/wp-json/convocept/v1/) or, on sites with
 * plain permalinks, a query string (?rest_route=/convocept/v1/), so query
 * arguments are added with URLSearchParams rather than by concatenation.
 *
 * @param {string}                 path             Route path relative to the namespace, e.g. "session".
 * @param {Object}                 [options]        Request options.
 * @param {string}                 [options.method] HTTP method. Default GET.
 * @param {Object}                 [options.data]   JSON body.
 * @param {Object<string, string>} [options.query]  Query arguments.
 * @param {string}                 [options.nonce]  REST nonce for logged-in users.
 * @return {Promise<Object>} Parsed JSON response.
 */
export async function request(
	path,
	{ method = 'GET', data, query = {}, nonce } = {}
) {
	const url = new URL( config.rest + path, window.location.href );

	Object.keys( query ).forEach( ( key ) =>
		url.searchParams.set( key, query[ key ] )
	);

	const headers = { 'X-Convocept': '1' };

	if ( data ) {
		headers[ 'Content-Type' ] = 'application/json';
	}

	if ( nonce ) {
		headers[ 'X-WP-Nonce' ] = nonce;
	}

	let response;

	try {
		response = await fetch( url, {
			method,
			headers,
			body: data ? JSON.stringify( data ) : undefined,
			credentials: 'same-origin',
		} );
	} catch {
		throw new ApiError( t( 'network' ), 0 );
	}

	const body = await response.json().catch( () => ( {} ) );

	if ( ! response.ok ) {
		throw new ApiError( body.message || t( 'failed' ), response.status );
	}

	return body;
}
