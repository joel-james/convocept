<?php
/**
 * Plugin Name:       Convocept
 * Plugin URI:        https://convocept.com
 * Description:       The fastest comment system for WordPress. Server-rendered, zero JavaScript until readers reach the comments, and every comment stays in your own database.
 * Version:           1.0.0-dev
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Foxe Labs
 * Author URI:        https://foxelabs.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       convocept
 *
 * @package Convocept
 */

defined( 'ABSPATH' ) || exit;

define( 'CONVOCEPT_VERSION', '1.0.0-dev' );
define( 'CONVOCEPT_FILE', __FILE__ );
define( 'CONVOCEPT_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONVOCEPT_URL', plugin_dir_url( __FILE__ ) );

/*
 * Composer generates the PSR-4 autoloader. Release zips ship it with an
 * authoritative classmap and no third-party code. A git checkout without
 * `composer install` has no autoloader, so show a notice instead of fataling.
 */
if ( ! is_readable( CONVOCEPT_DIR . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Convocept is missing its autoloader. Run "composer install" in the plugin folder, or install a release build.', 'convocept' )
			);
		}
	);

	return;
}

require CONVOCEPT_DIR . 'vendor/autoload.php';

register_activation_hook( __FILE__, array( \Convocept\Lifecycle::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Convocept\Lifecycle::class, 'deactivate' ) );

/*
 * Boot once all plugins are loaded, so add-ons can hook our filters from
 * their own bootstrap files before anything is registered.
 */
add_action(
	'plugins_loaded',
	static function () {
		\Convocept\Plugin::instance()->boot();
	}
);
