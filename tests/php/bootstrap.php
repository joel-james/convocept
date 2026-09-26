<?php
/**
 * PHPUnit bootstrap: loads the WordPress test suite and the plugin.
 *
 * Inside wp-env, WP_TESTS_DIR points at the bundled test library. Run with
 * `npm run test:php`.
 *
 * @package Convocept
 */

$convocept_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $convocept_tests_dir ) {
	$convocept_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $convocept_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find the WordPress test library in {$convocept_tests_dir}. Run the tests with `npm run test:php`." . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Read by the WordPress test suite.

require_once $convocept_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/convocept.php';
	}
);

require $convocept_tests_dir . '/includes/bootstrap.php';
