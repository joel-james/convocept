<?php
/**
 * Uninstall routine.
 *
 * Removes ONLY data this plugin created: the settings option and `_convocept_*`
 * commentmeta. Comments themselves are the site's data and are never touched.
 *
 * @package Convocept
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'convocept_settings' );

delete_metadata( 'comment', 0, '_convocept_edited', '', true );
