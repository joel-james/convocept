<?php
/**
 * Signed form timestamp.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and checks a signed "form rendered at" timestamp.
 *
 * Printed in the no-JavaScript form so the timing check can tell how long
 * passed between rendering and submitting. It is the same for every
 * visitor (so the page stays cacheable) and is signed so bots cannot forge
 * an older time.
 *
 * @since 1.0.0
 */
class FormToken {

	/**
	 * Create a token for the current time.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $time Unix time; defaults to now.
	 * @return string "{time}.{signature}".
	 */
	public function create( ?int $time = null ): string {
		$time = null === $time ? time() : $time;

		return $time . '.' . $this->sign( $time );
	}

	/**
	 * Get the time a valid token was created.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $token Untrusted token.
	 * @return int|null Unix time, or null when the token is missing or forged.
	 */
	public function time( $token ): ?int {
		if ( ! is_string( $token ) || ! preg_match( '/^(\d{9,11})\.([a-f0-9]{16})$/', $token, $matches ) ) {
			return null;
		}

		$time = (int) $matches[1];

		return hash_equals( $this->sign( $time ), $matches[2] ) ? $time : null;
	}

	/**
	 * Sign a timestamp.
	 *
	 * @since 1.0.0
	 *
	 * @param int $time Unix time.
	 * @return string
	 */
	private function sign( int $time ): string {
		return substr( hash_hmac( 'sha256', 'convocept-form|' . $time, wp_salt( 'nonce' ) ), 0, 16 );
	}
}
