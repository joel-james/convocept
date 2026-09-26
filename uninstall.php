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
delete_metadata( 'comment', 0, '_convocept_edited', '', true );
