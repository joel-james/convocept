<?php
/**
 * Comment link integration.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Integration;

use Convocept\Render\ThreadRenderer;
use WP_Comment;

defined( 'ABSPATH' ) || exit;

/**
 * Makes core's comment links point at Convocept's pages.
 *
 * Core builds comment permalinks from its own pagination settings, which
 * Convocept replaces. Filtering get_comment_link() fixes every place that
 * links to a comment: the redirect after a no-JavaScript submission, "View"
 * links in wp-admin, notification emails, feeds and other plugins.
 *
 * @since 1.0.0
 */
class CommentLinks {

	/**
	 * Thread renderer.
	 *
	 * @since 1.0.0
	 * @var ThreadRenderer
	 */
	private $renderer;

	/**
	 * Create the integration.
	 *
	 * @since 1.0.0
	 *
	 * @param ThreadRenderer $renderer Thread renderer.
	 */
	public function __construct( ThreadRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'get_comment_link', array( $this, 'filter_link' ), 10, 2 );
	}

	/**
	 * Replace a comment's permalink with Convocept's.
	 *
	 * Only approved comments on posts Convocept handles are changed; core
	 * adds its own moderation arguments to links for pending comments.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $link    Core's link.
	 * @param WP_Comment $comment Comment.
	 * @return string
	 */
	public function filter_link( $link, $comment ) {
		if ( ! $comment instanceof WP_Comment || '1' !== (string) $comment->comment_approved || ! $this->renderer->is_enabled_for( (int) $comment->comment_post_ID ) ) {
			return $link;
		}

		return $this->renderer->comment_url( $comment );
	}
}
