<?php
/**
 * Uninstall routine.
 *
 * Removes only data this plugin created. Comments are the site's data and
 * are never touched. M11 adds the "delete all Convocept data" setting that
 * gates removal of custom tables.
 *
 * @package Convocept
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'convocept_settings' );
delete_option( 'convocept_db_version' );
delete_option( 'convocept_migration_lock' );

delete_metadata( 'comment', 0, 'convocept_edited_at', '', true );
delete_metadata( 'comment', 0, '_convocept_edit_token', '', true );
delete_metadata( 'comment', 0, 'convocept_moderation_log', '', true );
delete_metadata( 'comment', 0, 'convocept_reactions', '', true );
delete_metadata( 'comment', 0, 'convocept_score', '', true );
delete_metadata( 'comment', 0, 'convocept_pinned', '', true );

delete_metadata( 'comment', 0, '_convocept_notified', '', true );
delete_option( 'convocept_notification_jobs' );
delete_option( 'convocept_notification_lock' );
wp_clear_scheduled_hook( 'convocept_send_notifications' );

// The {prefix}convocept_reactions and {prefix}convocept_subscriptions tables
// are kept until M11 adds the "delete all Convocept data" option.

delete_option( 'convocept_import_job' );
delete_option( 'convocept_import_lock' );
delete_metadata( 'comment', 0, '_convocept_disqus_parent', '', true );

// Working files of an unfinished import hold commenters' emails and IPs.
$convocept_import_root = trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'convocept-import';

$convocept_dirs = array_merge( (array) glob( $convocept_import_root . '/*', GLOB_ONLYDIR ), array( $convocept_import_root ) );

foreach ( $convocept_dirs as $convocept_dir ) {
	$convocept_files = array_merge( (array) glob( $convocept_dir . '/*' ), array( $convocept_dir . '/.htaccess' ) );

	foreach ( $convocept_files as $convocept_file ) {
		if ( is_file( (string) $convocept_file ) ) {
			wp_delete_file( (string) $convocept_file );
		}
	}

	if ( is_dir( (string) $convocept_dir ) ) {
		rmdir( (string) $convocept_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Emptied above; WP_Filesystem may need credentials.
	}
}

// `_convocept_disqus_id` is kept: it stops a later re-import from duplicating comments.
