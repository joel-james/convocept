<?php
/**
 * Session endpoint.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Rest;

use Convocept\Comments\EditWindow;
use Convocept\Votes\Votes;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Tells the front-end script who the visitor is.
 *
 * The page HTML is identical for everyone (so it can be cached); this
 * endpoint returns the per-visitor state instead: login status, display
 * name, a REST nonce for logged-in users and whether they can moderate.
 *
 * Core's REST API ignores login cookies unless a nonce is sent, and the
 * script has no nonce yet (that is what it is asking for). So the login
 * cookie is validated here directly, but only for genuine same-origin
 * requests from our script: the request must carry the `X-Convocept`
 * header, which a browser only lets a cross-origin page send after a CORS
 * preflight that core's REST server does not allow for this header, and
 * must not be marked cross-site by the browser. That keeps other sites
 * from reading a visitor's nonce.
 *
 * @since 1.0.0
 */
class SessionEndpoint {

	/**
	 * Request header our script sends to prove the request is same-origin.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const HEADER = 'X-Convocept';

	/**
	 * Edit window service.
	 *
	 * @since 1.0.0
	 * @var EditWindow
	 */
	private $edit_window;

	/**
	 * Vote service.
	 *
	 * @since 1.0.0
	 * @var Votes
	 */
	private $votes;

	/**
	 * Create the endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param EditWindow $edit_window Edit window service.
	 * @param Votes      $votes       Vote service.
	 */
	public function __construct( EditWindow $edit_window, Votes $votes ) {
		$this->edit_window = $edit_window;
		$this->votes       = $votes;
	}

	/**
	 * Handle GET /session.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response {
	 *     @type bool        $logged_in    Whether the visitor is logged in.
	 *     @type array|null  $user         'id', 'name' and 'avatar' URL, or null.
	 *     @type string      $nonce        REST nonce for logged-in users, else empty.
	 *     @type bool        $can_moderate Whether the visitor may moderate comments.
	 *     @type int         $edit_window  Edit window in minutes.
	 *     @type int         $now          Server time (Unix), to correct clock skew.
	 *     @type int[]       $votes        IDs of comments on `post_id` the visitor upvoted.
	 * }
	 */
	public function get( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();

		if ( ! $user_id && $this->is_same_origin( $request ) ) {
			$user_id = (int) wp_validate_auth_cookie( '', 'logged_in' );

			if ( $user_id ) {
				wp_set_current_user( $user_id );
			}
		}

		$user = $user_id ? get_userdata( $user_id ) : false;

		$data = array(
			'logged_in'    => (bool) $user,
			'user'         => $user ? array(
				'id'     => (int) $user->ID,
				'name'   => $user->display_name,
				'avatar' => (string) get_avatar_url( $user->ID, array( 'size' => 80 ) ),
			) : null,
			'nonce'        => $user ? wp_create_nonce( 'wp_rest' ) : '',
			'can_moderate' => $user && user_can( $user, 'moderate_comments' ),
			'edit_window'  => $this->edit_window->minutes(),
			'now'          => time(),
			'votes'        => $request->get_param( 'post_id' ) ? $this->votes->voted_on_post( (int) $request->get_param( 'post_id' ) ) : array(),
		);

		/**
		 * Filters the session data sent to the front-end script.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $data    Session data.
		 * @param WP_REST_Request      $request Request.
		 */
		return Controller::response( (array) apply_filters( 'convocept_session_data', $data, $request ) );
	}

	/**
	 * Whether the request certainly comes from a page on this site.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private function is_same_origin( WP_REST_Request $request ): bool {
		if ( '1' !== $request->get_header( self::HEADER ) || null !== $request->get_param( '_jsonp' ) ) {
			return false;
		}

		$fetch_site = $request->get_header( 'sec_fetch_site' );

		if ( null !== $fetch_site && ! in_array( $fetch_site, array( 'same-origin', 'none' ), true ) ) {
			return false;
		}

		$origin = $request->get_header( 'origin' );

		return null === $origin || $this->origin_of( $origin ) === $this->origin_of( home_url() ) || $this->origin_of( $origin ) === $this->origin_of( site_url() );
	}

	/**
	 * Reduce a URL to scheme://host[:port].
	 *
	 * @since 1.0.0
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function origin_of( string $url ): string {
		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		return strtolower( $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) );
	}
}
