<?php
/**
 * Email template: confirm a subscription (HTML).
 *
 * Override by copying to `{theme}/convocept/emails/confirm.php`. Keep it
 * simple: many mail apps support only basic HTML and inline styles.
 *
 * @package Convocept
 * @since   1.0.0
 *
 * @var array $args {
 *     @type string $site_name   Site name (plain text).
 *     @type string $description What the subscription follows (plain text).
 *     @type string $confirm_url Confirmation link.
 * }
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
<body style="margin:0;padding:24px;background:#f6f7f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1f2937;">
	<div style="max-width:560px;margin:0 auto;padding:28px;background:#ffffff;border-radius:10px;">
		<p style="margin:0 0 16px;font-size:18px;font-weight:600;"><?php esc_html_e( 'Please confirm your subscription', 'convocept' ); ?></p>
		<p style="margin:0 0 20px;line-height:1.6;">
			<?php
			/* translators: 1: What the subscription follows, 2: Site name. */
			echo esc_html( sprintf( __( 'You asked to get emails about: %1$s on %2$s.', 'convocept' ), $args['description'], $args['site_name'] ) );
			?>
		</p>
		<p style="margin:0 0 24px;">
			<a href="<?php echo esc_url( $args['confirm_url'] ); ?>" style="display:inline-block;padding:12px 20px;background:#2563eb;color:#ffffff;border-radius:8px;text-decoration:none;font-weight:600;"><?php esc_html_e( 'Confirm subscription', 'convocept' ); ?></a>
		</p>
		<p style="margin:0;color:#6b7280;font-size:13px;line-height:1.5;"><?php esc_html_e( 'If you did not ask for this, ignore this email and nothing will be sent. The link works for 48 hours.', 'convocept' ); ?></p>
	</div>
</body>
</html>
