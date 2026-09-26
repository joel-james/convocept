<?php
/**
 * Constants PHPStan needs to analyse the plugin outside WordPress.
 *
 * @package Convocept
 */

define( 'CONVOCEPT_VERSION', '1.0.0-dev' );
define( 'CONVOCEPT_FILE', __DIR__ . '/../../convocept.php' );
define( 'CONVOCEPT_DIR', dirname( __DIR__, 2 ) . '/' );
define( 'CONVOCEPT_URL', 'https://example.org/wp-content/plugins/convocept/' );
define( 'WP_UNINSTALL_PLUGIN', 'convocept/convocept.php' );
