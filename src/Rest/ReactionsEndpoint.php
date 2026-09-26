<?php
/**
 * Reactions endpoint.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Rest;

use Convocept\Plugin;
use WP_Comment;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Lets readers upvote comments.
 *
 * Public, like the comment form, and rate-limited per IP. One vote per
 * voter per comment is enforced by the database.
 *
 * @since 1.0.0
 */
class ReactionsEndpoint {

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
	 * Get the argument schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function args(): array {
		return array(
			'id'       => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			),
			'reaction' => array(
				'type'    => 'string',
				'enum'    => array( 'up' ),
				'default' => 'up',
			),
			'active'   => array(
				'type'     => 'boolean',
				'required' => true,
			),
		);
	}

	/**
	 * Permission check: voting is on and the comment is visible.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function can_react( WP_REST_Request $request ) {
		$comment = get_comment( (int) $request->get_param( 'id' ) );

		if (
			! $this->plugin->settings()->get( 'votes' )
			|| ! $comment instanceof WP_Comment
			|| '1' !== (string) $comment->comment_approved
			|| ! $this->plugin->renderer()->is_enabled_for( (int) $comment->comment_post_ID )
			|| post_password_required( (int) $comment->comment_post_ID )
		) {
			return new WP_Error( 'convocept_invalid_comment', __( 'Comment not found.', 'convocept' ), array( 'status' => 404 ) );
		}

		return true;
	}

	/**
	 * Handle POST /comments/{id}/reactions: add or remove the visitor's vote.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error Response data: 'id', 'count' and 'active'.
	 */
	public function update( WP_REST_Request $request ) {
		if ( ! $this->plugin->limiter()->attempt( 'vote', $this->plugin->ips()->hash(), 30, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'convocept_rate_limited', __( 'Too many votes. Please wait a moment.', 'convocept' ), array( 'status' => 429 ) );
		}

		$comment = get_comment( (int) $request->get_param( 'id' ) );
		$active  = (bool) $request->get_param( 'active' );
		$count   = $comment instanceof WP_Comment ? $this->plugin->votes()->set( $comment, $active ) : 0;

		return Controller::response(
			array(
				'id'     => (int) $request->get_param( 'id' ),
				'count'  => $count,
				'active' => $active,
			)
		);
	}
}
