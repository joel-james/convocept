<?php
/**
 * Preview endpoint.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Rest;

use Convocept\Plugin;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a Markdown preview with the same parser used for published comments.
 *
 * Rendering on the server keeps the parser in one place (no second parser
 * shipped to the browser) and guarantees the preview matches the result.
 *
 * @since 1.0.0
 */
class PreviewEndpoint {

	/**
	 * Plugin instance.
	 *
	 * @since 1.0.0
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Create the endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Get the argument schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function args(): array {
		return array(
			'content' => Controller::content_arg( true ),
		);
	}

	/**
	 * Handle POST /preview.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create( WP_REST_Request $request ) {
		if ( ! $this->plugin->limiter()->attempt( 'preview', $this->plugin->ips()->hash(), 30, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'convocept_rate_limited', __( 'Too many previews. Please wait a moment.', 'convocept' ), array( 'status' => 429 ) );
		}

		$markdown = $this->plugin->submission()->encode_markdown( (string) $request->get_param( 'content' ) );

		return Controller::response( array( 'html' => $this->plugin->markdown()->parse( $markdown ) ) );
	}
}
