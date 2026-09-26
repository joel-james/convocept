<?php
/**
 * Edit-own-comment window logic.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept;

use WP_Comment;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether a comment may be edited by the current user.
 *
 * No extra data is stored to support this: the window is computed purely
 * from the comment's own `comment_date_gmt`, so nothing can drift out of
 * sync and uninstalling leaves no trace.
 *
 * Editing is logged-in-only in v1. A guest's identity lives in a plain
 * `comment_author_*` cookie holding their name and email — values anyone
 * can set — so it cannot prove authorship and guest edits would be
 * trivially spoofable. Sites that solve identity another way can opt in
 * through the `convocept_can_edit_comment` filter.
 *
 * @since 1.0.0
 */
class EditWindow {

	/**
	 * Settings service, used to read the configured window length.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get the configured edit window length in seconds.
	 *
	 * @since 1.0.0
	 *
	 * @return int Window length in seconds; 0 when editing is disabled.
	 */
	public function window_seconds() {
		return (int) $this->settings->get( 'edit_window' ) * MINUTE_IN_SECONDS;
	}

	/**
	 * Calculate how much of the edit window is left for a comment.
	 *
	 * The app uses this to show a countdown and to hide the edit control
	 * once it lapses; the server re-checks on every write regardless.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment to measure.
	 * @return int Seconds remaining, or 0 when expired or disabled.
	 */
	public function seconds_remaining( WP_Comment $comment ) {
		$window = $this->window_seconds();

		if ( ! $window ) {
			return 0;
		}

		// Comment dates are stored in GMT; anchor the parse to UTC so the
		// result does not shift with the site's timezone setting.
		$age = time() - strtotime( $comment->comment_date_gmt . ' +0000' );

		return max( 0, $window - $age );
	}

	/**
	 * Determine whether the current user may edit a comment.
	 *
	 * Moderators may always edit, matching their admin capability. Authors
	 * may edit their own comment while the window is open.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment to check.
	 * @return bool True when editing is permitted.
	 */
	public function current_user_can_edit( WP_Comment $comment ) {
		$can = false;

		if ( current_user_can( 'moderate_comments' ) ) {
			$can = true;
		} elseif ( is_user_logged_in()
			&& (int) $comment->user_id === get_current_user_id()
			&& $this->seconds_remaining( $comment ) > 0
		) {
			$can = true;
		}

		/**
		 * Filters whether the current user can edit a comment.
		 *
		 * Use this to extend editing to guests once your site has a
		 * trustworthy way to verify guest identity.
		 *
		 * @since 1.0.0
		 *
		 * @param bool       $can     Whether editing is allowed.
		 * @param WP_Comment $comment Comment being checked.
		 */
		return (bool) apply_filters( 'convocept_can_edit_comment', $can, $comment );
	}
}
