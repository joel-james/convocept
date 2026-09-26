<?php
/**
 * Client IP resolver.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Security;

use Convocept\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Works out the visitor's IP address without trusting spoofable headers.
 *
 * `REMOTE_ADDR` is used unless the request came from a proxy the site owner
 * has listed as trusted. Only then is the configured forwarding header read,
 * and for `X-Forwarded-For` the right-most address that is not itself a
 * trusted proxy is taken (the left-most entries are client-controlled).
 *
 * @since 1.0.0
 */
class IpResolver {

	/**
	 * Settings service.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Create the resolver.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get the visitor's IP address.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $server Server variables; defaults to $_SERVER.
	 * @return string A valid IP address, or an empty string when none is known.
	 */
	public function resolve( ?array $server = null ): string {
		$server  = null === $server ? $_SERVER : $server; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is validated below.
		$remote  = $this->valid_ip( $server['REMOTE_ADDR'] ?? '' );
		$trusted = (array) $this->settings->get( 'trusted_proxies' );

		if ( '' === $remote || ! $trusted || ! $this->in_ranges( $remote, $trusted ) ) {
			return $remote;
		}

		$header = 'HTTP_' . strtoupper( str_replace( '-', '_', (string) $this->settings->get( 'proxy_header' ) ) );
		$value  = isset( $server[ $header ] ) && is_string( $server[ $header ] ) ? $server[ $header ] : '';

		// Walk right to left: the right-most untrusted hop is the real client.
		foreach ( array_reverse( array_map( 'trim', explode( ',', $value ) ) ) as $candidate ) {
			$ip = $this->valid_ip( $candidate );

			if ( '' === $ip ) {
				break;
			}

			if ( ! $this->in_ranges( $ip, $trusted ) ) {
				return $ip;
			}
		}

		return $remote;
	}

	/**
	 * Get a salted hash of the visitor's IP, for rate limits and voter IDs.
	 *
	 * Raw IPs are never used as storage keys.
	 *
	 * @since 1.0.0
	 *
	 * @return string 32 hex characters.
	 */
	public function hash(): string {
		return substr( hash_hmac( 'sha256', $this->resolve(), wp_salt( 'nonce' ) ), 0, 32 );
	}

	/**
	 * Whether an IP falls in any of the given CIDR ranges or addresses.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $ip     IP address.
	 * @param string[] $ranges CIDR ranges ("192.0.2.0/24", "2001:db8::/32") or single IPs.
	 * @return bool
	 */
	public function in_ranges( string $ip, array $ranges ): bool {
		$packed = inet_pton( $ip );

		if ( false === $packed ) {
			return false;
		}

		foreach ( $ranges as $range ) {
			$parts  = explode( '/', trim( (string) $range ), 2 );
			$subnet = inet_pton( $parts[0] );

			if ( false === $subnet || strlen( $subnet ) !== strlen( $packed ) ) {
				continue;
			}

			$bits = isset( $parts[1] ) ? (int) $parts[1] : strlen( $packed ) * 8;

			if ( $this->prefix_matches( $packed, $subnet, $bits ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Compare the first $bits bits of two packed addresses.
	 *
	 * @since 1.0.0
	 *
	 * @param string $a    Packed address.
	 * @param string $b    Packed address of the same length.
	 * @param int    $bits Prefix length.
	 * @return bool
	 */
	private function prefix_matches( string $a, string $b, int $bits ): bool {
		$bytes = intdiv( $bits, 8 );

		if ( substr( $a, 0, $bytes ) !== substr( $b, 0, $bytes ) ) {
			return false;
		}

		$remainder = $bits % 8;

		if ( 0 === $remainder ) {
			return true;
		}

		$mask = ( 0xFF << ( 8 - $remainder ) ) & 0xFF;

		return ( ord( $a[ $bytes ] ) & $mask ) === ( ord( $b[ $bytes ] ) & $mask );
	}

	/**
	 * Return the value if it is a valid IP address.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Candidate.
	 * @return string
	 */
	private function valid_ip( $value ): string {
		return is_string( $value ) && false !== filter_var( $value, FILTER_VALIDATE_IP ) ? $value : '';
	}
}
