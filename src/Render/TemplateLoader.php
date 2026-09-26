<?php
/**
 * Template loader.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Render;

defined( 'ABSPATH' ) || exit;

/**
 * Finds and renders theme-overridable PHP templates.
 *
 * A theme overrides a template by copying it from the plugin's `templates/`
 * folder to `{theme}/convocept/{name}.php`. The child theme wins over the
 * parent theme, which wins over the plugin.
 *
 * @since 1.0.0
 */
class TemplateLoader {

	/**
	 * Folder inside a theme that holds template overrides.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const THEME_FOLDER = 'convocept';

	/**
	 * Resolved template paths, keyed by template name, for this request.
	 *
	 * Rendering a thread loads comment.php once per comment, so the file
	 * lookups are cached.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	private $paths = array();

	/**
	 * Find the file for a template.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Template name without extension, e.g. 'comment'.
	 * @return string Absolute path, or an empty string when no file exists.
	 */
	public function locate( string $name ): string {
		$name = $this->normalize( $name );

		if ( isset( $this->paths[ $name ] ) ) {
			return $this->paths[ $name ];
		}

		// locate_template() checks the child theme, then the parent theme.
		$path = locate_template( self::THEME_FOLDER . '/' . $name . '.php' );

		if ( ! $path ) {
			$plugin_path = CONVOCEPT_DIR . 'templates/' . $name . '.php';
			$path        = is_readable( $plugin_path ) ? $plugin_path : '';
		}

		/**
		 * Filters the resolved path of a Convocept template.
		 *
		 * @since 1.0.0
		 *
		 * @param string $path Absolute template path, or empty when none was found.
		 * @param string $name Template name, e.g. 'comment'.
		 */
		$path = (string) apply_filters( 'convocept_locate_template', $path, $name );

		$this->paths[ $name ] = $path;

		return $path;
	}

	/**
	 * Render a template and return its HTML.
	 *
	 * Templates receive their data as `$args` (the same convention as core's
	 * get_template_part()), and are responsible for escaping everything they
	 * print.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $name Template name without extension.
	 * @param array<string, mixed> $args Data passed to the template as `$args`.
	 * @return string Rendered HTML, or an empty string when the template is missing.
	 */
	public function render( string $name, array $args = array() ): string {
		$path = $this->locate( $name );

		if ( '' === $path ) {
			return '';
		}

		ob_start();
		load_template( $path, false, $args );

		return (string) ob_get_clean();
	}

	/**
	 * Reduce a template name to safe path characters.
	 *
	 * Names come from our own code, but add-ons can pass names through
	 * filters, so path traversal is ruled out here.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Template name.
	 * @return string
	 */
	private function normalize( string $name ): string {
		return trim( (string) preg_replace( '/[^a-z0-9\/_-]|\/{2,}/', '', strtolower( str_replace( '..', '', $name ) ) ), '/' );
	}
}
