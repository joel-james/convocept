<?php
/**
 * Count labels.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Render;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the labels that contain a count.
 *
 * Plural forms differ per language (Polish and Russian have three, Arabic
 * six), and only gettext on the server knows them. So when the script
 * updates a count it shows text from the REST response built here, never
 * text it composes itself. The templates use the same strings.
 *
 * @since 1.0.0
 */
class Labels {

	/**
	 * Heading of the thread: "12 comments", or "Comments" when empty.
	 *
	 * @since 1.0.0
	 *
	 * @param int $total Number of comments.
	 * @return string Plain text.
	 */
	public static function comment_count( int $total ): string {
		if ( 0 === $total ) {
			return __( 'Comments', 'convocept' );
		}

		/* translators: %s: Number of comments. */
		return sprintf( _n( '%s comment', '%s comments', $total, 'convocept' ), number_format_i18n( $total ) );
	}

	/**
	 * Link that loads hidden replies: "Show 3 more replies".
	 *
	 * @since 1.0.0
	 *
	 * @param int $count Number of hidden replies.
	 * @return string Plain text.
	 */
	public static function more_replies( int $count ): string {
		/* translators: %s: Number of hidden replies. */
		return sprintf( _n( 'Show %s more reply', 'Show %s more replies', $count, 'convocept' ), number_format_i18n( $count ) );
	}
}
