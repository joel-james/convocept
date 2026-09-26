<?php
/**
 * Subscription page.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Subscriptions;

use Convocept\Render\TemplateLoader;

defined( 'ABSPATH' ) || exit;

/**
 * The page readers reach from subscription emails.
 *
 * URLs look like `/?convocept=manage&convocept_token=…`: the token proves
 * ownership and the email address never appears in a URL. The page is
 * shown inside the active theme (its header and footer, classic or block
 * theme), is never indexed, and is never page-cached.
 *
 * Nothing changes on a plain GET, because mail security scanners open
 * links: confirming and unsubscribing are buttons (POST). The exception is
 * the RFC 8058 one-click unsubscribe, which mail apps send as a POST with
 * `List-Unsubscribe=One-Click` and which must work without a page.
 *
 * @since 1.0.0
 */
class ManagePage {

	/**
	 * Actions the page understands.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const ACTIONS = array( 'confirm', 'manage', 'unsubscribe' );

	/**
	 * Query variable carrying the result message after a redirect.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const MESSAGE_VAR = 'convocept_msg';

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
	 * Rendered page content, once built.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $content = '';

	/**
	 * Create the page.
	 *
	 * @since 1.0.0
	 *
	 * @param Subscriptions  $subscriptions Subscription service.
	 * @param TemplateLoader $templates     Templates.
	 */
	public function __construct( Subscriptions $subscriptions, TemplateLoader $templates ) {
		$this->subscriptions = $subscriptions;
		$this->templates     = $templates;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle' ), 1 );
	}

	/**
	 * Register the page's query variables.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $vars Query variables.
	 * @return string[]
	 */
	public function query_vars( $vars ) {
		return array_merge( (array) $vars, array( Subscriptions::ACTION_VAR, Subscriptions::TOKEN_VAR, self::MESSAGE_VAR ) );
	}

	/**
	 * Handle a request for the subscription page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle(): void {
		$action = (string) get_query_var( Subscriptions::ACTION_VAR );

		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			return;
		}

		// Personal page: never cache it anywhere.
		nocache_headers();

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standard constant read by caching plugins.
		}

		$token = sanitize_text_field( (string) get_query_var( Subscriptions::TOKEN_VAR ) );

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$this->handle_post( $action, $token );
		}

		$this->content = $this->render( $action, $token );

		// Keep it out of search engines and archives, and keep the token in
		// the URL from reaching other sites through the Referer header.
		add_filter( 'wp_robots', 'wp_robots_sensitive_page' );
		add_action( 'wp_head', 'wp_strict_cross_origin_referrer' );
		add_filter( 'pre_get_document_title', array( $this, 'title' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_filter( 'template_include', array( $this, 'template' ), 99 );
	}

	/**
	 * Use Convocept's page shell inside the theme.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function template(): string {
		return CONVOCEPT_DIR . 'templates/compat/subscription-page.php';
	}

	/**
	 * Get the rendered page content, for the page shell.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function content(): string {
		return $this->content;
	}

	/**
	 * Set the document title.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Your comment subscriptions', 'convocept' ) . ' – ' . get_bloginfo( 'name' );
	}

	/**
	 * Add a body class so themes can style the page.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function body_class( $classes ) {
		$classes   = (array) $classes;
		$classes[] = 'convocept-subscription-page';

		return $classes;
	}

	/**
	 * Apply a confirm or unsubscribe action, then redirect (POST/redirect/GET).
	 *
	 * @since 1.0.0
	 *
	 * @param string $action Page action from the URL.
	 * @param string $token  Link token.
	 * @return void
	 */
	private function handle_post( string $action, string $token ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The signed link token authorizes the action.
		$one_click = isset( $_POST['List-Unsubscribe'] ) && 'One-Click' === sanitize_text_field( wp_unslash( $_POST['List-Unsubscribe'] ) );
		$do        = isset( $_POST['convocept_do'] ) ? sanitize_key( wp_unslash( $_POST['convocept_do'] ) ) : '';
		$target    = isset( $_POST['convocept_subscription'] ) ? absint( $_POST['convocept_subscription'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// RFC 8058: mail apps POST here; answer without a page or redirect.
		if ( 'unsubscribe' === $action && $one_click ) {
			$row = $this->subscriptions->from_token( $token, Tokens::MANAGE );

			if ( $row ) {
				$this->subscriptions->unsubscribe( $row );
			}

			status_header( $row ? 200 : 400 );
			exit;
		}

		if ( 'confirm' === $do ) {
			$row = $this->subscriptions->from_token( $token, Tokens::CONFIRM );

			if ( $row && $this->subscriptions->confirm( $row ) ) {
				$this->redirect( $row, 'confirmed' );
			}

			return;
		}

		$row = $this->subscriptions->from_token( $token, Tokens::MANAGE );

		if ( ! $row ) {
			return;
		}

		if ( 'unsubscribe_all' === $do ) {
			$this->subscriptions->unsubscribe( $row, true );
			$this->redirect( $row, 'unsubscribed_all' );
		}

		if ( 'unsubscribe' === $do || 'resubscribe' === $do ) {
			// The target must belong to the same address as the token.
			$target_row = $this->subscriptions->repository()->find( $target ? $target : (int) $row['id'] );

			if ( $target_row && hash_equals( (string) $row['email_hash'], (string) $target_row['email_hash'] ) ) {
				if ( 'unsubscribe' === $do ) {
					$this->subscriptions->unsubscribe( $target_row );
				} else {
					$this->subscriptions->repository()->update( (int) $target_row['id'], array( 'status' => Repository::ACTIVE ) );
				}

				$this->redirect( $row, 'unsubscribe' === $do ? 'unsubscribed' : 'resubscribed' );
			}
		}
	}

	/**
	 * Redirect to the manage view with a result message.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $row     Subscription row.
	 * @param string               $message Message key.
	 * @return void
	 */
	private function redirect( array $row, string $message ): void {
		wp_safe_redirect( add_query_arg( self::MESSAGE_VAR, $message, $this->subscriptions->url( $row, 'manage' ) ), 303 );
		exit;
	}

	/**
	 * Build the page content for a GET request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action Page action.
	 * @param string $token  Link token.
	 * @return string
	 */
	private function render( string $action, string $token ): string {
		$row = 'confirm' === $action
			? $this->subscriptions->from_token( $token, Tokens::CONFIRM )
			: $this->subscriptions->from_token( $token, Tokens::MANAGE );

		$messages = array(
			'confirmed'        => __( 'Thanks! Your subscription is confirmed.', 'convocept' ),
			'unsubscribed'     => __( 'You are unsubscribed.', 'convocept' ),
			'unsubscribed_all' => __( 'You are unsubscribed from everything on this site.', 'convocept' ),
			'resubscribed'     => __( 'You are subscribed again.', 'convocept' ),
		);
		$message  = $messages[ (string) get_query_var( self::MESSAGE_VAR ) ] ?? '';

		$args = array(
			'state'       => $row ? $action : 'invalid',
			'message'     => $message,
			'email'       => $row ? (string) $row['email'] : '',
			'description' => $row ? $this->subscriptions->describe( $row ) : '',
			'current'     => $row ? (int) $row['id'] : 0,
			'items'       => array(),
			'expired'     => false,
		);

		if ( $row && 'confirm' === $action ) {
			$args['expired'] = Repository::PENDING !== $row['status'] || strtotime( $row['created_at'] . ' UTC' ) + Subscriptions::CONFIRM_LIFETIME < time();

			if ( Repository::ACTIVE === $row['status'] ) {
				$this->redirect( $row, 'confirmed' );
			}
		}

		if ( $row && 'confirm' !== $action ) {
			foreach ( $this->subscriptions->repository()->for_email( (string) $row['email_hash'] ) as $subscription ) {
				$args['items'][] = array(
					'id'     => (int) $subscription['id'],
					'label'  => $this->subscriptions->describe( $subscription ),
					'status' => (string) $subscription['status'],
					'url'    => $subscription['post_id'] ? (string) get_permalink( (int) $subscription['post_id'] ) : home_url( '/' ),
				);
			}
		}

		return $this->templates->render( 'subscription-manage', $args );
	}
}
