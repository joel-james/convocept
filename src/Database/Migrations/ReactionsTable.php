<?php
/**
 * Migration 1: reactions table.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Database\Migrations;

use Convocept\Database\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the table that stores one row per vote or reaction.
 *
 * Upvotes (1.0) are the reaction 'up'; emoji reactions (1.2) reuse the
 * same table. The unique key makes "one vote per voter per comment" a
 * database guarantee, not just an application check.
 *
 * @since 1.0.0
 */
class ReactionsTable implements Migration {

	/**
	 * Get the table name with the site's prefix.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function name(): string {
		global $wpdb;

		return $wpdb->prefix . 'convocept_reactions';
	}

	/**
	 * Create or update the table.
	 *
	 * The dbDelta() call is idempotent, so re-running is safe.
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

		// dbDelta needs two spaces after PRIMARY KEY and one field per line.
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				comment_id bigint(20) unsigned NOT NULL,
				reaction varchar(32) NOT NULL,
				voter_hash char(64) NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY comment_voter_reaction (comment_id,voter_hash,reaction),
				KEY voter_hash (voter_hash)
			) {$charset};"
		);
	}
}
