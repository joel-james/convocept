<?php
/**
 * Timing check.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Moderation\Checks;

use Convocept\Moderation\Context;
use Convocept\Moderation\Decision;

defined( 'ABSPATH' ) || exit;

/**
 * Holds comments submitted implausibly fast after the form appeared.
 *
 * Holds rather than marks as spam: a person pasting prepared text can be
 * quick, and a moderator can approve them with one click.
 *
 * @since 1.0.0
 */
class TimingCheck {

	/**
	 * Run the check.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $commentdata Comment data (unused).
	 * @param Context              $context     Submission context.
	 * @return Decision|null
	 */
	public function check( array $commentdata, Context $context ): ?Decision {
		/**
		 * Filters the minimum seconds between the form appearing and a submission.
		 *
		 * Return 0 to disable the timing check.
		 *
		 * @since 1.0.0
		 *
		 * @param float $seconds Minimum seconds. Default 3.
		 */
		$minimum = (float) apply_filters( 'convocept_min_submit_seconds', 3.0 );

		if ( $minimum <= 0 ) {
			return null;
		}

		if ( null === $context->elapsed ) {
			return new Decision( Decision::HOLD, 'timing', __( 'The form timing token was missing or invalid.', 'convocept' ) );
		}

		if ( $context->elapsed < $minimum ) {
			return new Decision(
				Decision::HOLD,
				'timing',
				sprintf(
					/* translators: %s: Number of seconds. */
					__( 'Submitted %s seconds after the form appeared.', 'convocept' ),
					number_format_i18n( $context->elapsed, 1 )
				)
			);
		}

		return null;
	}
}
