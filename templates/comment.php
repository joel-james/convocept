<?php
/**
 * Template: a single comment and its replies.
 *
 * Override by copying to `{theme}/convocept/comment.php`. Keep the
 * `id="comment-{ID}"` and `data-convocept-comment` attributes: deep links and
 * the front-end script rely on them.
 *
 * @package Convocept
 * @since   1.0.0
 *
 * @var array $args {
 *     View data from \Convocept\Render\CommentPresenter::present(), plus:
 *
 *     @type \WP_Comment $comment      The comment.
 *     @type int         $id           Comment ID.
 *     @type int         $depth        Visual nesting depth, starting at 1.
 *     @type string      $author       Author name (unescaped).
 *     @type string      $author_url   Author website (unescaped), or empty.
 *     @type string      $avatar_html  Avatar markup (already escaped).
 *     @type array[]     $badges       Badges with 'label' and 'type' keys (unescaped).
 *     @type string      $date_iso     ISO 8601 date.
 *     @type string      $date_text    Localized date.
 *     @type string      $date_title   Localized date and time.
 *     @type string      $permalink    Link to this comment.
 *     @type string      $reply_url    No-JS reply link, or empty.
 *     @type string      $reply_to     Parent author for flattened replies, or empty.
 *     @type string      $content_html Comment content as safe HTML.
 *     @type bool        $is_pending   Whether the comment awaits moderation (only in
 *                                     responses to its own author, never on the page).
 *     @type bool        $is_edited    Whether the comment was edited.
 *     @type int         $author_id    Author's user ID, 0 for guests.
 *     @type int         $timestamp    Unix time the comment was posted.
 *     @type string[]    $classes      CSS classes.
 *     @type string      $replies_html Rendered replies (already escaped), or empty.
 *     @type bool        $replies_flat Whether the replies are a flat list (maximum depth).
 *     @type array       $more_replies Replies not shown yet: 'count', 'offset' (replies
 *                                     already shown) and 'url' (no-JS link that expands
 *                                     the branch).
 * }
 */

defined( 'ABSPATH' ) || exit;

$convocept_comment = $args['comment'];
?>
<li
	id="comment-<?php echo esc_attr( (string) $args['id'] ); ?>"
	class="<?php echo esc_attr( implode( ' ', $args['classes'] ) ); ?>"
	data-convocept-comment="<?php echo esc_attr( (string) $args['id'] ); ?>"
	data-convocept-author="<?php echo esc_attr( (string) $args['author_id'] ); ?>"
	data-convocept-time="<?php echo esc_attr( (string) $args['timestamp'] ); ?>"
>
	<article class="convocept-comment__body">
		<div class="convocept-comment__avatar">
			<?php echo $args['avatar_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by get_avatar() or escaped in CommentPresenter. ?>
		</div>

		<div class="convocept-comment__main">
			<header class="convocept-comment__meta">
				<?php if ( $args['author_url'] ) : ?>
					<a class="convocept-comment__author" href="<?php echo esc_url( $args['author_url'] ); ?>" rel="external nofollow ugc"><?php echo esc_html( $args['author'] ); ?></a>
				<?php else : ?>
					<span class="convocept-comment__author"><?php echo esc_html( $args['author'] ); ?></span>
				<?php endif; ?>

				<?php foreach ( $args['badges'] as $convocept_badge ) : ?>
					<span class="convocept-badge convocept-badge--<?php echo esc_attr( sanitize_html_class( $convocept_badge['type'] ) ); ?>"><?php echo esc_html( $convocept_badge['label'] ); ?></span>
				<?php endforeach; ?>

				<?php
				/**
				 * Fires after a comment's badges, inside the comment header.
				 *
				 * @since 1.0.0
				 *
				 * @param \WP_Comment $comment The comment.
				 */
				do_action( 'convocept_render_badges', $convocept_comment );
				?>

				<a class="convocept-comment__date" href="<?php echo esc_url( $args['permalink'] ); ?>">
					<time datetime="<?php echo esc_attr( $args['date_iso'] ); ?>" title="<?php echo esc_attr( $args['date_title'] ); ?>"><?php echo esc_html( $args['date_text'] ); ?></time>
				</a>

				<?php if ( $args['is_edited'] ) : ?>
					<span class="convocept-comment__edited"><?php esc_html_e( '(edited)', 'convocept' ); ?></span>
				<?php endif; ?>
			</header>

			<?php if ( $args['is_pending'] ) : ?>
				<p class="convocept-comment__pending"><?php esc_html_e( 'Your comment is awaiting moderation.', 'convocept' ); ?></p>
			<?php endif; ?>

			<?php if ( $args['reply_to'] ) : ?>
				<p class="convocept-comment__reply-to">
					<?php
					/* translators: %s: Name of the person being replied to. */
					echo esc_html( sprintf( __( 'Replying to %s', 'convocept' ), $args['reply_to'] ) );
					?>
				</p>
			<?php endif; ?>

			<div class="convocept-comment__content" data-convocept-content>
				<?php echo $args['content_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML from the Markdown parser (kses-filtered) or core's comment_text filters. ?>
			</div>

			<footer class="convocept-comment__actions" data-convocept-actions>
				<?php if ( $args['reply_url'] ) : ?>
					<a
						class="convocept-action convocept-action--reply"
						href="<?php echo esc_url( $args['reply_url'] ); ?>"
						rel="nofollow"
						data-convocept-reply="<?php echo esc_attr( (string) $args['id'] ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: Comment author name. */ __( 'Reply to %s', 'convocept' ), $args['author'] ) ); ?>"
					><?php esc_html_e( 'Reply', 'convocept' ); ?></a>
				<?php endif; ?>

				<?php
				/**
				 * Fires inside a comment's action bar, after the built-in actions.
				 *
				 * @since 1.0.0
				 *
				 * @param \WP_Comment $comment The comment.
				 */
				do_action( 'convocept_render_comment_actions', $convocept_comment );
				?>
			</footer>

			<?php
			/**
			 * Fires at the end of a comment, before its replies.
			 *
			 * @since 1.0.0
			 *
			 * @param \WP_Comment $comment The comment.
			 */
			do_action( 'convocept_render_comment_footer', $convocept_comment );
			?>
		</div>
	</article>

	<?php if ( $args['replies_html'] ) : ?>
		<ol class="convocept-replies<?php echo $args['replies_flat'] ? ' convocept-replies--flat' : ''; ?>">
			<?php echo $args['replies_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in comment.php. ?>
		</ol>
	<?php endif; ?>

	<?php if ( $args['more_replies']['count'] > 0 ) : ?>
		<p class="convocept-more-replies">
			<a
				href="<?php echo esc_url( $args['more_replies']['url'] ); ?>"
				rel="nofollow"
				data-convocept-more-replies="<?php echo esc_attr( (string) $args['id'] ); ?>"
				data-convocept-offset="<?php echo esc_attr( (string) $args['more_replies']['offset'] ); ?>"
			>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: Number of hidden replies. */
						_n( 'Show %s more reply', 'Show %s more replies', $args['more_replies']['count'], 'convocept' ),
						number_format_i18n( $args['more_replies']['count'] )
					)
				);
				?>
			</a>
		</p>
	<?php endif; ?>
</li>
