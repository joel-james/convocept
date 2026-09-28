<?php
/**
 * Settings REST endpoint.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Rest;

use Convocept\Settings\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and saves the plugin settings for the settings screen.
 *
 * `GET /convocept/v1/settings` returns every setting; `POST` takes only the
 * changed keys under `settings`, checks them with Settings::validate() and
 * answers 400 with a message per invalid key (nothing is saved then), or
 * saves them and returns the complete new settings.
 *
 * @since 1.0.0
 */
class SettingsEndpoint {

	/**
	 * Settings service.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Create the endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Whether the current user may read and change settings.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get the arguments of the save route.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function save_args(): array {
		return array(
			'settings' => array(
				'type'     => 'object',
				'required' => true,
			),
		);
	}

	/**
	 * Return every setting.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response
	 */
	public function get(): WP_REST_Response {
		return Controller::response( array( 'settings' => $this->settings->all() ) );
	}

	/**
	 * Validate and save changed settings.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		$changes = (array) $request->get_param( 'settings' );
		$errors  = $this->settings->validate( $changes );

		if ( $errors ) {
			return new WP_Error(
				'convocept_invalid_settings',
				__( 'Some settings need fixing before they can be saved.', 'convocept' ),
				array(
					'status' => 400,
					'fields' => $errors,
				)
			);
		}

		return Controller::response( array( 'settings' => $this->settings->save( $changes ) ) );
	}
}
