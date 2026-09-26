<?php
/**
 * Base test case.
 *
 * @package Convocept
 */

namespace Convocept\Tests;

use Convocept\Comments\ThreadQuery;
use Convocept\Markdown\Parser;
use Convocept\Render\CommentPresenter;
use Convocept\Render\TemplateLoader;
use Convocept\Render\ThreadRenderer;
use Convocept\Security\FormToken;
use Convocept\Settings\Settings;
use WP_UnitTestCase;

/**
 * Shared helpers for Convocept tests.
 */
abstract class TestCase extends WP_UnitTestCase {

	/**
	 * Build a fresh renderer, so "already rendered" state never leaks between tests.
	 *
	 * @param array<string, mixed> $settings Settings to store before building.
	 * @return ThreadRenderer
	 */
	protected function renderer( array $settings = array() ): ThreadRenderer {
		if ( $settings ) {
			update_option( Settings::OPTION, $settings );
		}

		return new ThreadRenderer( new Settings(), new TemplateLoader(), new ThreadQuery(), new CommentPresenter( new Parser() ), new FormToken() );
	}

	/**
	 * Create an approved comment.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $args    Extra comment fields.
	 * @return int Comment ID.
	 */
	protected function comment( int $post_id, array $args = array() ): int {
		static $minute = 0;
		++$minute;

		$date = gmdate( 'Y-m-d H:i:s', strtotime( '2026-01-01 00:00:00' ) + $minute * 60 );

		return (int) self::factory()->comment->create(
			array_merge(
				array(
					'comment_post_ID'  => $post_id,
					'comment_approved' => 1,
					'comment_date'     => $date,
					'comment_date_gmt' => $date,
				),
				$args
			)
		);
	}

	/**
	 * Set up the main query as a singular view of a post.
	 *
	 * @param int                  $post_id    Post ID.
	 * @param array<string, mixed> $query_vars Extra query vars, e.g. cpage.
	 * @return void
	 */
	protected function view_post( int $post_id, array $query_vars = array() ): void {
		$this->go_to( get_permalink( $post_id ) );

		foreach ( $query_vars as $key => $value ) {
			set_query_var( $key, $value );
		}

		$GLOBALS['post'] = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test setup.
		setup_postdata( $GLOBALS['post'] );
	}
}
