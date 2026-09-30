<?php
/**
 * Template: thread header with the comment count and sort links.
 *
 * Override by copying to `{theme}/convocept/thread-header.php`.
 *
 * @package Convocept
 * @since   1.0.0
 *
 * @var array $args {
 *     @type \Convocept\Comments\Thread $thread     The thread being rendered.
 *     @type array[]                    $sort_links Sort links, each with 'label', 'url' and
 *                                                  'current' keys. Empty when sorting would
 *                                                  not change anything.
 *     @type bool                       $follow     Whether to show the Follow link, which
 *                                                  opens the in-thread subscribe form
 *                                                  (`#convocept-subscribe`).
 * }
 */

defined( 'ABSPATH' ) || exit;

$convocept_total = $args['thread']->total();
?>
<header class="convocept-header">
	<h2 class="convocept-title">
		<?php
		echo esc_html( \Convocept\Render\Labels::comment_count( $convocept_total ) );
		?>
	</h2>

	<?php if ( $args['sort_links'] || ! empty( $args['follow'] ) ) : ?>
		<div class="convocept-header__tools">
			<?php if ( ! empty( $args['follow'] ) ) : ?>
				<?php
				// A link to the form, so it opens (:target) without JavaScript; the script makes it a toggle.
				?>
				<a class="convocept-follow" href="#convocept-subscribe" data-convocept-follow>
					<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
					<?php esc_html_e( 'Follow', 'convocept' ); ?>
				</a>
			<?php endif; ?>

			<?php if ( $args['sort_links'] ) : ?>
				<nav class="convocept-sort" aria-label="<?php esc_attr_e( 'Sort comments', 'convocept' ); ?>">
					<?php foreach ( $args['sort_links'] as $convocept_link ) : ?>
						<a
							class="convocept-sort__link"
							href="<?php echo esc_url( $convocept_link['url'] ); ?>"
							rel="nofollow"
							<?php echo $convocept_link['current'] ? 'aria-current="true"' : ''; ?>
						><?php echo esc_html( $convocept_link['label'] ); ?></a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</header>
