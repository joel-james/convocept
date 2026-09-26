<?php
/**
 * Tests for IP resolution, rate limiting and form tokens.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Security;

use Convocept\Security\FormToken;
use Convocept\Security\IpResolver;
use Convocept\Security\RateLimiter;
use Convocept\Settings\Settings;
use Convocept\Tests\TestCase;

/**
 * @covers \Convocept\Security\IpResolver
 * @covers \Convocept\Security\RateLimiter
 * @covers \Convocept\Security\FormToken
 */
class SecurityTest extends TestCase {

	/**
	 * Build a resolver with settings.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return IpResolver
	 */
	private function resolver( array $settings = array() ): IpResolver {
		update_option( Settings::OPTION, $settings );

		return new IpResolver( new Settings() );
	}

	public function test_forwarded_header_ignored_without_trusted_proxy(): void {
		$ip = $this->resolver()->resolve(
			array(
				'REMOTE_ADDR'          => '198.51.100.7',
				'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
			)
		);

		$this->assertSame( '198.51.100.7', $ip );
	}

	public function test_forwarded_header_used_from_trusted_proxy(): void {
		$ip = $this->resolver( array( 'trusted_proxies' => array( '10.0.0.0/8' ) ) )->resolve(
			array(
				'REMOTE_ADDR'          => '10.1.2.3',
				'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 203.0.113.9, 10.0.0.5',
			)
		);

		$this->assertSame( '203.0.113.9', $ip, 'Right-most untrusted hop, not the spoofable left-most.' );
	}

	public function test_custom_header_and_ipv6_ranges(): void {
		$resolver = $this->resolver(
			array(
				'trusted_proxies' => array( '2001:db8::/32' ),
				'proxy_header'    => 'CF-Connecting-IP',
			)
		);

		$this->assertSame(
			'203.0.113.9',
			$resolver->resolve(
				array(
					'REMOTE_ADDR'           => '2001:db8::1',
					'HTTP_CF_CONNECTING_IP' => '203.0.113.9',
				)
			)
		);
		$this->assertFalse( $resolver->in_ranges( '2001:db9::1', array( '2001:db8::/32' ) ) );
		$this->assertTrue( $resolver->in_ranges( '192.0.2.200', array( '192.0.2.128/25' ) ) );
		$this->assertFalse( $resolver->in_ranges( '192.0.2.100', array( '192.0.2.128/25' ) ) );
	}

	public function test_invalid_forwarded_value_falls_back(): void {
		$ip = $this->resolver( array( 'trusted_proxies' => array( '10.0.0.1' ) ) )->resolve(
			array(
				'REMOTE_ADDR'          => '10.0.0.1',
				'HTTP_X_FORWARDED_FOR' => 'not-an-ip',
			)
		);

		$this->assertSame( '10.0.0.1', $ip );
	}

	public function test_invalid_proxy_settings_are_dropped(): void {
		$clean = ( new Settings() )->sanitize(
			array(
				'trusted_proxies' => array( '10.0.0.0/8', 'bogus', '1.2.3.4/99', '2001:db8::/32' ),
				'proxy_header'    => 'X-Evil',
			)
		);

		$this->assertSame( array( '10.0.0.0/8', '2001:db8::/32' ), $clean['trusted_proxies'] );
		$this->assertSame( 'X-Forwarded-For', $clean['proxy_header'] );
	}

	public function test_rate_limiter_blocks_after_limit(): void {
		$limiter = new RateLimiter();
		$key     = wp_generate_password( 8, false );

		$this->assertTrue( $limiter->attempt( 'test', $key, 2, 60 ) );
		$this->assertTrue( $limiter->attempt( 'test', $key, 2, 60 ) );
		$this->assertFalse( $limiter->attempt( 'test', $key, 2, 60 ) );
		$this->assertTrue( $limiter->attempt( 'test', 'someone-else', 2, 60 ) );
	}

	public function test_rate_limit_can_be_disabled_by_filter(): void {
		add_filter( 'convocept_rate_limit', '__return_zero' );
		$limiter = new RateLimiter();

		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertTrue( $limiter->attempt( 'test', 'k', 1, 60 ) );
		}

		remove_filter( 'convocept_rate_limit', '__return_zero' );
	}

	public function test_form_token_round_trip_and_forgery(): void {
		$tokens = new FormToken();
		$token  = $tokens->create( 1700000000 );

		$this->assertSame( 1700000000, $tokens->time( $token ) );
		$this->assertNull( $tokens->time( '1600000000.' . substr( $token, 11 ) ) );
		$this->assertNull( $tokens->time( 'garbage' ) );
		$this->assertNull( $tokens->time( array() ) );
	}
}
