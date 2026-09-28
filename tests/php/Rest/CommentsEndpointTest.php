<?php
/**
 * Tests for creating and editing comments over REST.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Rest;

use Convocept\Comments\EditWindow;
use Convocept\Comments\Submission;

/**
 * @covers \Convocept\Rest\CommentsEndpoint
 * @covers \Convocept\Comments\Submission
 * @covers \Convocept\Moderation\Pipeline
 */
class CommentsEndpointTest extends RestTestCase {

	public function test_guest_comment_is_created_as_markdown(): void {
		$post_id  = self::factory()->post->create();
		$response = $this->post_comment( $post_id, array( 'content' => 'Hi **there** <b>raw</b>' ) );
		$data     = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'approved', $data['status'] );
		$this->assertStringContainsString( '<strong>there</strong>', $data['html'] );
		$this->assertStringContainsString( '&lt;b&gt;raw&lt;/b&gt;', $data['html'] );
		$this->assertNotEmpty( $data['edit_token'] );
		$this->assertSame( 1, $data['total'] );
		$this->assertSame( '1 comment', $data['count_text'] );
		$this->assertSame( 'markdown', get_comment_meta( $data['id'], Submission::FORMAT_META_KEY, true ) );
		$this->assertSame( 'Hi **there** &lt;b&gt;raw&lt;/b&gt;', get_comment( $data['id'] )->comment_content );
		$this->assertSame( 'no-store, private', $response->get_headers()['Cache-Control'] );
	}

	public function test_honeypot_marks_spam_but_reports_pending(): void {
		$post_id = self::factory()->post->create();
		$data    = $this->post_comment( $post_id, array( 'honeypot' => 'I am a bot' ) )->get_data();

		$this->assertSame( 'pending', $data['status'] );
		$this->assertSame( 'spam', get_comment( $data['id'] )->comment_approved );
		$this->assertStringContainsString( 'honeypot', (string) get_comment_meta( $data['id'], Submission::LOG_META_KEY, true ) );
	}

	public function test_too_fast_submission_is_held(): void {
		$post_id = self::factory()->post->create();
		$data    = $this->post_comment( $post_id, array( 'elapsed' => 0.5 ) )->get_data();

		$this->assertSame( '0', get_comment( $data['id'] )->comment_approved );
		$this->assertStringContainsString( 'timing', (string) get_comment_meta( $data['id'], Submission::LOG_META_KEY, true ) );
	}

	public function test_markdown_links_respect_core_link_limit(): void {
		update_option( 'comment_max_links', 2 );
		$post_id = self::factory()->post->create();
		$data    = $this->post_comment( $post_id, array( 'content' => '[a](https://a.com) [b](https://b.com)' ) )->get_data();

		$this->assertSame( '0', get_comment( $data['id'] )->comment_approved );
	}

	public function test_core_spam_verdict_is_never_loosened(): void {
		$akismet = static function () {
			return 'spam';
		};
		add_filter( 'pre_comment_approved', $akismet, 10 );

		$post_id = self::factory()->post->create();
		$data    = $this->post_comment( $post_id )->get_data();

		remove_filter( 'pre_comment_approved', $akismet, 10 );

		$this->assertSame( 'spam', get_comment( $data['id'] )->comment_approved );
		$this->assertSame( 'pending', $data['status'] );
	}

	public function test_duplicate_comment_is_rejected_with_message(): void {
		$post_id = self::factory()->post->create();
		$this->post_comment( $post_id, array( 'content' => 'Same text' ) );
		$response = $this->post_comment( $post_id, array( 'content' => 'Same text' ) );

		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
		$this->assertSame( 'comment_duplicate', $response->get_data()['code'] );
	}

	public function test_rate_limit_rejects_bursts(): void {
		$post_id = self::factory()->post->create();

		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertSame( 201, $this->post_comment( $post_id )->get_status() );
		}

		$response = $this->post_comment( $post_id );

		$this->assertSame( 429, $response->get_status() );
		$this->assertSame( 'convocept_rate_limited', $response->get_data()['code'] );
	}

	public function test_before_submit_filter_can_reject(): void {
		$reject = static function () {
			return new \WP_Error( 'blocked', 'Blocked by add-on', array( 'status' => 403 ) );
		};
		add_filter( 'convocept_before_submit', $reject );

		$response = $this->post_comment( self::factory()->post->create() );

		remove_filter( 'convocept_before_submit', $reject );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'Blocked by add-on', $response->get_data()['message'] );
	}

	public function test_missing_required_fields_error_from_core(): void {
		$response = $this->post_comment( self::factory()->post->create(), array( 'email' => '' ) );

		$this->assertSame( 'require_name_email', $response->get_data()['code'] );
		$this->assertSame( 400, $response->get_status() );
	}

	public function test_disabled_post_is_not_available(): void {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'convocept_settings', array( 'post_types' => array( 'post' ) ) );
		\Convocept\Plugin::instance()->settings()->flush();

		$response = $this->post_comment( $page_id );

		\Convocept\Plugin::instance()->settings()->flush();

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_closed_post_rejected_by_core(): void {
		$post_id  = self::factory()->post->create( array( 'comment_status' => 'closed' ) );
		$response = $this->post_comment( $post_id );

		$this->assertSame( 'comment_closed', $response->get_data()['code'] );
	}

	public function test_guest_can_edit_with_token_within_window(): void {
		$post_id = self::factory()->post->create();
		$created = $this->post_comment( $post_id, array( 'content' => 'Before <tag>' ) )->get_data();

		$source = $this->dispatch( 'GET', "comments/{$created['id']}", array( 'edit_token' => $created['edit_token'] ) );
		$this->assertSame( 'Before <tag>', $source->get_data()['content'] );

		$response = $this->dispatch(
			'PATCH',
			"comments/{$created['id']}",
			array(
				'content'    => 'After *edit*',
				'edit_token' => $created['edit_token'],
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringContainsString( '<em>edit</em>', $response->get_data()['html'] );
		$this->assertStringContainsString( '(edited)', $response->get_data()['html'] );
		$this->assertNotEmpty( get_comment_meta( $created['id'], EditWindow::EDITED_META_KEY, true ) );
	}

	public function test_wrong_token_cannot_edit_or_read_source(): void {
		$created = $this->post_comment( self::factory()->post->create() )->get_data();

		$this->assertSame( 403, $this->dispatch( 'GET', "comments/{$created['id']}", array( 'edit_token' => 'nope' ) )->get_status() );
		$this->assertSame(
			403,
			$this->dispatch(
				'PATCH',
				"comments/{$created['id']}",
				array(
					'content'    => 'Hijacked',
					'edit_token' => 'nope',
				)
			)->get_status()
		);
	}

	public function test_edit_window_expires(): void {
		$created = $this->post_comment( self::factory()->post->create() )->get_data();
		$old     = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );

		wp_update_comment(
			array(
				'comment_ID'       => $created['id'],
				'comment_date'     => $old,
				'comment_date_gmt' => $old,
			)
		);

		$response = $this->dispatch(
			'PATCH',
			"comments/{$created['id']}",
			array(
				'content'    => 'Too late',
				'edit_token' => $created['edit_token'],
			)
		);

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_logged_in_author_edits_by_user_id(): void {
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user );

		$created = $this->post_comment( self::factory()->post->create() )->get_data();

		$this->assertSame( '', $created['edit_token'], 'Logged-in authors need no token.' );
		$this->assertSame( 200, $this->dispatch( 'PATCH', "comments/{$created['id']}", array( 'content' => 'Mine' ) )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, $this->dispatch( 'PATCH', "comments/{$created['id']}", array( 'content' => 'Not mine' ) )->get_status() );
	}

	public function test_non_markdown_comments_are_edited_in_admin(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$id = $this->comment( self::factory()->post->create() );

		$this->assertSame( 409, $this->dispatch( 'PATCH', "comments/{$id}", array( 'content' => 'x' ) )->get_status() );
	}

	public function test_edit_that_breaks_moderation_rules_is_held(): void {
		update_option( 'comment_max_links', 1 );
		$created = $this->post_comment( self::factory()->post->create(), array( 'content' => 'No links' ) )->get_data();

		$data = $this->dispatch(
			'PATCH',
			"comments/{$created['id']}",
			array(
				'content'    => 'Now https://spam.example',
				'edit_token' => $created['edit_token'],
			)
		)->get_data();

		$this->assertSame( 'pending', $data['status'] );
	}

	public function test_preview_renders_markdown(): void {
		$data = $this->dispatch( 'POST', 'preview', array( 'content' => '**b** <script>x</script>' ) )->get_data();

		$this->assertSame( '<p><strong>b</strong> &lt;script&gt;x&lt;/script&gt;</p>', $data['html'] );
	}

	public function test_moderators_skip_convocept_checks(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$data = $this->post_comment( self::factory()->post->create(), array( 'elapsed' => 0 ) )->get_data();

		$this->assertSame( 'approved', $data['status'] );
	}
}
