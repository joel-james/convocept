<?php
/**
 * Subscription service.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Subscriptions;

use Convocept\Render\TemplateLoader;
use Convocept\Security\RateLimiter;
use Convocept\Settings\Settings;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Subscribes, confirms and unsubscribes readers, and builds their links.
 *
 * Double opt-in: a new address gets a confirmation email and receives
 * nothing else until the link is used. Addresses that are already proven
 * (a logged-in reader's account email, or an address with an active
 * subscription) skip confirmation.
 *
 * @since 1.0.0
 */
class Subscriptions {

	/**
	 * How long a confirmation link works, in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const CONFIRM_LIFETIME = 2 * DAY_IN_SECONDS;

	/**
	 * Query variable naming the subscription page action.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ACTION_VAR = 'convocept';

	/**
	 * Query variable carrying a link token.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TOKEN_VAR = 'convocept_token';

	/**
	 * Storage.
	 *
	 * @since 1.0.0
	 * @var Repository
	 */
	private $repository;

	/**
	 * Link tokens.
	 *
	 * @since 1.0.0
	 * @var Tokens
	 */
	private $tokens;

	/**
	 * Mailer.
	 *
	 * @since 1.0.0
	 * @var Mailer
	 */
	private $mailer;

	/**
	 * Templates.
	 *
	 * @since 1.0.0
	 * @var TemplateLoader
	 */
	private $templates;

	/**
	 * Settings.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Rate limiter.
	 *
	 * @since 1.0.0
	 * @var RateLimiter
	 */
	private $limiter;

	/**
	 * Create the service.
	 *
	 * @since 1.0.0
	 *
	 * @param Repository     $repository Storage.
	 * @param Tokens         $tokens     Link tokens.
	 * @param Mailer         $mailer     Mailer.
	 * @param TemplateLoader $templates  Templates.
	 * @param Settings       $settings   Settings.
	 * @param RateLimiter    $limiter    Rate limiter.
	 */
	public function __construct( Repository $repository, Tokens $tokens, Mailer $mailer, TemplateLoader $templates, Settings $settings, RateLimiter $limiter ) {
		$this->repository = $repository;
		$this->tokens     = $tokens;
		$this->mailer     = $mailer;
		$this->templates  = $templates;
		$this->settings   = $settings;
		$this->limiter    = $limiter;
	}

	/**
	 * Whether subscriptions are turned on.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function enabled(): bool {
		return (bool) $this->settings->get( 'subscriptions' );
	}

	/**
	 * Get the storage.
	 *
	 * @since 1.0.0
	 *
	 * @return Repository
	 */
	public function repository(): Repository {
		return $this->repository;
	}

	/**
	 * Subscribe an address to a post, a comment's replies, or the site.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email      Email address.
	 * @param int    $post_id    Post ID, or 0 for every post.
	 * @param int    $comment_id Comment whose replies to follow, or 0.
	 * @param bool   $verified   Whether the address is already proven (e.g. account email).
	 * @param string $source     Origin, e.g. 'native'.
	 * @return string 'active', 'pending' (always, for an unproven address while double opt-in
	 *                is on), or '' when nothing was done.
	 */
	public function subscribe( string $email, int $post_id, int $comment_id, bool $verified, string $source = 'native' ): string {
		$email = sanitize_email( $email );

		if ( ! $this->enabled() || ! is_email( $email ) ) {
			return '';
		}

		// An unproven address always confirms by email, even when it already has other
		// subscriptions: otherwise anyone could subscribe someone else to everything.
		$confirm = ! $verified && $this->settings->get( 'double_opt_in' );
		$row     = $this->repository->upsert( $email, $post_id, $comment_id, $confirm ? Repository::PENDING : Repository::ACTIVE, $source );

		if ( ! $row ) {
			return '';
		}

		if ( Repository::PENDING === $row['status'] ) {
			$this->send_confirmation( $row );
		}

		// Report "pending" for every unproven address, so the answer never reveals
		// whether that address was already subscribed.
		return $confirm ? Repository::PENDING : (string) $row['status'];
	}

	/**
	 * Confirm a pending subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $row Subscription row.
	 * @return bool Whether it is active now.
	 */
	public function confirm( array $row ): bool {
		if ( Repository::ACTIVE === $row['status'] ) {
			return true;
		}

		if ( Repository::PENDING !== $row['status'] || strtotime( $row['created_at'] . ' UTC' ) + self::CONFIRM_LIFETIME < time() ) {
			return false;
		}

		$this->repository->update(
			(int) $row['id'],
			array(
				'status'       => Repository::ACTIVE,
				'confirmed_at' => current_time( 'mysql', true ),
			)
		);

		/**
		 * Fires after a reader confirms a subscription.
		 *
		 * @since 1.0.0
		 *
		 * @param int $subscription_id Subscription ID.
		 */
		do_action( 'convocept_subscription_confirmed', (int) $row['id'] );

		return true;
	}

	/**
	 * Unsubscribe one subscription, or every subscription of its address.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $row Subscription row.
	 * @param bool                 $all Whether to unsubscribe the whole address.
	 * @return void
	 */
	public function unsubscribe( array $row, bool $all = false ): void {
		$rows = $all ? $this->repository->for_email( (string) $row['email_hash'] ) : array( $row );

		foreach ( $rows as $subscription ) {
			$this->repository->update( (int) $subscription['id'], array( 'status' => Repository::UNSUBSCRIBED ) );
		}

		/**
		 * Fires after a reader unsubscribes.
		 *
		 * @since 1.0.0
		 *
		 * @param int  $subscription_id Subscription ID from the link used.
		 * @param bool $all             Whether every subscription of the address was ended.
		 */
		do_action( 'convocept_unsubscribed', (int) $row['id'], $all );
	}

	/**
	 * Find the subscription a link token belongs to.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $token   Untrusted token.
	 * @param string $purpose Tokens::CONFIRM or Tokens::MANAGE.
	 * @return array<string, mixed>|null
	 */
	public function from_token( $token, string $purpose ): ?array {
		return $this->tokens->verify( $token, array( $this->repository, 'find' ), $purpose );
	}

	/**
	 * Build a link to the subscription page.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $row    Subscription row.
	 * @param string               $action 'confirm', 'manage' or 'unsubscribe'.
	 * @return string
	 */
	public function url( array $row, string $action ): string {
		$purpose = 'confirm' === $action ? Tokens::CONFIRM : Tokens::MANAGE;

		return add_query_arg(
			array(
				self::ACTION_VAR => $action,
				self::TOKEN_VAR  => $this->tokens->create( $row, $purpose ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Describe what a subscription follows, for emails and the manage page.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $row Subscription row.
	 * @return string Plain text.
	 */
	public function describe( array $row ): string {
		$post  = $row['post_id'] ? get_post( (int) $row['post_id'] ) : null;
		$title = $post instanceof WP_Post ? wp_strip_all_tags( get_the_title( $post ) ) : '';

		if ( $row['comment_id'] ) {
			/* translators: %s: Post title. */
			return sprintf( __( 'Replies to your comment on “%s”', 'convocept' ), $title );
		}

		if ( $row['post_id'] ) {
			/* translators: %s: Post title. */
			return sprintf( __( 'All new comments on “%s”', 'convocept' ), $title );
		}

		return __( 'All new comments on this site', 'convocept' );
	}

	/**
	 * Email a confirmation link, at most once per address every 10 minutes.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $row Pending subscription row.
	 * @return void
	 */
	private function send_confirmation( array $row ): void {
		if ( ! $this->limiter->attempt( 'confirm_email', (string) $row['email_hash'], 1, 10 * MINUTE_IN_SECONDS ) ) {
			return;
		}

		$args = array(
			'site_name'   => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'description' => $this->describe( $row ),
			'confirm_url' => $this->url( $row, 'confirm' ),
		);

		$this->mailer->send(
			(string) $row['email'],
			/* translators: %s: Site name. */
			sprintf( __( 'Confirm your subscription to %s', 'convocept' ), $args['site_name'] ),
			$this->templates->render( 'emails/confirm', $args ),
			$this->templates->render( 'emails/confirm-text', $args )
		);
	}
}
