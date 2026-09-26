<?php
/**
 * Tests for the thread read routes.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Rest;

use Convocept\Plugin;

/**
 * @covers \Convocept\Rest\ThreadEndpoint
 */
class ThreadEndpointTest extends RestTestCase {

	public function set_up(): void {
		parent::set_up();
		update_option(
			'convocept_settings',
			array(
				'per_page'      => 2,
				'replies_shown' => 1,
			)
		);
		Plugin::instance()->settings()->flush();
	}

	public function tear_down(): void {
		Plugin::instance()->settings()->flush();
		parent::tear_down();
	}

	public function test_page_route_returns_next_page(): void {
		$post_id = self::factory()->post->create();
		$ids     = array();

		for ( $i = 0; $i < 3; $i++ ) {
			$ids[] = $this->comment( $post_id );
		}

		$data = $this->dispatch( 'GET', "posts/{$post_id}/comments", array( 'page' => 2 ) )->get_data();

		$this->assertSame( 2, $data['page'] );
		$this->assertSame( 2, $data['pages'] );
		$this->assertStringContainsString( 'id="comment-' . $ids[2] . '"', $data['html'] );
		$this->assertStringNotContainsString( 'id="comment-' . $ids[0] . '"', $data['html'] );

		$past = $this->dispatch( 'GET', "posts/{$post_id}/comments", array( 'page' => 9 ) )->get_data();
		$this->assertSame( '', $past['html'] );
	}

	public function test_page_route_rejects_invalid_sort_and_protected_posts(): void {
		$post_id   = self::factory()->post->create();
		$protected = self::factory()->post->create( array( 'post_password' => 'x' ) );
		$draft     = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$this->assertSame( 400, $this->dispatch( 'GET', "posts/{$post_id}/comments", array( 'sort' => 'evil' ) )->get_status() );
		$this->assertSame( 404, $this->dispatch( 'GET', "posts/{$protected}/comments" )->get_status() );
		$this->assertSame( 404, $this->dispatch( 'GET', "posts/{$draft}/comments" )->get_status() );
	}

	public function test_replies_route(): void {
		$post_id = self::factory()->post->create();
		$parent  = $this->comment( $post_id );
		$one     = $this->comment( $post_id, array( 'comment_parent' => $parent ) );
		$two     = $this->comment( $post_id, array( 'comment_parent' => $parent ) );

		$data = $this->dispatch( 'GET', "comments/{$parent}/replies", array( 'offset' => 1 ) )->get_data();

		$this->assertStringContainsString( 'id="comment-' . $two . '"', $data['html'] );
		$this->assertStringNotContainsString( 'id="comment-' . $one . '"', $data['html'] );
		$this->assertSame( 0, $data['remaining'] );
	}

	public function test_context_route_and_unapproved_comments(): void {
		$post_id = self::factory()->post->create();
		$this->comment( $post_id );
		$this->comment( $post_id );
		$third   = $this->comment( $post_id );
		$pending = $this->comment( $post_id, array( 'comment_approved' => 0 ) );

		$data = $this->dispatch( 'GET', "comments/{$third}/context" )->get_data();

		$this->assertStringContainsString( 'cpage=2', $data['url'] );
		$this->assertSame( 404, $this->dispatch( 'GET', "comments/{$pending}/context" )->get_status() );
		$this->assertSame( 404, $this->dispatch( 'GET', "comments/{$pending}/replies" )->get_status() );
	}
}
