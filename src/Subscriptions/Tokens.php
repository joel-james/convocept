<?php
/**
 * Subscription link tokens.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Subscriptions;

defined( 'ABSPATH' ) || exit;

/**
 * Signs and verifies the tokens in subscription links.
 *
 * A token is "{id}.{signature}". The signature is an HMAC, keyed with the
 * site's secret salt, over the subscription ID, the link's purpose and the
 * row's random `token_key`. So tokens:
 *
 * - can't be guessed or forged without the site's secrets (a leaked
 *   database alone is not enough);
 * - only work for their purpose (a "confirm" token can't manage);
 * - can all be revoked by changing the row's token_key;
 * - never contain the email address.
 *
 * @since 1.0.0
 */
class Tokens {

	/**
	 * Confirm a pending subscription.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const CONFIRM = 'confirm';

	/**
	 * Manage (and unsubscribe from) an address's subscriptions.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const MANAGE = 'manage';

	/**
	 * Create a token.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $subscription Subscription row.
	 * @param string               $purpose      One of the class constants.
	 * @return string
	 */
	public function create( array $subscription, string $purpose ): string {
		return (int) $subscription['id'] . '.' . $this->sign( $subscription, $purpose );
	}

	/**
	 * Get the subscription ID from a token, if it is valid for a row and purpose.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed    $token      Untrusted token.
	 * @param callable $find       Loads a row by ID: fn( int $id ): ?array.
	 * @param string   $purpose    Expected purpose.
	 * @return array<string, mixed>|null The subscription row, or null.
	 */
	public function verify( $token, callable $find, string $purpose ): ?array {
		if ( ! is_string( $token ) || ! preg_match( '/^(\d{1,20})\.([a-f0-9]{40})$/', $token, $parts ) ) {
			return null;
		}

		$row = $find( (int) $parts[1] );

		if ( ! is_array( $row ) || ! hash_equals( $this->sign( $row, $purpose ), $parts[2] ) ) {
			return null;
		}

		return $row;
	}

	/**
	 * Compute a signature.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $subscription Subscription row.
	 * @param string               $purpose      Purpose.
	 * @return string 40 hex characters.
	 */
	private function sign( array $subscription, string $purpose ): string {
		$data = implode( '|', array( 'convocept-subscription', $purpose, (int) $subscription['id'], (string) $subscription['token_key'] ) );

		return substr( hash_hmac( 'sha256', $data, wp_salt( 'auth' ) ), 0, 40 );
	}
}
