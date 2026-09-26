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
				// The OR clause keeps comments that have no score yet; they
				// sort as NULL, which MySQL places last in descending order.
				return array(
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Top sort needs the score; indexed by comment_id.
						'relation' => 'OR',
						'score'    => array(
							'key'  => self::SCORE_META_KEY,
							'type' => 'NUMERIC',
						),
						'no_score' => array(
							'key'     => self::SCORE_META_KEY,
							'compare' => 'NOT EXISTS',
						),
					),
					'orderby'    => array(
						'score'            => 'DESC',
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
