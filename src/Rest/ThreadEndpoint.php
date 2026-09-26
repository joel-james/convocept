<?php
/**
 * Thread endpoint.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Rest;

use Convocept\Comments\Sort;
use Convocept\Comments\ThreadQuery;
use Convocept\Render\ThreadRenderer;
use WP_Comment;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Serves more of a thread: the next page, more replies, and where a comment lives.
 *
 * Everything here is public, approved content that is also in the page
 * HTML, rendered by the same templates.
 *
 * @since 1.0.0
 */
class ThreadEndpoint {

	/**
	 * Thread renderer.
	 *
	 * @since 1.0.0
	 * @var ThreadRenderer
	 */
	private $renderer;

	/**
	 * Create the endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param ThreadRenderer $renderer Thread renderer.
	 */
	public function __construct( ThreadRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Get the argument schema for a thread page.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function page_args(): array {
		return array(
			'post_id' => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			),
			'page'    => array(
				'type'    => 'integer',
				'default' => 1,
				'minimum' => 1,
			),
			'sort'    => array(
				'type' => 'string',
				'enum' => Sort::all(),
			),
		);
	}

	/**
	 * Get the argument schema for routes about one comment.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function comment_args(): array {
		return array(
			'id' => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			),
		);
	}

	/**
	 * Get the argument schema for more replies.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function replies_args(): array {
		return array_merge(
			$this->comment_args(),
			array(
				'offset' => array(
					'type'    => 'integer',
					'default' => 0,
					'minimum' => 0,
				),
			)
		);
	}

	/**
	 * Get the argument schema for new-comment checks.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function updates_args(): array {
		return array(
			'post_id' => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			),
			'after'   => array(
				'type'    => 'integer',
				'default' => 0,
				'minimum' => 0,
			),
		);
	}

	/**
	 * Permission check: the post's thread is public and handled by Convocept.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function can_read_post( WP_REST_Request $request ) {
		return $this->readable( (int) $request->get_param( 'post_id' ) )
			? true
			: new WP_Error( 'convocept_invalid_post', __( 'Comments are not available for this post.', 'convocept' ), array( 'status' => 404 ) );
	}

	/**
	 * Permission check: the comment is approved and on a readable thread.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function can_read_comment( WP_REST_Request $request ) {
		$comment = get_comment( (int) $request->get_param( 'id' ) );

		if ( $comment instanceof WP_Comment && '1' === (string) $comment->comment_approved && $this->readable( (int) $comment->comment_post_ID ) ) {
			return true;
		}

		return new WP_Error( 'convocept_invalid_comment', __( 'Comment not found.', 'convocept' ), array( 'status' => 404 ) );
	}

	/**
	 * Handle GET /posts/{post_id}/comments: one page of top-level comments.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response Response data: 'html', 'page', 'pages' and 'total'.
	 */
	public function page( WP_REST_Request $request ): WP_REST_Response {
		return Controller::response(
			$this->renderer->render_page(
				(int) $request->get_param( 'post_id' ),
				(string) $request->get_param( 'sort' ),
				(int) $request->get_param( 'page' )
			)
		);
	}

	/**
	 * Handle GET /comments/{id}/replies: replies not shown yet.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response Response data: 'html' and 'remaining'.
	 */
	public function replies( WP_REST_Request $request ): WP_REST_Response {
		$comment = get_comment( (int) $request->get_param( 'id' ) );

		return Controller::response(
			$comment instanceof WP_Comment
				? $this->renderer->render_more_replies( $comment, (int) $request->get_param( 'offset' ) )
				: array(
					'html'      => '',
					'remaining' => 0,
				)
		);
	}

	/**
	 * Handle GET /comments/{id}/context: the URL of the page showing a comment.
	 *
	 * Lets the script follow a #comment-{ID} link to a comment that is on
	 * another page or inside a collapsed reply list.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response Response data: 'id' and 'url'.
	 */
	public function context( WP_REST_Request $request ): WP_REST_Response {
		$comment = get_comment( (int) $request->get_param( 'id' ) );

		return Controller::response(
			array(
				'id'  => (int) $request->get_param( 'id' ),
				'url' => $comment instanceof WP_Comment ? $this->renderer->comment_url( $comment ) : '',
			)
		);
	}

	/**
	 * Handle GET /posts/{post_id}/updates: comments newer than `after`.
	 *
	 * Built for cheap polling: the response carries an ETag derived from the
	 * thread's state, and a request whose If-None-Match matches gets an
	 * empty 304 without rendering anything.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response Response data: 'items' (each 'id', 'parent', 'html') and 'total'.
	 */
	public function updates( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		$after   = (int) $request->get_param( 'after' );
		$query   = new ThreadQuery();
		$total   = $query->total( $post_id );
		$etag    = '"' . md5( $post_id . '|' . $after . '|' . $total . '|' . $query->latest_id( $post_id ) ) . '"';

		if ( $etag === $request->get_header( 'if_none_match' ) ) {
			$response = Controller::response( array(), 304 );
			$response->header( 'ETag', $etag );

			return $response;
		}

		$items = array();

		foreach ( $query->newer_than( $post_id, $after ) as $comment ) {
			$items[] = array(
				'id'     => (int) $comment->comment_ID,
				'parent' => (int) $comment->comment_parent,
				'html'   => $this->renderer->render_single( $comment ),
			);
		}

		$response = Controller::response(
			array(
				'items' => $items,
				'total' => $total,
			)
		);
		$response->header( 'ETag', $etag );

		return $response;
	}

	/**
	 * Whether a post's thread may be read over REST.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function readable( int $post_id ): bool {
		$post = get_post( $post_id );

		return $post instanceof WP_Post
			&& is_post_publicly_viewable( $post )
			&& ! post_password_required( $post )
			&& $this->renderer->is_enabled_for( $post_id );
	}
}
