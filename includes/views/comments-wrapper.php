<?php
/**
 * Wrapper around the theme's native comments template (classic themes).
 *
 * Rendered in place of the theme's comments template by the Template class,
 * which stores the original path for this file to include.
 *
 * Layout:
 *   .convocept-comments        Outer container the app looks for.
 *   [data-convocept-mount]     Empty node the React app mounts into.
 *   [data-convocept-native]    The theme's own markup, hidden after a successful
 *                        mount and revealed again if the app fails.
 *
 * Because the native markup is always server-rendered, comments keep
 * working with JavaScript disabled.
 *
 * @package Convocept
 * @since   1.0.0
 */

use Convocept\Frontend\Template;

defined( 'ABSPATH' ) || exit;

$convocept_theme_template = Template::current() ? Template::current()->theme_template() : '';
?>
<div class="convocept-comments" data-convocept-app>
	<div class="convocept-app" data-convocept-mount></div>
	<div class="convocept-native" data-convocept-native>
		<?php
		if ( $convocept_theme_template && is_readable( $convocept_theme_template ) ) {
			// Include the theme's template exactly as core would have.
			require $convocept_theme_template;
		} else {
			/*
			 * Defensive fallback for the unlikely case that the template
			 * path is missing or unreadable: render core's own default
			 * thread and form so comments still work.
			 */
			if ( have_comments() ) {
				wp_list_comments();
				the_comments_pagination();
			}

			comment_form();
		}
		?>
	</div>
</div>
