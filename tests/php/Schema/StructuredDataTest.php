<?php
/**
 * Tests for comment structured data (M7).
 *
 * @package Convocept
 */

namespace Convocept\Tests\Schema;

use Convocept\Comments\EditWindow;
use Convocept\Integration\SeoPlugins;
use Convocept\Markdown\Parser;
use Convocept\Render\CommentPresenter;
use Convocept\Render\FragmentCache;
use Convocept\Render\ThreadRenderer;
use Convocept\Schema\StructuredData;
use Convocept\Security\IpResolver;
use Convocept\Settings\Settings;
use Convocept\Tests\TestCase;
use Convocept\Votes\Votes;

/**
 * @covers \Convocept\Schema\StructuredData
 * @covers \Convocept\Integration\SeoPlugins
 * @covers \Convocept\Render\ThreadRenderer::visible_ids
 */
class StructuredDataTest extends TestCase {

	/**
	 * Renderer shared with the structured data service under test.
	 *
	 * @var ThreadRenderer
	 */
	private $thread_renderer;

	/**
	 * Build the service with fresh dependencies.
	 *
	 * @param array<string, mixed> $settings Settings to store first.
	 * @return StructuredData
	 */
	private function schema( array $settings = array() ): StructuredData {
		$this->thread_renderer = $this->renderer( $settings );
		$votes                 = new Votes( new IpResolver( new Settings() ) );

		return new StructuredData( new Settings(), $this->thread_renderer, new CommentPresenter( new Parser(), $votes, new Settings() ), $votes, new FragmentCache() );
	}

	/**
	 * Render the thread and capture the footer output.
	 *
	 * @param StructuredData $schema  Service.
	 * @param int            $post_id Post ID.
	 * @return array<string, mixed>|null Decoded JSON-LD, or null when nothing was printed.
	 */
	private function footer_json( StructuredData $schema, int $post_id ): ?array {
		$this->thread_renderer->render( $post_id );

		ob_start();
		$schema->print_standalone();
		$html = (string) ob_get_clean();

		if ( ! preg_match( '#<script type="application/ld\+json" id="convocept-schema">(.*)</script>#s', $html, $match ) ) {
			return null;
		}

		$json = json_decode( $match[1], true );
		$this->assertIsArray( $json, 'Output is valid JSON.' );

		return $json;
	}

	public function test_post_comments_nest_under_a_blog_posting(): void {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Hello schema' ) );
		$parent  = $this->comment(
			$post_id,
			array(
				'comment_author'  => 'Ada',
				'comment_content' => 'First <b>comment</b> &amp; more',
			)
		);
		$reply   = $this->comment(
			$post_id,
			array(
				'comment_author' => 'Grace',
				'comment_parent' => $parent,
			)
		);
		$this->view_post( $post_id );

		$json = $this->footer_json( $this->schema(), $post_id );

		$this->assertSame( 'https://schema.org', $json['@context'] );
		$this->assertSame( 'BlogPosting', $json['@type'] );
		$this->assertSame( 'Hello schema', $json['headline'] );
		$this->assertSame( get_permalink( $post_id ), $json['url'] );
		$this->assertSame( 2, $json['commentCount'] );
		$this->assertCount( 1, $json['comment'], 'Only top-level comments at the first level.' );

		$top = $json['comment'][0];
		$this->assertSame( 'Comment', $top['@type'] );
		$this->assertSame(
			array(
				'@type' => 'Person',
				'name'  => 'Ada',
			),
			$top['author']
		);
		$this->assertSame( 'First comment & more', $top['text'] );
		$this->assertSame( get_comment_date( 'c', $parent ), $top['datePublished'] );
		$this->assertStringEndsWith( '#comment-' . $parent, $top['url'] );
		$this->assertSame( 0, $top['upvoteCount'] );
		$this->assertStringEndsWith( '#comment-' . $reply, $top['comment'][0]['url'], 'Reply nests under its parent.' );
		$this->assertSame( 'Grace', $top['comment'][0]['author']['name'] );
	}

	public function test_pages_use_web_page_and_other_types_article(): void {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->comment( $page_id );
		$this->view_post( $page_id );

		$this->assertSame( 'WebPage', $this->footer_json( $this->schema(), $page_id )['@type'] );
	}

	public function test_nothing_is_printed_without_comments_or_when_disabled(): void {
		$post_id = self::factory()->post->create();
		$this->view_post( $post_id );
		$this->assertNull( $this->footer_json( $this->schema(), $post_id ), 'No comments, no node.' );

		$this->comment( $post_id );
		$this->assertNull( $this->footer_json( $this->schema( array( 'structured_data' => false ) ), $post_id ), 'Setting off.' );

		add_filter( 'convocept_structured_data_enabled', '__return_false' );
		$this->assertNull( $this->footer_json( $this->schema( array( 'structured_data' => true ) ), $post_id ), 'Filter off.' );
	}

	public function test_password_protected_posts_and_archives_get_nothing(): void {
		$post_id = self::factory()->post->create( array( 'post_password' => 'secret' ) );
		$this->comment( $post_id );
		$this->view_post( $post_id );

		$this->assertNull( $this->footer_json( $this->schema(), $post_id ) );

		$open_id = self::factory()->post->create();
		$this->comment( $open_id );
		$this->go_to( home_url( '/' ) );

		$this->assertSame( 0, $this->schema()->current_post_id(), 'Not singular.' );
	}

	public function test_nothing_is_printed_when_the_thread_was_not_rendered(): void {
		$post_id = self::factory()->post->create();
		$this->comment( $post_id );
		$this->view_post( $post_id );
		$schema = $this->schema();

		ob_start();
		$schema->print_standalone();

		$this->assertSame( '', ob_get_clean(), 'Template without comments: no structured data.' );
	}

	public function test_pending_comments_and_hidden_replies_are_left_out(): void {
		$post_id = self::factory()->post->create();
		$parent  = $this->comment( $post_id );
		$replies = array();

		for ( $i = 0; $i < 3; $i++ ) {
			$replies[] = $this->comment( $post_id, array( 'comment_parent' => $parent ) );
		}

		$pending = $this->comment( $post_id, array( 'comment_approved' => 0 ) );
		$this->view_post( $post_id );

		$json = $this->footer_json( $this->schema( array( 'replies_shown' => 2 ) ), $post_id );
		$ids  = wp_list_pluck( $json['comment'][0]['comment'], 'url' );

		$this->assertCount( 1, $json['comment'] );
		$this->assertCount( 2, $ids, 'Replies behind "Show more" are not described.' );
		$this->assertStringNotContainsString( '#comment-' . $replies[2], (string) wp_json_encode( $json ) );
		$this->assertStringNotContainsString( '#comment-' . $pending, (string) wp_json_encode( $json ) );

		$this->view_post( $post_id, array( ThreadRenderer::EXPAND_QUERY_VAR => $parent ) );
		$json = $this->footer_json( $this->schema( array( 'replies_shown' => 2 ) ), $post_id );

		$this->assertCount( 3, $json['comment'][0]['comment'], 'Expanded branch describes every reply.' );
	}

	public function test_replies_past_max_depth_keep_their_real_parent(): void {
		$post_id = self::factory()->post->create();
		$a       = $this->comment( $post_id );
		$b       = $this->comment( $post_id, array( 'comment_parent' => $a ) );
		$c       = $this->comment( $post_id, array( 'comment_parent' => $b ) );
		$this->view_post( $post_id );

		$json   = $this->footer_json( $this->schema( array( 'max_depth' => 2 ) ), $post_id );
		$b_item = $json['comment'][0]['comment'][0];

		$this->assertStringEndsWith( '#comment-' . $b, $b_item['url'] );
		$this->assertStringEndsWith( '#comment-' . $c, $b_item['comment'][0]['url'], 'Shown flat, described as a reply to its parent.' );
	}

	public function test_second_page_describes_its_own_comments(): void {
		$post_id = self::factory()->post->create();
		$first   = $this->comment( $post_id );
		$second  = $this->comment( $post_id );
		$this->view_post( $post_id, array( 'cpage' => 2 ) );

		$json = $this->footer_json( $this->schema( array( 'per_page' => 1 ) ), $post_id );

		$this->assertCount( 1, $json['comment'] );
		$this->assertStringContainsString( 'cpage=2#comment-' . $second, $json['comment'][0]['url'] );
		$this->assertSame( get_permalink( $post_id ), $json['url'], 'The article URL stays the first page.' );
		$this->assertSame( 2, $json['commentCount'] );
		$this->assertStringNotContainsString( '#comment-' . $first . '"', (string) wp_json_encode( $json ) );
	}

	public function test_votes_edits_and_author_urls(): void {
		$user_id = self::factory()->user->create( array( 'display_name' => 'Member' ) );
		$post_id = self::factory()->post->create();
		$member  = $this->comment(
			$post_id,
			array(
				'user_id'            => $user_id,
				'comment_author'     => 'Member',
				'comment_author_url' => 'https://member.example/',
			)
		);
		$this->comment(
			$post_id,
			array(
				'comment_author'     => 'Guest',
				'comment_author_url' => 'https://spam.example/',
			)
		);
		update_comment_meta( $member, EditWindow::EDITED_META_KEY, strtotime( '2026-02-01 10:00:00 UTC' ) );
		$this->view_post( $post_id );

		$json = $this->footer_json( $this->schema( array( 'votes' => false ) ), $post_id );

		$this->assertSame( 'https://member.example/', $json['comment'][0]['author']['url'] );
		$this->assertArrayNotHasKey( 'url', $json['comment'][1]['author'], 'Guest websites are left out.' );
		$this->assertSame( wp_date( 'c', strtotime( '2026-02-01 10:00:00 UTC' ) ), $json['comment'][0]['dateModified'] );
		$this->assertArrayNotHasKey( 'dateModified', $json['comment'][1] );
		$this->assertArrayNotHasKey( 'upvoteCount', $json['comment'][0], 'No vote count when votes are off.' );
	}

	public function test_comment_text_cannot_break_out_of_the_script_element(): void {
		$post_id = self::factory()->post->create();
		$this->comment( $post_id, array( 'comment_content' => 'x &lt;/script&gt;&lt;script&gt;alert(1)&lt;/script&gt;' ) );
		$this->view_post( $post_id );
		$schema = $this->schema();
		$this->thread_renderer->render( $post_id );

		ob_start();
		$schema->print_standalone();
		$html = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $html, '</script>' ), 'Only our own closing tag.' );
		$this->assertStringContainsString( '</script>', $html );
	}

	public function test_yoast_graph_gets_comments_on_its_article_node(): void {
		$post_id = self::factory()->post->create();
		$comment = $this->comment( $post_id );
		$this->view_post( $post_id );
		$schema = $this->schema();
		$seo    = new SeoPlugins( $schema );

		$seo->register();
		$this->assertSame( 20, has_filter( 'wpseo_schema_graph', array( $seo, 'filter_graph' ) ) );

		$graph = $seo->filter_graph(
			array(
				array(
					'@type' => 'WebPage',
					'@id'   => 'x#webpage',
				),
				array(
					'@type'        => array( 'Article', 'BlogPosting' ),
					'@id'          => 'x#article',
					'commentCount' => 1,
				),
			)
		);

		$this->assertArrayNotHasKey( 'comment', $graph[0] );
		$this->assertStringEndsWith( '#comment-' . $comment, $graph[1]['comment'][0]['url'] );
		$this->assertSame( 1, $graph[1]['commentCount'] );
		$this->assertNull( $this->footer_json( $schema, $post_id ), 'No duplicate standalone node.' );
	}

	public function test_rank_math_falls_back_to_the_web_page_node(): void {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->comment( $page_id );
		$this->view_post( $page_id );
		$schema = $this->schema();
		$seo    = new SeoPlugins( $schema );

		$seo->register();
		$this->assertSame( 999, has_filter( 'rank_math/json_ld', array( $seo, 'filter_graph' ) ) );

		$data = $seo->filter_graph(
			array(
				'publisher' => array( '@type' => 'Organization' ),
				'WebPage'   => array( '@type' => 'WebPage' ),
			)
		);

		$this->assertCount( 1, $data['WebPage']['comment'] );
		$this->assertSame( 1, $data['WebPage']['commentCount'] );
		$this->assertArrayNotHasKey( 'comment', $data['publisher'] );
	}

	public function test_graph_without_a_matching_node_leaves_the_footer_node(): void {
		$post_id = self::factory()->post->create();
		$this->comment( $post_id );
		$this->view_post( $post_id );
		$schema = $this->schema();

		$graph = $schema->attach_to_graph( array( array( '@type' => 'Organization' ) ) );

		$this->assertArrayNotHasKey( 'comment', $graph[0] );
		$this->assertNotNull( $this->footer_json( $schema, $post_id ) );
	}

	public function test_items_are_cached_and_filterable(): void {
		$post_id = self::factory()->post->create();
		$this->comment( $post_id );
		$this->view_post( $post_id );
		$this->schema()->build( $post_id );

		$calls = 0;
		add_filter(
			'comment_text',
			static function ( $text ) use ( &$calls ) {
				++$calls;
				return $text;
			}
		);
		add_filter(
			'convocept_structured_data_comments',
			static function ( $items ) {
				$items[0]['extra'] = 'yes';
				return $items;
			}
		);
		$data = $this->schema()->build( $post_id );

		$this->assertSame( 0, $calls, 'Second request reads the cache.' );
		$this->assertSame( 'yes', $data['comments'][0]['extra'], 'Filter runs after the cache.' );
	}
}
