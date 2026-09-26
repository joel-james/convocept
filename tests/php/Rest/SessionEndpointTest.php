<?php
/**
 * Tests for the session endpoint.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Rest;

/**
 * @covers \Convocept\Rest\SessionEndpoint
 */
class SessionEndpointTest extends RestTestCase {

	/**
	 * User with a logged-in cookie set for the request.
	 *
	 * @return int User ID.
	 */
	private function cookie_user(): int {
		$user_id                     = self::factory()->user->create( array( 'display_name' => 'Cookie User' ) );
		$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user_id, time() + HOUR_IN_SECONDS, 'logged_in' );

		return $user_id;
	}

	public function tear_down(): void {
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
		parent::tear_down();
	}

	public function test_guest_session(): void {
		$data = $this->dispatch( 'GET', 'session' )->get_data();

		$this->assertFalse( $data['logged_in'] );
		$this->assertNull( $data['user'] );
		$this->assertSame( '', $data['nonce'] );
	}

	public function test_cookie_user_recognised_for_same_origin_request(): void {
		$user_id = $this->cookie_user();
		$data    = $this->dispatch( 'GET', 'session', array(), array( 'X-Convocept' => '1' ) )->get_data();

		$this->assertTrue( $data['logged_in'] );
		$this->assertSame( $user_id, $data['user']['id'] );
		$this->assertSame( 'Cookie User', $data['user']['name'] );
		$this->assertNotEmpty( $data['nonce'] );
	}

	public function test_cookie_ignored_without_our_header(): void {
		$this->cookie_user();

		$this->assertFalse( $this->dispatch( 'GET', 'session' )->get_data()['logged_in'] );
	}

	public function test_cookie_ignored_for_cross_site_requests(): void {
		$this->cookie_user();

		$cross_origin = $this->dispatch(
			'GET',
			'session',
			array(),
			array(
				'X-Convocept' => '1',
				'Origin'      => 'https://evil.example',
			)
		)->get_data();

		$cross_site = $this->dispatch(
			'GET',
			'session',
			array(),
			array(
				'X-Convocept'    => '1',
				'Sec-Fetch-Site' => 'cross-site',
			)
		)->get_data();

		$jsonp = $this->dispatch( 'GET', 'session', array( '_jsonp' => 'steal' ), array( 'X-Convocept' => '1' ) )->get_data();

		$this->assertFalse( $cross_origin['logged_in'] );
		$this->assertFalse( $cross_site['logged_in'] );
		$this->assertFalse( $jsonp['logged_in'] );
	}

	public function test_moderator_flag(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertTrue( $this->dispatch( 'GET', 'session' )->get_data()['can_moderate'] );
	}
}
