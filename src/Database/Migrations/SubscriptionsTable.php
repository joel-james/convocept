<?php
/**
 * Migration 3: subscriptions table.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Database\Migrations;

use Convocept\Database\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the table of email subscriptions.
 *
 * One row per address per scope: a post (post_id), a comment's replies
 * (comment_id) or the whole site (post_id 0 and comment_id 0). Addresses
 * are looked up by a salted hash so lookups never need the plain address
 * in a query; the plain address is kept only because it is needed to send.
 *
 * @since 1.0.0
 */
class SubscriptionsTable implements Migration {

	/**
	 * Get the table name with the site's prefix.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function name(): string {
		global $wpdb;

		return $wpdb->prefix . 'convocept_subscriptions';
	}

	/**
	 * Create or update the table.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::name();
		$charset = $wpdb->get_charset_collate();

		// token_key is a random per-row secret mixed into signed links; changing it revokes them.
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				email varchar(100) NOT NULL,
				email_hash char(64) NOT NULL,
				post_id bigint(20) unsigned NOT NULL DEFAULT 0,
				comment_id bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'pending',
				token_key char(32) NOT NULL,
				created_at datetime NOT NULL,
				confirmed_at datetime DEFAULT NULL,
				source varchar(32) NOT NULL DEFAULT 'native',
				locale varchar(20) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				UNIQUE KEY email_scope (email_hash,post_id,comment_id),
				KEY post_status (post_id,status),
				KEY comment_status (comment_id,status)
			) {$charset};"
		);
	}
}
