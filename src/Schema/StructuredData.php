<?php
/**
 * Structured data for comments.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Schema;

use Convocept\Comments\EditWindow;
use Convocept\Comments\Thread;
use Convocept\Render\CommentPresenter;
use Convocept\Render\FragmentCache;
use Convocept\Render\ThreadRenderer;
use Convocept\Settings\Settings;
use Convocept\Votes\Votes;
use WP_Comment;

defined( 'ABSPATH' ) || exit;

/**
 * Describes a post's comments as schema.org JSON-LD.
 *
 * Google's guidance is to nest `Comment` items under the page's main
 * entity (an `Article` / `BlogPosting` for posts) and to keep
 * `DiscussionForumPosting` for forums, not for articles with comments.
 * Replies nest under their parent through the `comment` property.
 *
 * Only comments the page actually shows are described: the current
 * comment page and sort, and the replies visible before "Show more
 * replies". When Yoast SEO or Rank Math output a schema graph, the
 * comments are added to their article node (see SeoPlugins); otherwise a
 * standalone node is printed in the footer.
 *
 * Everything here is public data, the same for every visitor, so it is
 * safe in page-cached HTML.
 *
 * @since 1.0.0
 */
class StructuredData {

	/**
	 * Types that describe an article, in which comments are nested first.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const ARTICLE_TYPES = array(
		'Article',
		'AdvertiserContentArticle',
		'AnalysisNewsArticle',
		'BackgroundNewsArticle',
		'BlogPosting',
		'LiveBlogPosting',
		'NewsArticle',
		'OpinionNewsArticle',
		'Report',
		'ReportageNewsArticle',
		'ReviewNewsArticle',
		'SatiricalArticle',
		'ScholarlyArticle',
		'SocialMediaPosting',
		'TechArticle',
	);

	/**
	 * Page types used when a graph has no article node.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const PAGE_TYPES = array(
		'WebPage',
		'AboutPage',
		'CollectionPage',
		'ContactPage',
		'FAQPage',
		'ItemPage',
		'ProfilePage',
		'QAPage',
	);

	/**
	 * Settings service.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Thread renderer, which decides which comments a page shows.
	 *
	 * @since 1.0.0
	 * @var ThreadRenderer
	 */
	private $renderer;

	/**
	 * Comment presenter, used for the comment text.
	 *
	 * @since 1.0.0
	 * @var CommentPresenter
	 */
	private $presenter;

	/**
	 * Vote service.
	 *
	 * @since 1.0.0
	 * @var Votes
	 */
	private $votes;

	/**
	 * Cache for the built comment items.
	 *
	 * @since 1.0.0
	 * @var FragmentCache
	 */
	private $cache;

	/**
	 * Comment data already built in this request, keyed by post ID.
	 *
	 * @since 1.0.0
	 * @var array<int, array{comments: array<int, array<string, mixed>>, count: int}>
	 */
	private $built = array();

	/**
	 * Post IDs whose comments an SEO plugin's graph already carries.
	 *
	 * @since 1.0.0
	 * @var array<int, true>
	 */
	private $attached = array();

	/**
	 * Create the service.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings         $settings  Settings service.
	 * @param ThreadRenderer   $renderer  Thread renderer.
	 * @param CommentPresenter $presenter Comment presenter.
	 * @param Votes            $votes     Vote service.
	 * @param FragmentCache    $cache     Cache for built items.
	 */
	public function __construct( Settings $settings, ThreadRenderer $renderer, CommentPresenter $presenter, Votes $votes, FragmentCache $cache ) {
		$this->settings  = $settings;
		$this->renderer  = $renderer;
		$this->presenter = $presenter;
		$this->votes     = $votes;
		$this->cache     = $cache;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		// The footer runs after both classic (comments_template) and block themes rendered the thread.
		add_action( 'wp_footer', array( $this, 'print_standalone' ), 20 );
	}

	/**
	 * Get the post whose comments the current request can describe.
	 *
	 * Structured data belongs to the main entity of a singular page, so
	 * archives, feeds and secondary loops never get any.
	 *
	 * @since 1.0.0
	 *
	 * @return int Post ID, or 0 when the request has nothing to describe.
	 */
	public function current_post_id(): int {
		if ( ! is_singular() || is_feed() || ! $this->settings->get( 'structured_data' ) ) {
			return 0;
		}

		$post_id = (int) get_queried_object_id();

		if ( ! $post_id || ! $this->renderer->is_enabled_for( $post_id ) || post_password_required( $post_id ) ) {
			return 0;
		}

		/**
		 * Filters whether a post's comments are described in structured data.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $enabled Whether to output structured data. Default true
		 *                      when the `structured_data` setting is on.
		 * @param int  $post_id Post ID.
		 */
		return apply_filters( 'convocept_structured_data_enabled', true, $post_id ) ? $post_id : 0;
	}

	/**
	 * Build the comment items for the thread page the request shows.
	 *
	 * The items are cached with the same invalidation as the rendered
	 * list (any comment change bumps core's `last_changed` stamp), because
	 * turning each comment into plain text runs the Markdown parser or
	 * core's `comment_text` filters again.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return array{comments: array<int, array<string, mixed>>, count: int} Top-level
	 *         `Comment` items (replies nested) and the post's visible comment count.
	 */
	public function build( int $post_id ): array {
		if ( isset( $this->built[ $post_id ] ) ) {
			return $this->built[ $post_id ];
		}

		$thread = $this->renderer->current_thread( $post_id );
		$expand = $this->renderer->valid_expand( $thread, $this->renderer->current_expand() );
		$post   = get_post( $post_id );
		$key    = $this->cache->key(
			array(
				'schema'   => 1,
				'post'     => $post_id,
				'modified' => $post ? $post->post_modified_gmt : '',
				'page'     => $thread->page(),
				'sort'     => $thread->sort(),
				'expand'   => $expand,
				'settings' => $this->settings->all(),
			)
		);

		$cached = $this->cache->get( $key );
		$items  = null === $cached ? null : json_decode( $cached, true );

		if ( ! is_array( $items ) ) {
			$items = $this->items( $thread, $expand );
			$this->cache->set( $key, (string) wp_json_encode( $items ) );
		}

		/**
		 * Filters the `Comment` items describing a post's comments.
		 *
		 * Runs on every request (after the cache), so add-ons can add
		 * properties such as `interactionStatistic` for their own reactions.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array<string, mixed>> $items   Top-level `Comment` items, replies
		 *                                                  nested under `comment`.
		 * @param int                              $post_id Post ID.
		 * @param Thread                           $thread  The thread page described.
		 */
		$items = (array) apply_filters( 'convocept_structured_data_comments', $items, $post_id, $thread );

		$this->built[ $post_id ] = array(
			'comments' => array_values( $items ),
			'count'    => $thread->total(),
		);

		return $this->built[ $post_id ];
	}

	/**
	 * Build the items for every comment a thread page shows.
	 *
	 * @since 1.0.0
	 *
	 * @param Thread $thread The thread page.
	 * @param int    $expand Expanded top-level comment, or 0.
	 * @return array<int, array<string, mixed>>
	 */
	private function items( Thread $thread, int $expand ): array {
		$visible  = $this->renderer->visible_ids( $thread, $expand );
		$page_url = $this->renderer->page_url( $thread->post_id(), $thread->page(), $thread->sort() );
		$items    = array();

		foreach ( $thread->comments() as $comment ) {
			$items[] = $this->item( $comment, $visible, $page_url );
		}

		return $items;
	}

	/**
	 * Build one `Comment` item with its visible replies.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment       $comment  Comment.
	 * @param array<int, true> $visible  IDs of the comments the page shows.
	 * @param string           $page_url URL of the thread page, without fragment.
	 * @return array<string, mixed>
	 */
	private function item( WP_Comment $comment, array $visible, string $page_url ): array {
		$id   = (int) $comment->comment_ID;
		$item = array(
			'@type'         => 'Comment',
			'@id'           => $page_url . '#comment-' . $id,
			'url'           => $page_url . '#comment-' . $id,
			'author'        => $this->author( $comment ),
			'datePublished' => (string) get_comment_date( 'c', $comment ),
			'text'          => $this->text( $comment ),
		);

		$edited = (int) get_comment_meta( $id, EditWindow::EDITED_META_KEY, true );

		if ( $edited > 0 ) {
			$item['dateModified'] = (string) wp_date( 'c', $edited );
		}

		if ( $this->settings->get( 'votes' ) ) {
			$item['upvoteCount'] = $this->votes->count( $comment );
		}

		$replies = array();

		foreach ( $this->visible_children( $comment, $visible ) as $reply ) {
			$replies[] = $this->item( $reply, $visible, $page_url );
		}

		if ( $replies ) {
			$item['comment'] = $replies;
		}

		return $item;
	}

	/**
	 * Get a comment's visible direct replies, oldest first.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment       $comment Parent comment (children loaded by ThreadQuery).
	 * @param array<int, true> $visible IDs of the comments the page shows.
	 * @return WP_Comment[]
	 */
	private function visible_children( WP_Comment $comment, array $visible ): array {
		$children = array_filter(
			(array) $comment->get_children( array( 'format' => 'tree' ) ),
			static function ( $child ) use ( $visible ) {
				return $child instanceof WP_Comment && isset( $visible[ (int) $child->comment_ID ] );
			}
		);

		usort(
			$children,
			static function ( WP_Comment $a, WP_Comment $b ) {
				return array( $a->comment_date_gmt, (int) $a->comment_ID ) <=> array( $b->comment_date_gmt, (int) $b->comment_ID );
			}
		);

		return $children;
	}

	/**
	 * Build the `author` of a comment.
	 *
	 * A website is included only for registered users: a guest's URL is
	 * unverified, and a JSON-LD link cannot carry `rel="nofollow ugc"` the
	 * way the visible link does, so it would hand spammers a followed link.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment.
	 * @return array<string, string>
	 */
	private function author( WP_Comment $comment ): array {
		$author = array(
			'@type' => 'Person',
			'name'  => $this->plain( (string) get_comment_author( $comment ) ),
		);

		$url = (int) $comment->user_id > 0 ? esc_url_raw( (string) $comment->comment_author_url, array( 'http', 'https' ) ) : '';

		if ( '' !== $url ) {
			$author['url'] = $url;
		}

		return $author;
	}

	/**
	 * Get a comment's text as the reader sees it, without markup.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment $comment Comment.
	 * @return string
	 */
	private function text( WP_Comment $comment ): string {
		return $this->plain( $this->presenter->content( $comment ) );
	}

	/**
	 * Turn HTML into plain text on one line per paragraph.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private function plain( string $html ): string {
		// Keep paragraph and line breaks as spaces so words don't run together.
		$html = (string) preg_replace( '#<(?:br|/p|/li|/blockquote|/pre|/h[1-6])\b[^>]*>#i', ' ', $html );
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Build the standalone node for a post: the article with its comments.
	 *
	 * Used when no SEO plugin graph carries the comments. Posts are
	 * `BlogPosting`, pages `WebPage` and other post types `Article`.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> JSON-LD node, or an empty array when there is nothing to describe.
	 */
	public function standalone_node( int $post_id ): array {
		$post = get_post( $post_id );
		$data = $this->build( $post_id );

		if ( ! $post || ! $data['comments'] ) {
			return array();
		}

		$types     = array(
			'post' => 'BlogPosting',
			'page' => 'WebPage',
		);
		$permalink = (string) get_permalink( $post );
		$node      = array(
			'@context'         => 'https://schema.org',
			'@type'            => $types[ $post->post_type ] ?? 'Article',
			'url'              => $permalink,
			'mainEntityOfPage' => $permalink,
			'headline'         => $this->plain( get_the_title( $post ) ),
			'datePublished'    => (string) get_post_time( 'c', false, $post ),
			'dateModified'     => (string) get_post_modified_time( 'c', false, $post ),
		);

		$author = get_userdata( (int) $post->post_author );

		if ( $author ) {
			$node['author'] = array(
				'@type' => 'Person',
				'name'  => $this->plain( $author->display_name ),
				'url'   => get_author_posts_url( $author->ID ),
			);
		}

		$image = get_the_post_thumbnail_url( $post, 'full' );

		if ( $image ) {
			$node['image'] = $image;
		}

		$node['commentCount'] = $data['count'];
		$node['comment']      = $data['comments'];

		/**
		 * Filters the standalone structured data node for a post's comments.
		 *
		 * Printed only when no SEO plugin graph carries the comments. Return
		 * an empty array to print nothing, for example when the theme or
		 * another plugin already describes the article and should receive
		 * the comments through `convocept_structured_data_comments` instead.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $node    JSON-LD node.
		 * @param int                  $post_id Post ID.
		 */
		return (array) apply_filters( 'convocept_structured_data_node', $node, $post_id );
	}

	/**
	 * Add the current post's comments to a schema graph from another plugin.
	 *
	 * The comments go into the first article node, or the web page node
	 * when there is no article. Nodes are matched by `@type`, which can be
	 * a string or a list. Existing `comment` items are kept.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int|string, mixed> $graph Graph nodes, as a list or keyed array.
	 * @return array<int|string, mixed> The graph, with comments added when a target was found.
	 */
	public function attach_to_graph( array $graph ): array {
		$post_id = $this->current_post_id();

		if ( ! $post_id || isset( $this->attached[ $post_id ] ) ) {
			return $graph;
		}

		$target = $this->find_node( $graph, self::ARTICLE_TYPES );

		if ( null === $target ) {
			$target = $this->find_node( $graph, self::PAGE_TYPES );
		}

		if ( null === $target ) {
			return $graph;
		}

		// Marked before building, so a graph with no comments doesn't fall back to the footer node.
		$this->attached[ $post_id ] = true;

		$data = $this->build( $post_id );

		if ( ! $data['comments'] ) {
			return $graph;
		}

		$existing = isset( $graph[ $target ]['comment'] ) && is_array( $graph[ $target ]['comment'] ) ? array_values( $graph[ $target ]['comment'] ) : array();

		$graph[ $target ]['comment'] = array_merge( $existing, $data['comments'] );

		if ( ! isset( $graph[ $target ]['commentCount'] ) ) {
			$graph[ $target ]['commentCount'] = $data['count'];
		}

		return $graph;
	}

	/**
	 * Find the first graph node of one of the given types.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int|string, mixed> $graph Graph nodes.
	 * @param string[]                 $types Accepted types.
	 * @return int|string|null Key of the node, or null.
	 */
	private function find_node( array $graph, array $types ) {
		foreach ( $graph as $key => $node ) {
			if ( ! is_array( $node ) || ! isset( $node['@type'] ) ) {
				continue;
			}

			if ( array_intersect( (array) $node['@type'], $types ) ) {
				return $key;
			}
		}

		return null;
	}

	/**
	 * Whether an SEO plugin's graph already carries a post's comments.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_attached( int $post_id ): bool {
		return isset( $this->attached[ $post_id ] );
	}

	/**
	 * Print the standalone JSON-LD node in the footer.
	 *
	 * Skipped when an SEO plugin's graph already carries the comments, and
	 * when the page never rendered the thread (a template without comments).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function print_standalone(): void {
		$post_id = $this->current_post_id();

		if ( ! $post_id || $this->is_attached( $post_id ) || ! $this->renderer->has_rendered( $post_id ) ) {
			return;
		}

		$node = $this->standalone_node( $post_id );

		if ( ! $node ) {
			return;
		}

		// JSON_HEX_TAG turns "<" and ">" into < / >, so comment text can't close the script element.
		$json = wp_json_encode( $node, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( ! $json ) {
			return;
		}

		echo '<script type="application/ld+json" id="convocept-schema">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG, safe inside a script element.
	}
}
