<?php
/**
 * Uninstall routine.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept;

use Convocept\Import\Workspace;
use Convocept\Settings\Settings;
use Convocept\Subscriptions\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Removes Convocept's data when the plugin is deleted.
 *
 * Comments always stay: they are the site's data, stored in WordPress's own
 * tables. Working state (locks, queued jobs, caches, import files) is always
 * removed. Settings, subscriptions, votes and comment meta are kept, so
 * installing Convocept again picks up where it left off, unless the owner
 * turned on "Delete all Convocept data" (`delete_data`) on the settings
 * screen. On multisite this runs for every site.
 *
 * @since 1.0.0
 */
class Uninstaller {

	/**
	 * Comment meta keys Convocept stores.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const COMMENT_META = array(
		'convocept_edited_at',
		'convocept_moderation_log',
		'convocept_reactions',
		'convocept_score',
		'convocept_pinned',
		'_convocept_notified',
		'_convocept_disqus_id',
	);

	/**
	 * Options holding settings and schema state.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const OPTIONS = array( Settings::OPTION, 'convocept_db_version' );

	/**
	 * Custom tables, without the site prefix.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const TABLES = array( 'convocept_reactions', 'convocept_subscriptions' );

	/**
	 * Clean up every site.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function run(): void {
		if ( ! is_multisite() ) {
			$this->run_site();

			return;
		}

		foreach ( get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		) as $site_id ) {
			switch_to_blog( (int) $site_id );
			$this->run_site();
			restore_current_blog();
		}
	}

	/**
	 * Clean up the current site.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function run_site(): void {
		$delete_data = $this->deletes_data();

		$this->remove_working_state();

		if ( $delete_data ) {
			$this->remove_data();
		}

		/**
		 * Fires after Convocept has cleaned up a site on uninstall.
		 *
		 * Add-ons store their own data and clean it up in their own
		 * uninstall routine; this is for code that extends Convocept from a
		 * theme or a site plugin.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $delete_data Whether the owner asked to delete all Convocept data.
		 */
		do_action( 'convocept_uninstalled', $delete_data );
	}

	/**
	 * Whether the owner asked to delete all data.
	 *
	 * Read straight from the option: on uninstall the plugin is not
	 * booted, so the settings service may never have run.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function deletes_data(): bool {
		$settings = get_option( Settings::OPTION, array() );

		return is_array( $settings ) && ! empty( $settings['delete_data'] );
	}

	/**
	 * Remove locks, queued jobs, schedules, caches and import files.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function remove_working_state(): void {
		global $wpdb;

		foreach ( array( 'convocept_migration_lock', 'convocept_import_job', 'convocept_import_lock', Queue::JOBS_OPTION, Queue::LOCK_OPTION ) as $option ) {
			delete_option( $option );
		}

		wp_clear_scheduled_hook( Queue::HOOK );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Queue::HOOK, array(), 'convocept' );
		}

		foreach ( array( '_convocept_edit_token', '_convocept_disqus_parent' ) as $key ) {
			delete_metadata( 'comment', 0, $key, '', true );
		}

		// Rendered comment lists and rate-limit counters. With a persistent object cache
		// they never reach the options table and expire on their own.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off cleanup; there is no API to list transients by prefix.
		$names = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_convocept_' ) . '%'
			)
		);

		foreach ( $names as $name ) {
			delete_transient( substr( (string) $name, strlen( '_transient_' ) ) );
		}

		( new Workspace() )->delete_all();
	}

	/**
	 * Remove settings, tables and comment meta.
	 *
	 * `_convocept_disqus_id` goes too, so importing the same Disqus export
	 * after reinstalling can only recognise comments the Disqus plugin
	 * itself synced; the owner asked for everything to go.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function remove_data(): void {
		global $wpdb;

		foreach ( self::OPTIONS as $option ) {
			delete_option( $option );
		}

		foreach ( self::COMMENT_META as $key ) {
			delete_metadata( 'comment', 0, $key, '', true );
		}

		foreach ( self::TABLES as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Our own tables, names from constants.
		}
	}
}
