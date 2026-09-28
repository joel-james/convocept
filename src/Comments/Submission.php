<?php
/**
 * Comment submission.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Comments;

use Convocept\Moderation\Context;
use Convocept\Moderation\Decision;
use Convocept\Moderation\Pipeline;
use Convocept\Security\FormToken;
use Convocept\Security\IpResolver;
use Convocept\Security\RateLimiter;
use WP_Comment;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Accepts comments from both submission paths and applies Convocept's checks.
 *
 * Comments are always created through core's wp_handle_comment_submission(),
 * so core's own checks (closed posts, required fields, flood control,
 * duplicates, disallowed words, moderation rules) and every anti-spam plugin
 * hooked into core (Akismet, Antispam Bee) still apply.
 *
 * On top of that Convocept adds rate limits, the `convocept_before_submit`
 * filter and the moderation pipeline. They run for:
 *
 * - the JavaScript composer, which posts to the REST API (submit()), and
 * - the plain form posting to wp-comments-post.php, recognised by its
 *   hidden `convocept_form` field (on_core_submission()).
 *
 * Comments from other sources (wp-admin, the core REST API, XML-RPC, other
 * plugins) are left alone.
 *
 * @since 1.0.0
 */
class Submission {

	/**
	 * Hidden field marking a submission from Convocept's plain form.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const FORM_MARKER = 'convocept_form';

	/**
	 * Hidden field carrying the signed "form rendered at" token.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TOKEN_FIELD = 'convocept_token';

	/**
	 * Comment meta key storing which checks decided and why.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const LOG_META_KEY = 'convocept_moderation_log';

	/**
	 * Comment meta key marking the content format.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const FORMAT_META_KEY = 'convocept_format';

	/**
	 * IP resolver.
	 *
	 * @since 1.0.0
	 * @var IpResolver
	 */
	private $ips;

	/**
	 * Rate limiter.
	 *
	 * @since 1.0.0
	 * @var RateLimiter
	 */
	private $limiter;

	/**
	 * Form token service.
	 *
	 * @since 1.0.0
	 * @var FormToken
	 */
	private $tokens;

	/**
	 * Moderation pipeline.
	 *
	 * @since 1.0.0
	 * @var Pipeline
	 */
	private $pipeline;

	/**
	 * Edit window service.
	 *
	 * @since 1.0.0
	 * @var EditWindow
	 */
	private $edit_window;

	/**
	 * Tells whether Convocept handles a post's comments.
	 *
	 * @since 1.0.0
	 * @var callable(int): bool|null
	 */
	private $enabled_for;

	/**
	 * Context of the submission being processed, if it is one of ours.
	 *
	 * @since 1.0.0
	 * @var Context|null
	 */
	private $context = null;

	/**
	 * Decisions the pipeline made for the current submission.
	 *
	 * @since 1.0.0
	 * @var Decision[]
	 */
	private $decisions = array();

	/**
	 * Edit token issued for the comment just created, if any.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $edit_token = '';

	/**
	 * Create the service.
	 *
	 * @since 1.0.0
	 *
	 * @param IpResolver    $ips         IP resolver.
	 * @param RateLimiter   $limiter     Rate limiter.
	 * @param FormToken     $tokens      Form token service.
	 * @param Pipeline      $pipeline    Moderation pipeline.
	 * @param EditWindow    $edit_window Edit window service.
	 * @param callable|null $enabled_for Receives a post ID and returns whether Convocept handles its
	 *                                   comments; plain form posts to those posts get Convocept's
	 *                                   checks even without its form marker.
	 */
	public function __construct( IpResolver $ips, RateLimiter $limiter, FormToken $tokens, Pipeline $pipeline, EditWindow $edit_window, ?callable $enabled_for = null ) {
		$this->ips         = $ips;
		$this->limiter     = $limiter;
		$this->tokens      = $tokens;
		$this->pipeline    = $pipeline;
		$this->edit_window = $edit_window;
		$this->enabled_for = $enabled_for;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		// Early, before other plugins act on a submission we might reject.
		add_action( 'pre_comment_on_post', array( $this, 'on_core_submission' ), 1 );

		// After Akismet (priority 10), so our verdict can only tighten its result.
		add_filter( 'pre_comment_approved', array( $this, 'moderate' ), 20, 2 );

		add_action( 'comment_post', array( $this, 'after_insert' ), 10, 3 );
	}

	/**
	 * Submit a comment from the JavaScript composer.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data {
	 *     Unslashed submission data.
	 *
	 *     @type int    $post_id  Post ID.
	 *     @type int    $parent   Parent comment ID, or 0.
	 *     @type string $content  Markdown content.
	 *     @type string $author   Guest name.
	 *     @type string $email    Guest email.
	 *     @type string $url      Guest website.
	 *     @type string $honeypot Honeypot value.
	 *     @type float  $elapsed  Seconds between the composer appearing and submitting.
	 *     @type string $token    The form's signed token. Without a valid one, the elapsed
	 *                            time is ignored and the timing check holds the comment.
	 *     @type bool   $subscribe_replies Email the commenter replies to this comment.
	 *     @type bool   $subscribe_post    Email the commenter every new comment on the post.
	 * }
	 * @return WP_Comment|WP_Error The new comment, or why it was rejected.
	 */
	public function submit( array $data ) {
		$post_id = (int) ( $data['post_id'] ?? 0 );
		$elapsed = isset( $data['elapsed'] ) && is_numeric( $data['elapsed'] ) ? max( 0.0, (float) $data['elapsed'] ) : null;

		// The elapsed time comes from the browser; only trust it from a client that loaded the form.
		if ( null === $this->tokens->time( $data['token'] ?? '' ) ) {
			$elapsed = null;
		}

		$this->begin(
			new Context(
				Context::SOURCE_REST,
				$post_id,
				(string) ( $data['honeypot'] ?? '' ),
				$elapsed,
				$this->ips->hash(),
				'markdown'
			)
		);

		$this->context->subscribe = array(
			'replies' => ! empty( $data['subscribe_replies'] ),
			'post'    => ! empty( $data['subscribe_post'] ),
		);

		$error = $this->guard( $data );

		if ( $error ) {
			$this->end();

			return $error;
		}

		$comment = wp_handle_comment_submission(
			array(
				'comment_post_ID' => $post_id,
				'comment_parent'  => (int) ( $data['parent'] ?? 0 ),
				'author'          => (string) ( $data['author'] ?? '' ),
				'email'           => (string) ( $data['email'] ?? '' ),
				'url'             => (string) ( $data['url'] ?? '' ),
				'comment'         => $this->encode_markdown( (string) ( $data['content'] ?? '' ) ),
			)
		);

		$this->end();

		return $comment;
	}

	/**
	 * Apply Convocept's checks to a plain form submission to wp-comments-post.php.
	 *
	 * Hooked to `pre_comment_on_post`, which core fires for every
	 * submission through wp_handle_comment_submission(). Only acts on
	 * submissions from Convocept's form; a rejection stops the request with
	 * core's standard error page.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_core_submission( $post_id ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public comment form; core does not use a nonce either.
		if ( null !== $this->context ) {
			return;
		}

		// On a post Convocept handles, its form is the only one shown, so a submission
		// without the marker is a bot posting straight to wp-comments-post.php: check it
		// too (no token means the timing check holds it for moderation).
		$ours = ! empty( $_POST[ self::FORM_MARKER ] ) || ( null !== $this->enabled_for && ( $this->enabled_for )( (int) $post_id ) );

		if ( ! $ours ) {
			return;
		}

		$issued  = $this->tokens->time( isset( $_POST[ self::TOKEN_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::TOKEN_FIELD ] ) ) : '' );
		$context = new Context(
			Context::SOURCE_FORM,
			(int) $post_id,
			isset( $_POST['convocept_hp'] ) ? sanitize_text_field( wp_unslash( $_POST['convocept_hp'] ) ) : '',
			null === $issued ? null : (float) ( time() - $issued ),
			$this->ips->hash()
		);

		$context->subscribe = array(
			'replies' => ! empty( $_POST['convocept_subscribe_replies'] ),
			'post'    => ! empty( $_POST['convocept_subscribe_post'] ),
		);

		$data = array(
			'post_id' => (int) $post_id,
			'content' => isset( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$this->begin( $context );

		$error = $this->guard( $data );

		if ( $error ) {
			$this->end();

			$status = (int) ( $error->get_error_data()['status'] ?? 403 );

			wp_die(
				esc_html( $error->get_error_message() ),
				esc_html__( 'Comment Submission Failure', 'convocept' ),
				array(
					'response'  => absint( $status ),
					'back_link' => true,
				)
			);
		}

		// The context stays active until after_insert() for this comment.
	}

	/**
	 * Run the moderation pipeline and tighten core's approval status.
	 *
	 * @since 1.0.0
	 *
	 * @param int|string|WP_Error  $approved    Core's status, or an error (flood, duplicate).
	 * @param array<string, mixed> $commentdata Comment data.
	 * @return int|string|WP_Error
	 */
	public function moderate( $approved, $commentdata ) {
		if ( null === $this->context || is_wp_error( $approved ) ) {
			return $approved;
		}

		// Like core, never hold a moderator's own comment.
		if ( ! empty( $commentdata['user_id'] ) && user_can( (int) $commentdata['user_id'], 'moderate_comments' ) ) {
			return $approved;
		}

		$this->decisions = $this->pipeline->run( (array) $commentdata, $this->context );

		return $this->pipeline->apply( $approved, $this->pipeline->strictest( $this->decisions ) );
	}

	/**
	 * Store Convocept's metadata once the comment exists.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $comment_id  Comment ID.
	 * @param int|string           $approved    Final status.
	 * @param array<string, mixed> $commentdata Comment data.
	 * @return void
	 */
	public function after_insert( $comment_id, $approved, $commentdata ): void {
		if ( null === $this->context ) {
			return;
		}

		$comment_id = (int) $comment_id;

		if ( $this->decisions ) {
			update_comment_meta(
				$comment_id,
				self::LOG_META_KEY,
				wp_json_encode(
					array_map(
						static function ( Decision $decision ) {
							return $decision->to_array();
						},
						$this->decisions
					)
				)
			);
		}

		if ( 'markdown' === $this->context->format ) {
			update_comment_meta( $comment_id, self::FORMAT_META_KEY, 'markdown' );
		}

		// Guests get a token to edit within the window; logged-in authors use their account.
		if ( Context::SOURCE_REST === $this->context->source && empty( $commentdata['user_id'] ) ) {
			$this->edit_token = $this->edit_window->issue( $comment_id );
		}

		/**
		 * Fires after a comment is created through Convocept.
		 *
		 * @since 1.0.0
		 *
		 * @param int        $comment_id Comment ID.
		 * @param int|string $approved   Final status: 1, 0, 'spam' or 'trash'.
		 * @param Context    $context    Submission context.
		 */
		do_action( 'convocept_comment_created', $comment_id, $approved, $this->context );

		if ( Context::SOURCE_FORM === $this->context->source ) {
			$this->end();
		}
	}

	/**
	 * Get the edit token issued for the last comment created via submit().
	 *
	 * @since 1.0.0
	 *
	 * @return string Raw token, or empty.
	 */
	public function edit_token(): string {
		return $this->edit_token;
	}

	/**
	 * Escape Markdown for storage.
	 *
	 * Comment content is displayed as HTML by wp-admin, feeds and other
	 * plugins. Encoding `&`, `<` and `>` stores the Markdown as literal text,
	 * so it shows exactly as typed everywhere and survives core's kses
	 * filters (which would otherwise strip anything that looks like a tag,
	 * breaking code samples). The Markdown parser decodes it again.
	 *
	 * @since 1.0.0
	 *
	 * @param string $markdown Raw Markdown.
	 * @return string
	 */
	public function encode_markdown( string $markdown ): string {
		return str_replace( array( '&', '<', '>' ), array( '&amp;', '&lt;', '&gt;' ), $markdown );
	}

	/**
	 * Reverse encode_markdown(), for editing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $stored Stored comment content.
	 * @return string Raw Markdown.
	 */
	public function decode_markdown( string $stored ): string {
		return str_replace( array( '&lt;', '&gt;', '&amp;' ), array( '<', '>', '&' ), $stored );
	}

	/**
	 * Apply rate limits and the `convocept_before_submit` filter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data Submission data.
	 * @return WP_Error|null An error to reject the submission, or null.
	 */
	private function guard( array $data ): ?WP_Error {
		$key = (string) $this->context->ip_hash;

		if ( ! $this->limiter->attempt( 'comment_minute', $key, 5, MINUTE_IN_SECONDS ) || ! $this->limiter->attempt( 'comment_hour', $key, 30, HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'convocept_rate_limited', __( 'You are posting comments too quickly. Please wait a moment and try again.', 'convocept' ), array( 'status' => 429 ) );
		}

		/**
		 * Filters whether a comment submission may proceed.
		 *
		 * Return a WP_Error to reject it; its message is shown to the
		 * commenter and its 'status' data is used as the HTTP status.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_Error|null        $error   Null to allow, or an error to reject.
		 * @param array<string, mixed> $data    Submission data.
		 * @param Context              $context Submission context.
		 */
		$error = apply_filters( 'convocept_before_submit', null, $data, $this->context );

		return $error instanceof WP_Error ? $error : null;
	}

	/**
	 * Start processing a submission.
	 *
	 * @since 1.0.0
	 *
	 * @param Context $context Submission context.
	 * @return void
	 */
	private function begin( Context $context ): void {
		$this->context    = $context;
		$this->decisions  = array();
		$this->edit_token = '';
	}

	/**
	 * Finish processing a submission.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function end(): void {
		$this->context   = null;
		$this->decisions = array();
	}
}
