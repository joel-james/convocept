<?php
/**
 * Email template: new comment notification (HTML).
 *
 * Override by copying to `{theme}/convocept/emails/notification.php`.
 *
 * @package Convocept
 * @since   1.0.0
 *
 * @var array $args {
 *     @type string $site_name       Site name (plain text).
 *     @type string $post_title      Post title (plain text).
 *     @type string $author          Comment author (plain text).
 *     @type bool   $is_reply        Whether it replies to the recipient's comment.
 *     @type string $excerpt         Comment text (plain text).
 *     @type string $comment_url     Link to the comment.
 *     @type string $unsubscribe_url One-click unsubscribe link.
 *     @type string $manage_url      Link to manage all subscriptions.
 * }
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
<body style="margin:0;padding:24px;background:#f6f7f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1f2937;">
	<div style="max-width:560px;margin:0 auto;padding:28px;background:#ffffff;border-radius:10px;">
		<p style="margin:0 0 16px;font-size:16px;line-height:1.5;">
			<?php
			echo esc_html(
				$args['is_reply']
					/* translators: 1: Comment author, 2: Post title. */
					? sprintf( __( '%1$s replied to your comment on “%2$s”:', 'convocept' ), $args['author'], $args['post_title'] )
					/* translators: 1: Comment author, 2: Post title. */
					: sprintf( __( '%1$s commented on “%2$s”:', 'convocept' ), $args['author'], $args['post_title'] )
			);
			?>
		</p>
		<blockquote style="margin:0 0 24px;padding:12px 16px;border-<?php echo is_rtl() ? 'right' : 'left'; ?>:3px solid #d1d5db;color:#374151;line-height:1.6;"><?php echo esc_html( $args['excerpt'] ); ?></blockquote>
		<p style="margin:0 0 28px;">
			<a href="<?php echo esc_url( $args['comment_url'] ); ?>" style="display:inline-block;padding:12px 20px;background:#2563eb;color:#ffffff;border-radius:8px;text-decoration:none;font-weight:600;"><?php esc_html_e( 'Read and reply', 'convocept' ); ?></a>
		</p>
		<p style="margin:0;color:#6b7280;font-size:13px;line-height:1.5;">
			<?php
			/* translators: %s: Site name. */
			echo esc_html( sprintf( __( 'You get this because you subscribed on %s.', 'convocept' ), $args['site_name'] ) );
			?>
			<a href="<?php echo esc_url( $args['unsubscribe_url'] ); ?>" style="color:#6b7280;"><?php esc_html_e( 'Unsubscribe', 'convocept' ); ?></a>
			&middot;
			<a href="<?php echo esc_url( $args['manage_url'] ); ?>" style="color:#6b7280;"><?php esc_html_e( 'Manage subscriptions', 'convocept' ); ?></a>
		</p>
	</div>
</body>
</html>
