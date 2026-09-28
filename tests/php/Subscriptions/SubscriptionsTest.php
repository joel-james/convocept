<?php
/**
 * Tests for subscribing, confirming, tokens and forms.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Subscriptions;

use Convocept\Plugin;
use Convocept\Subscriptions\Repository;
use Convocept\Subscriptions\Tokens;
use Convocept\Tests\Rest\RestTestCase;

/**
 * @covers \Convocept\Subscriptions\Subscriptions
 * @covers \Convocept\Subscriptions\Repository
 * @covers \Convocept\Subscriptions\Tokens
 * @covers \Convocept\Subscriptions\SubscribeForm
 * @covers \Convocept\Rest\SubscriptionsEndpoint
 */
class SubscriptionsTest extends RestTestCase {

	public function set_up(): void {
		parent::set_up();
		reset_phpmailer_instance();

		// wp-env's tests site runs on "localhost", which makes an invalid From address.
		add_filter(
			'wp_mail_from',
			static function () {
				return 'wordpress@example.org';
			}
		);
		Plugin::instance()->settings()->flush();
	}

	/**
	 * Get emails sent during the test.
	 *
	 * @return array<int, object>
	 */
	private function sent(): array {
		return tests_retrieve_phpmailer_instance()->mock_sent;
	}

	public function test_new_address_gets_a_confirmation_email_and_stays_pending(): void {
		$post_id = self::factory()->post->create();
		$status  = Plugin::instance()->subscriptions()->subscribe( 'new@example.com', $post_id, 0, false );
		$mails   = $this->sent();

		$this->assertSame( Repository::PENDING, $status );
		$this->assertCount( 1, $mails );
		$this->assertSame( 'new@example.com', $mails[0]['to'][0][0] );
		$this->assertStringContainsString( 'convocept=confirm', $mails[0]['body'] );
		$this->assertStringNotContainsString( 'new%40example.com', $mails[0]['body'], 'No email address in links.' );
		$this->assertStringNotContainsString( 'new@example.com&', $mails[0]['body'] );
	}

	public function test_only_verified_addresses_skip_confirmation(): void {
		$post_id       = self::factory()->post->create();
		$subscriptions = Plugin::instance()->subscriptions();
		$repository    = $subscriptions->repository();

		$this->assertSame( Repository::ACTIVE, $subscriptions->subscribe( 'known@example.com', $post_id, 0, true ) );
		$this->assertCount( 0, $this->sent() );

		// Anyone can type someone else's address: an active subscription proves nothing about the requester.
		$this->assertSame( Repository::PENDING, $subscriptions->subscribe( 'known@example.com', 0, 0, false ) );
		$this->assertCount( 1, $this->sent() );
		$site = wp_list_filter( $repository->for_email( $repository->hash_email( 'known@example.com' ) ), array( 'post_id' => 0 ) );
		$this->assertSame( Repository::PENDING, array_values( $site )[0]['status'] );

		// The answer never reveals that the address already follows the post.
		$this->assertSame( Repository::PENDING, $subscriptions->subscribe( 'known@example.com', $post_id, 0, false ) );
	}

	public function test_confirmation_emails_are_rate_limited_per_address(): void {
		$subscriptions = Plugin::instance()->subscriptions();

		$subscriptions->subscribe( 'spam-target@example.com', self::factory()->post->create(), 0, false );
		$subscriptions->subscribe( 'spam-target@example.com', self::factory()->post->create(), 0, false );

		$this->assertCount( 1, $this->sent() );
	}

	public function test_tokens_are_purpose_bound_forgery_proof_and_revocable(): void {
		$subscriptions = Plugin::instance()->subscriptions();
		$subscriptions->subscribe( 'token@example.com', self::factory()->post->create(), 0, false );
		$row    = $subscriptions->repository()->for_email( $subscriptions->repository()->hash_email( 'token@example.com' ) )[0];
		$tokens = new Tokens();

		$confirm = $tokens->create( $row, Tokens::CONFIRM );

		$this->assertNotNull( $subscriptions->from_token( $confirm, Tokens::CONFIRM ) );
		$this->assertNull( $subscriptions->from_token( $confirm, Tokens::MANAGE ), 'A confirm token cannot manage.' );
		$this->assertNull( $subscriptions->from_token( $row['id'] . '.' . str_repeat( 'a', 40 ), Tokens::CONFIRM ) );
		$this->assertNull( $subscriptions->from_token( 'garbage', Tokens::CONFIRM ) );

		$subscriptions->repository()->update( $row['id'], array( 'token_key' => 'rotated-key-rotated-key-rotated!' ) );
		$this->assertNull( $subscriptions->from_token( $confirm, Tokens::CONFIRM ), 'Rotating the key revokes old links.' );
	}

	public function test_confirm_expires_after_48_hours(): void {
		$subscriptions = Plugin::instance()->subscriptions();
		$subscriptions->subscribe( 'late@example.com', self::factory()->post->create(), 0, false );
		$row = $subscriptions->repository()->for_email( $subscriptions->repository()->hash_email( 'late@example.com' ) )[0];

		$row['created_at'] = gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS );
		$this->assertFalse( $subscriptions->confirm( $row ) );

		$row['created_at'] = gmdate( 'Y-m-d H:i:s' );
		$this->assertTrue( $subscriptions->confirm( $row ) );
		$this->assertSame( Repository::ACTIVE, $subscriptions->repository()->find( $row['id'] )['status'] );
	}

	public function test_unsubscribe_one_or_all_and_resubscribe_needs_confirming(): void {
		$subscriptions = Plugin::instance()->subscriptions();
		$a             = self::factory()->post->create();
		$b             = self::factory()->post->create();
		$subscriptions->subscribe( 'many@example.com', $a, 0, true );
		$subscriptions->subscribe( 'many@example.com', $b, 0, true );
		$rows = $subscriptions->repository()->for_email( $subscriptions->repository()->hash_email( 'many@example.com' ) );

		$subscriptions->unsubscribe( $rows[0] );
		$this->assertSame( Repository::UNSUBSCRIBED, $subscriptions->repository()->find( $rows[0]['id'] )['status'] );
		$this->assertSame( Repository::ACTIVE, $subscriptions->repository()->find( $rows[1]['id'] )['status'] );

		$subscriptions->unsubscribe( $rows[1], true );
		$this->assertSame( Repository::UNSUBSCRIBED, $subscriptions->repository()->find( $rows[1]['id'] )['status'] );

		$this->assertSame( Repository::PENDING, $subscriptions->subscribe( 'many@example.com', $a, 0, false ), 'Coming back after unsubscribing starts over.' );
	}

	public function test_subscribe_endpoint_answers_the_same_for_everyone(): void {
		$post_id = self::factory()->post->create();
		Plugin::instance()->subscriptions()->subscribe( 'already@example.com', $post_id, 0, true );

		$known   = $this->dispatch(
			'POST',
			'subscriptions',
			array(
				'email'   => 'already@example.com',
				'post_id' => $post_id,
			)
		)->get_data();
		$unknown = $this->dispatch(
			'POST',
			'subscriptions',
			array(
				'email'   => 'fresh@example.com',
				'post_id' => $post_id,
			)
		)->get_data();

		$this->assertSame( $known, $unknown );
	}

	public function test_subscribe_endpoint_validation_honeypot_and_rate_limit(): void {
		$post_id = self::factory()->post->create();

		$this->assertSame(
			400,
			$this->dispatch(
				'POST',
				'subscriptions',
				array(
					'email'   => 'not-an-email',
					'post_id' => $post_id,
				)
			)->get_status()
		);

		$bot = $this->dispatch(
			'POST',
			'subscriptions',
			array(
				'email'    => 'bot@example.com',
				'post_id'  => $post_id,
				'honeypot' => 'x',
			)
		);
		$this->assertSame( 200, $bot->get_status() );
		$this->assertSame( array(), Plugin::instance()->subscriptions()->repository()->for_email( Plugin::instance()->subscriptions()->repository()->hash_email( 'bot@example.com' ) ) );

		for ( $i = 0; $i < 4; $i++ ) {
			$this->dispatch(
				'POST',
				'subscriptions',
				array(
					'email'   => "r{$i}@example.com",
					'post_id' => $post_id,
				)
			);
		}

		$this->assertSame(
			400,
			$this->dispatch(
				'POST',
				'subscriptions',
				array(
					'email'   => 'last@example.com',
					'post_id' => $post_id,
				)
			)->get_status()
		);
	}

	public function test_logged_in_reader_subscribes_their_own_address_directly(): void {
		$user = self::factory()->user->create( array( 'user_email' => 'member@example.com' ) );
		wp_set_current_user( $user );

		$data = $this->dispatch(
			'POST',
			'subscriptions',
			array(
				'email'   => 'member@example.com',
				'post_id' => self::factory()->post->create(),
			)
		)->get_data();

		$this->assertSame( 'You are subscribed.', $data['message'] );
		$this->assertCount( 0, $this->sent() );
	}

	public function test_composer_checkboxes_create_subscriptions(): void {
		$post_id  = self::factory()->post->create();
		$response = $this->post_comment(
			$post_id,
			array(
				'email'             => 'commenter@example.com',
				'subscribe_replies' => true,
				'subscribe_post'    => true,
			)
		)->get_data();

		$rows = Plugin::instance()->subscriptions()->repository()->for_email( Plugin::instance()->subscriptions()->repository()->hash_email( 'commenter@example.com' ) );

		$this->assertSame( Repository::PENDING, $response['subscription'] );
		$this->assertCount( 2, $rows );
		$this->assertSame( array( $response['id'], 0 ), array_column( $rows, 'comment_id' ) );
		$this->assertCount( 1, $this->sent(), 'One confirmation email, not two.' );
	}

	public function test_subscribe_form_renders_in_thread_when_open(): void {
		$post_id = self::factory()->post->create();
		$this->view_post( $post_id );

		$html = $this->renderer()->render( $post_id );

		$this->assertStringContainsString( 'data-convocept-subscribe-form', $html );
		$this->assertStringContainsString( 'name="convocept_subscribe_replies"', $html );
		$this->assertStringContainsString( 'name="post_id" value="0"', do_shortcode( '[convocept_subscribe scope="site"]' ) );
	}
}
