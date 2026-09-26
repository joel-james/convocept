<?php
/**
 * Front-end assets.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Assets;

use Convocept\Comments\EditWindow;
use Convocept\Render\ThreadRenderer;
use Convocept\Rest\Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the thread stylesheet and script, and removes core's comment-reply.
 *
 * For now both files are enqueued on posts that show the thread (the
 * script deferred, in the footer). M4 replaces this with the inline lazy
 * loader, so nothing is fetched until the reader nears the comments.
 *
 * The inline configuration is identical for every visitor, so pages stay
 * cacheable; per-visitor state comes from the session endpoint.
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
	 * Edit window service.
	 *
	 * @since 1.0.0
	 * @var EditWindow
	 */
	private $edit_window;

	/**
	 * Create the asset service.
	 *
	 * @since 1.0.0
	 *
	 * @param ThreadRenderer $renderer    Thread renderer.
	 * @param EditWindow     $edit_window Edit window service.
	 */
	public function __construct( ThreadRenderer $renderer, EditWindow $edit_window ) {
		$this->renderer    = $renderer;
		$this->edit_window = $edit_window;
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
	 * Enqueue the stylesheet and script on singular views that show the thread.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! $this->is_thread_page() ) {
			return;
		}

		$css = CONVOCEPT_DIR . 'assets/dist/convocept.css';
		$js  = CONVOCEPT_DIR . 'assets/dist/convocept.js';

		// A git checkout without `npm run build` has no built files.
		if ( is_readable( $css ) ) {
			wp_enqueue_style( self::HANDLE, CONVOCEPT_URL . 'assets/dist/convocept.css', array(), $this->version( $css ) );

			// Lets core inline the small stylesheet instead of making a request.
			wp_style_add_data( self::HANDLE, 'path', $css );
		}

		if ( is_readable( $js ) ) {
			wp_enqueue_script(
				self::HANDLE,
				CONVOCEPT_URL . 'assets/dist/convocept.js',
				array(),
				$this->version( $js ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);

			wp_add_inline_script( self::HANDLE, 'window.convoceptConfig = ' . wp_json_encode( $this->config() ) . ';', 'before' );
		}
	}

	/**
	 * Get the configuration passed to the front-end script.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function config(): array {
		$config = array(
			'rest'       => esc_url_raw( rest_url( Controller::NAMESPACE . '/' ) ),
			'editWindow' => $this->edit_window->minutes(),
			'maxLength'  => (int) Controller::content_arg( true )['maxLength'],
			'i18n'       => array(
				'posting'        => __( 'Posting…', 'convocept' ),
				'posted'         => __( 'Your comment was posted.', 'convocept' ),
				'pending'        => __( 'Your comment is awaiting moderation.', 'convocept' ),
				'failed'         => __( 'Your comment could not be posted.', 'convocept' ),
				'retry'          => __( 'Try again', 'convocept' ),
				'network'        => __( 'Could not reach the site. Check your connection and try again.', 'convocept' ),
				'write'          => __( 'Write', 'convocept' ),
				'preview'        => __( 'Preview', 'convocept' ),
				'nothingPreview' => __( 'Nothing to preview yet.', 'convocept' ),
				'edit'           => __( 'Edit', 'convocept' ),
				'save'           => __( 'Save', 'convocept' ),
				'cancel'         => __( 'Cancel', 'convocept' ),
				'saved'          => __( 'Your changes were saved.', 'convocept' ),
				/* translators: %s: Remaining time, e.g. 4:59. */
				'editFor'        => __( 'Edit (%s)', 'convocept' ),
				/* translators: %s: Display name. */
				'commentingAs'   => __( 'Commenting as %s', 'convocept' ),
				/* translators: %s: Number of characters left. */
				'charactersLeft' => __( '%s characters left', 'convocept' ),
				/* translators: %s: Number of comments. */
				'countOne'       => __( '%s comment', 'convocept' ),
				/* translators: %s: Number of comments. */
				'countMany'      => __( '%s comments', 'convocept' ),
				'linkPrompt'     => __( 'Link address (https://…)', 'convocept' ),
				/* translators: %s: Name of the person being replied to. */
				'replyingTo'     => __( 'Replying to %s', 'convocept' ),
				/* translators: %s: Number of hidden replies (one). */
				'moreReply'      => __( 'Show %s more reply', 'convocept' ),
				/* translators: %s: Number of hidden replies. */
				'moreReplies'    => __( 'Show %s more replies', 'convocept' ),
			),
		);

		/**
		 * Filters the configuration passed to the front-end script.
		 *
		 * Must not contain anything specific to the current visitor: it is
		 * printed into pages that may be served from a full-page cache.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $config Configuration.
		 */
		return (array) apply_filters( 'convocept_front_config', $config );
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
	 * Build a cache-busting version string for a built file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute file path.
	 * @return string
	 */
	private function version( string $path ): string {
		return CONVOCEPT_VERSION . '-' . (string) filemtime( $path );
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
