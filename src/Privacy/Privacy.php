<?php
/**
 * Privacy tools.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Privacy;

use Convocept\Settings\Settings;
use Convocept\Subscriptions\Repository;
use Convocept\Subscriptions\Subscriptions;
use Convocept\Votes\Votes;
use WP_Comment;

defined( 'ABSPATH' ) || exit;

/**
 * Connects Convocept's data to WordPress's privacy tools.
 *
 * - Tools → Export Personal Data and Erase Personal Data include comment
 *   subscriptions and a registered user's upvotes. Comments themselves are
 *   core data and are handled by core's own exporter and eraser.
 * - Settings → Privacy gets suggested policy text describing what
 *   Convocept stores.
 * - The `commenter_ip` setting decides what is stored of a new comment's
 *   IP address, for comments posted on the site and for imported ones.
 *
 * @since 1.0.0
 */
class Privacy {

	/**
	 * Votes exported or erased per request.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const PER_PAGE = 100;

	/**
	 * Settings.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Subscriptions service.
	 *
	 * @since 1.0.0
	 * @var Subscriptions
	 */
	private $subscriptions;

	/**
	 * Votes service.
	 *
	 * @since 1.0.0
	 * @var Votes
	 */
	private $votes;

	/**
	 * Create the service.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings      $settings      Settings.
	 * @param Subscriptions $subscriptions Subscriptions service.
	 * @param Votes         $votes         Votes service.
	 */
	public function __construct( Settings $settings, Subscriptions $subscriptions, Votes $votes ) {
		$this->settings      = $settings;
		$this->subscriptions = $subscriptions;
		$this->votes         = $votes;
	}

	/**
	 * Register the hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		// Early, so later `wp_insert_comment` and `comment_post` callbacks read the stored address.
		add_action( 'wp_insert_comment', array( $this, 'apply_ip_setting' ), 0, 2 );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_erasers' ) );
		add_action( 'admin_init', array( $this, 'add_policy_text' ) );
	}

	/**
	 * Apply the `commenter_ip` setting to a comment that was just saved.
	 *
	 * Runs after the comment is inserted rather than on `pre_comment_user_ip`,
	 * because core's own checks read the address from the comment data before
	 * insertion: the flood check, duplicate check and the IP entries of the
	 * moderation and disallowed lists. With an empty address every guest
	 * would look like the same visitor to the flood check, and an anonymized
	 * one would match a whole network. So every check, Akismet included, sees
	 * the full address, and only what is saved changes.
	 *
	 * Idempotent, so comments that already carry a stored address (imports)
	 * are unchanged.
	 *
	 * @since 1.0.0
	 *
	 * @param int        $comment_id Comment ID.
	 * @param WP_Comment $comment    The saved comment.
	 * @return void
	 */
	public function apply_ip_setting( $comment_id, $comment ): void {
		if ( ! $comment instanceof WP_Comment ) {
			return;
		}

		$stored = $this->stored_ip( $comment->comment_author_IP );

		if ( $stored === $comment->comment_author_IP ) {
			return;
		}

		global $wpdb;

		// Not wp_update_comment(): that would fire edit hooks and filter the whole comment again.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Single-column write; the cache is cleaned below.
		$wpdb->update(
			$wpdb->comments,
			array( 'comment_author_IP' => $stored ),
			array( 'comment_ID' => (int) $comment_id )
		);
		clean_comment_cache( (int) $comment_id );

		$comment->comment_author_IP = $stored;
	}

	/**
	 * What to store for an IP address under the `commenter_ip` setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ip IP address.
	 * @return string What to store: the address, an anonymized address or ''.
	 */
	public function stored_ip( $ip ): string {
		$ip = (string) $ip;

		switch ( $this->settings->get( 'commenter_ip' ) ) {
			case 'none':
				return '';

			case 'anonymize':
				return '' === $ip ? '' : wp_privacy_anonymize_ip( $ip );
		}

		return $ip;
	}

	/**
	 * Add Convocept's exporters to Tools → Export Personal Data.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, mixed>> $exporters Registered exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_exporters( $exporters ): array {
		$exporters = (array) $exporters;

		$exporters['convocept-subscriptions'] = array(
			'exporter_friendly_name' => __( 'Comment subscriptions (Convocept)', 'convocept' ),
			'callback'               => array( $this, 'export_subscriptions' ),
		);
		$exporters['convocept-votes']         = array(
			'exporter_friendly_name' => __( 'Comment upvotes (Convocept)', 'convocept' ),
			'callback'               => array( $this, 'export_votes' ),
		);

		return $exporters;
	}

	/**
	 * Add Convocept's erasers to Tools → Erase Personal Data.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, mixed>> $erasers Registered erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_erasers( $erasers ): array {
		$erasers = (array) $erasers;

		$erasers['convocept-subscriptions'] = array(
			'eraser_friendly_name' => __( 'Comment subscriptions (Convocept)', 'convocept' ),
			'callback'             => array( $this, 'erase_subscriptions' ),
		);
		$erasers['convocept-votes']         = array(
			'eraser_friendly_name' => __( 'Comment upvotes (Convocept)', 'convocept' ),
			'callback'             => array( $this, 'erase_votes' ),
		);

		return $erasers;
	}

	/**
	 * Export the subscriptions of an email address.
	 *
	 * An address rarely has more than a handful, so everything goes in one
	 * page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page, from 1.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export_subscriptions( $email, $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature set by core.
		$repository = $this->subscriptions->repository();
		$rows       = $repository->for_email( $repository->hash_email( (string) $email ) );
		$data       = array();

		foreach ( $rows as $row ) {
			$item = array(
				array(
					'name'  => __( 'Email', 'convocept' ),
					'value' => (string) $row['email'],
				),
				array(
					'name'  => __( 'Follows', 'convocept' ),
					'value' => $this->subscriptions->describe( $row ),
				),
				array(
					'name'  => __( 'Status', 'convocept' ),
					'value' => $this->status_label( (string) $row['status'] ),
				),
				array(
					'name'  => __( 'Subscribed', 'convocept' ),
					'value' => $this->local_date( (string) $row['created_at'] ),
				),
			);

			if ( ! empty( $row['confirmed_at'] ) ) {
				$item[] = array(
					'name'  => __( 'Confirmed', 'convocept' ),
					'value' => $this->local_date( (string) $row['confirmed_at'] ),
				);
			}

			if ( $row['post_id'] ) {
				$item[] = array(
					'name'  => __( 'Post', 'convocept' ),
					'value' => (string) get_permalink( (int) $row['post_id'] ),
				);
			}

			$data[] = array(
				'group_id'    => 'convocept-subscriptions',
				'group_label' => __( 'Comment subscriptions', 'convocept' ),
				'item_id'     => 'convocept-subscription-' . (int) $row['id'],
				'data'        => $item,
			);
		}//end foreach

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Delete the subscriptions of an email address.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page, from 1.
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public function erase_subscriptions( $email, $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature set by core.
		$repository = $this->subscriptions->repository();
		$removed    = $repository->delete_for_email( $repository->hash_email( (string) $email ) );

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Export the upvotes of the registered user with an email address.
	 *
	 * Guest votes are stored as a one-way hash of the IP address,
	 * so they cannot be linked to an email address and are not exported.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page, from 1.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export_votes( $email, $page = 1 ): array {
		$voter = $this->voter_for_email( (string) $email );

		if ( '' === $voter ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$page = max( 1, (int) $page );
		$rows = $this->votes->for_voter( $voter, self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE );
		$data = array();

		foreach ( $rows as $row ) {
			$comment = get_comment( $row['comment_id'] );

			$data[] = array(
				'group_id'    => 'convocept-votes',
				'group_label' => __( 'Comment upvotes', 'convocept' ),
				'item_id'     => 'convocept-vote-' . $row['comment_id'],
				'data'        => array(
					array(
						'name'  => __( 'Comment', 'convocept' ),
						'value' => $comment instanceof WP_Comment ? (string) get_comment_link( $comment ) : (string) $row['comment_id'],
					),
					array(
						'name'  => __( 'Upvoted', 'convocept' ),
						'value' => $this->local_date( $row['created_at'] ),
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => count( $rows ) < self::PER_PAGE,
		);
	}

	/**
	 * Delete the upvotes of the registered user with an email address.
	 *
	 * Vote counts on the comments involved are updated. Guest votes cannot
	 * be linked to an address (see export_votes()).
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page, from 1.
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public function erase_votes( $email, $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature set by core; erasing always takes the first rows left.
		$voter   = $this->voter_for_email( (string) $email );
		$removed = '' === $voter ? 0 : $this->votes->delete_for_voter( $voter, self::PER_PAGE );

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => $removed < self::PER_PAGE,
		);
	}

	/**
	 * Suggest privacy policy text on Settings → Privacy.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$ip = array(
			'keep'      => __( 'the IP address you comment from is stored with your comment, as WordPress normally does', 'convocept' ),
			'anonymize' => __( 'the IP address you comment from is stored with its last part removed, so it no longer identifies you', 'convocept' ),
			'none'      => __( 'the IP address you comment from is not stored with your comment', 'convocept' ),
		);

		$paragraphs = array(
			sprintf(
				/* translators: %s: What happens to the commenter's IP address, e.g. "the IP address you comment from is not stored with your comment". */
				__( 'When you comment, we store what you enter in the comment form. In addition, %s.', 'convocept' ),
				$ip[ (string) $this->settings->get( 'commenter_ip' ) ] ?? $ip['keep']
			),
			__( 'If you subscribe to comments, we store your email address, what you follow, when you subscribed and confirmed, and your language, so we can email you new comments. Every email has a link to unsubscribe or manage your subscriptions. You can also ask us to export or erase this data.', 'convocept' ),
			__( 'If you upvote a comment, we store a one-way code instead of who you are: made from your user account if you are logged in, otherwise from your IP address. We cannot turn it back into your IP address. Votes of logged-in users are included when you ask us to export or erase your data.', 'convocept' ),
			__( 'To limit spam, a one-way code made from your IP address is kept for a few minutes to count how often you post. After you comment, your browser keeps a code that lets you edit your comment for a short time; it is not sent to anyone else.', 'convocept' ),
		);

		$content = '';

		foreach ( $paragraphs as $paragraph ) {
			$content .= '<p>' . esc_html( $paragraph ) . "</p>\n";
		}

		wp_add_privacy_policy_content( 'Convocept', wp_kses_post( $content ) );
	}

	/**
	 * Get the voter ID of the registered user with an email address.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Email address.
	 * @return string Voter ID, or '' when no user has the address.
	 */
	private function voter_for_email( string $email ): string {
		$user = get_user_by( 'email', $email );

		return $user ? $this->votes->voter_for_user( (int) $user->ID ) : '';
	}

	/**
	 * Describe a subscription status in words.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Stored status.
	 * @return string
	 */
	private function status_label( string $status ): string {
		$labels = array(
			Repository::ACTIVE       => __( 'Active', 'convocept' ),
			Repository::PENDING      => __( 'Waiting for confirmation', 'convocept' ),
			Repository::UNSUBSCRIBED => __( 'Unsubscribed', 'convocept' ),
		);

		return $labels[ $status ] ?? $status;
	}

	/**
	 * Format a stored UTC date for an export.
	 *
	 * @since 1.0.0
	 *
	 * @param string $gmt Date in UTC (Y-m-d H:i:s).
	 * @return string Local date and time in the site's format.
	 */
	private function local_date( string $gmt ): string {
		return (string) get_date_from_gmt( $gmt, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
	}
}
