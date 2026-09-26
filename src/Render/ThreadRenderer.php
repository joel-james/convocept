<?php
/**
 * Thread renderer.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Render;

use Convocept\Comments\Sort;
use Convocept\Comments\Submission;
use Convocept\Comments\Thread;
use Convocept\Comments\ThreadQuery;
use Convocept\Moderation\Checks\HoneypotCheck;
use Convocept\Security\FormToken;
use Convocept\Settings\Settings;
use WP_Comment;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the complete comment thread HTML for a post.
 *
 * The output is the same for every visitor: no nonces, no "logged in as",
 * no commenter cookie values and no moderation links. That keeps it safe
 * for full-page caches. Anything per visitor is added later by JavaScript
 * from the REST session endpoint (M2).
 *
 * Rendering is bottom-up: replies are rendered first and passed to their
 * parent's template as HTML, so every template only prints its own markup.
 *
 * @since 1.0.0
 */
class ThreadRenderer {

	/**
	 * Settings service.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Template loader.
	 *
	 * @since 1.0.0
	 * @var TemplateLoader
	 */
	private $templates;

	/**
	 * Thread query.
	 *
	 * @since 1.0.0
	 * @var ThreadQuery
	 */
	private $query;

	/**
	 * Comment presenter.
	 *
	 * @since 1.0.0
	 * @var CommentPresenter
	 */
	private $presenter;

	/**
	 * Form token service.
	 *
	 * @since 1.0.0
	 * @var FormToken
	 */
	private $tokens;

	/**
	 * Post IDs whose thread was already rendered in this request.
	 *
	 * Themes and blocks can ask for comments more than once on a page; the
	 * thread must still appear only once.
	 *
	 * @since 1.0.0
	 * @var array<int, true>
	 */
	private $rendered = array();

	/**
	 * Create the renderer.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings         $settings  Settings service.
	 * @param TemplateLoader   $templates Template loader.
	 * @param ThreadQuery      $query     Thread query.
	 * @param CommentPresenter $presenter Comment presenter.
	 * @param FormToken        $tokens    Form token service.
	 */
	public function __construct( Settings $settings, TemplateLoader $templates, ThreadQuery $query, CommentPresenter $presenter, FormToken $tokens ) {
		$this->settings  = $settings;
		$this->templates = $templates;
		$this->query     = $query;
		$this->presenter = $presenter;
		$this->tokens    = $tokens;
	}

	/**
	 * Whether Convocept should replace the comments of a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_enabled_for( int $post_id ): bool {
		$post    = get_post( $post_id );
		$enabled = $post instanceof WP_Post
			&& (bool) $this->settings->get( 'enabled' )
			&& in_array( $post->post_type, (array) $this->settings->get( 'post_types' ), true )
			&& post_type_supports( $post->post_type, 'comments' );

		/**
		 * Filters whether Convocept replaces the comments of a post.
		 *
		 * Use it to turn Convocept off for specific posts, post types or
		 * templates. When false, the theme's own comments render.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $enabled Whether Convocept is enabled for the post.
		 * @param int  $post_id Post ID.
		 */
		return (bool) apply_filters( 'convocept_enabled', $enabled, $post_id );
	}

	/**
	 * Whether the thread for a post was already rendered in this request.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function has_rendered( int $post_id ): bool {
		return isset( $this->rendered[ $post_id ] );
	}

	/**
	 * Render the thread for a post.
	 *
	 * Returns an empty string (and renders nothing) when the post is
	 * password-protected, or when comments are closed and there are none to
	 * show, matching what core themes do.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string Thread HTML.
	 */
	public function render( int $post_id ): string {
		$this->rendered[ $post_id ] = true;

		if ( post_password_required( $post_id ) ) {
			return '';
		}

		$thread = $this->query->run(
			$post_id,
			array(
				'sort'     => $this->requested_sort(),
				'page'     => $this->requested_page(),
				'per_page' => (int) $this->settings->get( 'per_page' ),
			)
		);

		$is_open = comments_open( $post_id );

		if ( ! $is_open && $thread->is_empty() ) {
			return '';
		}

		$args = array(
			'thread'        => $thread,
			'post_id'       => $post_id,
			'classes'       => $this->wrapper_classes( $post_id ),
			'is_open'       => $is_open,
			'color_scheme'  => (string) $this->settings->get( 'color_scheme' ),
			'accent_color'  => (string) $this->settings->get( 'accent_color' ),
			'header_html'   => $this->render_header( $thread ),
			'composer_html' => $is_open ? $this->render_composer( $post_id ) : '',
			'list_html'     => $this->render_list( $thread->comments(), 1, $thread, $is_open ),
			'pagination'    => $this->render_pagination( $thread ),
		);

		/**
		 * Filters the data passed to the thread template.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $args   Template arguments.
		 * @param Thread               $thread The thread being rendered.
		 */
		$args = (array) apply_filters( 'convocept_thread_template_args', $args, $thread );

		return $this->templates->render( 'thread', $args );
	}

	/**
	 * Get the CSS classes for the thread wrapper.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	private function wrapper_classes( int $post_id ): array {
		/**
		 * Filters the CSS classes on the thread wrapper.
		 *
		 * Theme integrations use it to add the classes a theme expects on its
		 * comments container (for example width constraints).
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $classes CSS classes. Always includes 'convocept'.
		 * @param int      $post_id Post ID.
		 */
		$classes = (array) apply_filters( 'convocept_thread_classes', array( 'convocept' ), $post_id );

		return array_values( array_unique( array_filter( array_map( 'sanitize_html_class', $classes ) ) ) );
	}

	/**
	 * Render one comment on its own, for REST responses.
	 *
	 * Used after posting or editing, when the browser inserts or replaces a
	 * single comment. The depth is worked out from the comment's ancestors,
	 * capped at the maximum depth like the full thread.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment.
	 * @return string Comment HTML (an <li> element).
	 */
	public function render_single( WP_Comment $comment ): string {
		$post_id   = (int) $comment->comment_post_ID;
		$max_depth = (int) $this->settings->get( 'max_depth' );
		$depth     = $this->depth_of( $comment, $max_depth + 1 );
		$reply_to  = '';

		if ( $depth > $max_depth ) {
			$parent   = get_comment( (int) $comment->comment_parent );
			$reply_to = $parent instanceof WP_Comment ? get_comment_author( $parent ) : '';
			$depth    = $max_depth;
		}

		return $this->render_comment( $comment, $depth, (string) get_permalink( $post_id ), comments_open( $post_id ), $reply_to, '' );
	}

	/**
	 * Count a comment's nesting depth by walking up its parents.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment.
	 * @param int        $limit   Stop counting at this depth.
	 * @return int Depth, starting at 1 for a top-level comment.
	 */
	private function depth_of( WP_Comment $comment, int $limit ): int {
		$depth     = 1;
		$parent_id = (int) $comment->comment_parent;

		while ( $parent_id && $depth < $limit ) {
			$parent    = get_comment( $parent_id );
			$parent_id = $parent instanceof WP_Comment ? (int) $parent->comment_parent : 0;
			++$depth;
		}

		return $depth;
	}

	/**
	 * Build the URL of a thread page for a sort order.
	 *
	 * Uses core's comment page URLs (`/comment-page-2/` or `?cpage=2`) so
	 * pages are crawlable and work with core rewrite rules and canonical
	 * redirects. The sort is added only when it differs from the default,
	 * which keeps one canonical URL for the default view.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id Post ID.
	 * @param int    $page    Page number, starting at 1.
	 * @param string $sort    Sort order.
	 * @return string URL without fragment.
	 */
	public function page_url( int $post_id, int $page, string $sort ): string {
		$url = (string) get_permalink( $post_id );

		if ( $page > 1 ) {
			global $wp_rewrite;

			if ( $wp_rewrite->using_permalinks() && false === strpos( $url, '?' ) ) {
				$url = user_trailingslashit( trailingslashit( $url ) . $wp_rewrite->comments_pagination_base . '-' . $page, 'commentpaged' );
			} else {
				$url = add_query_arg( 'cpage', $page, $url );
			}
		}

		if ( (string) $this->settings->get( 'default_sort' ) !== $sort ) {
			$url = add_query_arg( Sort::QUERY_VAR, $sort, $url );
		}

		return $url;
	}

	/**
	 * Render a list of comments and, recursively, their replies.
	 *
	 * Replies nest until the configured maximum depth. Deeper replies are
	 * shown flattened at the maximum depth, in date order, with a "replying
	 * to" label so the conversation stays readable on small screens.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment[] $comments Comments at this level.
	 * @param int          $depth    Visual depth of this level, starting at 1.
	 * @param Thread       $thread   The thread being rendered.
	 * @param bool         $is_open  Whether new comments are accepted.
	 * @return string Comment list items HTML.
	 */
	private function render_list( array $comments, int $depth, Thread $thread, bool $is_open ): string {
		$max_depth = (int) $this->settings->get( 'max_depth' );
		$page_url  = $this->page_url( $thread->post_id(), $thread->page(), $thread->sort() );
		$html      = '';

		foreach ( $comments as $comment ) {
			$children = $this->sorted_children( $comment );

			if ( $depth < $max_depth ) {
				$replies_html = $children ? $this->render_list( $children, $depth + 1, $thread, $is_open ) : '';
				$html        .= $this->render_comment( $comment, $depth, $page_url, $is_open, '', $replies_html );
				continue;
			}

			// At the maximum depth: this comment, then all its descendants as siblings.
			$html .= $this->render_comment( $comment, $depth, $page_url, $is_open, '', '' );

			foreach ( $this->flattened_descendants( $comment ) as $descendant ) {
				$parent   = get_comment( (int) $descendant->comment_parent );
				$reply_to = $parent instanceof WP_Comment ? get_comment_author( $parent ) : '';
				$html    .= $this->render_comment( $descendant, $depth, $page_url, $is_open, $reply_to, '' );
			}
		}

		return $html;
	}

	/**
	 * Render one comment through the comment template.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment      Comment.
	 * @param int        $depth        Visual depth.
	 * @param string     $page_url     URL of the current thread page.
	 * @param bool       $is_open      Whether replies are accepted.
	 * @param string     $reply_to     Parent author name for flattened replies, or empty.
	 * @param string     $replies_html Rendered replies, or empty.
	 * @return string
	 */
	private function render_comment( WP_Comment $comment, int $depth, string $page_url, bool $is_open, string $reply_to, string $replies_html ): string {
		$view                 = $this->presenter->present( $comment, $depth, $page_url, $is_open, $reply_to );
		$view['replies_html'] = $replies_html;

		return $this->templates->render( 'comment', $view );
	}

	/**
	 * Get a comment's direct replies, oldest first.
	 *
	 * Replies read as a conversation whatever the top-level sort, so they
	 * are always chronological.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Parent comment (children already loaded by ThreadQuery).
	 * @return WP_Comment[]
	 */
	private function sorted_children( WP_Comment $comment ): array {
		$children = array_values(
			array_filter(
				(array) $comment->get_children( array( 'format' => 'tree' ) ),
				static function ( $child ) {
					return $child instanceof WP_Comment && '1' === (string) $child->comment_approved;
				}
			)
		);

		usort( $children, array( $this, 'compare_chronological' ) );

		return $children;
	}

	/**
	 * Get every descendant of a comment as one chronological list.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Ancestor comment.
	 * @return WP_Comment[]
	 */
	private function flattened_descendants( WP_Comment $comment ): array {
		$descendants = array();

		foreach ( $this->sorted_children( $comment ) as $child ) {
			$descendants[] = $child;
			$descendants   = array_merge( $descendants, $this->flattened_descendants( $child ) );
		}

		usort( $descendants, array( $this, 'compare_chronological' ) );

		return $descendants;
	}

	/**
	 * Compare two comments by date, then ID, for usort().
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $a First comment.
	 * @param WP_Comment $b Second comment.
	 * @return int
	 */
	private function compare_chronological( WP_Comment $a, WP_Comment $b ): int {
		return array( $a->comment_date_gmt, (int) $a->comment_ID ) <=> array( $b->comment_date_gmt, (int) $b->comment_ID );
	}

	/**
	 * Render the thread header: comment count and sort links.
	 *
	 * @since 1.0.0
	 *
	 * @param Thread $thread The thread.
	 * @return string
	 */
	private function render_header( Thread $thread ): string {
		$sorts = array(
			Sort::OLDEST => __( 'Oldest', 'convocept' ),
			Sort::NEWEST => __( 'Newest', 'convocept' ),
			Sort::TOP    => __( 'Top', 'convocept' ),
		);

		$links = array();

		foreach ( $sorts as $sort => $label ) {
			$links[] = array(
				'label'   => $label,
				'url'     => $this->page_url( $thread->post_id(), 1, $sort ) . '#comments',
				'current' => $thread->sort() === $sort,
			);
		}

		return $this->templates->render(
			'thread-header',
			array(
				'thread'     => $thread,
				'sort_links' => $thread->total() > 1 ? $links : array(),
			)
		);
	}

	/**
	 * Render the comment form.
	 *
	 * Core hooks wp_comment_form_unfiltered_html_nonce() to `comment_form`,
	 * which prints a nonce for users who may post unfiltered HTML. That is
	 * per-user output and must never reach a cached page, so it is detached
	 * while the form renders and restored afterwards. (Without it, a
	 * no-JavaScript submission is filtered through kses like any other
	 * comment; the JavaScript composer posts over REST instead.)
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function render_composer( int $post_id ): string {
		$parent   = $this->requested_parent( $post_id );
		$priority = has_action( 'comment_form', 'wp_comment_form_unfiltered_html_nonce' );

		if ( false !== $priority ) {
			remove_action( 'comment_form', 'wp_comment_form_unfiltered_html_nonce', (int) $priority );
		}

		$html = $this->templates->render(
			'composer',
			array(
				'post_id'               => $post_id,
				'action_url'            => site_url( '/wp-comments-post.php' ),
				'parent_id'             => $parent ? (int) $parent->comment_ID : 0,
				'reply_to'              => $parent ? get_comment_author( $parent ) : '',
				'cancel_reply_url'      => (string) get_permalink( $post_id ) . '#respond',
				'registration_required' => (bool) get_option( 'comment_registration' ),
				'login_url'             => wp_login_url( (string) get_permalink( $post_id ) . '#respond' ),
				'require_name_email'    => (bool) get_option( 'require_name_email' ),
				'cookies_opt_in'        => has_action( 'set_comment_cookies', 'wp_set_comment_cookies' ) && get_option( 'show_comments_cookies_opt_in' ),
				'max_lengths'           => wp_get_comment_fields_max_lengths(),
				'marker_field'          => Submission::FORM_MARKER,
				'token_field'           => Submission::TOKEN_FIELD,
				'form_token'            => $this->tokens->create(),
				'honeypot_field'        => HoneypotCheck::FIELD,
			)
		);

		if ( false !== $priority ) {
			add_action( 'comment_form', 'wp_comment_form_unfiltered_html_nonce', (int) $priority );
		}

		return $html;
	}

	/**
	 * Render pagination links.
	 *
	 * Shows the first and last page plus two pages either side of the
	 * current one; skipped ranges become a gap entry (rendered as "…").
	 *
	 * @since 1.0.0
	 *
	 * @param Thread $thread The thread.
	 * @return string
	 */
	private function render_pagination( Thread $thread ): string {
		$pages = $thread->pages();

		if ( $pages < 2 ) {
			return '';
		}

		$page     = $thread->page();
		$post_id  = $thread->post_id();
		$sort     = $thread->sort();
		$links    = array();
		$previous = 0;

		for ( $number = 1; $number <= $pages; $number++ ) {
			if ( 1 !== $number && $pages !== $number && abs( $number - $page ) > 2 ) {
				continue;
			}

			if ( $previous && $number - $previous > 1 ) {
				$links[] = array( 'gap' => true );
			}

			$links[] = array(
				'gap'     => false,
				'number'  => $number,
				'url'     => $this->page_url( $post_id, $number, $sort ) . '#comments',
				'current' => $number === $page,
			);

			$previous = $number;
		}

		return $this->templates->render(
			'pagination',
			array(
				'thread'   => $thread,
				'links'    => $links,
				'prev_url' => $page > 1 ? $this->page_url( $post_id, $page - 1, $sort ) . '#comments' : '',
				'next_url' => $page < $pages ? $this->page_url( $post_id, $page + 1, $sort ) . '#comments' : '',
			)
		);
	}

	/**
	 * Get the sort order requested in the URL, or the default.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function requested_sort(): string {
		return Sort::resolve( get_query_var( Sort::QUERY_VAR ), (string) $this->settings->get( 'default_sort' ) );
	}

	/**
	 * Get the comment page requested in the URL, starting at 1.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	private function requested_page(): int {
		return max( 1, absint( get_query_var( 'cpage' ) ) );
	}

	/**
	 * Get the comment being replied to via the no-JS `replytocom` link.
	 *
	 * The value comes from the URL, not from the visitor, so it is safe to
	 * vary the cached page on it. It is only honoured for an approved
	 * comment on the same post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Comment|null
	 */
	private function requested_parent( int $post_id ): ?WP_Comment {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameter.
		$parent_id = isset( $_GET['replytocom'] ) ? absint( $_GET['replytocom'] ) : 0;

		if ( ! $parent_id ) {
			return null;
		}

		$parent = get_comment( $parent_id );

		if ( ! $parent instanceof WP_Comment || (int) $parent->comment_post_ID !== $post_id || '1' !== (string) $parent->comment_approved ) {
			return null;
		}

		return $parent;
	}
}
