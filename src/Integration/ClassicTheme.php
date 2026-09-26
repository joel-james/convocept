<?php
/**
 * Classic theme integration.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Integration;

use Convocept\Render\ThreadRenderer;
use WP_Comment_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces the output of comments_template() in classic themes.
 *
 * Core's comments_template() loads every comment on the post before it
 * includes the theme's comments.php. Convocept runs its own paginated
 * query, so core's query is short-circuited to avoid loading the thread
 * twice (which on a post with thousands of comments is the slowest part of
 * the page).
 *
 * @since 1.0.0
 */
class ClassicTheme {

	/**
	 * Query variable marking core's comments_template() query as skippable.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SKIP_QUERY_VAR = 'convocept_skip';

	/**
	 * Extra wrapper classes needed by specific themes, keyed by template slug.
	 *
	 * Most classic themes (Twenty*, Underscores-based themes, Astra,
	 * GeneratePress, Kadence) style a `comments-area` container; some also
	 * need a width class from their own comments.php.
	 *
	 * @since 1.0.0
	 * @var array<string, string[]>
	 */
	const THEME_CLASSES = array(
		'twentytwentyone' => array( 'default-max-width' ),
		'twentytwenty'    => array( 'section-inner' ),
	);

	/**
	 * Thread renderer.
	 *
	 * @since 1.0.0
	 * @var ThreadRenderer
	 */
	private $renderer;

	/**
	 * Create the integration.
	 *
	 * @since 1.0.0
	 *
	 * @param ThreadRenderer $renderer Thread renderer.
	 */
	public function __construct( ThreadRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'comments_template_query_args', array( $this, 'mark_core_query' ) );
		add_filter( 'comments_pre_query', array( $this, 'skip_core_query' ), 10, 2 );

		// Late, so we win over themes and plugins that also filter the path.
		add_filter( 'comments_template', array( $this, 'template' ), 99 );

		if ( ! wp_is_block_theme() ) {
			add_filter( 'convocept_thread_classes', array( $this, 'wrapper_classes' ) );
		}
	}

	/**
	 * Add the container classes classic themes style their comments with.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $classes Wrapper classes.
	 * @return string[]
	 */
	public function wrapper_classes( $classes ) {
		$classes   = (array) $classes;
		$classes[] = 'comments-area';

		return array_merge( $classes, self::THEME_CLASSES[ get_template() ] ?? array() );
	}

	/**
	 * Mark the query comments_template() is about to run as skippable.
	 *
	 * This filter only fires inside comments_template(), so no other comment
	 * query is ever affected.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args WP_Comment_Query arguments.
	 * @return array<string, mixed>
	 */
	public function mark_core_query( $args ) {
		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;

		if ( $post_id && $this->renderer->is_enabled_for( $post_id ) ) {
			$args[ self::SKIP_QUERY_VAR ] = true;
		}

		return $args;
	}

	/**
	 * Return no comments for a marked query, without touching the database.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, mixed>|int|null $comment_data Short-circuit value; null to run the query.
	 * @param WP_Comment_Query           $query        The query.
	 * @return array<int, mixed>|int|null
	 */
	public function skip_core_query( $comment_data, $query ) {
		if ( null !== $comment_data || ! $query instanceof WP_Comment_Query || empty( $query->query_vars[ self::SKIP_QUERY_VAR ] ) ) {
			return $comment_data;
		}

		return empty( $query->query_vars['count'] ) ? array() : 0;
	}

	/**
	 * Swap the theme's comments.php for Convocept's.
	 *
	 * If the thread was already rendered on this page (for example by a
	 * block), an empty template is used so it does not appear twice.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template Path to the theme's comments template.
	 * @return string
	 */
	public function template( $template ) {
		$post_id = (int) get_the_ID();

		if ( ! $post_id || ! $this->renderer->is_enabled_for( $post_id ) ) {
			return $template;
		}

		if ( $this->renderer->has_rendered( $post_id ) ) {
			return CONVOCEPT_DIR . 'templates/compat/empty.php';
		}

		return CONVOCEPT_DIR . 'templates/compat/comments.php';
	}
}
