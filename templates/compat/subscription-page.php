<?php
/**
 * Page shell for the subscription page.
 *
 * Wraps Convocept's content in the active theme's header and footer, for
 * classic themes (header.php / footer.php) and block themes (the header
 * and footer template parts). The content is a Convocept card with its own
 * width and margins, so <main> adds none. Not meant to be overridden:
 * override `subscription-manage.php` instead.
 *
 * @package Convocept
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$convocept_content = \Convocept\Plugin::instance()->manage_page()->content();

if ( wp_is_block_theme() ) :
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>
	<div class="wp-site-blocks">
		<?php block_template_part( 'header' ); ?>
		<main class="wp-block-group is-layout-constrained has-global-padding">
			<?php echo $convocept_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in subscription-manage.php. ?>
		</main>
		<?php block_template_part( 'footer' ); ?>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
	<?php
else :
	get_header();
	?>
	<main id="primary" class="site-main convocept-subscription-main">
		<?php echo $convocept_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in subscription-manage.php. ?>
	</main>
	<?php
	get_footer();
endif;
