<?php
/**
 * Block theme integration.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Integration;

use Convocept\Render\ThreadRenderer;
use WP_Block;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces the core comment blocks in block themes.
 *
 * Uses `pre_render_block`, which runs before a block renders, so core's
 * comment blocks never run their own queries. The first matching block on a
 * page shows the thread; any later one (for example a separate comment form
 * block) renders nothing.
 *
 * @since 1.0.0
 */
class BlockTheme {

	/**
	 * Core blocks replaced by the thread.
	 *
	 * - core/comments:           the comments block in block themes.
	 * - core/post-comments:      deprecated legacy block, still in older content.
	 * - core/post-comments-form: the form, when placed outside core/comments.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const BLOCKS = array( 'core/comments', 'core/post-comments', 'core/post-comments-form' );

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
		add_filter( 'pre_render_block', array( $this, 'pre_render' ), 10, 3 );
	}

	/**
	 * Render the thread instead of a core comment block.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null          $pre_render   Short-circuit HTML, or null to render normally.
	 * @param array<string, mixed> $parsed_block Parsed block data.
	 * @param WP_Block|null        $parent_block Parent block, if any.
	 * @return string|null
	 */
	public function pre_render( $pre_render, $parsed_block, $parent_block = null ) {
		if ( null !== $pre_render || ! in_array( $parsed_block['blockName'] ?? '', self::BLOCKS, true ) ) {
			return $pre_render;
		}

		$post_id = $this->post_id( $parent_block );

		if ( ! $post_id || ! $this->renderer->is_enabled_for( $post_id ) ) {
			return $pre_render;
		}

		if ( $this->renderer->has_rendered( $post_id ) ) {
			return '';
		}

		return $this->renderer->render( $post_id );
	}

	/**
	 * Find the post whose comments the block shows.
	 *
	 * Inside a Query Loop the post comes from the block context; otherwise it
	 * is the current post.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Block|null $parent_block Parent block, if any.
	 * @return int
	 */
	private function post_id( $parent_block ): int {
		if ( $parent_block instanceof WP_Block && ! empty( $parent_block->context['postId'] ) ) {
			return (int) $parent_block->context['postId'];
		}

		return (int) get_the_ID();
	}
}
