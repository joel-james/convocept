<?php
/**
 * Comment sort orders.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Comments;

defined( 'ABSPATH' ) || exit;

/**
 * Knows the valid sort orders and turns them into query arguments.
 *
 * The sort is part of the URL (`?convocept_sort=newest`) rather than a
 * cookie, so every sort order is a separate, cacheable, crawlable page.
 *
 * @since 1.0.0
 */
final class Sort {

	/**
	 * Oldest comments first (conversation order, like core).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OLDEST = 'oldest';

	/**
	 * Newest comments first.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NEWEST = 'newest';

	/**
	 * Highest score first, then oldest. Comments without a score rank last.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TOP = 'top';

	/**
	 * Public query variable carrying the requested sort.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const QUERY_VAR = 'convocept_sort';

	/**
	 * Comment meta key holding a comment's score, used by the "top" sort.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SCORE_META_KEY = 'convocept_score';

	/**
	 * WP_Comment_Query variable marking a query sorted by score.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TOP_QUERY_VAR = 'convocept_top';

	/**
	 * Register the query clause filter for the "top" sort.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'comments_clauses', array( self::class, 'top_clauses' ), 10, 2 );
	}

	/**
	 * Order a "top" query by score, highest first; unscored comments last.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $clauses SQL clauses.
	 * @param \WP_Comment_Query     $query   The query.
	 * @return array<string, string>
	 */
	public static function top_clauses( $clauses, $query ) {
		global $wpdb;

		if ( ! $query instanceof \WP_Comment_Query || empty( $query->query_vars[ self::TOP_QUERY_VAR ] ) || ! empty( $query->query_vars['count'] ) ) {
			return $clauses;
		}

		$clauses['join'] .= $wpdb->prepare(
			" LEFT JOIN {$wpdb->commentmeta} AS convocept_score ON ( convocept_score.comment_id = {$wpdb->comments}.comment_ID AND convocept_score.meta_key = %s )",
			self::SCORE_META_KEY
		);

		// COALESCE puts comments without a score at 0, below every upvoted comment.
		$score = 'COALESCE( CAST( convocept_score.meta_value AS SIGNED ), 0 ) DESC';

		$clauses['orderby'] = '' === trim( (string) $clauses['orderby'] ) ? $score : $score . ', ' . $clauses['orderby'];

		return $clauses;
	}

	/**
	 * Get every valid sort order.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::OLDEST, self::NEWEST, self::TOP );
	}

	/**
	 * Resolve a requested sort against the allow-list.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $requested Untrusted value, e.g. from the query string.
	 * @param string $fallback  Sort to use when the request is empty or invalid.
	 * @return string A valid sort order.
	 */
	public static function resolve( $requested, string $fallback ): string {
		if ( is_string( $requested ) && in_array( $requested, self::all(), true ) ) {
			return $requested;
		}

		return in_array( $fallback, self::all(), true ) ? $fallback : self::OLDEST;
	}

	/**
	 * Get the WP_Comment_Query ordering arguments for a sort.
	 *
	 * Every order ends with the comment ID as a tie-breaker so pagination is
	 * stable when several comments share a timestamp or score.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sort A valid sort order.
	 * @return array<string, mixed> Arguments to merge into a WP_Comment_Query.
	 */
	public static function query_args( string $sort ): array {
		switch ( $sort ) {
			case self::NEWEST:
				return array(
					'orderby' => array(
						'comment_date_gmt' => 'DESC',
						'comment_ID'       => 'DESC',
					),
				);

			case self::TOP:
				// Ordering by score is added in top_clauses(): a key-restricted
				// LEFT JOIN, so comments without a score (and comments with any
				// other meta) sort correctly.
				return array(
					self::TOP_QUERY_VAR => true,
					// Core's query cache keys only include core query vars; a
					// distinct cache_domain keeps this from reusing "oldest" results.
					'cache_domain'      => 'convocept_top',
					'orderby'           => array(
						'comment_date_gmt' => 'ASC',
						'comment_ID'       => 'ASC',
					),
				);

			default:
				return array(
					'orderby' => array(
						'comment_date_gmt' => 'ASC',
						'comment_ID'       => 'ASC',
					),
				);
		}//end switch
	}
}
