<?php
/**
 * Template: the subscription page (confirm, manage, unsubscribe).
 *
 * Override by copying to `{theme}/convocept/subscription-manage.php`.
 * Forms post back to the same URL; its token authorizes the action.
 *
 * @package Convocept
 * @since   1.0.0
 *
 * @var array $args {
 *     @type string  $state       'confirm', 'manage', 'unsubscribe' or 'invalid'.
 *     @type string  $message     Result of the last action, or empty.
 *     @type string  $email       Address the subscriptions belong to.
 *     @type string  $description What the linked subscription follows.
 *     @type int     $current     ID of the subscription the link was for.
 *     @type array[] $items       All subscriptions of the address: 'id', 'label', 'status', 'url'.
 *     @type bool    $expired     Whether a confirmation link has expired.
 * }
 */

defined( 'ABSPATH' ) || exit;

$convocept_active = array_filter(
	$args['items'],
	static function ( $item ) {
		return 'active' === $item['status'];
	}
);
?>
<section class="convocept-subscriptions">
	<h1><?php esc_html_e( 'Your comment subscriptions', 'convocept' ); ?></h1>

	<?php if ( $args['message'] ) : ?>
		<p class="convocept-subscriptions__message" role="status"><strong><?php echo esc_html( $args['message'] ); ?></strong></p>
	<?php endif; ?>

	<?php if ( 'invalid' === $args['state'] ) : ?>
		<p><?php esc_html_e( 'This link is not valid any more. Use the link in your most recent email, or subscribe again from any post.', 'convocept' ); ?></p>

	<?php elseif ( 'confirm' === $args['state'] ) : ?>
		<?php if ( $args['expired'] ) : ?>
			<p><?php esc_html_e( 'This confirmation link has expired. Please subscribe again.', 'convocept' ); ?></p>
		<?php else : ?>
			<p>
				<?php
				/* translators: 1: What the subscription follows, 2: Email address. */
				echo esc_html( sprintf( __( 'Confirm email notifications for: %1$s, sent to %2$s.', 'convocept' ), $args['description'], $args['email'] ) );
				?>
			</p>
			<form method="post">
				<input type="hidden" name="convocept_do" value="confirm">
				<button type="submit" class="wp-element-button"><?php esc_html_e( 'Confirm subscription', 'convocept' ); ?></button>
			</form>
		<?php endif; ?>

	<?php else : ?>
		<p>
			<?php
			/* translators: %s: Email address. */
			echo esc_html( sprintf( __( 'Subscriptions for %s:', 'convocept' ), $args['email'] ) );
			?>
		</p>

		<ul class="convocept-subscriptions__list">
			<?php foreach ( $args['items'] as $convocept_item ) : ?>
				<li>
					<a href="<?php echo esc_url( $convocept_item['url'] ); ?>"><?php echo esc_html( $convocept_item['label'] ); ?></a>
					&mdash;
					<?php if ( 'active' === $convocept_item['status'] ) : ?>
						<form method="post" style="display:inline">
							<input type="hidden" name="convocept_do" value="unsubscribe">
							<input type="hidden" name="convocept_subscription" value="<?php echo esc_attr( (string) $convocept_item['id'] ); ?>">
							<button type="submit" class="wp-element-button"><?php esc_html_e( 'Unsubscribe', 'convocept' ); ?></button>
						</form>
					<?php elseif ( 'unsubscribed' === $convocept_item['status'] ) : ?>
						<?php esc_html_e( 'Unsubscribed', 'convocept' ); ?>
						<form method="post" style="display:inline">
							<input type="hidden" name="convocept_do" value="resubscribe">
							<input type="hidden" name="convocept_subscription" value="<?php echo esc_attr( (string) $convocept_item['id'] ); ?>">
							<button type="submit" class="wp-element-button"><?php esc_html_e( 'Subscribe again', 'convocept' ); ?></button>
						</form>
					<?php else : ?>
						<?php esc_html_e( 'Waiting for confirmation', 'convocept' ); ?>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $convocept_active ) : ?>
			<form method="post">
				<input type="hidden" name="convocept_do" value="unsubscribe_all">
				<button type="submit" class="wp-element-button"><?php esc_html_e( 'Unsubscribe from everything', 'convocept' ); ?></button>
			</form>
		<?php endif; ?>
	<?php endif; ?>
</section>
