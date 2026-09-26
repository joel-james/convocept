<?php
/**
 * Block theme integration.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Frontend;

use Convocept\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps the rendered core/comments block so the app can mount over it.
 *
 * Block themes render comments through the Comments block rather than a
 * comments.php template, so this is the block-theme counterpart to the
 * Template class. The block's own output is preserved inside the wrapper
 * as the no-JS fallback and is hidden only after the app mounts.
 *
 * @since 1.0.0
 */
class Blocks {

	/**
	 * Assets service.
	 *
	 * @since 1.0.0
	 * @var Assets
	 */
	private $assets;

	/**
	 * Whether the wrapper has already been output for this request.
	 *
	 * The render_block filter can fire more than once for the same comments
	 * block; without this guard the wrapper would nest inside itself and the
	 * app would mount twice.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $wrapped = false;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Assets $assets Assets service.
	 */
	public function __construct( Assets $assets ) {
		$this->assets = $assets;
	}

	/**
	 * Register the hooks this service needs.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'render_block_core/comments', array( $this, 'wrap_block' ), 100, 2 );
	}

	/**
	 * Wrap the comments block output in the app's mount container.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Rendered block content.
	 * @param array  $block   Parsed block, unused but supplied by the filter.
	 * @return string Original content, or the content wrapped for the app.
	 */
	public function wrap_block( $content, $block ) {
		// Skip when already wrapped, off the frontend, on a non-singular view,
		// disabled for this post, or when the block rendered nothing at all.
		if ( $this->wrapped
			|| is_admin()
			|| ! is_singular()
			|| ! Plugin::instance()->enabled_for()
			|| '' === trim( $content )
		) {
			return $content;
		}

		$this->wrapped = true;
		$this->assets->need();

		// The mount point carries the block's own class so theme rules for
		// the comments block (spacing, typography) apply to the app too.
		return '<div class="convocept-comments" data-convocept-app>'
			. '<div class="wp-block-comments convocept-app" data-convocept-mount></div>'
			. '<div class="convocept-native" data-convocept-native>' . $content . '</div>'
			. '</div>';
	}
}
