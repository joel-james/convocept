<?php
/**
 * Activation and deactivation handlers.
 *
 * @package Convocept
 * @since   2.0.0
 */

namespace Convocept;

use Convocept\Database\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Runs setup on activation and cleanup on deactivation.
 *
 * Deactivation never removes data; that only happens on uninstall.
 *
 * @since 2.0.0
 */
final class Lifecycle {

	/**
	 * Handle plugin activation.
	 *
	 * On multisite network activation only the current site migrates here;
	 * every other site migrates lazily on its next request (Plugin::boot()).
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function activate(): void {
		( new Migrator() )->maybe_migrate();

		/**
		 * Fires after Convocept is activated.
		 *
		 * @since 2.0.0
		 */
		do_action( 'convocept_activated' );
	}

	/**
	 * Handle plugin deactivation.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		/**
		 * Filters the WP-Cron hooks cleared on deactivation.
		 *
		 * Add-ons that schedule events append their hook names.
		 *
		 * @since 2.0.0
		 *
		 * @param string[] $hooks Cron hook names.
		 */
		$hooks = (array) apply_filters( 'convocept_cron_hooks', array() );

		foreach ( $hooks as $hook ) {
			wp_clear_scheduled_hook( (string) $hook );
		}

		/**
		 * Fires after Convocept is deactivated.
		 *
		 * @since 2.0.0
		 */
		do_action( 'convocept_deactivated' );
	}
}
