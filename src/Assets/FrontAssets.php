<?php
/**
 * Front-end assets.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Assets;

use Convocept\Render\ThreadRenderer;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the thread stylesheet and removes core's comment-reply script.
 *
 * For now the stylesheet is enqueued normally on posts that show the
 * thread. M4 replaces this with the lazy loader (styles and script fetched
 * only when the reader nears the comments, plus a <noscript> fallback).
 *
 * @since 1.0.0
 */
class FrontAssets {

	/**
	 * Style and script handle.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const HANDLE = 'convocept';

	/**
	 * Thread renderer.
	 *
	 * @since 1.0.0
	 * @var ThreadRenderer
	 */
	private $renderer;

	/**
	 * Create the asset service.
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		// Late, after themes enqueue comment-reply at the default priority.
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_comment_reply' ), 100 );

		// Some themes enqueue it from header.php, after wp_enqueue_scripts.
		add_action( 'wp_print_scripts', array( $this, 'dequeue_comment_reply' ), 1 );
		add_action( 'wp_print_footer_scripts', array( $this, 'dequeue_comment_reply' ), 1 );
	}

	/**
	 * Enqueue the stylesheet on singular views that show the thread.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! $this->is_thread_page() ) {
			return;
		}

		$path = CONVOCEPT_DIR . 'assets/dist/convocept.css';

		// A git checkout without `npm run build` has no built file.
		if ( ! is_readable( $path ) ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE,
			CONVOCEPT_URL . 'assets/dist/convocept.css',
			array(),
			CONVOCEPT_VERSION . '-' . (string) filemtime( $path )
		);
		wp_style_add_data( self::HANDLE, 'path', $path );
	}

	/**
	 * Remove core's comment-reply script where Convocept renders comments.
	 *
	 * It only works with core's markup, and Convocept ships its own reply
	 * handling; loading it would add JavaScript to every post for nothing.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function dequeue_comment_reply(): void {
		if ( $this->is_thread_page() ) {
			wp_dequeue_script( 'comment-reply' );
		}
	}

	/**
	 * Whether the current request is a singular view that shows the thread.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function is_thread_page(): bool {
		return is_singular() && $this->renderer->is_enabled_for( (int) get_queried_object_id() );
	}
}
