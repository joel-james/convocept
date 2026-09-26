<?php
/**
 * Main plugin class.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept;

use Convocept\Database\Migrator;
use Convocept\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's services into WordPress.
 *
 * Services are created lazily so a request only pays for what it uses:
 * front-end requests never load admin, import or CLI code.
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
	 * Settings service, created on first use.
	 *
	 * @since 1.0.0
	 * @var Settings|null
	 */
	private $settings = null;

	/**
	 * Whether boot() already ran.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Get the shared instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private so the plugin can only be obtained through instance().
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Register every hook the plugin uses.
	 *
	 * Called once on `plugins_loaded`.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		// Run pending migrations after an update, where activation hooks don't fire.
		add_action(
			'init',
			static function () {
				( new Migrator() )->maybe_migrate();
			},
			1
		);

		/**
		 * Fires after Convocept has registered its hooks.
		 *
		 * Add-ons should register their own hooks here.
		 *
		 * @since 1.0.0
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'convocept_loaded', $this );
	}

	/**
	 * Get the settings service.
	 *
	 * @since 1.0.0
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		if ( null === $this->settings ) {
			$this->settings = new Settings();
		}

		return $this->settings;
	}
}
