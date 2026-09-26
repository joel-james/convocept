<?php
/**
 * Classic theme compatibility template.
 *
 * Returned to core's `comments_template` filter in place of the theme's
 * comments.php. Not meant to be overridden: override `thread.php` instead.
 *
 * @package Convocept
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

echo \Convocept\Plugin::instance()->renderer()->render( (int) get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the thread templates.
