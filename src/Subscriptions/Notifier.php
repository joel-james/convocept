<?php
/**
 * New-comment notifications.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Subscriptions;

use Convocept\Render\CommentPresenter;
use Convocept\Render\TemplateLoader;
use Convocept\Render\ThreadRenderer;
use WP_Comment;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Queues and writes the emails subscribers get when a comment is approved.
 *
 * Only approved comments are ever sent, once each: when saved already
 * approved (from any source), or when a moderator approves them later. The comment's own
 * author is never notified, and each address gets one email per comment
 * even if it follows the post, the parent comment and the whole site.
 *
 * @since 1.0.0
 */
class Notifier {

	/**
	 * Private comment meta key recording that a comment was queued.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const QUEUED_META_KEY = '_convocept_notified';

	/**
	 * Subscription service.
	 *
	 * @since 1.0.0
	 * @var Subscriptions
	 */
	private $subscriptions;

	/**
	 * Queue.
	 *
	 * @since 1.0.0
	 * @var Queue
	 */
	private $queue;

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
	 * Thread renderer, for enablement checks and comment links.
	 *
	 * @since 1.0.0
	 * @var ThreadRenderer
	 */
	private $renderer;

	/**
	 * Comment presenter, for the comment text.
	 *
	 * @since 1.0.0
	 * @var CommentPresenter
	 */
	private $presenter;

	/**
	 * Create the notifier.
	 *
	 * @since 1.0.0
	 *
	 * @param Subscriptions    $subscriptions Subscription service.
	 * @param Queue            $queue         Queue.
	 * @param Mailer           $mailer        Mailer.
	 * @param TemplateLoader   $templates     Templates.
	 * @param ThreadRenderer   $renderer      Thread renderer.
	 * @param CommentPresenter $presenter     Comment presenter.
	 */
	public function __construct( Subscriptions $subscriptions, Queue $queue, Mailer $mailer, TemplateLoader $templates, ThreadRenderer $renderer, CommentPresenter $presenter ) {
		$this->subscriptions = $subscriptions;
		$this->queue         = $queue;
		$this->mailer        = $mailer;
		$this->templates     = $templates;
		$this->renderer      = $renderer;
		$this->presenter     = $presenter;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		// wp_insert_comment fires for every path (comment form, REST, imports, admin).
		add_action( 'wp_insert_comment', array( $this, 'on_insert' ), 20, 2 );
		add_action( 'transition_comment_status', array( $this, 'on_status_change' ), 10, 3 );
		add_action( Queue::HOOK, array( $this, 'run' ) );
		add_filter( 'convocept_cron_hooks', array( $this, 'cron_hooks' ) );
	}

	/**
	 * Queue a comment that was saved already approved.
	 *
	 * @since 1.0.0
	 *
	 * @param int        $comment_id Comment ID.
	 * @param WP_Comment $comment    Comment.
	 * @return void
	 */
	public function on_insert( $comment_id, $comment ): void {
		if ( $comment instanceof WP_Comment && '1' === (string) $comment->comment_approved ) {
			$this->maybe_queue( (int) $comment_id );
		}
	}

	/**
	 * Queue a comment when a moderator approves it.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $new_status New status.
	 * @param string     $old_status Old status.
	 * @param WP_Comment $comment    Comment.
	 * @return void
	 */
	public function on_status_change( $new_status, $old_status, $comment ): void {
		if ( 'approved' === $new_status && 'approved' !== $old_status && $comment instanceof WP_Comment ) {
			$this->maybe_queue( (int) $comment->comment_ID );
		}
	}

	/**
	 * Process the queue (hooked to the background event).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function run(): void {
		$this->queue->run( array( $this, 'deliver' ) );
	}

	/**
	 * Send the next emails for one comment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $comment_id Comment ID.
	 * @param int $cursor     Cursor from the last batch.
	 * @param int $limit      Most emails to send now.
	 * @return array{cursor: int, sent: int, done: bool}
	 */
	public function deliver( int $comment_id, int $cursor, int $limit ): array {
		$comment = get_comment( $comment_id );

		// The comment may have been unapproved or deleted, or its post made private, since it was queued.
		if ( ! $comment instanceof WP_Comment || '1' !== (string) $comment->comment_approved || ! $this->is_public( (int) $comment->comment_post_ID ) ) {
			return array(
				'cursor' => $cursor,
				'sent'   => 0,
				'done'   => true,
			);
		}

		$repository = $this->subscriptions->repository();
		$recipients = $repository->recipients(
			(int) $comment->comment_post_ID,
			$this->ancestors( $comment ),
			$repository->hash_email( (string) $comment->comment_author_email ),
			$cursor,
			$limit
		);

		foreach ( $recipients as $recipient ) {
			$this->send( $comment, $recipient );
			$cursor = (int) $recipient['cursor'];
		}

		return array(
			'cursor' => $cursor,
			'sent'   => count( $recipients ),
			'done'   => count( $recipients ) < $limit,
		);
	}

	/**
	 * Add the queue's hook to the hooks cleared on deactivation.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $hooks Cron hooks.
	 * @return string[]
	 */
	public function cron_hooks( $hooks ) {
		$hooks   = (array) $hooks;
		$hooks[] = Queue::HOOK;

		return $hooks;
	}

	/**
	 * Queue a comment once, if it is on a post Convocept handles.
	 *
	 * @since 1.0.0
	 *
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	private function maybe_queue( int $comment_id ): void {
		$comment = get_comment( $comment_id );

		if (
			! $this->subscriptions->enabled()
			|| ! $comment instanceof WP_Comment
			|| in_array( $comment->comment_type, array( 'pingback', 'trackback' ), true )
			|| ! $this->renderer->is_enabled_for( (int) $comment->comment_post_ID )
			|| ! $this->is_public( (int) $comment->comment_post_ID )
		) {
			return;
		}

		/**
		 * Filters whether subscribers are notified about a comment.
		 *
		 * Importers return false so bringing in old comments never emails anyone.
		 *
		 * @since 1.0.0
		 *
		 * @param bool       $notify  Whether to notify. Default true.
		 * @param WP_Comment $comment The comment.
		 */
		if ( ! apply_filters( 'convocept_should_notify', true, $comment ) ) {
			return;
		}

		// Unique meta: a comment is queued at most once, however often it is approved.
		if ( ! add_comment_meta( $comment_id, self::QUEUED_META_KEY, time(), true ) ) {
			return;
		}

		$this->queue->add( $comment_id );
	}

	/**
	 * Write and send one notification.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment                                                                                    $comment   Comment.
	 * @param array{cursor: int, id: int, email: string, is_reply: bool, token_key: string, locale: string} $recipient Recipient.
	 * @return void
	 */
	private function send( WP_Comment $comment, array $recipient ): void {
		$row = $this->subscriptions->repository()->find( $recipient['id'] );

		if ( ! $row ) {
			return;
		}

		// Write the email in the language the reader subscribed in.
		$switched = $recipient['locale'] && switch_to_locale( $recipient['locale'] );

		$post_title = wp_strip_all_tags( get_the_title( (int) $comment->comment_post_ID ) );
		$author     = wp_strip_all_tags( get_comment_author( $comment ) );
		$args       = array(
			'site_name'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'post_title'      => $post_title,
			'author'          => $author,
			'is_reply'        => $recipient['is_reply'],
			'excerpt'         => $this->excerpt( $comment ),
			'comment_url'     => $this->renderer->comment_url( $comment ),
			'unsubscribe_url' => $this->subscriptions->url( $row, 'unsubscribe' ),
			'manage_url'      => $this->subscriptions->url( $row, 'manage' ),
		);

		$subject = $recipient['is_reply']
			/* translators: 1: Comment author, 2: Post title. */
			? sprintf( __( '%1$s replied to your comment on “%2$s”', 'convocept' ), $author, $post_title )
			/* translators: 1: Comment author, 2: Post title. */
			: sprintf( __( '%1$s commented on “%2$s”', 'convocept' ), $author, $post_title );

		$this->mailer->send(
			$recipient['email'],
			$subject,
			$this->templates->render( 'emails/notification', $args ),
			$this->templates->render( 'emails/notification-text', $args ),
			array(
				// RFC 8058 one-click unsubscribe: mail apps POST to this URL.
				'List-Unsubscribe: <' . esc_url_raw( $args['unsubscribe_url'] ) . '>',
				'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
			)
		);

		if ( $switched ) {
			restore_previous_locale();
		}
	}

	/**
	 * Get a plain-text excerpt of a comment.
	 *
	 * Built from the rendered HTML (so Markdown reads naturally) with every
	 * tag stripped; nothing in it is ever run as a shortcode.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment.
	 * @return string
	 */
	private function excerpt( WP_Comment $comment ): string {
		$text = trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $this->presenter->content( $comment ) ), ENT_QUOTES, 'UTF-8' ) ) );

		return wp_html_excerpt( $text, 400, '…' );
	}

	/**
	 * Get the IDs of a comment's ancestors (parent first).
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment.
	 * @return int[]
	 */
	private function ancestors( WP_Comment $comment ): array {
		$ids       = array();
		$parent_id = (int) $comment->comment_parent;
		$guard     = 0;

		// The guard stops a corrupted parent loop from spinning forever.
		while ( $parent_id && $guard++ < 100 ) {
			$ids[]     = $parent_id;
			$parent    = get_comment( $parent_id );
			$parent_id = $parent instanceof WP_Comment ? (int) $parent->comment_parent : 0;
		}

		return $ids;
	}

	/**
	 * Whether anyone may read a post's comments.
	 *
	 * Anyone can subscribe to "all new comments on this site", so comments on
	 * private, draft or password-protected posts are never emailed: that would
	 * leak their title and content to strangers.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_public( int $post_id ): bool {
		$post = get_post( $post_id );

		return $post instanceof WP_Post && is_post_publicly_viewable( $post ) && '' === (string) $post->post_password;
	}
}
