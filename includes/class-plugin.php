<?php
/**
 * Main plugin class.
 *
 * Holds the service objects that make up the plugin and wires them into
 * WordPress. This is the only class instantiated from the bootstrap file.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept;

use Convocept\Frontend\Assets;
use Convocept\Frontend\Blocks;
use Convocept\Frontend\Template;
use Convocept\Rest\Routes;

defined( 'ABSPATH' ) || exit;

/**
 * Boots and wires all plugin services.
 *
 * Services are deliberately split by responsibility:
 *
 * - Settings    stores and renders plugin options.
 * - EditWindow  decides who may edit a comment, and for how long.
 * - Routes      exposes the custom REST endpoints.
 * - Assets      loads the built app and its configuration.
 * - Template    integrates with classic themes.
 * - Blocks      integrates with block themes.
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings service.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Edit window service.
	 *
	 * @since 1.0.0
	 * @var EditWindow
	 */
	private $edit_window;

	/**
	 * Get the singleton instance, creating it on first use.
	 *
	 * @since 1.0.0
	 *
	 * @return Plugin The shared plugin instance.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Build the services the plugin depends on.
	 *
	 * Private so the class can only be obtained through instance(); this
	 * keeps a single set of services per request.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->settings    = new Settings();
		$this->edit_window = new EditWindow( $this->settings );
	}

	/**
	 * Register every hook the plugin uses.
	 *
	 * Called once from the bootstrap file on `plugins_loaded`.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init() {
		// Settings and REST routes are needed in every context: the admin
		// screens read options, and REST requests run outside the frontend.
		$this->settings->register();
		( new Routes( $this->settings, $this->edit_window ) )->register();

		// Everything below only ever renders on the frontend.
		if ( ! is_admin() ) {
			$assets = new Assets( $this->settings, $this->edit_window );
			$assets->register();

			// Both theme integrations receive the same Assets instance so
			// whichever one renders can flag the assets as needed.
			( new Template( $assets ) )->register();
			( new Blocks( $assets ) )->register();
		}
	}

	/**
	 * Get the settings service.
	 *
	 * @since 1.0.0
	 *
	 * @return Settings The settings service.
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * Determine whether the modern comments UI is enabled for a post.
	 *
	 * Combines the site-wide setting with the `convocept_enabled` filter, which
	 * lets integrations disable the app per post, per type, or per template.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Optional. Post ID to check. Default 0 (current post).
	 * @return bool True when the app should replace native comments.
	 */
	public function enabled_for( $post_id = 0 ) {
		$post_id = $post_id ? $post_id : get_the_ID();
		$enabled = (bool) $this->settings->get( 'enabled' );

		/**
		 * Filters whether the modern comments UI is enabled for a post.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $enabled Whether the app is enabled.
		 * @param int  $post_id Post ID being rendered.
		 */
		return (bool) apply_filters( 'convocept_enabled', $enabled, (int) $post_id );
	}
}
