<?php
/**
 * Template: the comment form.
 *
 * Override by copying to `{theme}/convocept/composer.php`.
 *
 * A plain HTML form that posts to wp-comments-post.php, so commenting works
 * with JavaScript disabled. It deliberately prints nothing that depends on
 * the visitor (no "logged in as", no prefilled name or email, no nonces),
 * because the page may be served from a full-page cache. The front-end
 * script fills those in after it loads (M2).
 *
 * Core form hooks (`comment_form_before`, `comment_form_top`,
 * `comment_form_field_comment`, `comment_form`, `comment_form_after`) are
 * fired so anti-spam plugins such as Akismet and Antispam Bee keep working.
 *
 * Keep the field names and the `commentform` / `respond` IDs: core, anti-spam
 * plugins and the front-end script depend on them.
 *
 * @package Convocept
 * @since   1.0.0
 *
 * @var array $args {
 *     @type int                $post_id               Post ID.
 *     @type string             $action_url            Form action (wp-comments-post.php).
 *     @type int                $parent_id             Comment being replied to, or 0.
 *     @type string             $reply_to              Name of the author being replied to, or empty.
 *     @type string             $cancel_reply_url      Link that leaves reply mode.
 *     @type bool               $registration_required Whether only registered users may comment.
 *     @type string             $login_url             Login URL that returns to the form.
 *     @type bool               $require_name_email    Whether guests must give a name and email.
 *     @type bool               $cookies_opt_in        Whether to show the "remember me" checkbox.
 *     @type array<string, int> $max_lengths           Maximum field lengths from core.
 * }
 */

defined( 'ABSPATH' ) || exit;

$convocept_post_id  = (int) $args['post_id'];
$convocept_required = $args['require_name_email'];

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core comment form hooks, fired for anti-spam compatibility.

/** This action is documented in wp-includes/comment-template.php */
do_action( 'comment_form_before' );
?>
<div id="respond" class="convocept-composer">
	<?php if ( $args['registration_required'] ) : ?>
		<p class="convocept-notice convocept-notice--login">
			<a href="<?php echo esc_url( $args['login_url'] ); ?>"><?php esc_html_e( 'Log in to join the conversation.', 'convocept' ); ?></a>
		</p>
	<?php else : ?>
		<form
			id="commentform"
			class="convocept-form"
			action="<?php echo esc_url( $args['action_url'] ); ?>"
			method="post"
			data-convocept-composer
		>
			<?php
			/** This action is documented in wp-includes/comment-template.php */
			do_action( 'comment_form_top' );
			?>

			<?php if ( $args['parent_id'] ) : ?>
				<p class="convocept-form__replying">
					<?php
					/* translators: %s: Name of the person being replied to. */
					echo esc_html( sprintf( __( 'Replying to %s', 'convocept' ), $args['reply_to'] ) );
					?>
					<a class="convocept-form__cancel" href="<?php echo esc_url( $args['cancel_reply_url'] ); ?>" rel="nofollow"><?php esc_html_e( 'Cancel', 'convocept' ); ?></a>
				</p>
			<?php endif; ?>

			<?php
			$convocept_textarea = sprintf(
				'<p class="convocept-form__field convocept-form__field--comment"><label class="screen-reader-text" for="comment">%1$s</label><textarea id="comment" name="comment" class="convocept-form__textarea" rows="4" maxlength="%2$d" placeholder="%3$s" required></textarea></p>',
				esc_html__( 'Comment', 'convocept' ),
				absint( $args['max_lengths']['comment_content'] ?? 65525 ),
				esc_attr__( 'Join the discussion…', 'convocept' )
			);

			/** This filter is documented in wp-includes/comment-template.php */
			echo apply_filters( 'comment_form_field_comment', $convocept_textarea ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above; filtered by anti-spam plugins as in core.
			?>

			<div class="convocept-form__identity" data-convocept-guest-fields>
				<p class="convocept-form__field">
					<label for="author">
						<?php esc_html_e( 'Name', 'convocept' ); ?>
						<?php if ( $convocept_required ) : ?>
							<span class="convocept-form__required" aria-hidden="true">*</span>
						<?php endif; ?>
					</label>
					<input id="author" name="author" type="text" autocomplete="name" maxlength="<?php echo esc_attr( (string) absint( $args['max_lengths']['comment_author'] ?? 245 ) ); ?>">
				</p>
				<p class="convocept-form__field">
					<label for="email">
						<?php esc_html_e( 'Email', 'convocept' ); ?>
						<?php if ( $convocept_required ) : ?>
							<span class="convocept-form__required" aria-hidden="true">*</span>
						<?php endif; ?>
					</label>
					<input id="email" name="email" type="email" autocomplete="email" maxlength="<?php echo esc_attr( (string) absint( $args['max_lengths']['comment_author_email'] ?? 100 ) ); ?>" aria-describedby="convocept-email-note">
				</p>
				<p class="convocept-form__field">
					<label for="url"><?php esc_html_e( 'Website', 'convocept' ); ?></label>
					<input id="url" name="url" type="url" autocomplete="url" maxlength="<?php echo esc_attr( (string) absint( $args['max_lengths']['comment_author_url'] ?? 200 ) ); ?>">
				</p>
				<p id="convocept-email-note" class="convocept-form__note">
					<?php
					echo esc_html(
						$convocept_required
							? __( 'Your email address will not be published. Fields marked * are required.', 'convocept' )
							: __( 'Your email address will not be published.', 'convocept' )
					);
					?>
				</p>
				<?php if ( $args['cookies_opt_in'] ) : ?>
					<p class="convocept-form__field convocept-form__field--checkbox">
						<input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes">
						<label for="wp-comment-cookies-consent"><?php esc_html_e( 'Save my name, email, and website in this browser for the next time I comment.', 'convocept' ); ?></label>
					</p>
				<?php endif; ?>
			</div>

			<p class="convocept-form__submit">
				<button type="submit" class="convocept-button"><?php esc_html_e( 'Post comment', 'convocept' ); ?></button>
			</p>

			<input type="hidden" name="comment_post_ID" value="<?php echo esc_attr( (string) $convocept_post_id ); ?>">
			<input type="hidden" name="comment_parent" value="<?php echo esc_attr( (string) absint( $args['parent_id'] ) ); ?>">

			<?php
			/** This action is documented in wp-includes/comment-template.php */
			do_action( 'comment_form', $convocept_post_id );
			?>
		</form>
	<?php endif; ?>

	<?php
	/**
	 * Fires after the comment form, inside the composer.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 */
	do_action( 'convocept_render_composer_after', $convocept_post_id );
	?>
</div>
<?php
/** This action is documented in wp-includes/comment-template.php */
do_action( 'comment_form_after' );

// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
