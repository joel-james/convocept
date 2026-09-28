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
</header>
