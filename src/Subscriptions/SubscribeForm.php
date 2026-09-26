<?php
/**
 * Subscribe forms.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Subscriptions;

use Convocept\Moderation\Context;
use Convocept\Render\TemplateLoader;
use Convocept\Render\ThreadRenderer;
use Convocept\Security\IpResolver;
use Convocept\Security\RateLimiter;
use WP_Comment;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Every way a reader can subscribe.
 *
 * - The composer's checkboxes ("email me replies" / "all new comments"),
 *   acted on once the comment is saved.
 * - The "subscribe without commenting" form: in the thread, as the
 *   `[convocept_subscribe]` shortcode and as the Subscribe block. It posts
 *   to admin-post.php without JavaScript and to the REST API with it.
 *
 * Responses never reveal whether an address is already subscribed.
 *
 * @since 1.0.0
 */
class SubscribeForm {

	/**
	 * Action name for admin-post.php.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ACTION = 'convocept_subscribe';

	/**
	 * Query variable set after a no-JavaScript subscription.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DONE_VAR = 'convocept_subscribed';

	/**
	 * Subscription service.
	 *
	 * @since 1.0.0
	 * @var Subscriptions
	 */
	private $subscriptions;

	/**
	 * Templates.
	 *
	 * @since 1.0.0
	 * @var TemplateLoader
	 */
	private $templates;

	/**
	 * Thread renderer, for enablement checks.
	 *
	 * @since 1.0.0
	 * @var ThreadRenderer
	 */
	private $renderer;

	/**
	 * Rate limiter.
	 *
	 * @since 1.0.0
	 * @var RateLimiter
	 */
	private $limiter;

	/**
	 * IP resolver.
	 *
	 * @since 1.0.0
	 * @var IpResolver
	 */
	private $ips;

	/**
	 * Result of the composer checkboxes for the last comment: 'active', 'pending' or ''.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $last_status = '';

	/**
	 * Create the forms.
	 *
	 * @since 1.0.0
	 *
	 * @param Subscriptions  $subscriptions Subscription service.
	 * @param TemplateLoader $templates     Templates.
	 * @param ThreadRenderer $renderer      Thread renderer.
	 * @param RateLimiter    $limiter       Rate limiter.
	 * @param IpResolver     $ips           IP resolver.
	 */
	public function __construct( Subscriptions $subscriptions, TemplateLoader $templates, ThreadRenderer $renderer, RateLimiter $limiter, IpResolver $ips ) {
		$this->subscriptions = $subscriptions;
		$this->templates     = $templates;
		$this->renderer      = $renderer;
		$this->limiter       = $limiter;
		$this->ips           = $ips;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_post' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_post' ) );
		add_action( 'convocept_comment_created', array( $this, 'on_comment_created' ), 10, 3 );
		add_filter( 'convocept_thread_template_args', array( $this, 'thread_args' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_shortcode( 'convocept_subscribe', array( $this, 'shortcode' ) );
	}

	/**
	 * Subscribe an address from a form or the REST API.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email    Email address.
	 * @param int    $post_id  Post to follow, or 0 for the whole site.
	 * @param string $honeypot Honeypot value.
	 * @return array{ok: bool, message: string}
	 */
	public function request( string $email, int $post_id, string $honeypot ): array {
		$generic = array(
			'ok'      => true,
			'message' => __( 'Thanks! Check your inbox: if this address needs confirming, we have sent you a link.', 'convocept' ),
		);

		// Bots get the normal answer and nothing happens.
		if ( '' !== $honeypot || ! $this->subscriptions->enabled() ) {
			return $generic;
		}

		if ( ! $this->limiter->attempt( 'subscribe', $this->ips->hash(), 5, 10 * MINUTE_IN_SECONDS ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Too many attempts. Please try again in a few minutes.', 'convocept' ),
			);
		}

		$email = sanitize_email( $email );

		if ( ! is_email( $email ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Please enter a valid email address.', 'convocept' ),
			);
		}

		if ( $post_id && ! $this->can_follow( $post_id ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'This post does not accept subscriptions.', 'convocept' ),
			);
		}

		$user     = wp_get_current_user();
		$verified = $user->exists() && 0 === strcasecmp( $user->user_email, $email );
		$status   = $this->subscriptions->subscribe( $email, $post_id, 0, $verified );

		if ( $verified && Repository::ACTIVE === $status ) {
			return array(
				'ok'      => true,
				'message' => __( 'You are subscribed.', 'convocept' ),
			);
		}

		return $generic;
	}

	/**
	 * Handle the no-JavaScript form (admin-post.php).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_post(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public form, like core's comment form; protected by honeypot and rate limits.
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$honeypot = isset( $_POST['convocept_hp'] ) ? sanitize_text_field( wp_unslash( $_POST['convocept_hp'] ) ) : '';
		$back     = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$result = $this->request( $email, $post_id, $honeypot );
		$back   = wp_validate_redirect( $back, home_url( '/' ) );

		wp_safe_redirect( add_query_arg( self::DONE_VAR, $result['ok'] ? 'sent' : 'error', $back ) . '#convocept-subscribe', 303 );
		exit;
	}

	/**
	 * Subscribe a commenter who ticked the composer's checkboxes.
	 *
	 * @since 1.0.0
	 *
	 * @param int        $comment_id Comment ID.
	 * @param int|string $approved   Status: 1, 0, 'spam' or 'trash'.
	 * @param Context    $context    Submission context.
	 * @return void
	 */
	public function on_comment_created( $comment_id, $approved, $context ): void {
		$this->last_status = '';
		$comment           = get_comment( (int) $comment_id );

		if ( ! $context instanceof Context || ! $comment instanceof WP_Comment || in_array( (string) $approved, array( 'spam', 'trash' ), true ) ) {
			return;
		}

		$email    = (string) $comment->comment_author_email;
		$user     = $comment->user_id ? get_userdata( (int) $comment->user_id ) : false;
		$verified = $user && 0 === strcasecmp( $user->user_email, $email );
		$post_id  = (int) $comment->comment_post_ID;

		if ( ! empty( $context->subscribe['replies'] ) ) {
			$this->last_status = $this->subscriptions->subscribe( $email, $post_id, (int) $comment_id, (bool) $verified );
		}

		if ( ! empty( $context->subscribe['post'] ) ) {
			$this->last_status = $this->subscriptions->subscribe( $email, $post_id, 0, (bool) $verified );
		}
	}

	/**
	 * Get the subscription result for the last comment.
	 *
	 * @since 1.0.0
	 *
	 * @return string 'active', 'pending' or ''.
	 */
	public function last_status(): string {
		return $this->last_status;
	}

	/**
	 * Render a subscribe form.
	 *
	 * @since 1.0.0
	 *
	 * @param int  $post_id Post to follow, or 0 for the whole site.
	 * @param bool $compact Whether to render the collapsed in-thread variant.
	 * @return string
	 */
	public function render( int $post_id, bool $compact = false ): string {
		if ( ! $this->subscriptions->enabled() || ( $post_id && ! $this->can_follow( $post_id ) ) ) {
			return '';
		}

		$done = (string) get_query_var( self::DONE_VAR );

		return $this->templates->render(
			'subscribe-form',
			array(
				'post_id'     => $post_id,
				'compact'     => $compact,
				'action_url'  => admin_url( 'admin-post.php' ),
				'action'      => self::ACTION,
				'redirect_to' => $post_id ? (string) get_permalink( $post_id ) : home_url( add_query_arg( array() ) ),
				'message'     => 'sent' === $done
					? __( 'Thanks! Check your inbox: if this address needs confirming, we have sent you a link.', 'convocept' )
					: ( 'error' === $done ? __( 'That did not work. Please check the address and try again.', 'convocept' ) : '' ),
				'is_error'    => 'error' === $done,
			)
		);
	}

	/**
	 * Add the in-thread subscribe form to the thread template.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Thread template arguments.
	 * @return array<string, mixed>
	 */
	public function thread_args( $args ) {
		$args                   = (array) $args;
		$args['subscribe_html'] = ! empty( $args['is_open'] ) ? $this->render( (int) $args['post_id'], true ) : '';

		return $args;
	}

	/**
	 * Register the query variable used after a no-JavaScript subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $vars Query variables.
	 * @return string[]
	 */
	public function query_vars( $vars ) {
		$vars   = (array) $vars;
		$vars[] = self::DONE_VAR;

		return $vars;
	}

	/**
	 * Handle the [convocept_subscribe] shortcode.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string>|string $atts {
	 *     Shortcode attributes.
	 *
	 *     @type string $scope   'post' (default: the current post) or 'site'.
	 *     @type string $post_id A specific post to follow.
	 * }
	 * @return string
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'scope'   => 'post',
				'post_id' => '',
			),
			(array) $atts,
			'convocept_subscribe'
		);

		$post_id = 'site' === $atts['scope'] ? 0 : ( $atts['post_id'] ? absint( $atts['post_id'] ) : (int) get_the_ID() );

		return $this->render( $post_id );
	}

	/**
	 * Register the Subscribe block (built by `npm run build`).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_block(): void {
		$dir = CONVOCEPT_DIR . 'assets/dist/blocks/subscribe';

		if ( ! file_exists( $dir . '/block.json' ) ) {
			return;
		}

		register_block_type(
			$dir,
			array(
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Render the Subscribe block.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $attributes Block attributes: 'scope'.
	 * @return string
	 */
	public function render_block( $attributes ): string {
		$scope = isset( $attributes['scope'] ) && 'site' === $attributes['scope'] ? 'site' : 'post';

		return $this->render( 'site' === $scope ? 0 : (int) get_the_ID() );
	}

	/**
	 * Whether readers may follow a post's comments.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function can_follow( int $post_id ): bool {
		$post = get_post( $post_id );

		return $post instanceof WP_Post && is_post_publicly_viewable( $post ) && comments_open( $post ) && $this->renderer->is_enabled_for( $post_id );
	}
}
