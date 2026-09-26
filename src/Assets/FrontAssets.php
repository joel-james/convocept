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
use Convocept\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Delivers Convocept's CSS and JavaScript without a single request on page load.
 *
 * Around the first thread on a page it prints:
 *
 * - the stylesheet, inline, just before the thread. Nothing is added to
 *   <head>, so the content above the comments renders without waiting for
 *   it, the thread is styled with or without JavaScript, and there is no
 *   extra request or layout shift;
 * - the configuration, as an inert JSON block after the thread;
 * - the inline loader (under 1 KB), which fetches the interaction script
 *   only when the reader nears the comments or starts interacting.
 *
 * Everything printed is identical for every visitor, so pages stay
 * cacheable. Core's comment-reply.js is removed where Convocept renders.
 *
 * @since 1.0.0
 */
class FrontAssets {

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
	 * Settings service.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Whether the assets were already printed on this page.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $printed = false;

	/**
	 * Create the asset service.
	 *
	 * @since 1.0.0
	 *
	 * @param ThreadRenderer $renderer    Thread renderer.
	 * @param EditWindow     $edit_window Edit window service.
	 * @param Settings       $settings    Settings service.
	 */
	public function __construct( ThreadRenderer $renderer, EditWindow $edit_window, Settings $settings ) {
		$this->renderer    = $renderer;
		$this->edit_window = $edit_window;
		$this->settings    = $settings;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'convocept_thread_html', array( $this, 'wrap' ) );

		// Late, after themes enqueue comment-reply at the default priority.
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_comment_reply' ), 100 );

		// Some themes enqueue it from header.php, after wp_enqueue_scripts.
		add_action( 'wp_print_scripts', array( $this, 'dequeue_comment_reply' ), 1 );
		add_action( 'wp_print_footer_scripts', array( $this, 'dequeue_comment_reply' ), 1 );
	}

	/**
	 * Add the stylesheet, configuration and loader around the first thread.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html Thread HTML.
	 * @return string
	 */
	public function wrap( $html ) {
		if ( $this->printed || '' === $html ) {
			return $html;
		}

		$this->printed = true;

		return $this->style() . $html . $this->config_block() . $this->loader();
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
			'scripts'    => $this->scripts(),
			'editWindow' => $this->edit_window->minutes(),
			'maxLength'  => (int) Controller::content_arg( true )['maxLength'],
			'poll'       => $this->settings->get( 'live_updates' ) ? (int) $this->settings->get( 'poll_interval' ) : 0,
			'adminUrl'   => esc_url_raw( admin_url() ),
			'i18n'       => array(
				'posting'        => __( 'Posting…', 'convocept' ),
				'posted'         => __( 'Your comment was posted.', 'convocept' ),
				'pending'        => __( 'Your comment is awaiting moderation.', 'convocept' ),
				'failed'         => __( 'Your comment could not be posted.', 'convocept' ),
				'network'        => __( 'Could not reach the site. Check your connection and try again.', 'convocept' ),
				'nothingPreview' => __( 'Nothing to preview yet.', 'convocept' ),
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
				/* translators: %s: Number of new comments. */
				'newComments'    => __( 'New comments: %s', 'convocept' ),
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
		if ( is_singular() && $this->renderer->is_enabled_for( (int) get_queried_object_id() ) ) {
			wp_dequeue_script( 'comment-reply' );
		}
	}

	/**
	 * Get the scripts the loader injects, in order.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Script URLs.
	 */
	private function scripts(): array {
		$main    = 'assets/dist/convocept.js';
		$scripts = is_readable( CONVOCEPT_DIR . $main )
			? array( CONVOCEPT_URL . $main . '?ver=' . rawurlencode( CONVOCEPT_VERSION . '-' . (string) filemtime( CONVOCEPT_DIR . $main ) ) )
			: array();

		/**
		 * Filters the scripts the inline loader injects after Convocept's own.
		 *
		 * Add-ons append their module URL here instead of enqueueing a script
		 * on every page, so they load lazily together with Convocept.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $scripts Script URLs, Convocept's first.
		 */
		$scripts = (array) apply_filters( 'convocept_front_modules', $scripts );

		return array_values( array_filter( array_map( 'esc_url_raw', $scripts ) ) );
	}

	/**
	 * Build the inline stylesheet.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function style(): string {
		$css = $this->read( 'assets/dist/convocept.css' );

		/**
		 * Filters the inline stylesheet.
		 *
		 * Return an empty string for themes that style the thread themselves.
		 *
		 * @since 1.0.0
		 *
		 * @param string $css Minified CSS.
		 */
		$css = (string) apply_filters( 'convocept_inline_css', $css );

		// The CSS must never be able to close its own <style> element.
		$css = str_ireplace( '</style', '', $css );

		return '' === $css ? '' : '<style id="convocept-css">' . $css . '</style>';
	}

	/**
	 * Build the JSON configuration block.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function config_block(): string {
		// Hex-escaping <, >, & and quotes keeps the JSON inert inside <script>.
		$json = wp_json_encode( $this->config(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return '<script type="application/json" id="convocept-config">' . $json . '</script>';
	}

	/**
	 * Build the inline loader.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function loader(): string {
		$js = $this->read( 'assets/dist/loader.js' );

		return '' === $js ? '' : '<script id="convocept-loader">' . $js . '</script>';
	}

	/**
	 * Read a built asset, once per request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $relative Path relative to the plugin folder.
	 * @return string Contents, or empty when the file is missing (unbuilt checkout).
	 */
	private function read( string $relative ): string {
		static $files = array();

		if ( ! isset( $files[ $relative ] ) ) {
			$path = CONVOCEPT_DIR . $relative;

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin file.
			$files[ $relative ] = is_readable( $path ) ? trim( (string) file_get_contents( $path ) ) : '';
		}

		return $files[ $relative ];
	}
}
