<?php
/**
 * Email template: new comment notification (plain text).
 *
 * Override by copying to `{theme}/convocept/emails/notification-text.php`.
 *
 * @package Convocept
 * @since   1.0.0
 *
 * @var array $args See emails/notification.php.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text email body; values are plain text.
echo (
	$args['is_reply']
		/* translators: 1: Comment author, 2: Post title. */
		? sprintf( __( '%1$s replied to your comment on “%2$s”:', 'convocept' ), $args['author'], $args['post_title'] )
		/* translators: 1: Comment author, 2: Post title. */
		: sprintf( __( '%1$s commented on “%2$s”:', 'convocept' ), $args['author'], $args['post_title'] )
) . "\n\n";
echo $args['excerpt'] . "\n\n";
echo __( 'Read and reply:', 'convocept' ) . "\n" . esc_url_raw( $args['comment_url'] ) . "\n\n";
echo "--\n";
echo __( 'Unsubscribe:', 'convocept' ) . ' ' . esc_url_raw( $args['unsubscribe_url'] ) . "\n";
echo __( 'Manage subscriptions:', 'convocept' ) . ' ' . esc_url_raw( $args['manage_url'] ) . "\n";
// phpcs:enable
