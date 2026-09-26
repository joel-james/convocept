<?php
/**
 * Comment presenter.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Render;

use Convocept\Comments\EditWindow;
use Convocept\Comments\Submission;
use Convocept\Comments\ThreadQuery;
use Convocept\Markdown\Parser;
use Convocept\Settings\Settings;
use Convocept\Votes\Votes;
use WP_Comment;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a comment into the plain data the comment template prints.
 *
 * Keeping this logic out of the template means a theme that overrides
 * `comment.php` only handles markup, and gets the same avatars, badges and
 * links as the default template. Nothing here depends on who is viewing the
 * page, so the output is safe to page-cache.
 *
 * @since 1.0.0
 */
class CommentPresenter {

	/**
	 * Avatar size in CSS pixels.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const AVATAR_SIZE = 40;

	/**
	 * Markdown parser.
	 *
	 * @since 1.0.0
	 * @var Parser
	 */
	private $markdown;

	/**
	 * Vote service.
	 *
	 * @since 1.0.0
	 * @var Votes
	 */
	private $votes;

	/**
	 * Settings service.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Create the presenter.
	 *
	 * @since 1.0.0
	 *
	 * @param Parser   $markdown Markdown parser.
	 * @param Votes    $votes    Vote service.
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Parser $markdown, Votes $votes, Settings $settings ) {
		$this->markdown = $markdown;
		$this->votes    = $votes;
		$this->settings = $settings;
	}

	/**
	 * Build the view data for one comment.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment   Comment to present.
	 * @param int        $depth     Visual nesting depth, starting at 1.
	 * @param string     $page_url  URL of the thread page the comment is shown on (no fragment).
	 * @param bool       $can_reply Whether a reply link should be shown.
	 * @param string     $reply_to  Name of the comment being replied to when nesting is
	 *                              capped and the reply is shown flattened; otherwise empty.
	 * @return array<string, mixed> {
	 *     View data.
	 *
	 *     @type WP_Comment $comment     The comment.
	 *     @type int        $id          Comment ID.
	 *     @type int        $depth       Visual nesting depth.
	 *     @type string     $author      Author display name (unescaped).
	 *     @type string     $author_url  Author website (unescaped), or empty.
	 *     @type string     $avatar_html Avatar markup (already escaped).
	 *     @type array[]    $badges      Badges, each with 'label' and 'type' keys (unescaped).
	 *     @type string     $date_iso    ISO 8601 date for the datetime attribute.
	 *     @type string     $date_text   Localized date for display.
	 *     @type string     $date_title  Localized date and time for the title attribute.
	 *     @type string     $permalink   Link to the comment on this page.
	 *     @type string     $reply_url   No-JS reply link, or empty when replies are not allowed.
	 *     @type string     $reply_to    Name of the parent comment's author for flattened replies.
	 *     @type string     $content_html Comment content as safe HTML.
	 *     @type bool       $is_pending  Whether the comment is not yet approved. Only ever
	 *                                   true in responses to the comment's own author.
	 *     @type bool       $is_edited   Whether the comment was edited after posting.
	 *     @type int        $author_id   Author's user ID, 0 for guests.
	 *     @type int        $timestamp   Unix time the comment was posted (GMT).
	 *     @type bool       $votes_on    Whether voting is enabled.
	 *     @type int        $votes       Upvote count.
	 *     @type bool       $is_pinned   Whether the comment is pinned.
	 *     @type string[]   $classes     CSS classes for the comment element (unescaped).
	 * }
	 */
	public function present( WP_Comment $comment, int $depth, string $page_url, bool $can_reply, string $reply_to = '' ): array {
		$id      = (int) $comment->comment_ID;
		$post_id = (int) $comment->comment_post_ID;
		$author  = get_comment_author( $comment );
		$badges  = $this->badges( $comment );

		$classes    = array( 'convocept-comment', 'convocept-comment--depth-' . $depth );
		$is_pending = '1' !== (string) $comment->comment_approved;
		$is_pinned  = 0 === (int) $comment->comment_parent && '1' === (string) get_comment_meta( $id, ThreadQuery::PINNED_META_KEY, true );

		if ( $badges ) {
			$classes[] = 'convocept-comment--has-badge';
		}

		if ( $is_pending ) {
			$classes[] = 'convocept-comment--pending';
		}

		if ( $is_pinned ) {
			$classes[] = 'convocept-comment--pinned';
		}

		$view = array(
			'comment'      => $comment,
			'id'           => $id,
			'depth'        => $depth,
			'author'       => $author,
			'author_url'   => $this->author_url( $comment ),
			'avatar_html'  => $this->avatar( $comment, $author ),
			'badges'       => $badges,
			'date_iso'     => (string) get_comment_date( 'c', $comment ),
			'date_text'    => (string) get_comment_date( '', $comment ),
			'date_title'   => sprintf(
				/* translators: 1: Comment date, 2: Comment time. */
				__( '%1$s at %2$s', 'convocept' ),
				get_comment_date( '', $comment ),
				get_comment_time( '', false, true, $comment )
			),
			'permalink'    => $page_url . '#comment-' . $id,
			'reply_url'    => $can_reply && ! $is_pending ? $this->reply_url( $post_id, $id ) : '',
			'reply_to'     => $reply_to,
			'content_html' => $this->content( $comment ),
			'is_pending'   => $is_pending,
			'is_edited'    => '' !== (string) get_comment_meta( $id, EditWindow::EDITED_META_KEY, true ),
			'author_id'    => (int) $comment->user_id,
			'timestamp'    => (int) strtotime( $comment->comment_date_gmt . ' UTC' ),
			'votes_on'     => (bool) $this->settings->get( 'votes' ) && ! $is_pending,
			'votes'        => $this->votes->count( $comment ),
			'is_pinned'    => $is_pinned,
			'classes'      => $classes,
		);

		/**
		 * Filters the view data for one comment before its template renders.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $view    View data (see CommentPresenter::present()).
		 * @param WP_Comment           $comment The comment.
		 */
		return (array) apply_filters( 'convocept_comment_view', $view, $comment );
	}

	/**
	 * Render a comment's content as safe HTML.
	 *
	 * Comments written in Convocept's composer are Markdown and go through
	 * the safe parser. Everything else (older comments, imports, comments
	 * posted without JavaScript) goes through core's `comment_text` filters,
	 * exactly as the theme would have shown it.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment.
	 * @return string
	 */
	public function content( WP_Comment $comment ): string {
		if ( 'markdown' === get_comment_meta( (int) $comment->comment_ID, Submission::FORMAT_META_KEY, true ) ) {
			$html = $this->markdown->parse( (string) $comment->comment_content );
		} else {
			ob_start();
			comment_text( $comment );
			$html = (string) ob_get_clean();
		}

		/**
		 * Filters a comment's rendered content.
		 *
		 * @since 1.0.0
		 *
		 * @param string     $html    Content HTML.
		 * @param WP_Comment $comment The comment.
		 */
		return (string) apply_filters( 'convocept_comment_content', $html, $comment );
	}

	/**
	 * Get the badges shown next to the author name.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment.
	 * @return array<int, array{label: string, type: string}>
	 */
	private function badges( WP_Comment $comment ): array {
		$badges = array();
		$post   = get_post( (int) $comment->comment_post_ID );

		// Match on the user ID, never on name or email, which guests can fake.
		if ( $post && (int) $comment->user_id > 0 && (int) $comment->user_id === (int) $post->post_author ) {
			$badges[] = array(
				'label' => __( 'Author', 'convocept' ),
				'type'  => 'author',
			);
		}

		if ( 0 === (int) $comment->comment_parent && '1' === (string) get_comment_meta( (int) $comment->comment_ID, ThreadQuery::PINNED_META_KEY, true ) ) {
			$badges[] = array(
				'label' => __( 'Pinned', 'convocept' ),
				'type'  => 'pinned',
			);
		}

		/**
		 * Filters the badges shown next to a comment author's name.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array{label: string, type: string}> $badges  Badges. 'label' is shown
		 *                                                                to readers, 'type' becomes
		 *                                                                a CSS modifier class.
		 * @param WP_Comment                                     $comment The comment.
		 */
		$badges = (array) apply_filters( 'convocept_comment_badges', $badges, $comment );

		// Drop malformed entries from filters rather than trusting their shape.
		return array_values(
			array_filter(
				$badges,
				static function ( $badge ) {
					return is_array( $badge ) && isset( $badge['label'], $badge['type'] ) && is_string( $badge['label'] ) && is_string( $badge['type'] );
				}
			)
		);
	}

	/**
	 * Get the author's website, if it is a usable http(s) URL.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment.
	 * @return string
	 */
	private function author_url( WP_Comment $comment ): string {
		$url = (string) get_comment_author_url( $comment );

		if ( '' === $url || 'http://' === $url ) {
			return '';
		}

		// Only allow http(s). No wp_http_validate_url(): it does a DNS lookup per call.
		return '' !== esc_url_raw( $url, array( 'http', 'https' ) ) ? $url : '';
	}

	/**
	 * Build the avatar markup.
	 *
	 * Uses the site's avatar setting. When avatars are off, or no avatar can
	 * be built, it falls back to the author's initials, which need no request.
	 * Images are lazy-loaded with fixed dimensions so they never shift layout.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment.
	 * @param string     $author  Author display name.
	 * @return string Escaped HTML.
	 */
	private function avatar( WP_Comment $comment, string $author ): string {
		if ( get_option( 'show_avatars' ) ) {
			$avatar = get_avatar(
				$comment,
				self::AVATAR_SIZE,
				'',
				'',
				array(
					'class'    => 'convocept-avatar',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			);

			if ( $avatar ) {
				return $avatar;
			}
		}

		return sprintf(
			'<span class="convocept-avatar convocept-avatar--initials" style="--convocept-avatar-hue:%1$d" aria-hidden="true">%2$s</span>',
			absint( crc32( $author ) % 360 ),
			esc_html( $this->initials( $author ) )
		);
	}

	/**
	 * Get up to two initials from a name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	private function initials( string $name ): string {
		$words    = preg_split( '/\s+/u', trim( wp_strip_all_tags( $name ) ), -1, PREG_SPLIT_NO_EMPTY );
		$initials = '';

		foreach ( array_slice( is_array( $words ) ? $words : array(), 0, 2 ) as $word ) {
			$initials .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 );
		}

		$initials = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $initials ) : strtoupper( $initials );

		return '' !== $initials ? $initials : '?';
	}

	/**
	 * Build the no-JavaScript reply link.
	 *
	 * Uses core's `replytocom` query argument, which wp-comments-post.php
	 * understands. The link is marked nofollow so crawlers don't index one URL
	 * per comment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id    Post ID.
	 * @param int $comment_id Comment being replied to.
	 * @return string
	 */
	private function reply_url( int $post_id, int $comment_id ): string {
		return add_query_arg( 'replytocom', $comment_id, (string) get_permalink( $post_id ) ) . '#respond';
	}
}
