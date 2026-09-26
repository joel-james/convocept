<?php
/**
 * Thread query.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Comments;

use WP_Comment;
use WP_Comment_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Loads one page of a post's comment thread.
 *
 * Pagination counts top-level comments only, so a page never splits a
 * conversation. Replies are loaded with core's hierarchical query, which
 * fetches one level per query (not one query per comment) and primes the
 * comment and meta caches.
 *
 * Only approved comments are ever returned. Showing a visitor their own
 * pending comment depends on who is looking, which must never end up in
 * page-cached HTML; that happens client-side (M2).
 *
 * @since 1.0.0
 */
class ThreadQuery {

	/**
	 * Load a page of a post's thread.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $args {
	 *     Query options.
	 *
	 *     @type string $sort     A valid sort order (see Sort). Default 'oldest'.
	 *     @type int    $page     Page to load, starting at 1. Values past the last
	 *                            page load the last page. Default 1.
	 *     @type int    $per_page Top-level comments per page. Default 20.
	 * }
	 * @return Thread
	 */
	public function run( int $post_id, array $args = array() ): Thread {
		$sort     = Sort::resolve( $args['sort'] ?? '', Sort::OLDEST );
		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );

		$base = array(
			'post_id'            => $post_id,
			'status'             => 'approve',
			'type__not_in'       => $this->excluded_types(),
			// Explicitly empty: never include a visitor's own pending comments.
			'include_unapproved' => array(),
		);

		$total = $this->count( $base );

		if ( 0 === $total ) {
			return new Thread( $post_id, array(), $sort, 1, 0, 0 );
		}

		$top_level_total = $this->count( array_merge( $base, array( 'parent' => 0 ) ) );
		$pages           = (int) ceil( $top_level_total / $per_page );
		$page            = min( $page, max( 1, $pages ) );

		$query_args = array_merge(
			$base,
			Sort::query_args( $sort ),
			array(
				'parent'                    => 0,
				'number'                    => $per_page,
				'offset'                    => ( $page - 1 ) * $per_page,
				'hierarchical'              => 'threaded',
				'no_found_rows'             => true,
				'update_comment_meta_cache' => true,
			)
		);

		/**
		 * Filters the WP_Comment_Query arguments for a thread page.
		 *
		 * Runs for the top-level query; core reuses the same arguments to
		 * fetch each level of replies.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $query_args WP_Comment_Query arguments.
		 * @param int                  $post_id    Post ID.
		 * @param string               $sort       Sort order.
		 * @param int                  $page       Page being loaded.
		 */
		$query_args = (array) apply_filters( 'convocept_thread_query_args', $query_args, $post_id, $sort, $page );

		$comments = ( new WP_Comment_Query( $query_args ) )->comments;
		$comments = is_array( $comments ) ? array_values( array_filter( $comments, array( $this, 'is_comment' ) ) ) : array();

		return new Thread( $post_id, $comments, $sort, $page, $pages, $total );
	}

	/**
	 * Count the visible comments on a post, replies included.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public function total( int $post_id ): int {
		return $this->count( $this->base_args( $post_id ) );
	}

	/**
	 * Get visible comments newer than a given comment.
	 *
	 * WP_Comment_Query cannot filter "ID greater than", so the query is
	 * narrowed to comments dated at or after the reference comment (an
	 * indexed range) and the IDs are checked in PHP.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @param int $after   Newest comment ID the browser already has, or 0.
	 * @param int $limit   Maximum number returned.
	 * @return \WP_Comment[] Oldest first.
	 */
	public function newer_than( int $post_id, int $after, int $limit = 50 ): array {
		$args = array_merge(
			$this->base_args( $post_id ),
			array(
				'orderby'       => 'comment_ID',
				'order'         => 'ASC',
				'number'        => $limit + 1,
				'no_found_rows' => true,
			)
		);

		$reference = $after ? get_comment( $after ) : null;

		if ( $reference instanceof \WP_Comment ) {
			$args['date_query'] = array(
				array(
					'column'    => 'comment_date_gmt',
					'after'     => $reference->comment_date_gmt,
					'inclusive' => true,
				),
			);
		}

		$comments = ( new WP_Comment_Query() )->query( $args );

		$newer = array_filter(
			is_array( $comments ) ? $comments : array(),
			static function ( $comment ) use ( $after ) {
				return $comment instanceof \WP_Comment && (int) $comment->comment_ID > $after;
			}
		);

		return array_slice( array_values( $newer ), 0, $limit );
	}

	/**
	 * Get the ID of the newest visible comment on a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return int 0 when there are none.
	 */
	public function latest_id( int $post_id ): int {
		$ids = ( new WP_Comment_Query() )->query(
			array_merge(
				$this->base_args( $post_id ),
				array(
					'orderby'       => 'comment_ID',
					'order'         => 'DESC',
					'number'        => 1,
					'fields'        => 'ids',
					'no_found_rows' => true,
				)
			)
		);

		return $ids ? (int) reset( $ids ) : 0;
	}

	/**
	 * Get the zero-based position of a top-level comment in a sort order.
	 *
	 * Loads only the IDs of the post's top-level comments (one indexed
	 * query, cached by core's comment query cache until comments change),
	 * so it is cheap even on large threads.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id    Post ID.
	 * @param int    $comment_id Top-level comment ID.
	 * @param string $sort       Sort order.
	 * @return int|null Position, or null when the comment is not a visible top-level comment.
	 */
	public function position( int $post_id, int $comment_id, string $sort ): ?int {
		$ids = ( new WP_Comment_Query() )->query(
			array_merge(
				$this->base_args( $post_id ),
				Sort::query_args( Sort::resolve( $sort, Sort::OLDEST ) ),
				array(
					'parent'                    => 0,
					'fields'                    => 'ids',
					'no_found_rows'             => true,
					'update_comment_meta_cache' => false,
				)
			)
		);

		$index = array_search( $comment_id, array_map( 'intval', (array) $ids ), true );

		return false === $index ? null : (int) $index;
	}

	/**
	 * Get the query arguments every thread query shares.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private function base_args( int $post_id ): array {
		return array(
			'post_id'            => $post_id,
			'status'             => 'approve',
			'type__not_in'       => $this->excluded_types(),
			// Explicitly empty: never include a visitor's own pending comments.
			'include_unapproved' => array(),
		);
	}

	/**
	 * Get comment types that are never shown in the thread.
	 *
	 * Pingbacks and trackbacks are excluded by default: they are not part of
	 * the conversation and are rarely wanted in a modern comment UI.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public function excluded_types(): array {
		/**
		 * Filters the comment types excluded from the thread.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $types Comment types. Default pingback and trackback.
		 */
		return (array) apply_filters( 'convocept_excluded_comment_types', array( 'pingback', 'trackback' ) );
	}

	/**
	 * Count comments matching query arguments.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args WP_Comment_Query arguments.
	 * @return int
	 */
	private function count( array $args ): int {
		$args['count'] = true;

		return (int) ( new WP_Comment_Query() )->query( $args );
	}

	/**
	 * Whether a value is a comment object.
	 *
	 * Guards against filters returning unexpected data.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	private function is_comment( $value ): bool {
		return $value instanceof WP_Comment;
	}
}
