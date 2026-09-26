<?php
/**
 * Migration 1: unreleased prototype data.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Database\Migrations;

use Convocept\Database\Migration;
use Convocept\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Moves data written by the unreleased prototype to the 1.x keys.
 *
 * - Comment meta `_convocept_edited` becomes `convocept_edited_at`.
 * - Prototype-only settings are dropped; `enabled` and `edit_window` stay.
 *
 * @since 1.0.0
 */
class PrototypeData implements Migration {

	/**
	 * Settings keys that only existed in the prototype.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const REMOVED_SETTINGS = array( 'live_updates', 'poll_interval', 'load_styles' );

	/**
	 * Apply the migration.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		// A single UPDATE is idempotent and avoids loading every row into PHP.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration.
		$wpdb->update(
			$wpdb->commentmeta,
			array( 'meta_key' => 'convocept_edited_at' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			array( 'meta_key' => '_convocept_edited' ) // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		);

		// Drop cached meta so persistent object caches don't serve the old key.
		if ( wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'comment_meta' );
		}

		$settings = get_option( Settings::OPTION );

		if ( is_array( $settings ) ) {
			foreach ( self::REMOVED_SETTINGS as $key ) {
				unset( $settings[ $key ] );
			}

			update_option( Settings::OPTION, $settings );
		}
	}
}
