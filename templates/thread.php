<?php
/**
 * Template: the comment thread wrapper.
 *
 * Override by copying to `{theme}/convocept/thread.php`.
 *
 * Every `*_html` value is already rendered and escaped by its own template;
 * print it as is.
 *
 * @package Convocept
 * @since   1.0.0
 *
 * @var array $args {
 *     @type \Convocept\Comments\Thread $thread        The thread being rendered.
 *     @type int                        $post_id       Post ID.
 *     @type string[]                   $classes       Wrapper CSS classes (sanitized).
 *     @type bool                       $is_open       Whether new comments are accepted.
 *     @type string                     $color_scheme  'auto', 'system', 'light' or 'dark'.
 *     @type string                     $accent_color  Sanitized hex accent color.
 *     @type string                     $header_html   Count and sort navigation.
 *     @type string                     $composer_html Comment form, empty when closed.
 *     @type string                     $list_html     Comment list items.
 *     @type string                     $pagination    Pagination, empty for a single page.
 * }
 */

defined( 'ABSPATH' ) || exit;

$convocept_thread = $args['thread'];
?>
<section
	id="comments"
	class="<?php echo esc_attr( implode( ' ', $args['classes'] ) ); ?>"
	data-convocept-post="<?php echo esc_attr( (string) $args['post_id'] ); ?>"
	data-convocept-theme="<?php echo esc_attr( $args['color_scheme'] ); ?>"
	style="--convocept-accent: <?php echo esc_attr( $args['accent_color'] ); ?>;"
	aria-label="<?php esc_attr_e( 'Comments', 'convocept' ); ?>"
>
	<?php
	/**
	 * Fires at the top of the thread, before the header.
	 *
	 * Use it for thread-level additions such as summaries.
	 *
	 * @since 1.0.0
	 *
	 * @param \Convocept\Comments\Thread $thread The thread being rendered.
	 */
	do_action( 'convocept_render_thread_header', $convocept_thread );

	echo $args['header_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in thread-header.php.
	echo $args['composer_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in composer.php.
	?>

	<?php if ( ! $args['is_open'] ) : ?>
		<p class="convocept-notice convocept-notice--closed"><?php esc_html_e( 'Comments are closed.', 'convocept' ); ?></p>
	<?php endif; ?>

	<?php if ( $convocept_thread->is_empty() ) : ?>
		<p class="convocept-empty"><?php esc_html_e( 'No comments yet. Start the conversation.', 'convocept' ); ?></p>
	<?php else : ?>
		<ol class="convocept-list">
			<?php echo $args['list_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in comment.php. ?>
		</ol>
	<?php endif; ?>

	<?php echo $args['pagination']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in pagination.php. ?>
</section>
