<?php
/**
 * Tests for notifications and the background queue.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Subscriptions;

use Convocept\Database\Migrations\SubscriptionsTable;
use Convocept\Plugin;
use Convocept\Subscriptions\Notifier;
use Convocept\Subscriptions\Queue;
use Convocept\Tests\TestCase;

/**
 * @covers \Convocept\Subscriptions\Notifier
 * @covers \Convocept\Subscriptions\Queue
 * @covers \Convocept\Subscriptions\Repository::recipients
 * @covers \Convocept\Subscriptions\Mailer
 */
class NotifierTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		reset_phpmailer_instance();

		// Behave like an SMTP sender: with PHP's mail() transport PHPMailer
		// RFC 2047-encodes long headers, including List-Unsubscribe.
		add_action(
			'phpmailer_init',
			static function ( $phpmailer ) {
				$phpmailer->Mailer = 'smtp'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer API.
			}
		);

		// wp-env's tests site runs on "localhost", which makes an invalid From address.
		add_filter(
			'wp_mail_from',
			static function () {
				return 'wordpress@example.org';
			}
		);
		delete_option( Queue::JOBS_OPTION );
		delete_option( Queue::LOCK_OPTION );
	}

	/**
	 * Emails sent so far.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sent(): array {
		return tests_retrieve_phpmailer_instance()->mock_sent;
	}

	/**
	 * Recipient addresses of the emails sent so far.
	 *
	 * @return string[]
	 */
	private function recipients(): array {
		$to = array_map(
			static function ( $mail ) {
				return $mail['to'][0][0];
			},
			$this->sent()
		);
		sort( $to );

		return $to;
	}

	/**
	 * Run the queue until it is empty.
	 *
	 * @return void
	 */
	private function drain(): void {
		$queue = new Queue( Plugin::instance()->settings() );

		for ( $i = 0; $i < 500; $i++ ) {
			if ( ! $queue->jobs() ) {
				break;
			}

			Plugin::instance()->notifier()->run();
		}
	}

	public function test_post_reply_and_site_subscribers_get_one_email_each(): void {
		$post_id       = self::factory()->post->create( array( 'post_title' => 'Big News' ) );
		$subscriptions = Plugin::instance()->subscriptions();
		$parent        = $this->comment( $post_id, array( 'comment_author_email' => 'parent@example.com' ) );
		$this->drain();

		$subscriptions->subscribe( 'post@example.com', $post_id, 0, true );
		$subscriptions->subscribe( 'parent@example.com', $post_id, $parent, true );
		$subscriptions->subscribe( 'site@example.com', 0, 0, true );
		$subscriptions->subscribe( 'both@example.com', $post_id, 0, true );
		$subscriptions->subscribe( 'both@example.com', 0, 0, true );
		$subscriptions->subscribe( 'author@example.com', $post_id, 0, true );
		$subscriptions->subscribe( 'other-post@example.com', self::factory()->post->create(), 0, true );
		reset_phpmailer_instance();

		$this->comment(
			$post_id,
			array(
				'comment_parent'       => $parent,
				'comment_author'       => 'Replier',
				'comment_author_email' => 'author@example.com',
			)
		);
		$this->drain();

		$this->assertSame( array( 'both@example.com', 'parent@example.com', 'post@example.com', 'site@example.com' ), $this->recipients() );

		foreach ( $this->sent() as $mail ) {
			$this->assertStringContainsString( 'List-Unsubscribe: <', $mail['header'] );
			$this->assertStringContainsString( 'List-Unsubscribe-Post: List-Unsubscribe=One-Click', $mail['header'] );
			$this->assertStringNotContainsString( '%40', $mail['body'] );

			if ( 'parent@example.com' === $mail['to'][0][0] ) {
				$this->assertStringContainsString( 'replied to your comment', $mail['subject'] );
			}
		}
	}

	public function test_only_approved_comments_notify_and_only_once(): void {
		$post_id = self::factory()->post->create();
		Plugin::instance()->subscriptions()->subscribe( 'reader@example.com', $post_id, 0, true );
		reset_phpmailer_instance();

		$id = $this->comment( $post_id, array( 'comment_approved' => 0 ) );
		$this->drain();
		$this->assertCount( 0, $this->sent() );

		wp_set_comment_status( $id, 'approve' );
		$this->drain();
		$this->assertCount( 1, $this->sent() );

		wp_set_comment_status( $id, 'hold' );
		wp_set_comment_status( $id, 'approve' );
		$this->drain();
		$this->assertCount( 1, $this->sent(), 'Approving again does not resend.' );
		$this->assertNotEmpty( get_comment_meta( $id, Notifier::QUEUED_META_KEY, true ) );
	}

	public function test_unconfirmed_and_unsubscribed_addresses_get_nothing(): void {
		$post_id       = self::factory()->post->create();
		$subscriptions = Plugin::instance()->subscriptions();
		$subscriptions->subscribe( 'pending@example.com', $post_id, 0, false );
		$subscriptions->subscribe( 'gone@example.com', $post_id, 0, true );
		$subscriptions->unsubscribe( $subscriptions->repository()->for_email( $subscriptions->repository()->hash_email( 'gone@example.com' ) )[0] );
		reset_phpmailer_instance();

		$this->comment( $post_id );
		$this->drain();

		$this->assertCount( 0, $this->sent() );
	}

	public function test_five_thousand_subscribers_are_sent_in_background_batches(): void {
		global $wpdb;

		$post_id = self::factory()->post->create();
		$table   = SubscriptionsTable::name();
		$values  = array();
		$repo    = Plugin::instance()->subscriptions()->repository();

		for ( $i = 0; $i < 5000; $i++ ) {
			$email    = "bulk{$i}@example.com";
			$values[] = $wpdb->prepare( '(%s, %s, %d, 0, %s, %s, %s)', $email, $repo->hash_email( $email ), $post_id, 'active', 'key' . $i, gmdate( 'Y-m-d H:i:s' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Test fixture, values prepared above.
		$wpdb->query( "INSERT INTO {$table} (email, email_hash, post_id, comment_id, status, token_key, created_at) VALUES " . implode( ',', $values ) );

		// Approving the comment must only queue work.
		$started = microtime( true );
		$this->comment( $post_id );
		$this->assertLessThan( 1.0, microtime( true ) - $started, 'Posting a comment must not send emails inline.' );
		$this->assertCount( 0, $this->sent() );

		Plugin::instance()->notifier()->run();
		$this->assertCount( 50, $this->sent(), 'One run sends one batch.' );
		$this->assertNotFalse( wp_next_scheduled( Queue::HOOK ), 'The next batch is scheduled.' );

		$this->drain();
		$this->assertCount( 5000, $this->sent() );
		$this->assertCount( 5000, array_unique( $this->recipients() ), 'Nobody gets two emails.' );
	}

	public function test_comments_on_non_public_posts_are_never_emailed(): void {
		$private  = self::factory()->post->create( array( 'post_status' => 'private' ) );
		$password = self::factory()->post->create( array( 'post_password' => 'secret' ) );
		$public   = self::factory()->post->create();
		Plugin::instance()->subscriptions()->subscribe( 'everything@example.com', 0, 0, true );
		reset_phpmailer_instance();

		$this->comment( $private );
		$this->comment( $password );
		$later = $this->comment( $public );
		wp_update_post(
			array(
				'ID'          => $public,
				'post_status' => 'private',
			)
		);
		$this->drain();

		$this->assertCount( 0, $this->sent(), 'Private, protected, and made private after queueing.' );
		$this->assertNotEmpty( get_comment_meta( $later, Notifier::QUEUED_META_KEY, true ) );
	}
}
