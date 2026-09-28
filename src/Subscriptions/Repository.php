<?php
/**
 * Subscription storage.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Subscriptions;

use Convocept\Database\Migrations\SubscriptionsTable;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes rows of the subscriptions table.
 *
 * Rows are returned as associative arrays with integer IDs. All queries
 * are prepared; the table name comes from $wpdb->prefix.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; every value is prepared, the table name is from $wpdb->prefix.
 *
 * @since 1.0.0
 */
class Repository {

	/**
	 * Subscription waiting for double opt-in.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PENDING = 'pending';

	/**
	 * Confirmed subscription that receives email.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ACTIVE = 'active';

	/**
	 * Subscription the reader turned off.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const UNSUBSCRIBED = 'unsubscribed';

	/**
	 * Hash an email address for lookups.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Email address.
	 * @return string 64 hex characters.
	 */
	public function hash_email( string $email ): string {
		return hash_hmac( 'sha256', strtolower( trim( $email ) ), wp_salt( 'auth' ) );
	}

	/**
	 * Create a subscription, or return the existing one for the same scope.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email      Email address (already validated).
	 * @param int    $post_id    Post ID, or 0 for the whole site.
	 * @param int    $comment_id Comment whose replies to follow, or 0.
	 * @param string $status     Initial status.
	 * @param string $source     Where it came from, e.g. 'native'.
	 * @return array<string, mixed>|null The row.
	 */
	public function upsert( string $email, int $post_id, int $comment_id, string $status, string $source = 'native' ): ?array {
		global $wpdb;

		$hash     = $this->hash_email( $email );
		$existing = $this->find_scope( $hash, $post_id, $comment_id );

		if ( $existing ) {
			// Re-subscribing after unsubscribing starts over (and needs confirming again).
			if ( self::UNSUBSCRIBED === $existing['status'] ) {
				$this->update( (int) $existing['id'], array( 'status' => $status ) );
				$existing['status'] = $status;
			}

			return $existing;
		}

		$wpdb->insert(
			SubscriptionsTable::name(),
			array(
				'email'        => $email,
				'email_hash'   => $hash,
				'post_id'      => $post_id,
				'comment_id'   => $comment_id,
				'status'       => $status,
				'token_key'    => wp_generate_password( 32, false ),
				'created_at'   => current_time( 'mysql', true ),
				'confirmed_at' => self::ACTIVE === $status ? current_time( 'mysql', true ) : null,
				'source'       => $source,
				'locale'       => determine_locale(),
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $wpdb->insert_id ? $this->find( (int) $wpdb->insert_id ) : null;
	}

	/**
	 * Get a subscription by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Subscription ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		$table = SubscriptionsTable::name();
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ), ARRAY_A );

		return is_array( $row ) ? $this->normalize( $row ) : null;
	}

	/**
	 * Get every subscription for an email hash.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_hash Email hash.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_email( string $email_hash ): array {
		global $wpdb;

		$table = SubscriptionsTable::name();
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE email_hash = %s ORDER BY id', $table, $email_hash ), ARRAY_A );

		return array_map( array( $this, 'normalize' ), (array) $rows );
	}

	/**
	 * Whether an address already has a confirmed subscription.
	 *
	 * Used to skip double opt-in for readers who already proved they own it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public function has_active( string $email ): bool {
		global $wpdb;

		$table = SubscriptionsTable::name();

		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i WHERE email_hash = %s AND status = %s LIMIT 1', $table, $this->hash_email( $email ), self::ACTIVE ) );
	}

	/**
	 * Update columns of a subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $id      Subscription ID.
	 * @param array<string, mixed> $columns Columns to set.
	 * @return void
	 */
	public function update( int $id, array $columns ): void {
		global $wpdb;

		$wpdb->update( SubscriptionsTable::name(), $columns, array( 'id' => $id ) );
	}

	/**
	 * Get the next batch of distinct recipients for a new comment.
	 *
	 * One row per address, even when it follows the post, the parent
	 * comment and the whole site. `is_reply` tells whether the address
	 * follows one of the comment's ancestors (for the "replied to you"
	 * wording). The cursor is the lowest subscription ID of each address,
	 * so paging is stable while rows are added.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id      Post the comment is on.
	 * @param int[]  $ancestor_ids The comment's ancestors (reply subscriptions to notify).
	 * @param string $exclude_hash Email hash of the comment's author, who is never notified.
	 * @param int    $after        Cursor from the previous batch.
	 * @param int    $limit        Batch size.
	 * @return array<int, array{cursor: int, id: int, email: string, is_reply: bool, token_key: string, locale: string}>
	 */
	public function recipients( int $post_id, array $ancestor_ids, string $exclude_hash, int $after, int $limit ): array {
		global $wpdb;

		$table = SubscriptionsTable::name();
		// -1 never matches: an empty IN () is invalid SQL, and IN (0) would match
		// every subscription that is not for a comment.
		$ancestors = $ancestor_ids ? array_map( 'intval', $ancestor_ids ) : array( -1 );
		$in        = implode( ',', array_fill( 0, count( $ancestors ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $in holds %d placeholders; values are passed as one array.
		$sql = $wpdb->prepare(
			"SELECT MIN(id) AS cursor_id, email_hash,
				MAX(CASE WHEN comment_id IN ({$in}) THEN 1 ELSE 0 END) AS is_reply
			FROM %i
			WHERE status = %s
				AND email_hash <> %s
				AND ( ( post_id = %d AND comment_id = 0 ) OR ( post_id = 0 AND comment_id = 0 ) OR comment_id IN ({$in}) )
			GROUP BY email_hash
			HAVING MIN(id) > %d
			ORDER BY cursor_id
			LIMIT %d",
			array_merge( $ancestors, array( $table, self::ACTIVE, $exclude_hash, $post_id ), $ancestors, array( $after, $limit ) )
		);

		$groups = (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.

		$recipients = array();

		foreach ( $groups as $group ) {
			$row = $this->find( (int) $group['cursor_id'] );

			if ( $row ) {
				$recipients[] = array(
					'cursor'    => (int) $group['cursor_id'],
					'id'        => (int) $row['id'],
					'email'     => (string) $row['email'],
					'is_reply'  => (bool) $group['is_reply'],
					'token_key' => (string) $row['token_key'],
					'locale'    => (string) $row['locale'],
				);
			}
		}

		return $recipients;
	}

	/**
	 * Delete every subscription for an email hash (privacy erasure).
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_hash Email hash.
	 * @return int Rows deleted.
	 */
	public function delete_for_email( string $email_hash ): int {
		global $wpdb;

		return (int) $wpdb->delete( SubscriptionsTable::name(), array( 'email_hash' => $email_hash ), array( '%s' ) );
	}

	/**
	 * Find the subscription for one address and scope.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_hash Email hash.
	 * @param int    $post_id    Post ID.
	 * @param int    $comment_id Comment ID.
	 * @return array<string, mixed>|null
	 */
	private function find_scope( string $email_hash, int $post_id, int $comment_id ): ?array {
		global $wpdb;

		$table = SubscriptionsTable::name();
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE email_hash = %s AND post_id = %d AND comment_id = %d', $table, $email_hash, $post_id, $comment_id ), ARRAY_A );

		return is_array( $row ) ? $this->normalize( $row ) : null;
	}

	/**
	 * Cast numeric columns.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<string, mixed>
	 */
	private function normalize( array $row ): array {
		foreach ( array( 'id', 'post_id', 'comment_id' ) as $key ) {
			$row[ $key ] = (int) $row[ $key ];
		}

		return $row;
	}
}
