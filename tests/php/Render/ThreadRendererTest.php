<?php
/**
 * Tests for the thread renderer and templates.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Render;

use Convocept\Comments\Sort;
use Convocept\Tests\TestCase;

/**
 * @covers \Convocept\Render\ThreadRenderer
 * @covers \Convocept\Render\CommentPresenter
 */
class ThreadRendererTest extends TestCase {

	public function test_renders_thread_with_count_form_and_comments(): void {
		$post_id = self::factory()->post->create();
		$id      = $this->comment( $post_id, array( 'comment_content' => 'Hello thread' ) );
		$this->view_post( $post_id );

		$html = $this->renderer()->render( $post_id );

		$this->assertStringContainsString( 'id="comments"', $html );
		$this->assertStringContainsString( 'id="commentform"', $html );
		$this->assertStringContainsString( 'id="comment-' . $id . '"', $html );
		$this->assertStringContainsString( 'Hello thread', $html );
		$this->assertStringContainsString( '1 comment', $html );
		$this->assertStringContainsString( 'name="comment_post_ID" value="' . $post_id . '"', $html );
	}

	public function test_output_contains_no_per_user_data(): void {
		$admin   = self::factory()->user->create(
			array(
				'role'         => 'administrator',
				'display_name' => 'Secret Admin Name',
			)
		);
		$post_id = self::factory()->post->create();
		$this->comment( $post_id );
		$this->comment(
			$post_id,
			array(
				'comment_approved'     => 0,
				'comment_author_email' => 'guest@example.com',
				'comment_content'      => 'MY PENDING COMMENT',
			)
		);

		wp_set_current_user( $admin );
		$_COOKIE[ 'comment_author_email_' . COOKIEHASH ] = 'guest@example.com';
		$_COOKIE[ 'comment_author_' . COOKIEHASH ]       = 'Cookie Guest Name';
		$this->view_post( $post_id );

		$html = $this->renderer()->render( $post_id );

		unset( $_COOKIE[ 'comment_author_email_' . COOKIEHASH ], $_COOKIE[ 'comment_author_' . COOKIEHASH ] );

		foreach ( array( '_wpnonce', 'nonce', '_wp_unfiltered_html_comment', 'Secret Admin Name', 'Logged in as', 'Cookie Guest Name', 'guest@example.com', 'MY PENDING COMMENT', 'wp-admin/comment.php' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $html, "Found per-user data: {$needle}" );
		}
	}

	public function test_password_protected_post_renders_nothing(): void {
		$post_id = self::factory()->post->create( array( 'post_password' => 'secret' ) );
		$this->comment( $post_id );
		$this->view_post( $post_id );

		$this->assertSame( '', $this->renderer()->render( $post_id ) );
	}

	public function test_closed_post_without_comments_renders_nothing(): void {
		$post_id = self::factory()->post->create( array( 'comment_status' => 'closed' ) );
		$this->view_post( $post_id );

		$this->assertSame( '', $this->renderer()->render( $post_id ) );
	}

	public function test_closed_post_with_comments_shows_thread_without_form(): void {
		$post_id = self::factory()->post->create( array( 'comment_status' => 'closed' ) );
		$this->comment( $post_id );
		$this->view_post( $post_id );

		$html = $this->renderer()->render( $post_id );

		$this->assertStringNotContainsString( 'commentform', $html );
		$this->assertStringContainsString( 'Comments are closed.', $html );
	}

	public function test_registration_required_hides_form_and_shows_login_link(): void {
		update_option( 'comment_registration', 1 );
		$post_id = self::factory()->post->create();
		$this->view_post( $post_id );

		$html = $this->renderer()->render( $post_id );

		update_option( 'comment_registration', 0 );

		$this->assertMatchesRegularExpression( '/<form[^>]+id="commentform"[^>]*\shidden/s', $html, 'Form is hidden until the script confirms a login.' );
		$this->assertStringContainsString( 'wp-login.php', $html );
	}

	public function test_replies_nest_until_max_depth_then_flatten(): void {
		$post_id = self::factory()->post->create();
		$a       = $this->comment( $post_id, array( 'comment_author' => 'Alice' ) );
		$b       = $this->comment(
			$post_id,
			array(
				'comment_parent' => $a,
				'comment_author' => 'Bob',
			)
		);
		$c       = $this->comment(
			$post_id,
			array(
				'comment_parent' => $b,
				'comment_author' => 'Carol',
			)
		);
		$this->view_post( $post_id );

		$html = $this->renderer( array( 'max_depth' => 2 ) )->render( $post_id );

		$this->assertStringContainsString( 'convocept-comment--depth-1', $html );
		$this->assertSame( 2, substr_count( $html, 'convocept-comment--depth-2' ), 'Bob and flattened Carol share depth 2.' );
		$this->assertStringContainsString( 'Replying to Bob', $html );
		$this->assertLessThan( strpos( $html, 'id="comment-' . $c . '"' ), strpos( $html, 'id="comment-' . $b . '"' ) );
	}

	public function test_author_badge_matches_user_id_only(): void {
		$author  = self::factory()->user->create( array( 'display_name' => 'Post Author' ) );
		$post_id = self::factory()->post->create( array( 'post_author' => $author ) );
		$this->comment(
			$post_id,
			array(
				'user_id'        => $author,
				'comment_author' => 'Post Author',
			)
		);
		$this->comment(
			$post_id,
			array(
				'comment_author'       => 'Post Author',
				'comment_author_email' => get_userdata( $author )->user_email,
			)
		);
		$this->view_post( $post_id );

		$html = $this->renderer()->render( $post_id );

		$this->assertSame( 1, substr_count( $html, 'convocept-badge--author' ) );
	}

	public function test_guest_fields_are_escaped(): void {
		$post_id = self::factory()->post->create();
		$this->comment(
			$post_id,
			array(
				'comment_author'     => '<script>alert(1)</script>',
				'comment_author_url' => 'javascript:alert(1)',
			)
		);
		$this->view_post( $post_id );

		$html = $this->renderer()->render( $post_id );

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_pagination_links_and_sort_urls(): void {
		$post_id = self::factory()->post->create();
		for ( $i = 0; $i < 5; $i++ ) {
			$this->comment( $post_id );
		}
		$this->view_post( $post_id, array( 'cpage' => 2 ) );

		$renderer = $this->renderer( array( 'per_page' => 2 ) );
		$html     = $renderer->render( $post_id );

		$this->assertStringContainsString( 'convocept-pagination', $html );
		$this->assertStringContainsString( 'aria-current="page">2<', $html );
		$this->assertStringContainsString( 'rel="prev"', $html );
		$this->assertStringContainsString( 'rel="next"', $html );
		$this->assertStringContainsString( Sort::QUERY_VAR . '=newest', $renderer->page_url( $post_id, 1, Sort::NEWEST ) );
		$this->assertStringNotContainsString( Sort::QUERY_VAR, $renderer->page_url( $post_id, 1, Sort::OLDEST ) );
	}

	public function test_invalid_replytocom_is_ignored(): void {
		$post_id = self::factory()->post->create();
		$other   = $this->comment( self::factory()->post->create() );
		$this->view_post( $post_id );
		$_GET['replytocom'] = (string) $other;

		$html = $this->renderer()->render( $post_id );

		unset( $_GET['replytocom'] );

		$this->assertStringContainsString( 'name="comment_parent" value="0"', $html );
	}

	public function test_valid_replytocom_sets_parent(): void {
		$post_id = self::factory()->post->create();
		$parent  = $this->comment( $post_id, array( 'comment_author' => 'Dana' ) );
		$this->view_post( $post_id );
		$_GET['replytocom'] = (string) $parent;

		$html = $this->renderer()->render( $post_id );

		unset( $_GET['replytocom'] );

		$this->assertStringContainsString( 'name="comment_parent" value="' . $parent . '"', $html );
		$this->assertStringContainsString( 'Replying to Dana', $html );
	}

	public function test_enabled_respects_settings_and_filter(): void {
		$post_id = self::factory()->post->create();
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$renderer = $this->renderer( array( 'post_types' => array( 'post' ) ) );
		$this->assertTrue( $renderer->is_enabled_for( $post_id ) );
		$this->assertFalse( $renderer->is_enabled_for( $page_id ) );

		add_filter( 'convocept_enabled', '__return_false' );
		$this->assertFalse( $renderer->is_enabled_for( $post_id ) );
		remove_filter( 'convocept_enabled', '__return_false' );
	}

	public function test_held_comment_notice_only_with_a_valid_moderation_hash(): void {
		$post_id = self::factory()->post->create();
		$this->comment( $post_id );
		$held = $this->comment(
			$post_id,
			array(
				'comment_approved'     => 0,
				'comment_author_email' => 'held@example.com',
				// Core shows a held comment to its author for 10 minutes only.
				'comment_date'         => current_time( 'mysql', true ),
				'comment_date_gmt'     => current_time( 'mysql', true ),
			)
		);
		$this->view_post( $post_id );

		$_GET['unapproved']      = (string) $held;
		$_GET['moderation-hash'] = 'wrong';
		$this->assertStringNotContainsString( 'awaiting moderation', $this->renderer()->render( $post_id ) );

		$_GET['moderation-hash'] = wp_hash( get_comment( $held )->comment_date_gmt );
		$this->assertStringContainsString( 'awaiting moderation', $this->renderer()->render( $post_id ) );

		unset( $_GET['unapproved'], $_GET['moderation-hash'] );
	}
}
