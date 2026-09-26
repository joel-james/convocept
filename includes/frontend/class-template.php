<?php
/**
 * Classic (non-block) theme integration.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Frontend;

use Convocept\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Substitutes a wrapper template around the theme's comments template.
 *
 * Rather than replacing the theme's markup, the wrapper includes the
 * theme's own comments template untouched inside a hidden container. With
 * JavaScript disabled — or if the app fails to mount — the native thread
 * and form render and submit exactly as the theme intended, including the
 * usual post to wp-comments-post.php.
 *
 * The React app hides that markup only after a successful mount, and
 * reveals it again if loading the thread fails.
 *
 * @since 1.0.0
 */
class Template {

	/**
	 * Instance that rendered the wrapper currently being included.
	 *
	 * The wrapper is a plain template file with no access to this object,
	 * so it looks the active instance up through current().
	 *
	 * @since 1.0.0
	 * @var Template|null
	 */
	private static $current = null;

	/**
	 * Assets service.
	 *
	 * @since 1.0.0
	 * @var Assets
	 */
	private $assets;

	/**
	 * Absolute path of the theme's original comments template.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $theme_template = '';

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
		// Late priority so themes and other plugins pick their template first
		// and we wrap whatever they settled on.
		add_filter( 'comments_template', array( $this, 'wrap_template' ), 100 );
	}

	/**
	 * Swap the theme's comments template for the plugin wrapper.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template Path of the comments template to load.
	 * @return string Path of the wrapper, or the original path when inactive.
	 */
	public function wrap_template( $template ) {
		// Block themes render comments through the core/comments block
		// instead of this template; the Blocks class handles those.
		if ( wp_is_block_theme() || ! Plugin::instance()->enabled_for() ) {
			return $template;
		}

		// Remember what to include, and expose this instance to the wrapper.
		$this->theme_template = $template;
		$this->set_current();
		$this->assets->need();

		return CONVOCEPT_DIR . 'includes/views/comments-wrapper.php';
	}

	/**
	 * Get the theme's original comments template path.
	 *
	 * Consumed by the wrapper view to include the native markup.
	 *
	 * @since 1.0.0
	 *
	 * @return string Absolute template path, or an empty string.
	 */
	public function theme_template() {
		return $this->theme_template;
	}

	/**
	 * Get the instance that rendered the wrapper being included.
	 *
	 * @since 1.0.0
	 *
	 * @return Template|null Active instance, or null outside the wrapper.
	 */
	public static function current() {
		return self::$current;
	}

	/**
	 * Mark this instance as the one rendering the current wrapper.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function set_current() {
		self::$current = $this;
	}
}
