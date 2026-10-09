<?php
/**
 * Tests for the rendered HTML cache.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Render;

use Convocept\Comments\ThreadQuery;
use Convocept\Render\FragmentCache;
use Convocept\Tests\TestCase;

/**
 * @covers \Convocept\Render\FragmentCache
 * @covers \Convocept\Render\ThreadRenderer::render_items
 */
class FragmentCacheTest extends TestCase {

	public function test_second_render_hits_the_cache(): void {
		global $wpdb;

		$post_id = self::factory()->post->create();
		$this->comment( $post_id, array( 'comment_content' => 'Cached words' ) );
		$renderer = $this->renderer();
		$thread   = ( new ThreadQuery() )->run( $post_id );

		$first = $renderer->render_items( $thread );

		$queries = $wpdb->num_queries;
		$second  = $renderer->render_items( $thread );

		$this->assertSame( $first, $second );
		$this->assertLessThanOrEqual( 2, $wpdb->num_queries - $queries, 'Only the cache lookup should query.' );
	}

	public function test_new_comment_invalidates(): void {
		$post_id  = self::factory()->post->create();
		$renderer = $this->renderer();
		$this->comment( $post_id, array( 'comment_content' => 'One' ) );
		$renderer->render_items( ( new ThreadQuery() )->run( $post_id ) );

		$this->comment( $post_id, array( 'comment_content' => 'Two' ) );

		$this->assertStringContainsString( 'Two', $renderer->render_items( ( new ThreadQuery() )->run( $post_id ) ) );
	}

	public function test_comment_meta_and_status_changes_invalidate(): void {
		$post_id  = self::factory()->post->create();
		$renderer = $this->renderer();
		$id       = $this->comment( $post_id, array( 'comment_content' => 'Visible' ) );
		$this->assertStringContainsString( 'Visible', $renderer->render_items( ( new ThreadQuery() )->run( $post_id ) ) );

		wp_update_comment(
			array(
				'comment_ID'      => $id,
				'comment_content' => 'Edited text',
			)
		);
		$this->assertStringContainsString( 'Edited text', $renderer->render_items( ( new ThreadQuery() )->run( $post_id ) ) );

		update_comment_meta( $id, 'convocept_edited_at', time() );
		$this->assertStringContainsString( '(edited)', $renderer->render_items( ( new ThreadQuery() )->run( $post_id ) ) );
	}

	public function test_settings_and_options_are_part_of_the_key(): void {
		$cache = new FragmentCache();
		$a     = $cache->key( array( 'post' => 1 ) );

		update_option( 'date_format', 'Y-m-d' );

		$this->assertNotSame( $a, $cache->key( array( 'post' => 1 ) ) );
	}

	public function test_changes_overwrite_one_row_per_view_instead_of_adding_rows(): void {
		global $wpdb;

		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'Rows only exist without a persistent object cache.' );
		}

		$post_id  = self::factory()->post->create();
		$renderer = $this->renderer();
		$id       = $this->comment( $post_id );
		$count    = static function () use ( $wpdb ): int {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_convocept\\_html\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		};

		$renderer->render_items( ( new ThreadQuery() )->run( $post_id ) );
		$rows = $count();

		// A vote anywhere bumps core's "comments last changed".
		update_comment_meta( $id, 'convocept_score', 3 );
		$renderer->render_items( ( new ThreadQuery() )->run( $post_id ) );

		$this->assertSame( $rows, $count() );
	}

	public function test_cache_can_be_disabled(): void {
		add_filter( 'convocept_fragment_cache', '__return_false' );
		$cache = new FragmentCache();
		$cache->set( 'k', 'html' );

		$this->assertNull( $cache->get( 'k' ) );
		remove_filter( 'convocept_fragment_cache', '__return_false' );
	}
}
