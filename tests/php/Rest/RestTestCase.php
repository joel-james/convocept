<?php
/**
 * Base test case for REST routes.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Rest;

use Convocept\Tests\TestCase;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Helpers for dispatching Convocept REST requests.
 */
abstract class RestTestCase extends TestCase {

	public function set_up(): void {
		parent::set_up();

		// Core flood control would block back-to-back test submissions from one IP.
		remove_action( 'check_comment_flood', 'check_comment_flood_db' );

		// Cookies can't be sent once PHPUnit has produced output.
		remove_action( 'set_comment_cookies', 'wp_set_comment_cookies' );
		update_option( 'comment_previously_approved', 0 );
		wp_set_current_user( 0 );
	}

	public function tear_down(): void {
		add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );
		add_action( 'set_comment_cookies', 'wp_set_comment_cookies', 10, 3 );
		parent::tear_down();
	}

	/**
	 * Dispatch a request.
	 *
	 * @param string               $method  HTTP method.
	 * @param string               $route   Route below /convocept/v1.
	 * @param array<string, mixed> $params  Parameters.
	 * @param array<string, string> $headers Headers.
	 * @return WP_REST_Response
	 */
	protected function dispatch( string $method, string $route, array $params = array(), array $headers = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/convocept/v1/' . ltrim( $route, '/' ) );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		foreach ( $headers as $key => $value ) {
			$request->set_header( $key, $value );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Post a guest comment through the REST route.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $params  Extra or overriding parameters.
	 * @return WP_REST_Response
	 */
	protected function post_comment( int $post_id, array $params = array() ): WP_REST_Response {
		return $this->dispatch(
			'POST',
			"posts/{$post_id}/comments",
			array_merge(
				array(
					'content' => 'Hello **world** ' . wp_generate_password( 6, false ),
					'author'  => 'Guest',
					'email'   => 'guest@example.com',
					'elapsed' => 10,
				),
				$params
			)
		);
	}
}
