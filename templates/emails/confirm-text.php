<?php
/**
 * Email template: confirm a subscription (plain text).
 *
 * Override by copying to `{theme}/convocept/emails/confirm-text.php`.
 * Plain-text emails are not HTML: values are printed as text, never as
 * markup, and every value here is already plain text.
 *
 * @package Convocept
 * @since   1.0.0
 *
 * @var array $args See emails/confirm.php.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text email body; values are plain text.
echo __( 'Please confirm your subscription', 'convocept' ) . "\n\n";
/* translators: 1: What the subscription follows, 2: Site name. */
echo sprintf( __( 'You asked to get emails about: %1$s on %2$s.', 'convocept' ), $args['description'], $args['site_name'] ) . "\n\n";
echo __( 'Confirm here:', 'convocept' ) . "\n" . esc_url_raw( $args['confirm_url'] ) . "\n\n";
echo __( 'If you did not ask for this, ignore this email and nothing will be sent. The link works for 48 hours.', 'convocept' ) . "\n";
// phpcs:enable
