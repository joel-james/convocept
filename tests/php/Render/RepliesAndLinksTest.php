<?php
/**
 * Tests for reply limits, more replies and comment links (M3).
 *
 * @package Convocept
 */

namespace Convocept\Tests\Render;

use Convocept\Comments\ThreadQuery;
use Convocept\Integration\CommentLinks;
use Convocept\Render\ThreadRenderer;
use Convocept\Tests\TestCase;

/**
 * @covers \Convocept\Render\ThreadRenderer
 * @covers \Convocept\Integration\CommentLinks
 * @covers \Convocept\Comments\ThreadQuery::position
 */
class RepliesAndLinksTest extends TestCase {

	/**
	 * Create a top-level comment with direct replies.
	 *
	 * @param int $post_id Post ID.
	 * @param int $count   Number of replies.
	 * @return array{0: int, 1: int[]} Parent ID and reply IDs.
	 */
	private function branch( int $post_id, int $count ): array {
		$parent  = $this->comment( $post_id );
		$replies = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$replies[] = $this->comment( $post_id, array( 'comment_parent' => $parent ) );
		}

		return array( $parent, $replies );
	}

	public function test_reply_lists_are_limited_with_a_show_more_link(): void {
		$post_id                  = self::factory()->post->create();
		list( $parent, $replies ) = $this->branch( $post_id, 4 );
		$this->view_post( $post_id );

		$html = $this->renderer( array( 'replies_shown' => 2 ) )->render( $post_id );

		$this->assertStringContainsString( 'id="comment-' . $replies[1] . '"', $html );
		$this->assertStringNotContainsString( 'id="comment-' . $replies[2] . '"', $html );
		$this->assertStringContainsString( 'Show 2 more replies', $html );
		$this->assertStringContainsString( 'data-convocept-offset="2"', $html );
		$this->assertStringContainsString( ThreadRenderer::EXPAND_QUERY_VAR . '=' . $parent, $html, 'No-JS link expands the branch.' );
	}

	public function test_expanded_branch_shows_every_reply(): void {
		$post_id                  = self::factory()->post->create();
		list( $parent, $replies ) = $this->branch( $post_id, 4 );
		$this->view_post( $post_id, array( ThreadRenderer::EXPAND_QUERY_VAR => $parent ) );

		$html = $this->renderer( array( 'replies_shown' => 2 ) )->render( $post_id );

		$this->assertStringContainsString( 'id="comment-' . $replies[3] . '"', $html );
		$this->assertStringNotContainsString( 'more replies', $html );
	}

	public function test_render_more_replies_returns_the_rest_in_batches(): void {
		$post_id                  = self::factory()->post->create();
		list( $parent, $replies ) = $this->branch( $post_id, 5 );
		$renderer                 = $this->renderer( array( 'replies_shown' => 2 ) );

		$batch = $renderer->render_more_replies( get_comment( $parent ), 2 );

		$this->assertStringContainsString( 'id="comment-' . $replies[2] . '"', $batch['html'] );
		$this->assertStringContainsString( 'id="comment-' . $replies[3] . '"', $batch['html'] );
		$this->assertStringNotContainsString( 'id="comment-' . $replies[4] . '"', $batch['html'] );
		$this->assertSame( 1, $batch['remaining'] );
	}

	public function test_replies_past_max_depth_render_in_a_flat_list(): void {
		$post_id = self::factory()->post->create();
		$a       = $this->comment( $post_id );
		$b       = $this->comment( $post_id, array( 'comment_parent' => $a ) );
		$this->comment( $post_id, array( 'comment_parent' => $b ) );
		$this->view_post( $post_id );

		$html = $this->renderer( array( 'max_depth' => 2 ) )->render( $post_id );

		$this->assertStringContainsString( 'convocept-replies--flat', $html );
	}

	public function test_position_follows_sort(): void {
		$post_id = self::factory()->post->create();
		$first   = $this->comment( $post_id );
		$second  = $this->comment( $post_id );
		$reply   = $this->comment( $post_id, array( 'comment_parent' => $first ) );
		$query   = new ThreadQuery();

		$this->assertSame( 1, $query->position( $post_id, $second, 'oldest' ) );
		$this->assertSame( 0, $query->position( $post_id, $second, 'newest' ) );
		$this->assertNull( $query->position( $post_id, $reply, 'oldest' ), 'Replies have no top-level position.' );
	}

	public function test_comment_url_points_at_the_right_page_and_branch(): void {
		$post_id = self::factory()->post->create();
		$ids     = array();

		for ( $i = 0; $i < 5; $i++ ) {
			$ids[] = $this->comment( $post_id );
		}

		$reply    = $this->comment( $post_id, array( 'comment_parent' => $ids[4] ) );
		$renderer = $this->renderer( array( 'per_page' => 2 ) );

		$this->assertStringNotContainsString( 'cpage', $renderer->comment_url( get_comment( $ids[0] ) ) );
		$this->assertStringContainsString( 'cpage=3', $renderer->comment_url( get_comment( $ids[4] ) ) );
		$this->assertStringEndsWith( '#comment-' . $ids[4], $renderer->comment_url( get_comment( $ids[4] ) ) );

		$reply_url = $renderer->comment_url( get_comment( $reply ) );
		$this->assertStringContainsString( 'cpage=3', $reply_url );
		$this->assertStringContainsString( ThreadRenderer::EXPAND_QUERY_VAR . '=' . $ids[4], $reply_url );
	}

	public function test_get_comment_link_is_filtered_for_approved_comments_only(): void {
		$post_id  = self::factory()->post->create();
		$approved = $this->comment( $post_id );
		$pending  = $this->comment( $post_id, array( 'comment_approved' => 0 ) );
		$links    = new CommentLinks( $this->renderer() );

		$this->assertStringEndsWith( '#comment-' . $approved, $links->filter_link( 'core-link', get_comment( $approved ) ) );
		$this->assertSame( 'core-link', $links->filter_link( 'core-link', get_comment( $pending ) ) );
	}
}
