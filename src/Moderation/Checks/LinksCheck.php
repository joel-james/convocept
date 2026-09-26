<?php
/**
 * Link count check for Markdown comments.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Moderation\Checks;

use Convocept\Moderation\Context;
use Convocept\Moderation\Decision;

defined( 'ABSPATH' ) || exit;

/**
 * Applies core's "hold a comment with N or more links" rule to Markdown.
 *
 * Core counts `<a href` tags, which Markdown comments don't contain until
 * they are rendered, so without this check `[text](url)` links would slip
 * past the site owner's link limit.
 *
 * @since 1.0.0
 */
class LinksCheck {

	/**
	 * Run the check.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $commentdata Comment data.
	 * @param Context              $context     Submission context.
	 * @return Decision|null
	 */
	public function check( array $commentdata, Context $context ): ?Decision {
		$max = (int) get_option( 'comment_max_links' );

		if ( 'markdown' !== $context->format || $max <= 0 ) {
			return null;
		}

		$links = preg_match_all( '#https?://#i', (string) ( $commentdata['comment_content'] ?? '' ) );

		if ( $links < $max ) {
			return null;
		}

		return new Decision(
			Decision::HOLD,
			'links',
			sprintf(
				/* translators: %d: Number of links. */
				_n( 'Contains %d link.', 'Contains %d links.', (int) $links, 'convocept' ),
				(int) $links
			)
		);
	}
}
