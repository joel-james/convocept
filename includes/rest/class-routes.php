<?php
/**
 * Custom REST routes.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Rest;

use Convocept\EditWindow;
use Convocept\Settings;
use WP_Comment;
use WP_Comment_Query;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `convocept/v1` namespace.
 *
 * Only behavior core lacks is implemented here. In particular there is
 * deliberately no route for creating comments: the app posts to core's
 * /wp/v2/comments endpoint so every native filter, flood check, disallowed
 * key, and moderation rule applies exactly as it does for the native form.
 *
 * Routes:
 *   PATCH /convocept/v1/comments/<id>  Edit own comment within the window.
 *   GET   /convocept/v1/updates        Poll for comments newer than a time.
 *   GET   /convocept/v1/settings       Public display settings.
 *
 * @since 1.0.0
 */
class Routes {

	/**
	 * REST namespace for the plugin's routes.
	 *
	 * Named REST_NAMESPACE because `NAMESPACE` is a reserved word in PHP.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const REST_NAMESPACE = 'convocept/v1';

	/**
	 * Settings service.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Edit window service.
	 *
	 * @since 1.0.0
	 * @var EditWindow
	 */
	private $edit_window;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings   $settings    Settings service.
	 * @param EditWindow $edit_window Edit window service.
	 */
	public function __construct( Settings $settings, EditWindow $edit_window ) {
		$this->settings    = $settings;
		$this->edit_window = $edit_window;
	}

	/**
	 * Register the hooks this service needs.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_allow_anonymous_comments', array( $this, 'allow_anonymous_comments' ) );
	}

	/**
	 * Allow guests to create comments over REST when the site permits it.
	 *
	 * Core disables anonymous REST comment creation by default, which would
	 * block guest commenting in the app even on sites that allow it in
	 * Discussion settings. Opting in here restores the site's own policy:
	 * core still runs wp_allow_comment(), so flood control, moderation,
	 * disallowed keys, and duplicate detection all apply unchanged.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $allowed Whether anonymous comments are currently allowed.
	 * @return bool True when guests may comment, false otherwise.
	 */
	public function allow_anonymous_comments( $allowed ) {
		// Never override a site that requires registration to comment.
		if ( get_option( 'comment_registration' ) ) {
			return false;
		}

		/**
		 * Filters whether guests may post comments through the REST API.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $allowed Whether anonymous commenting is allowed.
		 */
		return (bool) apply_filters( 'convocept_allow_anonymous_comments', true );
	}

	/**
	 * Register every route in the plugin's namespace.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes() {
		// Edit an existing comment. PATCH because only content is replaced.
		register_rest_route(
			self::REST_NAMESPACE,
			'/comments/(?P<id>\d+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'update_comment' ),
				'permission_callback' => array( $this, 'can_update_comment' ),
				'args'                => array(
					'id'      => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'content' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		// Poll for comments added since a given timestamp.
		register_rest_route(
			self::REST_NAMESPACE,
			'/updates',
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_updates' ),
				/*
				 * Public by design: returns only approved comments on a post
				 * whose comments are already publicly readable — the same data
				 * core's public GET /wp/v2/comments serves to anonymous
				 * visitors. Post visibility is verified in the callback.
				 */
				'permission_callback' => '__return_true',
				'args'                => array(
					'post'  => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'after' => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);

		// Public display settings for the app.
		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_settings' ),
				/*
				 * Public by design: exposes only the display-behavior subset
				 * (edit window length, live-update mode, poll interval) that
				 * is already inlined into every rendered page. No private data.
				 */
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Permission check for the comment edit route.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return true|WP_Error True when permitted, WP_Error otherwise.
	 */
	public function can_update_comment( WP_REST_Request $request ) {
		$comment = get_comment( (int) $request['id'] );

		if ( ! $comment instanceof WP_Comment ) {
			return new WP_Error(
				'convocept_comment_not_found',
				__( 'Comment not found.', 'convocept' ),
				array( 'status' => 404 )
			);
		}

		// Ownership and window are both enforced by EditWindow.
		if ( ! $this->edit_window->current_user_can_edit( $comment ) ) {
			return new WP_Error(
				'convocept_cannot_edit',
				__( 'Sorry, you are not allowed to edit this comment.', 'convocept' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Replace the content of an existing comment.
	 *
	 * Only the comment body is ever modified; author fields are carried
	 * through untouched so the stored record stays consistent.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error Updated comment, or error on failure.
	 */
	public function update_comment( WP_REST_Request $request ) {
		$comment = get_comment( (int) $request['id'] );
		$content = trim( (string) $request['content'] );

		if ( '' === $content ) {
			return new WP_Error(
				'convocept_empty_content',
				__( 'Comment content cannot be empty.', 'convocept' ),
				array( 'status' => 400 )
			);
		}

		/*
		 * wp_filter_comment() expects a complete comment array and emits
		 * notices for missing keys, so the existing author fields are
		 * carried over alongside the new content.
		 */
		$data = array(
			'comment_ID'           => $comment->comment_ID,
			'comment_post_ID'      => $comment->comment_post_ID,
			'comment_author'       => $comment->comment_author,
			'comment_author_email' => $comment->comment_author_email,
			'comment_author_url'   => $comment->comment_author_url,
			'comment_author_IP'    => $comment->comment_author_IP,
			'comment_agent'        => $comment->comment_agent,
			'comment_type'         => $comment->comment_type,
			'user_ID'              => $comment->user_id,

			// Same allowed tags core permits in comment content.
			'comment_content' => wp_kses( $content, wp_kses_allowed_html( 'pre' ) ),
		);

		// Re-run core's comment filters exactly as a native edit would.
		$data = wp_filter_comment( $data );

		// wp_update_comment() expects slashed data.
		$updated = wp_update_comment( wp_slash( $data ), true );

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		// Recorded so the app can show an "(edited)" marker. Removed on
		// uninstall along with the plugin's other data.
		update_comment_meta( (int) $comment->comment_ID, '_convocept_edited', time() );

		// Re-fetch so the response reflects what core actually stored.
		return rest_ensure_response(
			$this->prepare_comment( get_comment( (int) $comment->comment_ID ) )
		);
	}

	/**
	 * Return approved comments newer than a given timestamp.
	 *
	 * Powers live updates. The response includes the server's current time
	 * so the client can use it as the cursor for its next poll, avoiding
	 * clock-skew gaps between browser and server.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error New comments, or error when unreadable.
	 */
	public function get_updates( WP_REST_Request $request ) {
		$post_id = (int) $request['post'];
		$post    = get_post( $post_id );

		// A closed thread can still have publicly readable comments, so only
		// post visibility is checked here — not whether commenting is open.
		if ( ! $post || 'publish' !== get_post_status( $post ) || post_password_required( $post ) ) {
			return new WP_Error(
				'convocept_post_not_readable',
				__( 'Comments for this post are not publicly readable.', 'convocept' ),
				array( 'status' => 404 )
			);
		}

		$args = array(
			'post_id' => $post_id,

			// Never leak comments held for moderation to other visitors.
			'status' => 'approve',
			'type'   => 'comment',
			'order'  => 'ASC',

			// Upper bound in case a thread receives a burst of comments.
			'number' => 100,
		);

		$after = (string) $request->get_param( 'after' );

		if ( $after ) {
			$timestamp = strtotime( $after );

			if ( false === $timestamp ) {
				return new WP_Error(
					'convocept_invalid_after',
					__( 'The "after" parameter must be a valid date.', 'convocept' ),
					array( 'status' => 400 )
				);
			}

			// Compare against the GMT column to stay timezone independent.
			$args['date_query'] = array(
				array(
					'after'     => gmdate( 'Y-m-d H:i:s', $timestamp ),
					'column'    => 'comment_date_gmt',
					'inclusive' => false,
				),
			);
		}

		$query    = new WP_Comment_Query( $args );
		$prepared = array();

		foreach ( $query->comments as $comment ) {
			$prepared[] = $this->prepare_comment( $comment );
		}

		return rest_ensure_response(
			array(
				'comments' => $prepared,
				'now'      => gmdate( 'Y-m-d\TH:i:s\Z' ),
			)
		);
	}

	/**
	 * Return the public subset of plugin settings.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response Public settings.
	 */
	public function get_settings() {
		return rest_ensure_response( $this->settings->public_subset() );
	}

	/**
	 * Shape a comment object for the app.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment to prepare.
	 * @return array {
	 *     Comment data for the JavaScript app.
	 *
	 *     @type int    $id         Comment ID.
	 *     @type int    $parent     Parent comment ID; 0 for top level.
	 *     @type int    $post       Post ID the comment belongs to.
	 *     @type int    $author     Author user ID; 0 for guests.
	 *     @type string $authorName Display name.
	 *     @type string $authorUrl  Author website URL.
	 *     @type string $avatar     Avatar URL.
	 *     @type string $date       Comment date, RFC3339 in GMT.
	 *     @type string $content    Rendered, filtered comment HTML.
	 *     @type string $raw        Unrendered content, only when editable.
	 *     @type bool   $approved   Whether the comment is approved.
	 *     @type bool   $canEdit    Whether the current user may edit it.
	 *     @type int    $editUntil  Seconds left in the edit window.
	 *     @type int    $edited     Timestamp of the last edit; 0 if never.
	 * }
	 */
	private function prepare_comment( WP_Comment $comment ) {
		$edited   = get_comment_meta( (int) $comment->comment_ID, '_convocept_edited', true );
		$can_edit = $this->edit_window->current_user_can_edit( $comment );

		$data = array(
			'id'         => (int) $comment->comment_ID,
			'parent'     => (int) $comment->comment_parent,
			'post'       => (int) $comment->comment_post_ID,
			'author'     => (int) $comment->user_id,
			'authorName' => $comment->comment_author,
			'authorUrl'  => $comment->comment_author_url,
			'avatar'     => get_avatar_url( $comment, array( 'size' => 48 ) ),
			'date'       => mysql_to_rfc3339( $comment->comment_date_gmt ),

			// Rendered through core's filter so embeds, links, and smilies
			// match what the native thread would display.
			'content' => apply_filters( 'comment_text', $comment->comment_content, $comment ),

			// Raw content is only useful for the edit form, so it is only
			// exposed to users who are actually allowed to edit.
			'raw'       => $can_edit ? $comment->comment_content : '',
			'approved'  => '1' === (string) $comment->comment_approved,
			'canEdit'   => $can_edit,
			'editUntil' => $this->edit_window->seconds_remaining( $comment ),
			'edited'    => $edited ? (int) $edited : 0,
		);

		/**
		 * Filters the REST shape of a comment object.
		 *
		 * Extension point for future features such as reactions, which can
		 * attach their own keys without changing this class.
		 *
		 * @since 1.0.0
		 *
		 * @param array      $data    Prepared comment data.
		 * @param WP_Comment $comment Comment being prepared.
		 */
		return apply_filters( 'convocept_comment_response', $data, $comment );
	}
}
