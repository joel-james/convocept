<?php
/**
 * Tests for Convocept's checks on the plain (no-JavaScript) form.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Moderation;

use Convocept\Comments\Submission;
use Convocept\Moderation\Decision;
use Convocept\Moderation\Pipeline;
use Convocept\Security\FormToken;
use Convocept\Tests\TestCase;
use WPDieException;

/**
 * @covers \Convocept\Comments\Submission
 * @covers \Convocept\Moderation\Pipeline
 * @covers \Convocept\Moderation\Checks\HoneypotCheck
 * @covers \Convocept\Moderation\Checks\TimingCheck
 */
class FormSubmissionTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		remove_action( 'check_comment_flood', 'check_comment_flood_db' );
		update_option( 'comment_previously_approved', 0 );
	}

	public function tear_down(): void {
		add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Submit like wp-comments-post.php does.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $fields  Extra form fields.
	 * @return \WP_Comment|\WP_Error
	 */
	private function submit_form( int $post_id, array $fields ) {
		$_POST = array_merge(
			array(
				'comment_post_ID' => $post_id,
				'comment'         => 'Plain form ' . wp_generate_password( 6, false ),
				'author'          => 'Form Guest',
				'email'           => 'form@example.com',
			),
			$fields
		);

		return wp_handle_comment_submission( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Simulates core's public comment form.
	}

	public function test_valid_form_submission_is_approved(): void {
		$comment = $this->submit_form(
			self::factory()->post->create(),
			array(
				Submission::FORM_MARKER => '1',
				Submission::TOKEN_FIELD => ( new FormToken() )->create( time() - 60 ),
			)
		);

		$this->assertSame( '1', $comment->comment_approved );
		$this->assertSame( '', get_comment_meta( (int) $comment->comment_ID, Submission::FORMAT_META_KEY, true ), 'Plain form comments are not Markdown.' );
	}

	public function test_form_honeypot_marks_spam(): void {
		$comment = $this->submit_form(
			self::factory()->post->create(),
			array(
				Submission::FORM_MARKER => '1',
				Submission::TOKEN_FIELD => ( new FormToken() )->create( time() - 60 ),
				'convocept_hp'          => 'bot',
			)
		);

		$this->assertSame( 'spam', $comment->comment_approved );
	}

	public function test_form_without_valid_token_is_held(): void {
		$comment = $this->submit_form(
			self::factory()->post->create(),
			array(
				Submission::FORM_MARKER => '1',
				Submission::TOKEN_FIELD => '123.forged',
			)
		);

		$this->assertSame( '0', $comment->comment_approved );
	}

	public function test_other_submissions_are_untouched(): void {
		$comment = $this->submit_form( self::factory()->post->create(), array( 'convocept_hp' => 'ignored without marker' ) );

		$this->assertSame( '1', $comment->comment_approved );
		$this->assertSame( '', get_comment_meta( (int) $comment->comment_ID, Submission::LOG_META_KEY, true ) );
	}

	public function test_form_rate_limit_stops_request(): void {
		$post_id = self::factory()->post->create();
		$fields  = array(
			Submission::FORM_MARKER => '1',
			Submission::TOKEN_FIELD => ( new FormToken() )->create( time() - 60 ),
		);

		for ( $i = 0; $i < 5; $i++ ) {
			$this->submit_form( $post_id, $fields );
		}

		$this->expectException( WPDieException::class );
		$this->submit_form( $post_id, $fields );
	}

	public function test_pipeline_only_tightens(): void {
		$pipeline = new Pipeline();
		$hold     = new Decision( Decision::HOLD, 'x', 'r' );
		$approve  = new Decision( Decision::APPROVE, 'x', 'r' );

		$this->assertSame( 0, $pipeline->apply( 1, $hold ) );
		$this->assertSame( 'spam', $pipeline->apply( 'spam', $hold ) );
		$this->assertSame( 0, $pipeline->apply( 0, $approve ) );
		$this->assertSame( 1, $pipeline->apply( 1, null ) );
		$this->assertSame( Decision::HOLD, ( new Decision( 'bogus', 'x', 'r' ) )->verdict() );
	}
}
