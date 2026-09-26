<?php
/**
 * Template: pagination between pages of top-level comments.
 *
 * Override by copying to `{theme}/convocept/pagination.php`.
 *
 * @package Convocept
 * @since   1.0.0
 *
 * @var array $args {
 *     @type \Convocept\Comments\Thread $thread   The thread being rendered.
 *     @type array[]                    $links    Page links. Each has 'gap' (bool); non-gap
 *                                                entries also have 'number', 'url', 'current'.
 *     @type string                     $prev_url Previous page URL, or empty on the first page.
 *     @type string                     $next_url Next page URL, or empty on the last page.
 * }
 */

defined( 'ABSPATH' ) || exit;
?>
<nav class="convocept-pagination" aria-label="<?php esc_attr_e( 'Comment pages', 'convocept' ); ?>">
	<?php if ( $args['prev_url'] ) : ?>
		<a class="convocept-pagination__prev" href="<?php echo esc_url( $args['prev_url'] ); ?>" rel="prev"><?php esc_html_e( 'Previous', 'convocept' ); ?></a>
	<?php endif; ?>

	<ol class="convocept-pagination__pages">
		<?php foreach ( $args['links'] as $convocept_link ) : ?>
			<li>
				<?php if ( $convocept_link['gap'] ) : ?>
					<span class="convocept-pagination__gap" aria-hidden="true">&hellip;</span>
				<?php elseif ( $convocept_link['current'] ) : ?>
					<span class="convocept-pagination__page" aria-current="page"><?php echo esc_html( number_format_i18n( $convocept_link['number'] ) ); ?></span>
				<?php else : ?>
					<a class="convocept-pagination__page" href="<?php echo esc_url( $convocept_link['url'] ); ?>">
						<span class="screen-reader-text"><?php esc_html_e( 'Page', 'convocept' ); ?> </span><?php echo esc_html( number_format_i18n( $convocept_link['number'] ) ); ?>
					</a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>

	<?php if ( $args['next_url'] ) : ?>
		<a class="convocept-pagination__next" href="<?php echo esc_url( $args['next_url'] ); ?>" rel="next"><?php esc_html_e( 'Next', 'convocept' ); ?></a>
	<?php endif; ?>
</nav>
