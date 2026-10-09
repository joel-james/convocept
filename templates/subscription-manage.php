<?php
/**
 * Template: the subscription page (confirm, manage, unsubscribe).
 *
 * Override by copying to `{theme}/convocept/subscription-manage.php`.
 * Forms post back to the same URL; its token authorizes the action.
 *
 * The page wears the thread's design: the root carries `id="comments"` and
 * the `convocept` class, so the thread's stylesheet (reset, tokens, buttons,
 * colour schemes) applies here too.
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
 *     @type string  $scheme      Colour scheme: 'light', 'dark' or 'system'.
 *     @type string  $accent      Accent colour, as #rrggbb.
 * }
 */

defined( 'ABSPATH' ) || exit;

$convocept_active = array_filter(
	$args['items'],
	static function ( $item ) {
		return 'active' === $item['status'];
	}
);

$convocept_statuses = array(
	'active'       => __( 'Active', 'convocept' ),
	'unsubscribed' => __( 'Unsubscribed', 'convocept' ),
	'pending'      => __( 'Waiting for confirmation', 'convocept' ),
);

$convocept_buttons = array(
	'active'       => array( 'unsubscribe', __( 'Unsubscribe', 'convocept' ) ),
	'unsubscribed' => array( 'resubscribe', __( 'Subscribe again', 'convocept' ) ),
	// This page is only reachable from a link emailed to the address, so it can confirm.
	'pending'      => array( 'resubscribe', __( 'Confirm', 'convocept' ) ),
);
?>
<section
	id="comments"
	class="convocept convocept-subscriptions"
	data-convocept-theme="<?php echo esc_attr( $args['scheme'] ); ?>"
	style="--convocept-accent: <?php echo esc_attr( $args['accent'] ); ?>;"
>
	<header class="convocept-subscriptions__header">
		<h1 class="convocept-subscriptions__title"><?php esc_html_e( 'Your comment subscriptions', 'convocept' ); ?></h1>
		<?php if ( $args['email'] && 'confirm' !== $args['state'] ) : ?>
			<p class="convocept-subscriptions__email"><?php echo esc_html( $args['email'] ); ?></p>
		<?php endif; ?>
	</header>

	<?php if ( $args['message'] ) : ?>
		<p class="convocept-notice convocept-notice--success" role="status"><?php echo esc_html( $args['message'] ); ?></p>
	<?php endif; ?>

	<?php if ( 'invalid' === $args['state'] ) : ?>
		<p class="convocept-subscriptions__text"><?php esc_html_e( 'This link is not valid any more. Use the link in your most recent email, or subscribe again from any post.', 'convocept' ); ?></p>

	<?php elseif ( 'confirm' === $args['state'] ) : ?>
		<?php if ( $args['expired'] ) : ?>
			<p class="convocept-subscriptions__text"><?php esc_html_e( 'This confirmation link has expired. Please subscribe again.', 'convocept' ); ?></p>
		<?php else : ?>
			<p class="convocept-subscriptions__text">
				<?php
				/* translators: 1: What the subscription follows, 2: Email address. */
				echo esc_html( sprintf( __( 'Confirm email notifications for: %1$s, sent to %2$s.', 'convocept' ), $args['description'], $args['email'] ) );
				?>
			</p>
			<form method="post">
				<input type="hidden" name="convocept_do" value="confirm">
				<button type="submit" class="convocept-button"><?php esc_html_e( 'Confirm subscription', 'convocept' ); ?></button>
			</form>
		<?php endif; ?>

	<?php else : ?>
		<ul class="convocept-subscriptions__list">
			<?php foreach ( $args['items'] as $convocept_item ) : ?>
				<?php
				$convocept_status = isset( $convocept_statuses[ $convocept_item['status'] ] ) ? $convocept_item['status'] : 'pending';
				$convocept_button = $convocept_buttons[ $convocept_status ];
				?>
				<li class="convocept-subscriptions__item">
					<div class="convocept-subscriptions__info">
						<a class="convocept-subscriptions__link" href="<?php echo esc_url( $convocept_item['url'] ); ?>"><?php echo esc_html( $convocept_item['label'] ); ?></a>
						<span class="convocept-subscriptions__status is-<?php echo esc_attr( $convocept_status ); ?>"><?php echo esc_html( $convocept_statuses[ $convocept_status ] ); ?></span>
					</div>
					<form method="post">
						<input type="hidden" name="convocept_do" value="<?php echo esc_attr( $convocept_button[0] ); ?>">
						<input type="hidden" name="convocept_subscription" value="<?php echo esc_attr( (string) $convocept_item['id'] ); ?>">
						<button type="submit" class="convocept-button convocept-button--secondary"><?php echo esc_html( $convocept_button[1] ); ?></button>
					</form>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $convocept_active ) : ?>
			<form method="post" class="convocept-subscriptions__footer">
				<input type="hidden" name="convocept_do" value="unsubscribe_all">
				<button type="submit" class="convocept-subscriptions__all"><?php esc_html_e( 'Unsubscribe from everything', 'convocept' ); ?></button>
			</form>
		<?php endif; ?>
	<?php endif; ?>
</section>
