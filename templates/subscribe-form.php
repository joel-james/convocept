<?php
/**
 * Template: "subscribe without commenting" form.
 *
 * Override by copying to `{theme}/convocept/subscribe-form.php`. Works
 * without JavaScript (posts to admin-post.php); the thread script submits
 * it over REST instead when present.
 *
 * @package Convocept
 * @since   1.0.0
 *
 * @var array $args {
 *     @type int    $post_id     Post to follow, or 0 for the whole site.
 *     @type bool   $compact     Whether to render collapsed (in the thread).
 *     @type string $action_url  admin-post.php URL.
 *     @type string $action      admin-post action name.
 *     @type string $redirect_to Where to return after a no-JS submit.
 *     @type string $message     Result message after a no-JS submit, or empty.
 *     @type bool   $is_error    Whether the message is an error.
 * }
 */

defined( 'ABSPATH' ) || exit;

$convocept_label = $args['post_id']
	? __( 'Get new comments on this post by email', 'convocept' )
	: __( 'Get new comments on this site by email', 'convocept' );
?>
<?php if ( $args['compact'] ) : ?>
<details id="convocept-subscribe" class="convocept-subscribe" <?php echo $args['message'] ? 'open' : ''; ?>>
	<summary><?php echo esc_html( $convocept_label ); ?></summary>
<?php else : ?>
<div id="convocept-subscribe" class="convocept-subscribe convocept-subscribe--standalone">
	<p class="convocept-subscribe__title"><strong><?php echo esc_html( $convocept_label ); ?></strong></p>
<?php endif; ?>

	<form class="convocept-subscribe__form" action="<?php echo esc_url( $args['action_url'] ); ?>" method="post" data-convocept-subscribe-form>
		<label class="screen-reader-text" for="convocept-subscribe-email-<?php echo esc_attr( (string) $args['post_id'] ); ?>"><?php esc_html_e( 'Email address', 'convocept' ); ?></label>
		<input id="convocept-subscribe-email-<?php echo esc_attr( (string) $args['post_id'] ); ?>" type="email" name="email" required autocomplete="email" placeholder="<?php esc_attr_e( 'you@example.com', 'convocept' ); ?>">
		<button type="submit" class="convocept-button"><?php esc_html_e( 'Subscribe', 'convocept' ); ?></button>
		<input type="hidden" name="action" value="<?php echo esc_attr( $args['action'] ); ?>">
		<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $args['post_id'] ); ?>">
		<input type="hidden" name="redirect_to" value="<?php echo esc_url( $args['redirect_to'] ); ?>">
		<span class="convocept-hp" aria-hidden="true"><input type="text" name="convocept_hp" value="" tabindex="-1" autocomplete="off"></span>
	</form>
	<p class="convocept-subscribe__message<?php echo $args['is_error'] ? ' is-error' : ''; ?>" data-convocept-subscribe-message role="status"><?php echo esc_html( $args['message'] ); ?></p>
	<p class="convocept-subscribe__note"><?php esc_html_e( 'One email per new comment. Unsubscribe with one click at any time.', 'convocept' ); ?></p>

<?php if ( $args['compact'] ) : ?>
</details>
<?php else : ?>
</div>
<?php endif; ?>
