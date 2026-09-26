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
 * Keep the field names, the `commentform` / `respond` IDs and the
 * `data-convocept-*` attributes: core, anti-spam plugins and the front-end
 * script depend on them. Elements marked `hidden` (toolbar, preview, status,
 * identity) are revealed by the script; the form works without them.
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
 *     @type string             $marker_field          Name of the hidden field marking this form.
 *     @type string             $token_field           Name of the hidden signed-timestamp field.
 *     @type string             $form_token            Signed "form rendered at" token.
 *     @type string             $honeypot_field        Name of the honeypot field.
 *     @type bool               $subscriptions         Whether to offer email subscriptions.
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
		<?php
		/*
		 * The form is still rendered but hidden: the page is the same for
		 * everyone, and the script reveals it for logged-in readers.
		 */
		?>
		<p class="convocept-notice convocept-notice--login" data-convocept-login>
			<a href="<?php echo esc_url( $args['login_url'] ); ?>"><?php esc_html_e( 'Log in to join the conversation.', 'convocept' ); ?></a>
		</p>
	<?php endif; ?>

		<form
			id="commentform"
			class="convocept-form"
			action="<?php echo esc_url( $args['action_url'] ); ?>"
			method="post"
			data-convocept-composer
			<?php echo $args['registration_required'] ? 'hidden' : ''; ?>
		>
			<?php
			/** This action is documented in wp-includes/comment-template.php */
			do_action( 'comment_form_top' );
			?>

			<p class="convocept-form__replying" data-convocept-replying <?php echo $args['parent_id'] ? '' : 'hidden'; ?>>
				<span data-convocept-replying-to>
					<?php
					/* translators: %s: Name of the person being replied to. */
					echo esc_html( sprintf( __( 'Replying to %s', 'convocept' ), $args['reply_to'] ) );
					?>
				</span>
				<a class="convocept-form__cancel" href="<?php echo esc_url( $args['cancel_reply_url'] ); ?>" rel="nofollow" data-convocept-cancel-reply><?php esc_html_e( 'Cancel', 'convocept' ); ?></a>
			</p>

			<p class="convocept-form__user" data-convocept-user hidden></p>

			<div class="convocept-editor" data-convocept-editor>
				<div class="convocept-toolbar" data-convocept-toolbar hidden>
					<div class="convocept-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Comment editor', 'convocept' ); ?>">
						<button type="button" class="convocept-tab" role="tab" aria-selected="true" data-convocept-tab="write"><?php esc_html_e( 'Write', 'convocept' ); ?></button>
						<button type="button" class="convocept-tab" role="tab" aria-selected="false" data-convocept-tab="preview"><?php esc_html_e( 'Preview', 'convocept' ); ?></button>
					</div>
					<div class="convocept-format" role="toolbar" aria-label="<?php esc_attr_e( 'Formatting', 'convocept' ); ?>">
						<button type="button" class="convocept-format__button" data-convocept-format="bold" title="<?php esc_attr_e( 'Bold', 'convocept' ); ?>" aria-label="<?php esc_attr_e( 'Bold', 'convocept' ); ?>"><strong aria-hidden="true">B</strong></button>
						<button type="button" class="convocept-format__button" data-convocept-format="italic" title="<?php esc_attr_e( 'Italic', 'convocept' ); ?>" aria-label="<?php esc_attr_e( 'Italic', 'convocept' ); ?>"><em aria-hidden="true">I</em></button>
						<button type="button" class="convocept-format__button" data-convocept-format="link" title="<?php esc_attr_e( 'Link', 'convocept' ); ?>" aria-label="<?php esc_attr_e( 'Link', 'convocept' ); ?>"><svg aria-hidden="true" focusable="false" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg></button>
						<button type="button" class="convocept-format__button" data-convocept-format="code" title="<?php esc_attr_e( 'Code', 'convocept' ); ?>" aria-label="<?php esc_attr_e( 'Code', 'convocept' ); ?>"><span aria-hidden="true">&lt;/&gt;</span></button>
						<button type="button" class="convocept-format__button" data-convocept-format="quote" title="<?php esc_attr_e( 'Quote', 'convocept' ); ?>" aria-label="<?php esc_attr_e( 'Quote', 'convocept' ); ?>"><span aria-hidden="true">&ldquo;</span></button>
					</div>
				</div>

				<?php
				$convocept_textarea = sprintf(
					'<p class="convocept-form__field convocept-form__field--comment"><label class="screen-reader-text" for="comment">%1$s</label><textarea id="comment" name="comment" class="convocept-form__textarea" rows="4" maxlength="%2$d" placeholder="%3$s" required data-convocept-input></textarea></p>',
					esc_html__( 'Comment', 'convocept' ),
					absint( $args['max_lengths']['comment_content'] ?? 65525 ),
					esc_attr__( 'Join the discussion…', 'convocept' )
				);

				/** This filter is documented in wp-includes/comment-template.php */
				echo apply_filters( 'comment_form_field_comment', $convocept_textarea ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above; filtered by anti-spam plugins as in core.
				?>

				<div class="convocept-preview convocept-comment__content" data-convocept-preview hidden aria-live="polite"></div>
				<p class="convocept-form__counter" data-convocept-counter hidden></p>
			</div>

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

			<?php if ( $args['subscriptions'] ) : ?>
				<div class="convocept-form__subscribe">
					<p class="convocept-form__field convocept-form__field--checkbox">
						<input id="convocept-subscribe-replies" name="convocept_subscribe_replies" type="checkbox" value="1">
						<label for="convocept-subscribe-replies"><?php esc_html_e( 'Email me replies to my comment', 'convocept' ); ?></label>
					</p>
					<p class="convocept-form__field convocept-form__field--checkbox">
						<input id="convocept-subscribe-post" name="convocept_subscribe_post" type="checkbox" value="1">
						<label for="convocept-subscribe-post"><?php esc_html_e( 'Email me all new comments on this post', 'convocept' ); ?></label>
					</p>
				</div>
			<?php endif; ?>

			<p class="convocept-hp" aria-hidden="true">
				<label for="convocept-hp"><?php esc_html_e( 'Leave this field empty', 'convocept' ); ?></label>
				<input id="convocept-hp" name="<?php echo esc_attr( $args['honeypot_field'] ); ?>" type="text" value="" tabindex="-1" autocomplete="off">
			</p>

			<div class="convocept-form__footer">
				<p class="convocept-form__status" data-convocept-status role="status" aria-live="polite"></p>
				<p class="convocept-form__submit">
					<button type="submit" class="convocept-button" data-convocept-submit><?php esc_html_e( 'Post comment', 'convocept' ); ?></button>
				</p>
			</div>

			<input type="hidden" name="comment_post_ID" value="<?php echo esc_attr( (string) $convocept_post_id ); ?>">
			<input type="hidden" name="comment_parent" value="<?php echo esc_attr( (string) absint( $args['parent_id'] ) ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $args['marker_field'] ); ?>" value="1">
			<input type="hidden" name="<?php echo esc_attr( $args['token_field'] ); ?>" value="<?php echo esc_attr( $args['form_token'] ); ?>">

			<?php
			/** This action is documented in wp-includes/comment-template.php */
			do_action( 'comment_form', $convocept_post_id );
			?>
		</form>

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
