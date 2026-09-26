<?php
/**
 * Moderation decision.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Moderation;

defined( 'ABSPATH' ) || exit;

/**
 * One check's verdict on a submitted comment, with the reason.
 *
 * @since 1.0.0
 */
final class Decision {

	/**
	 * Publish the comment (does not override a stricter core decision).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const APPROVE = 'approve';

	/**
	 * Hold the comment for moderation.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const HOLD = 'hold';

	/**
	 * Mark the comment as spam.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SPAM = 'spam';

	/**
	 * Move the comment to the trash.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TRASH = 'trash';

	/**
	 * Severity of each verdict; the most severe verdict wins.
	 *
	 * @since 1.0.0
	 * @var array<string, int>
	 */
	const SEVERITY = array(
		self::APPROVE => 0,
		self::HOLD    => 1,
		self::SPAM    => 2,
		self::TRASH   => 3,
	);

	/**
	 * Verdict, one of the class constants.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $verdict;

	/**
	 * Machine name of the check that decided, e.g. 'honeypot'.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $check;

	/**
	 * Human-readable reason, shown to moderators.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $reason;

	/**
	 * Create a decision.
	 *
	 * @since 1.0.0
	 *
	 * @param string $verdict One of the class constants; unknown values become HOLD.
	 * @param string $check   Machine name of the check.
	 * @param string $reason  Reason shown to moderators.
	 */
	public function __construct( string $verdict, string $check, string $reason ) {
		$this->verdict = isset( self::SEVERITY[ $verdict ] ) ? $verdict : self::HOLD;
		$this->check   = sanitize_key( $check );
		$this->reason  = $reason;
	}

	/**
	 * Get the verdict.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function verdict(): string {
		return $this->verdict;
	}

	/**
	 * Get the check's machine name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function check(): string {
		return $this->check;
	}

	/**
	 * Get the reason.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function reason(): string {
		return $this->reason;
	}

	/**
	 * Get the severity rank of the verdict.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function severity(): int {
		return self::SEVERITY[ $this->verdict ];
	}

	/**
	 * Get the decision as an array for storage.
	 *
	 * @since 1.0.0
	 *
	 * @return array{verdict: string, check: string, reason: string}
	 */
	public function to_array(): array {
		return array(
			'verdict' => $this->verdict,
			'check'   => $this->check,
			'reason'  => $this->reason,
		);
	}
}
