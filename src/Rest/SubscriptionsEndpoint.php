<?php
/**
 * Subscriptions endpoint.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Rest;

use Convocept\Plugin;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * "Subscribe without commenting" for the JavaScript form.
 *
 * Public and rate-limited. The answer is the same whether or not the
 * address was already subscribed, so the endpoint can't be used to probe
 * who follows what.
 *
 * @since 1.0.0
 */
class SubscriptionsEndpoint {

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
			'email'    => array(
				'type'      => 'string',
				'required'  => true,
				'maxLength' => 100,
			),
			'post_id'  => array(
				'type'    => 'integer',
				'default' => 0,
				'minimum' => 0,
			),
			'honeypot' => array(
				'type'    => 'string',
				'default' => '',
			),
		);
	}

	/**
	 * Handle POST /subscriptions.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response Response data: 'ok' and 'message'.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->plugin->subscribe_form()->request(
			(string) $request->get_param( 'email' ),
			(int) $request->get_param( 'post_id' ),
			(string) $request->get_param( 'honeypot' )
		);

		return Controller::response( $result, $result['ok'] ? 200 : 400 );
	}
}
