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
	 * Public query variable naming a top-level comment whose replies are all shown.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const EXPAND_QUERY_VAR = 'convocept_expand';

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
	 * Rendered HTML cache.
	 *
	 * @since 1.0.0
	 * @var FragmentCache
	 */
	private $cache;

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
	 * @param FragmentCache    $cache     Rendered HTML cache.
	 */
	public function __construct( Settings $settings, TemplateLoader $templates, ThreadQuery $query, CommentPresenter $presenter, FormToken $tokens, FragmentCache $cache ) {
		$this->settings  = $settings;
		$this->templates = $templates;
		$this->query     = $query;
		$this->presenter = $presenter;
		$this->tokens    = $tokens;
		$this->cache     = $cache;
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

		$thread  = $this->current_thread( $post_id );
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
			'list_html'     => $this->render_items( $thread, $this->current_expand() ),
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

		/**
		 * Filters the complete thread HTML.
		 *
		 * The asset loader uses it to add the stylesheet and the inline
		 * loader around the first thread on a page.
		 *
		 * @since 1.0.0
		 *
		 * @param string $html    Thread HTML.
		 * @param int    $post_id Post ID.
		 */
		return (string) apply_filters( 'convocept_thread_html', $this->templates->render( 'thread', $args ), $post_id );
	}

	/**
	 * Load the thread page the current request asks for.
	 *
	 * Uses the sort and comment page from the URL, so the thread and
	 * anything describing it (structured data) always agree.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return Thread
	 */
	public function current_thread( int $post_id ): Thread {
		return $this->query->run(
			$post_id,
			array(
				'sort'     => $this->requested_sort(),
				'page'     => $this->requested_page(),
				'per_page' => (int) $this->settings->get( 'per_page' ),
			)
		);
	}

	/**
	 * Get the top-level comment whose replies the current request shows in full.
	 *
	 * @since 1.0.0
	 *
	 * @return int Comment ID, or 0.
	 */
	public function current_expand(): int {
		return absint( get_query_var( self::EXPAND_QUERY_VAR ) );
	}

	/**
	 * Keep an expanded comment ID only when it is a top-level comment on the page.
	 *
	 * The ID comes from the URL and is part of cache keys, so any other value
	 * would let visitors create cache entries without limit.
	 *
	 * @since 1.0.0
	 *
	 * @param Thread $thread The thread page.
	 * @param int    $expand Requested comment ID.
	 * @return int The ID, or 0.
	 */
	public function valid_expand( Thread $thread, int $expand ): int {
		foreach ( $thread->comments() as $comment ) {
			if ( $expand === (int) $comment->comment_ID ) {
				return $expand;
			}
		}

		return 0;
	}

	/**
	 * Get the IDs of every comment a thread page shows.
	 *
	 * Follows the same rules as the rendered list (reply limits, expanded
	 * branch, flattening past the maximum depth), so structured data never
	 * describes a reply hidden behind "Show more replies".
	 *
	 * @since 1.0.0
	 *
	 * @param Thread $thread The thread page.
	 * @param int    $expand Top-level comment whose replies are all shown, or 0.
	 * @return array<int, true> Visible comment IDs as keys.
	 */
	public function visible_ids( Thread $thread, int $expand = 0 ): array {
		$ids = array();

		foreach ( $thread->comments() as $comment ) {
			$this->collect_visible( $comment, 1, $expand === (int) $comment->comment_ID, $ids );
		}

		return $ids;
	}

	/**
	 * Add a comment and its visible replies to a list of IDs.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment       $comment  Comment.
	 * @param int              $depth    Visual depth, starting at 1.
	 * @param bool             $expanded Whether the comment's branch shows every reply.
	 * @param array<int, true> $ids      Collected IDs, updated in place.
	 * @return void
	 */
	private function collect_visible( WP_Comment $comment, int $depth, bool $expanded, array &$ids ): void {
		$ids[ (int) $comment->comment_ID ] = true;

		$replies = $this->visible_replies( $comment, $depth, $expanded );

		foreach ( $replies['shown'] as $reply ) {
			if ( $replies['flat'] ) {
				$ids[ (int) $reply->comment_ID ] = true;
				continue;
			}

			$this->collect_visible( $reply, $depth + 1, $expanded, $ids );
		}
	}

	/**
	 * Work out which replies to a comment are shown.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment  Comment.
	 * @param int        $depth    Visual depth of the comment, starting at 1.
	 * @param bool       $expanded Whether the branch shows every reply.
	 * @return array{all: WP_Comment[], shown: WP_Comment[], flat: bool}
	 */
	private function visible_replies( WP_Comment $comment, int $depth, bool $expanded ): array {
		$flat    = $depth >= (int) $this->settings->get( 'max_depth' );
		$replies = $flat ? $this->flattened_descendants( $comment ) : $this->sorted_children( $comment );
		$limit   = $expanded ? 0 : $this->replies_limit();

		return array(
			'all'   => $replies,
			'shown' => $limit > 0 ? array_slice( $replies, 0, $limit ) : $replies,
			'flat'  => $flat,
		);
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
	 * Load and render one page of a post's thread for "load more".
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id Post ID.
	 * @param string $sort    Requested sort (validated).
	 * @param int    $page    Page, starting at 1.
	 * @return array{html: string, page: int, pages: int, total: int}
	 */
	public function render_page( int $post_id, string $sort, int $page ): array {
		$thread = $this->query->run(
			$post_id,
			array(
				'sort'     => Sort::resolve( $sort, (string) $this->settings->get( 'default_sort' ) ),
				'page'     => $page,
				'per_page' => (int) $this->settings->get( 'per_page' ),
			)
		);

		// A page past the end would repeat the last page; return nothing instead.
		return array(
			'html'  => $page > $thread->pages() ? '' : $this->render_items( $thread ),
			'page'  => $thread->page(),
			'pages' => $thread->pages(),
			'total' => $thread->total(),
		);
	}

	/**
	 * Render the comment list items for a thread page, without the wrapper.
	 *
	 * Used for the full page render and for "load more" REST responses,
	 * which append the next page's items to the existing list.
	 *
	 * @since 1.0.0
	 *
	 * @param Thread $thread The thread page.
	 * @param int    $expand Top-level comment whose replies are all shown, or 0.
	 * @return string Comment list items HTML.
	 */
	public function render_items( Thread $thread, int $expand = 0 ): string {
		$expand  = $this->valid_expand( $thread, $expand );
		$post    = get_post( $thread->post_id() );
		$context = $this->context( $thread->post_id(), $this->page_url( $thread->post_id(), $thread->page(), $thread->sort() ), $expand );
		$key     = $this->cache->key(
			array(
				'post'     => $thread->post_id(),
				'modified' => $post ? $post->post_modified_gmt : '',
				'page'     => $thread->page(),
				'sort'     => $thread->sort(),
				'expand'   => $expand,
				'open'     => $context['is_open'],
				'settings' => $this->settings->all(),
				'template' => $this->templates->fingerprint( 'comment' ),
			)
		);

		$html = $this->cache->get( $key );

		if ( null === $html ) {
			$html = $this->render_list( $thread->comments(), 1, $context );
			$this->cache->set( $key, $html );
		}

		return $html;
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
			$reply_to = $this->parent_author( $comment );
			$depth    = $max_depth;
		}

		return $this->render_comment( $comment, $depth, $this->context( $post_id, (string) get_permalink( $post_id ), 0 ), $reply_to, '', array() );
	}

	/**
	 * Render the replies of a comment that were not shown yet.
	 *
	 * Backs the "Show N more replies" button: renders the parent's replies
	 * from $offset on, in the same shape the full thread uses, so the
	 * browser can append them to the parent's reply list.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Parent comment.
	 * @param int        $offset  Number of replies already shown.
	 * @return array{html: string, remaining: int}
	 */
	public function render_more_replies( WP_Comment $comment, int $offset ): array {
		$post_id   = (int) $comment->comment_post_ID;
		$max_depth = (int) $this->settings->get( 'max_depth' );
		$depth     = min( $this->depth_of( $comment, $max_depth ), $max_depth );
		$context   = $this->context( $post_id, $this->comment_url( $comment, false ), 0 );
		$limit     = $this->replies_limit();

		$context['root'] = (int) $this->root_of( $comment )->comment_ID;

		// At the maximum depth replies are a flat, chronological list.
		$flat    = $depth >= $max_depth;
		$replies = $flat ? $this->flattened_descendants( $comment ) : $this->sorted_children( $comment );
		$batch   = array_slice( $replies, $offset, $limit > 0 ? $limit : null );
		$html    = '';

		foreach ( $batch as $reply ) {
			$html .= $flat
				? $this->render_comment( $reply, $max_depth, $context, $this->parent_author( $reply ), '', array() )
				: $this->render_list( array( $reply ), $depth + 1, $context );
		}

		$remaining = max( 0, count( $replies ) - $offset - count( $batch ) );

		return array(
			'html'      => $html,
			'remaining' => $remaining,
			'more_text' => $remaining ? Labels::more_replies( $remaining ) : '',
		);
	}

	/**
	 * Get the permanent link to a comment in Convocept's pagination.
	 *
	 * Points at the page that contains the comment in the default sort.
	 * Links to replies also expand the reply's whole branch, so the reply is
	 * on the page even if it would otherwise sit behind "Show more replies".
	 * Works without JavaScript: the browser jumps to #comment-{ID}.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment       Comment.
	 * @param bool       $with_fragment Whether to append #comment-{ID}.
	 * @return string
	 */
	public function comment_url( WP_Comment $comment, bool $with_fragment = true ): string {
		$post_id = (int) $comment->comment_post_ID;
		$sort    = (string) $this->settings->get( 'default_sort' );
		$root    = $this->root_of( $comment );
		$index   = $this->query->position( $post_id, (int) $root->comment_ID, $sort );
		$page    = null === $index ? 1 : intdiv( $index, max( 1, (int) $this->settings->get( 'per_page' ) ) ) + 1;
		$url     = $this->page_url( $post_id, $page, $sort );

		if ( $root->comment_ID !== $comment->comment_ID ) {
			$url = add_query_arg( self::EXPAND_QUERY_VAR, (int) $root->comment_ID, $url );
		}

		return $with_fragment ? $url . '#comment-' . (int) $comment->comment_ID : $url;
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
	 * Build the rendering context shared by a list render.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $page_url URL of the page the comments are shown on (no fragment).
	 * @param int    $expand   Top-level comment whose replies are all shown, or 0.
	 * @return array{post_id: int, page_url: string, is_open: bool, expand: int, root: int}
	 */
	private function context( int $post_id, string $page_url, int $expand ): array {
		return array(
			'post_id'  => $post_id,
			'page_url' => $page_url,
			'is_open'  => comments_open( $post_id ),
			'expand'   => $expand,
			'root'     => 0,
		);
	}

	/**
	 * Render a list of comments and, recursively, their replies.
	 *
	 * Replies nest until the configured maximum depth. Deeper replies are
	 * shown in one flat, chronological list at the maximum depth, each with
	 * a "Replying to" label, so the conversation stays readable on small
	 * screens. Each reply list shows the first few replies (the
	 * `replies_shown` setting) and a "Show N more replies" link for the rest,
	 * unless the branch is expanded.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment[]         $comments Comments at this level.
	 * @param int                  $depth    Visual depth of this level, starting at 1.
	 * @param array<string, mixed> $context  Rendering context from context().
	 * @return string Comment list items HTML.
	 */
	private function render_list( array $comments, int $depth, array $context ): string {
		$max_depth = (int) $this->settings->get( 'max_depth' );
		$html      = '';

		foreach ( $comments as $comment ) {
			if ( 1 === $depth ) {
				$context['root'] = (int) $comment->comment_ID;
			}

			$visible = $this->visible_replies( $comment, $depth, $context['expand'] === $context['root'] );
			$flat    = $visible['flat'];
			$shown   = $visible['shown'];
			$more    = array(
				'count'  => count( $visible['all'] ) - count( $shown ),
				'offset' => count( $shown ),
				'url'    => add_query_arg( self::EXPAND_QUERY_VAR, $context['root'], $context['page_url'] ) . '#comment-' . (int) $comment->comment_ID,
				'flat'   => $flat,
			);

			$replies_html = '';

			foreach ( $shown as $reply ) {
				$replies_html .= $flat
					? $this->render_comment( $reply, $max_depth, $context, $this->parent_author( $reply ), '', array() )
					: $this->render_list( array( $reply ), $depth + 1, $context );
			}

			$html .= $this->render_comment( $comment, $depth, $context, '', $replies_html, $more );
		}//end foreach

		return $html;
	}

	/**
	 * Render one comment through the comment template.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment           $comment      Comment.
	 * @param int                  $depth        Visual depth.
	 * @param array<string, mixed> $context      Rendering context from context().
	 * @param string               $reply_to     Parent author name for flattened replies, or empty.
	 * @param string               $replies_html Rendered replies, or empty.
	 * @param array<string, mixed> $more         Hidden replies: 'count', 'offset', 'url', 'flat'.
	 * @return string
	 */
	private function render_comment( WP_Comment $comment, int $depth, array $context, string $reply_to, string $replies_html, array $more ): string {
		$view                 = $this->presenter->present( $comment, $depth, (string) $context['page_url'], (bool) $context['is_open'], $reply_to );
		$view['replies_html'] = $replies_html;
		$view['replies_flat'] = ! empty( $more['flat'] );
		$view['more_replies'] = array(
			'count'  => (int) ( $more['count'] ?? 0 ),
			'offset' => (int) ( $more['offset'] ?? 0 ),
			'url'    => (string) ( $more['url'] ?? '' ),
		);

		return $this->templates->render( 'comment', $view );
	}

	/**
	 * Get how many replies each reply list shows before "Show more".
	 *
	 * @since 1.0.0
	 *
	 * @return int 0 means all.
	 */
	private function replies_limit(): int {
		return (int) $this->settings->get( 'replies_shown' );
	}

	/**
	 * Get the author name of a comment's parent.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment.
	 * @return string
	 */
	private function parent_author( WP_Comment $comment ): string {
		$parent = get_comment( (int) $comment->comment_parent );

		return $parent instanceof WP_Comment ? get_comment_author( $parent ) : '';
	}

	/**
	 * Get a comment's top-level ancestor (itself for top-level comments).
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment.
	 * @return WP_Comment
	 */
	private function root_of( WP_Comment $comment ): WP_Comment {
		$root  = $comment;
		$guard = 0;

		// The guard stops a corrupted parent loop from spinning forever.
		while ( (int) $root->comment_parent && $guard++ < 100 ) {
			$parent = get_comment( (int) $root->comment_parent );

			if ( ! $parent instanceof WP_Comment ) {
				break;
			}

			$root = $parent;
		}

		return $root;
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

		$args = array(
			'thread'     => $thread,
			'sort_links' => $thread->total() > 1 ? $links : array(),
			'follow'     => false,
		);

		/**
		 * Filters the data passed to the thread header template.
		 *
		 * Subscriptions set `follow` to show the Follow link when the
		 * in-thread subscribe form is on the page.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $args   Template arguments: 'thread', 'sort_links' and 'follow'.
		 * @param Thread               $thread The thread being rendered.
		 */
		$args = (array) apply_filters( 'convocept_thread_header_args', $args, $thread );

		return $this->templates->render( 'thread-header', $args );
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
				'subscriptions'         => (bool) $this->settings->get( 'subscriptions' ),
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
