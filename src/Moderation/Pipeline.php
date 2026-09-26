<?php
/**
 * Moderation pipeline.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Moderation;

use Convocept\Moderation\Checks\HoneypotCheck;
use Convocept\Moderation\Checks\LinksCheck;
use Convocept\Moderation\Checks\TimingCheck;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the ordered list of moderation checks on a submission.
 *
 * Each check is a callable that receives the comment data and the
 * submission context and returns a Decision, or null for "no opinion".
 * The most severe verdict wins, and it can only make core's own decision
 * stricter, never looser: a check cannot approve a comment core held.
 *
 * Add-ons (for example AI moderation) append their own checks with the
 * `convocept_moderation_checks` filter.
 *
 * @since 1.0.0
 */
class Pipeline {

	/**
	 * Get the checks to run, in order.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, callable> Checks keyed by machine name.
	 */
	public function checks(): array {
		$checks = array(
			'honeypot' => array( new HoneypotCheck(), 'check' ),
			'timing'   => array( new TimingCheck(), 'check' ),
			'links'    => array( new LinksCheck(), 'check' ),
		);

		/**
		 * Filters the moderation checks run on every submitted comment.
		 *
		 * Each check is called with the comment data array (as passed to
		 * wp_new_comment()) and a \Convocept\Moderation\Context, and returns
		 * a \Convocept\Moderation\Decision or null for no opinion.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, callable> $checks Checks keyed by machine name.
		 */
		return (array) apply_filters( 'convocept_moderation_checks', $checks );
	}

	/**
	 * Run every check.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $commentdata Comment data.
	 * @param Context              $context     Submission context.
	 * @return Decision[] Decisions from checks that had an opinion, in order.
	 */
	public function run( array $commentdata, Context $context ): array {
		$decisions = array();

		foreach ( $this->checks() as $check ) {
			if ( ! is_callable( $check ) ) {
				continue;
			}

			$decision = call_user_func( $check, $commentdata, $context );

			if ( $decision instanceof Decision ) {
				$decisions[] = $decision;
			}
		}

		return $decisions;
	}

	/**
	 * Get the most severe decision.
	 *
	 * @since 1.0.0
	 *
	 * @param Decision[] $decisions Decisions.
	 * @return Decision|null
	 */
	public function strictest( array $decisions ): ?Decision {
		$strictest = null;

		foreach ( $decisions as $decision ) {
			if ( null === $strictest || $decision->severity() > $strictest->severity() ) {
				$strictest = $decision;
			}
		}

		return $strictest;
	}

	/**
	 * Combine core's approval status with a decision, keeping the stricter.
	 *
	 * @since 1.0.0
	 *
	 * @param int|string    $approved Core's status: 1, 0, 'spam' or 'trash'.
	 * @param Decision|null $decision Strictest decision, or null.
	 * @return int|string
	 */
	public function apply( $approved, ?Decision $decision ) {
		if ( null === $decision ) {
			return $approved;
		}

		$core_severity = array(
			'1'     => 0,
			'0'     => 1,
			'spam'  => 2,
			'trash' => 3,
		);

		$current = $core_severity[ (string) $approved ] ?? 0;

		if ( $decision->severity() <= $current ) {
			return $approved;
		}

		$map = array(
			Decision::HOLD  => 0,
			Decision::SPAM  => 'spam',
			Decision::TRASH => 'trash',
		);

		return $map[ $decision->verdict() ] ?? $approved;
	}
}
