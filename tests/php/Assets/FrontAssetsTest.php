<?php
/**
 * Tests for front-end asset loading.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Assets;

use Convocept\Assets\FrontAssets;
use Convocept\Tests\TestCase;

/**
 * @covers \Convocept\Assets\FrontAssets
 */
class FrontAssetsTest extends TestCase {

	public function test_comment_reply_is_dequeued_on_thread_pages(): void {
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		wp_enqueue_script( 'comment-reply' );

		( new FrontAssets( $this->renderer() ) )->dequeue_comment_reply();

		$this->assertFalse( wp_script_is( 'comment-reply', 'enqueued' ) );
	}

	public function test_comment_reply_kept_where_convocept_is_disabled(): void {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( get_permalink( $page_id ) );
		wp_enqueue_script( 'comment-reply' );

		( new FrontAssets( $this->renderer( array( 'post_types' => array( 'post' ) ) ) ) )->dequeue_comment_reply();

		$this->assertTrue( wp_script_is( 'comment-reply', 'enqueued' ) );
		wp_dequeue_script( 'comment-reply' );
	}

	public function test_nothing_enqueued_on_archives(): void {
		self::factory()->post->create();
		$this->go_to( home_url( '/' ) );

		( new FrontAssets( $this->renderer() ) )->enqueue();

		$this->assertFalse( wp_style_is( FrontAssets::HANDLE, 'enqueued' ) );
	}
}
