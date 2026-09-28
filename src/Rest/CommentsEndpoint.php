<?php
/**
 * Comments endpoint.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Rest;

use Convocept\Comments\EditWindow;
use Convocept\Comments\Submission;
use Convocept\Comments\ThreadQuery;
use Convocept\Plugin;
use Convocept\Render\Labels;
use WP_Comment;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and edits comments for the JavaScript composer.
 *
 * Responses carry the comment as HTML rendered by the same PHP template
 * as the page, so the browser never builds comment markup itself.
 *
 * @since 1.0.0
 */
class CommentsEndpoint {

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
	 * Get the argument schema for creating a comment.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function create_args(): array {
		$lengths = wp_get_comment_fields_max_lengths();

		return array(
			'post_id'           => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			),
			'content'           => Controller::content_arg( true ),
			'parent'            => array(
				'type'    => 'integer',
				'default' => 0,
				'minimum' => 0,
			),
			'author'            => array(
				'type'      => 'string',
				'default'   => '',
				'maxLength' => (int) $lengths['comment_author'],
			),
			'email'             => array(
				'type'      => 'string',
				'default'   => '',
				'maxLength' => (int) $lengths['comment_author_email'],
			),
			'url'               => array(
				'type'      => 'string',
				'default'   => '',
				'maxLength' => (int) $lengths['comment_author_url'],
			),
			'honeypot'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'elapsed'           => array(
				'type'    => 'number',
				'minimum' => 0,
			),
			'cookies_consent'   => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'subscribe_replies' => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'subscribe_post'    => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}

	/**
	 * Get the argument schema for editing a comment.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function update_args(): array {
		return array(
			'id'         => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			),
			'content'    => Controller::content_arg( true ),
			'edit_token' => array(
				'type'      => 'string',
				'default'   => '',
				'maxLength' => 64,
			),
		);
	}

	/**
	 * Get the argument schema for reading a comment's Markdown source.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function source_args(): array {
		$args = $this->update_args();
		unset( $args['content'] );

		return $args;
	}

	/**
	 * Permission check for creating a comment.
	 *
	 * Anyone may try to comment; core decides whether the post accepts
	 * comments and whether the visitor must be logged in. This only rejects
	 * posts where Convocept is not in charge of comments.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function can_create( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$post    = get_post( $post_id );

		if ( ! $post instanceof WP_Post || ! is_post_publicly_viewable( $post ) || ! $this->plugin->renderer()->is_enabled_for( $post_id ) ) {
			return new WP_Error( 'convocept_invalid_post', __( 'Comments are not available for this post.', 'convocept' ), array( 'status' => 404 ) );
		}

		return true;
	}

	/**
	 * Handle POST /posts/{post_id}/comments.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error Response data: 'id', 'status' ('approved' or
	 *                                   'pending'), 'parent', 'html', 'edit_token',
	 *                                   'edit_until', 'total', 'count_text' and 'subscription'
	 *                                   ('active', 'pending' or '').
	 */
	public function create( WP_REST_Request $request ) {
		$submission = $this->plugin->submission();
		$comment    = $submission->submit(
			array(
				'post_id'           => (int) $request->get_param( 'post_id' ),
				'parent'            => (int) $request->get_param( 'parent' ),
				'content'           => (string) $request->get_param( 'content' ),
				'author'            => (string) $request->get_param( 'author' ),
				'email'             => (string) $request->get_param( 'email' ),
				'url'               => (string) $request->get_param( 'url' ),
				'honeypot'          => (string) $request->get_param( 'honeypot' ),
				'elapsed'           => $request->get_param( 'elapsed' ),
				'subscribe_replies' => (bool) $request->get_param( 'subscribe_replies' ),
				'subscribe_post'    => (bool) $request->get_param( 'subscribe_post' ),
			)
		);

		if ( is_wp_error( $comment ) ) {
			return $this->with_status( $comment );
		}

		// Same as wp-comments-post.php: remember the guest's details if they agreed.
		/** This action is documented in wp-comments-post.php */
		do_action( 'set_comment_cookies', $comment, wp_get_current_user(), (bool) $request->get_param( 'cookies_consent' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.

		$edit_window = $this->plugin->edit_window();
		$total       = ( new ThreadQuery() )->total( (int) $comment->comment_post_ID );

		return Controller::response(
			array(
				'id'           => (int) $comment->comment_ID,
				// Spam and trash look "pending" too, so bots learn nothing.
				'status'       => '1' === (string) $comment->comment_approved ? 'approved' : 'pending',
				'parent'       => (int) $comment->comment_parent,
				'html'         => $this->plugin->renderer()->render_single( $comment ),
				'edit_token'   => $submission->edit_token(),
				'edit_until'   => $edit_window->minutes() > 0 ? $edit_window->closes_at( $comment ) : 0,
				'total'        => $total,
				'count_text'   => Labels::comment_count( $total ),
				'subscription' => $this->plugin->subscribe_form()->last_status(),
			),
			201
		);
	}

	/**
	 * Permission check for editing a comment.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function can_update( WP_REST_Request $request ) {
		$comment = get_comment( (int) $request->get_param( 'id' ) );

		if ( ! $comment instanceof WP_Comment || ! $this->plugin->renderer()->is_enabled_for( (int) $comment->comment_post_ID ) ) {
			return new WP_Error( 'convocept_invalid_comment', __( 'Comment not found.', 'convocept' ), array( 'status' => 404 ) );
		}

		if ( ! $this->plugin->edit_window()->can_edit( $comment, (string) $request->get_param( 'edit_token' ) ) ) {
			return new WP_Error( 'convocept_cannot_edit', __( 'This comment can no longer be edited.', 'convocept' ), array( 'status' => 403 ) );
		}

		// Only comments written in Convocept's composer are Markdown; others are edited in wp-admin.
		if ( 'markdown' !== get_comment_meta( (int) $comment->comment_ID, Submission::FORMAT_META_KEY, true ) ) {
			return new WP_Error( 'convocept_not_markdown', __( 'Please edit this comment from the dashboard.', 'convocept' ), array( 'status' => 409 ) );
		}

		return true;
	}

	/**
	 * Handle GET /comments/{id}: the Markdown source, for the edit form.
	 *
	 * Only available to someone who may edit the comment right now.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response Response data: 'id' and 'content' (raw Markdown).
	 */
	public function source( WP_REST_Request $request ): WP_REST_Response {
		$comment = get_comment( (int) $request->get_param( 'id' ) );

		return Controller::response(
			array(
				'id'      => (int) $request->get_param( 'id' ),
				'content' => $comment instanceof WP_Comment ? $this->plugin->submission()->decode_markdown( $comment->comment_content ) : '',
			)
		);
	}

	/**
	 * Handle PATCH /comments/{id}.
	 *
	 * The edited text goes through core's comment filters (kses), and an
	 * author's edit is re-checked against the site's moderation rules, so
	 * editing cannot sneak links or blocked words past moderation.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error Response data: 'id', 'status' and 'html'.
	 */
	public function update( WP_REST_Request $request ) {
		$comment_id = (int) $request->get_param( 'id' );

		if ( ! $this->plugin->limiter()->attempt( 'edit', $this->plugin->ips()->hash(), 10, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'convocept_rate_limited', __( 'Too many edits. Please wait a moment.', 'convocept' ), array( 'status' => 429 ) );
		}

		$content = $this->plugin->submission()->encode_markdown( (string) $request->get_param( 'content' ) );
		$result  = wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_content' => $content,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $this->with_status( $result );
		}

		update_comment_meta( $comment_id, EditWindow::EDITED_META_KEY, time() );

		$comment = get_comment( $comment_id );

		if ( ! $comment instanceof WP_Comment ) {
			return new WP_Error( 'convocept_invalid_comment', __( 'Comment not found.', 'convocept' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'moderate_comments' ) && '1' === (string) $comment->comment_approved && ! $this->passes_moderation_rules( $comment ) ) {
			wp_set_comment_status( $comment, 'hold' );
			$comment = get_comment( $comment_id );
		}

		/**
		 * Fires after a comment is edited through Convocept.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_Comment $comment The edited comment.
		 */
		do_action( 'convocept_comment_edited', $comment );

		return Controller::response(
			array(
				'id'     => $comment_id,
				'status' => '1' === (string) $comment->comment_approved ? 'approved' : 'pending',
				'html'   => $this->plugin->renderer()->render_single( $comment ),
			)
		);
	}

	/**
	 * Check edited content against the site's moderation settings.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment.
	 * @return bool False when core would hold the comment.
	 */
	private function passes_moderation_rules( WP_Comment $comment ): bool {
		$max_links = (int) get_option( 'comment_max_links' );

		if ( $max_links > 0 && preg_match_all( '#https?://#i', $comment->comment_content ) >= $max_links ) {
			return false;
		}

		return check_comment(
			$comment->comment_author,
			$comment->comment_author_email,
			$comment->comment_author_url,
			$comment->comment_content,
			$comment->comment_author_IP,
			$comment->comment_agent,
			$comment->comment_type
		);
	}

	/**
	 * Make sure an error carries an HTTP status.
	 *
	 * Core's comment errors (duplicate, flood, missing fields) sometimes
	 * carry a status and sometimes do not.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Error $error Error.
	 * @return WP_Error
	 */
	private function with_status( WP_Error $error ): WP_Error {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : ( is_int( $data ) ? $data : 400 );

		if ( $status < 400 ) {
			$status = 'comment_flood' === $error->get_error_code() ? 429 : 400;
		}

		$error->add_data( array( 'status' => $status ) );

		return $error;
	}
}
