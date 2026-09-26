<?php
/**
 * Plugin Name:       Convocept
 * Plugin URI:        https://convocept.com
 * Description:       A fast, modern, live-updating interface for native WordPress comments. All data stays in core WordPress tables — fully self-hosted, no external services.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Joel James
 * Author URI:        https://foxelabs.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       convocept
 *
 * @package Convocept
 */

defined( 'ABSPATH' ) || exit;

define( 'CONVOCEPT_VERSION', '1.0.0' );
define( 'CONVOCEPT_FILE', __FILE__ );
define( 'CONVOCEPT_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONVOCEPT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloader for the plugin's namespaced classes.
 *
 * Maps the `Convocept\` namespace onto `includes/`, translating
 * class names into WordPress Coding Standards file names:
 *
 *     Convocept\Plugin           => includes/class-plugin.php
 *     Convocept\EditWindow       => includes/class-edit-window.php
 *     Convocept\Frontend\Assets  => includes/frontend/class-assets.php
 *     Convocept\Rest\Routes      => includes/rest/class-routes.php
 *
 * Sub-namespaces become lowercase directories, and the class name becomes a
 * `class-` prefixed, hyphen-separated, lowercase file name. Registered as a
 * closure so the plugin needs no Composer dependency.
 *
 * @since 1.0.0
 *
 * @param string $class Fully qualified class name being loaded.
 * @return void
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'Convocept\\';

		// Ignore any class that does not belong to this plugin.
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		// Split the namespace-relative name; the last part is the class.
		$parts      = explode( '\\', substr( $class, strlen( $prefix ) ) );
		$class_name = array_pop( $parts );

		// PascalCase to hyphenated lowercase, e.g. "EditWindow" => "edit-window".
		$file_name = 'class-' . strtolower(
			preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $class_name )
		) . '.php';

		// Remaining namespace parts become lowercase sub-directories.
		$sub_dir = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';
		$path    = CONVOCEPT_DIR . 'includes/' . $sub_dir . $file_name;

		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

/**
 * Boot the plugin once all other plugins are loaded.
 *
 * Waiting for `plugins_loaded` means integrations can hook the plugin's
 * filters from their own bootstrap files before anything is registered.
 *
 * @since 1.0.0
 */
add_action(
	'plugins_loaded',
	static function () {
		\Convocept\Plugin::instance()->init();
	}
);
