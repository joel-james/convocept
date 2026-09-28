<?php
/**
 * Votes.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Votes;

use Convocept\Comments\Sort;
use Convocept\Database\Migrations\ReactionsTable;
use Convocept\Security\IpResolver;
use WP_Comment;

defined( 'ABSPATH' ) || exit;

/**
 * Stores upvotes and keeps each comment's count and score up to date.
 *
 * Each vote is a row in the reactions table, identified by a salted hash
 * of the voter: the user ID for logged-in readers, the IP address
 * for guests. Raw IPs are never stored. The count is also cached in comment
 * meta so rendering a thread never touches the reactions table, and the
 * score meta drives the "Top" sort.
 *
 * @since 1.0.0
 */
class Votes {

	/**
	 * Reaction name for an upvote.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const UP = 'up';

	/**
	 * Comment meta key caching reaction counts as JSON, e.g. {"up":3}.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const COUNTS_META_KEY = 'convocept_reactions';

	/**
	 * IP resolver, for guest voter IDs.
	 *
	 * @since 1.0.0
	 * @var IpResolver
	 */
	private $ips;

	/**
	 * Create the service.
	 *
	 * @since 1.0.0
	 *
	 * @param IpResolver $ips IP resolver.
	 */
	public function __construct( IpResolver $ips ) {
		$this->ips = $ips;
	}

	/**
	 * Get the current visitor's voter ID.
	 *
	 * @since 1.0.0
	 *
	 * @return string 64 hex characters.
	 */
	public function voter(): string {
		$user_id = get_current_user_id();

		if ( $user_id ) {
			return $this->voter_for_user( $user_id );
		}

		// The IP alone, not IP + browser: a changing User-Agent must not make a new voter.
		$source = 'guest|' . $this->ips->resolve();

		return hash_hmac( 'sha256', $source, wp_salt( 'nonce' ) );
	}

	/**
	 * Get a registered user's voter ID.
	 *
	 * Lets privacy requests find a user's votes. Guest votes cannot be
	 * found this way: their ID is a one-way hash of the IP address,
	 * which is the point.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 * @return string 64 hex characters.
	 */
	public function voter_for_user( int $user_id ): string {
		return hash_hmac( 'sha256', 'user|' . $user_id, wp_salt( 'nonce' ) );
	}

	/**
	 * Get a voter's votes, oldest first.
	 *
	 * @since 1.0.0
	 *
	 * @param string $voter  Voter ID.
	 * @param int    $limit  Rows to return.
	 * @param int    $offset Rows to skip.
	 * @return array<int, array{comment_id: int, created_at: string}>
	 */
	public function for_voter( string $voter, int $limit, int $offset = 0 ): array {
		global $wpdb;

		$table = ReactionsTable::name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy export, run rarely.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT comment_id, created_at FROM %i WHERE voter_hash = %s AND reaction = %s ORDER BY id LIMIT %d OFFSET %d',
				$table,
				$voter,
				self::UP,
				$limit,
				$offset
			),
			ARRAY_A
		);

		return array_map(
			static function ( $row ) {
				return array(
					'comment_id' => (int) $row['comment_id'],
					'created_at' => (string) $row['created_at'],
				);
			},
			(array) $rows
		);
	}

	/**
	 * Remove a voter's votes and refresh the counts of the comments involved.
	 *
	 * Works in batches so a very active voter cannot time out a privacy
	 * request; call again until it returns fewer rows than the limit.
	 *
	 * @since 1.0.0
	 *
	 * @param string $voter Voter ID.
	 * @param int    $limit Votes to remove in this call.
	 * @return int Votes removed.
	 */
	public function delete_for_voter( string $voter, int $limit ): int {
		global $wpdb;

		$table = ReactionsTable::name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Privacy erasure, run rarely.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, comment_id FROM %i WHERE voter_hash = %s ORDER BY id LIMIT %d',
				$table,
				$voter,
				$limit
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return 0;
		}

		$ids = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
		$in  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Privacy erasure; $in holds %d placeholders.
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE id IN ({$in})", array_merge( array( $table ), $ids ) ) );
		// phpcs:enable

		foreach ( array_unique( array_map( 'intval', wp_list_pluck( $rows, 'comment_id' ) ) ) as $comment_id ) {
			$this->recount( $comment_id );
		}

		return count( $ids );
	}

	/**
	 * Add or remove the visitor's upvote.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment.
	 * @param bool       $vote    True to vote, false to remove the vote.
	 * @return int The comment's new vote count.
	 */
	public function set( WP_Comment $comment, bool $vote ): int {
		global $wpdb;

		$comment_id = (int) $comment->comment_ID;
		$voter      = $this->voter();
		$table      = ReactionsTable::name();

		if ( $vote ) {
			// INSERT IGNORE + the unique key: a double click can never count twice.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; counts are cached in comment meta.
			$changed = $wpdb->query(
				$wpdb->prepare(
					'INSERT IGNORE INTO %i (comment_id, reaction, voter_hash, created_at) VALUES (%d, %s, %s, %s)',
					$table,
					$comment_id,
					self::UP,
					$voter,
					current_time( 'mysql', true )
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; counts are cached in comment meta.
			$changed = $wpdb->delete(
				$table,
				array(
					'comment_id' => $comment_id,
					'reaction'   => self::UP,
					'voter_hash' => $voter,
				),
				array( '%d', '%s', '%s' )
			);
		}//end if

		$count = $changed ? $this->recount( $comment_id ) : $this->count( $comment );

		if ( $changed && $vote ) {
			/**
			 * Fires after a visitor upvotes a comment.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $comment_id Comment ID.
			 * @param string $reaction   Reaction name ('up').
			 * @param int    $count      New count.
			 */
			do_action( 'convocept_reaction_added', $comment_id, self::UP, $count );
		}

		return $count;
	}

	/**
	 * Get a comment's cached vote count.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment.
	 * @return int
	 */
	public function count( WP_Comment $comment ): int {
		$counts = json_decode( (string) get_comment_meta( (int) $comment->comment_ID, self::COUNTS_META_KEY, true ), true );

		return is_array( $counts ) && isset( $counts[ self::UP ] ) ? max( 0, (int) $counts[ self::UP ] ) : 0;
	}

	/**
	 * Get the IDs of the comments on a post the visitor has upvoted.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	public function voted_on_post( int $post_id ): array {
		global $wpdb;

		$table = ReactionsTable::name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Per-visitor data, fetched once per session request.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT r.comment_id FROM %i r INNER JOIN %i c ON c.comment_ID = r.comment_id WHERE c.comment_post_ID = %d AND r.voter_hash = %s AND r.reaction = %s',
				$table,
				$wpdb->comments,
				$post_id,
				$this->voter(),
				self::UP
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Remove every vote on a comment (when it is deleted).
	 *
	 * @since 1.0.0
	 *
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	public function delete_for_comment( $comment_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup of a custom table.
		$wpdb->delete( ReactionsTable::name(), array( 'comment_id' => (int) $comment_id ), array( '%d' ) );
	}

	/**
	 * Count a comment's votes from the table and refresh the cached meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int $comment_id Comment ID.
	 * @return int
	 */
	private function recount( int $comment_id ): int {
		global $wpdb;

		$table = ReactionsTable::name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Refreshes the meta cache.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE comment_id = %d AND reaction = %s',
				$table,
				$comment_id,
				self::UP
			)
		);

		update_comment_meta( $comment_id, self::COUNTS_META_KEY, wp_json_encode( array( self::UP => $count ) ) );
		update_comment_meta( $comment_id, Sort::SCORE_META_KEY, $count );

		return $count;
	}
}
