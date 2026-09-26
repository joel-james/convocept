<?php
/**
 * Thread value object.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Comments;

use WP_Comment;

defined( 'ABSPATH' ) || exit;

/**
 * One page of a post's comment thread, ready to render.
 *
 * Immutable: built by ThreadQuery, read by the renderer and templates.
 *
 * @since 1.0.0
 */
final class Thread {

	/**
	 * Post the thread belongs to.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $post_id;

	/**
	 * Top-level comments on this page, with replies attached as children.
	 *
	 * @since 1.0.0
	 * @var WP_Comment[]
	 */
	private $comments;

	/**
	 * Sort order used.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $sort;

	/**
	 * Current page, starting at 1.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $page;

	/**
	 * Number of pages of top-level comments.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $pages;

	/**
	 * Number of visible comments on the post, replies included.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $total;

	/**
	 * Create a thread.
	 *
	 * @since 1.0.0
	 *
	 * @param int          $post_id  Post ID.
	 * @param WP_Comment[] $comments Top-level comments with children populated.
	 * @param string       $sort     Sort order used.
	 * @param int          $page     Current page, starting at 1.
	 * @param int          $pages    Total pages of top-level comments (0 when there are none).
	 * @param int          $total    Visible comments on the post, replies included.
	 */
	public function __construct( int $post_id, array $comments, string $sort, int $page, int $pages, int $total ) {
		$this->post_id  = $post_id;
		$this->comments = $comments;
		$this->sort     = $sort;
		$this->page     = $page;
		$this->pages    = $pages;
		$this->total    = $total;
	}

	/**
	 * Get the post ID.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function post_id(): int {
		return $this->post_id;
	}

	/**
	 * Get the top-level comments on this page.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_Comment[]
	 */
	public function comments(): array {
		return $this->comments;
	}

	/**
	 * Get the sort order.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function sort(): string {
		return $this->sort;
	}

	/**
	 * Get the current page, starting at 1.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function page(): int {
		return $this->page;
	}

	/**
	 * Get the number of pages.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function pages(): int {
		return $this->pages;
	}

	/**
	 * Get the number of visible comments, replies included.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function total(): int {
		return $this->total;
	}

	/**
	 * Whether the post has no visible comments at all.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return 0 === $this->total;
	}
}
