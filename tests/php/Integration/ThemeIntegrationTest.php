<?php
/**
 * Tests for the classic and block theme integrations.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Integration;

use Convocept\Integration\BlockTheme;
use Convocept\Integration\ClassicTheme;
use Convocept\Tests\TestCase;
use WP_Comment_Query;

/**
 * @covers \Convocept\Integration\ClassicTheme
 * @covers \Convocept\Integration\BlockTheme
 */
class ThemeIntegrationTest extends TestCase {

	public function test_classic_template_is_replaced_for_enabled_posts(): void {
		$post_id = self::factory()->post->create();
		$this->view_post( $post_id );

		$classic = new ClassicTheme( $this->renderer() );

		$this->assertSame( CONVOCEPT_DIR . 'templates/compat/comments.php', $classic->template( '/theme/comments.php' ) );
	}

	public function test_classic_template_untouched_when_disabled(): void {
		$post_id = self::factory()->post->create();
		$this->view_post( $post_id );
		add_filter( 'convocept_enabled', '__return_false' );

		$classic = new ClassicTheme( $this->renderer() );
		$result  = $classic->template( '/theme/comments.php' );

		remove_filter( 'convocept_enabled', '__return_false' );

		$this->assertSame( '/theme/comments.php', $result );
	}

	public function test_core_comments_template_query_is_short_circuited(): void {
		$post_id = self::factory()->post->create();
		$this->comment( $post_id );

		$classic           = new ClassicTheme( $this->renderer() );
		$args              = $classic->mark_core_query( array( 'post_id' => $post_id ) );
		$query             = new WP_Comment_Query();
		$query->query_vars = $args;

		$this->assertTrue( $args[ ClassicTheme::SKIP_QUERY_VAR ] );
		$this->assertSame( array(), $classic->skip_core_query( null, $query ) );

		$query->query_vars['count'] = true;
		$this->assertSame( 0, $classic->skip_core_query( null, $query ) );
	}

	public function test_other_comment_queries_are_untouched(): void {
		$classic           = new ClassicTheme( $this->renderer() );
		$query             = new WP_Comment_Query();
		$query->query_vars = array( 'post_id' => 1 );

		$this->assertNull( $classic->skip_core_query( null, $query ) );
	}

	public function test_classic_second_call_renders_empty(): void {
		$post_id  = self::factory()->post->create();
		$renderer = $this->renderer();
		$this->view_post( $post_id );
		$renderer->render( $post_id );

		$this->assertSame( CONVOCEPT_DIR . 'templates/compat/empty.php', ( new ClassicTheme( $renderer ) )->template( '/theme/comments.php' ) );
	}

	public function test_block_replaced_once_per_post(): void {
		$post_id = self::factory()->post->create();
		$this->view_post( $post_id );

		$blocks = new BlockTheme( $this->renderer() );
		$first  = $blocks->pre_render( null, array( 'blockName' => 'core/comments' ) );
		$second = $blocks->pre_render( null, array( 'blockName' => 'core/post-comments-form' ) );

		$this->assertStringContainsString( 'class="convocept', (string) $first );
		$this->assertSame( '', $second );
	}

	public function test_unrelated_blocks_untouched(): void {
		$blocks = new BlockTheme( $this->renderer() );

		$this->assertNull( $blocks->pre_render( null, array( 'blockName' => 'core/paragraph' ) ) );
		$this->assertSame( 'x', $blocks->pre_render( 'x', array( 'blockName' => 'core/comments' ) ) );
	}

	public function test_classic_wrapper_classes_include_comments_area(): void {
		$classes = ( new ClassicTheme( $this->renderer() ) )->wrapper_classes( array( 'convocept' ) );

		$this->assertContains( 'comments-area', $classes );
	}
}
