<?php
/**
 * Honeypot check.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Moderation\Checks;

use Convocept\Moderation\Context;
use Convocept\Moderation\Decision;

defined( 'ABSPATH' ) || exit;

/**
 * Marks a comment as spam when the hidden honeypot field was filled in.
 *
 * The field is visually hidden and skipped by assistive technology, so only
 * bots that fill every input fill it.
 *
 * @since 1.0.0
 */
class HoneypotCheck {

	/**
	 * Name of the honeypot form field.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const FIELD = 'convocept_hp';

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
		if ( '' === $context->honeypot ) {
			return null;
		}

		return new Decision( Decision::SPAM, 'honeypot', __( 'The hidden honeypot field was filled in.', 'convocept' ) );
	}
}
