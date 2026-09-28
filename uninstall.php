<?php
/**
 * Uninstall routine.
 *
 * Comments are the site's data and always stay. See Convocept\Uninstaller
 * for what is removed, and when.
 *
 * @package Convocept
 * @since   1.0.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

( new Convocept\Uninstaller() )->run();
