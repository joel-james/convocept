<?php
/**
 * Moderation endpoint.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Rest;

use Convocept\Comments\ThreadQuery;
use Convocept\Plugin;
use WP_Comment;
use WP_Comment_Query;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end moderation: approve, hold, spam, trash and pin, in place.
 *
 * Every route requires the `moderate_comments` capability and, for a
 * single comment, `edit_comment` on it. Requests are authenticated with
 * the logged-in cookie plus the REST nonce from the session endpoint.
 *
 * @since 1.0.0
 */
class ModerationEndpoint {

	/**
	 * Moderation actions and the core status each one sets.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	const ACTIONS = array(
		'approve' => 'approve',
		'hold'    => 'hold',
		'spam'    => 'spam',
		'trash'   => 'trash',
	);

	/**
	 * Plugin instance.
	 *
	 * @since 1.0.0
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Create the endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Get the argument schema for moderating a comment.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function moderate_args(): array {
		return array(
			'id'     => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			),
			'action' => array(
				'type'     => 'string',
				'required' => true,
				'enum'     => array_keys( self::ACTIONS ),
			),
		);
	}

	/**
	 * Get the argument schema for pinning.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function pin_args(): array {
		return array(
			'id'     => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			),
			'pinned' => array(
				'type'     => 'boolean',
				'required' => true,
			),
		);
	}

	/**
	 * Get the argument schema for listing pending comments.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function pending_args(): array {
		return array(
			'post_id' => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			),
		);
	}

	/**
	 * Permission check for routes about one comment.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function can_moderate_comment( WP_REST_Request $request ) {
		$comment = get_comment( (int) $request->get_param( 'id' ) );

		if ( ! $comment instanceof WP_Comment ) {
			return new WP_Error( 'convocept_invalid_comment', __( 'Comment not found.', 'convocept' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'moderate_comments' ) || ! current_user_can( 'edit_comment', (int) $comment->comment_ID ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You are not allowed to moderate this comment.', 'convocept' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Permission check for listing a post's pending comments.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function can_moderate_post( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( ! current_user_can( 'moderate_comments' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You are not allowed to moderate these comments.', 'convocept' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Handle POST /comments/{id}/moderate.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error Response data: 'id', 'status' and, when the comment
	 *                                   stays visible, 'html'.
	 */
	public function moderate( WP_REST_Request $request ) {
		$comment_id = (int) $request->get_param( 'id' );
		$action     = (string) $request->get_param( 'action' );
		$result     = 'trash' === $action ? wp_trash_comment( $comment_id ) : wp_set_comment_status( $comment_id, self::ACTIONS[ $action ], true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $result ) {
			return new WP_Error( 'convocept_moderation_failed', __( 'The comment could not be updated.', 'convocept' ), array( 'status' => 500 ) );
		}

		$comment = get_comment( $comment_id );

		/**
		 * Fires after a comment's status is changed from the front end.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $comment_id Comment ID.
		 * @param string $action     'approve', 'hold', 'spam' or 'trash'.
		 */
		do_action( 'convocept_comment_status_changed', $comment_id, $action );

		return Controller::response(
			array(
				'id'     => $comment_id,
				'status' => $action,
				'html'   => $comment instanceof WP_Comment && in_array( $action, array( 'approve', 'hold' ), true )
					? $this->plugin->renderer()->render_single( $comment )
					: '',
			)
		);
	}

	/**
	 * Handle POST /comments/{id}/pin.
	 *
	 * Only top-level, approved comments can be pinned.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error Response data: 'id', 'pinned' and 'html'.
	 */
	public function pin( WP_REST_Request $request ) {
		$comment = get_comment( (int) $request->get_param( 'id' ) );
		$pinned  = (bool) $request->get_param( 'pinned' );

		if ( ! $comment instanceof WP_Comment || 0 !== (int) $comment->comment_parent || '1' !== (string) $comment->comment_approved ) {
			return new WP_Error( 'convocept_cannot_pin', __( 'Only approved top-level comments can be pinned.', 'convocept' ), array( 'status' => 400 ) );
		}

		if ( $pinned ) {
			update_comment_meta( (int) $comment->comment_ID, ThreadQuery::PINNED_META_KEY, '1' );
		} else {
			delete_comment_meta( (int) $comment->comment_ID, ThreadQuery::PINNED_META_KEY );
		}

		/**
		 * Fires after a comment is pinned or unpinned.
		 *
		 * @since 1.0.0
		 *
		 * @param int  $comment_id Comment ID.
		 * @param bool $pinned     Whether it is now pinned.
		 */
		do_action( 'convocept_comment_pinned', (int) $comment->comment_ID, $pinned );

		return Controller::response(
			array(
				'id'     => (int) $comment->comment_ID,
				'pinned' => $pinned,
				'html'   => $this->plugin->renderer()->render_single( $comment ),
			)
		);
	}

	/**
	 * Handle GET /posts/{post_id}/pending: comments awaiting moderation.
	 *
	 * Lets moderators approve comments right in the thread. Never part of
	 * the cached page: only this authenticated response contains them.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response Response data: 'items', each with 'id', 'parent' and 'html'.
	 */
	public function pending( WP_REST_Request $request ): WP_REST_Response {
		$comments = ( new WP_Comment_Query() )->query(
			array(
				'post_id'       => (int) $request->get_param( 'post_id' ),
				'status'        => 'hold',
				'type__not_in'  => array( 'pingback', 'trackback' ),
				'orderby'       => 'comment_ID',
				'order'         => 'ASC',
				'number'        => 50,
				'no_found_rows' => true,
			)
		);

		$items = array();

		foreach ( is_array( $comments ) ? $comments : array() as $comment ) {
			if ( $comment instanceof WP_Comment ) {
				$items[] = array(
					'id'     => (int) $comment->comment_ID,
					'parent' => (int) $comment->comment_parent,
					'html'   => $this->plugin->renderer()->render_single( $comment ),
				);
			}
		}

		return Controller::response( array( 'items' => $items ) );
	}
}
