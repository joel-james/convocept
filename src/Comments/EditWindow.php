<?php
/**
 * Edit window.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Comments;

use Convocept\Settings\Settings;
use WP_Comment;

defined( 'ABSPATH' ) || exit;

/**
 * Decides who may edit a comment, and until when.
 *
 * Logged-in authors are recognised by user ID. Guests get a random token
 * when they post; only its hash is stored, and the browser keeps the token.
 * Name or email matching is never used, because anyone can type someone
 * else's name and email.
 *
 * @since 1.0.0
 */
class EditWindow {

	/**
	 * Private comment meta key holding the hashed guest edit token.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TOKEN_META_KEY = '_convocept_edit_token';

	/**
	 * Comment meta key holding the Unix time of the last edit.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const EDITED_META_KEY = 'convocept_edited_at';

	/**
	 * Settings service.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Create the service.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get the edit window length in minutes; 0 means editing is off.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function minutes(): int {
		return (int) $this->settings->get( 'edit_window' );
	}

	/**
	 * Issue an edit token for a guest's new comment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $comment_id Comment ID.
	 * @return string The raw token, to hand to the browser once. Empty when editing is off.
	 */
	public function issue( int $comment_id ): string {
		if ( $this->minutes() <= 0 ) {
			return '';
		}

		$token = wp_generate_password( 32, false );
		update_comment_meta( $comment_id, self::TOKEN_META_KEY, $this->hash( $token ) );

		return $token;
	}

	/**
	 * Get the Unix time the edit window closes.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment.
	 * @return int
	 */
	public function closes_at( WP_Comment $comment ): int {
		return (int) strtotime( $comment->comment_date_gmt . ' UTC' ) + $this->minutes() * MINUTE_IN_SECONDS;
	}

	/**
	 * Whether the current visitor may edit a comment.
	 *
	 * Users who can edit the comment in wp-admin (moderators) always can.
	 * Authors can while the window is open: logged-in authors by user ID,
	 * guests with the token issued when they posted.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment.
	 * @param string     $token   Guest edit token, if any.
	 * @return bool
	 */
	public function can_edit( WP_Comment $comment, string $token = '' ): bool {
		if ( current_user_can( 'edit_comment', (int) $comment->comment_ID ) ) {
			return true;
		}

		if ( $this->minutes() <= 0 || time() > $this->closes_at( $comment ) ) {
			return false;
		}

		$user_id = (int) $comment->user_id;

		if ( $user_id > 0 ) {
			return get_current_user_id() === $user_id;
		}

		$stored = (string) get_comment_meta( (int) $comment->comment_ID, self::TOKEN_META_KEY, true );

		return '' !== $token && '' !== $stored && hash_equals( $stored, $this->hash( $token ) );
	}

	/**
	 * Hash a token for storage and comparison.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token Raw token.
	 * @return string
	 */
	private function hash( string $token ): string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}
}
