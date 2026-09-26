<?php
/**
 * Tests for the thread query and sort orders.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Comments;

use Convocept\Comments\Sort;
use Convocept\Comments\ThreadQuery;
use Convocept\Tests\TestCase;

/**
 * @covers \Convocept\Comments\ThreadQuery
 * @covers \Convocept\Comments\Thread
 * @covers \Convocept\Comments\Sort
 */
class ThreadQueryTest extends TestCase {

	public function test_empty_post_returns_empty_thread(): void {
		$thread = ( new ThreadQuery() )->run( self::factory()->post->create() );

		$this->assertTrue( $thread->is_empty() );
		$this->assertSame( 0, $thread->pages() );
		$this->assertSame( array(), $thread->comments() );
	}

	public function test_only_approved_comments_and_no_pings(): void {
		$post_id = self::factory()->post->create();
		$visible = $this->comment( $post_id );
		$this->comment( $post_id, array( 'comment_approved' => 0 ) );
		$this->comment( $post_id, array( 'comment_approved' => 'spam' ) );
		$this->comment( $post_id, array( 'comment_type' => 'pingback' ) );

		$thread = ( new ThreadQuery() )->run( $post_id );

		$this->assertSame( 1, $thread->total() );
		$this->assertSame( array( (string) $visible ), wp_list_pluck( $thread->comments(), 'comment_ID' ) );
	}

	public function test_paginates_top_level_comments_only(): void {
		$post_id = self::factory()->post->create();
		$first   = $this->comment( $post_id );
		$this->comment( $post_id, array( 'comment_parent' => $first ) );
		$this->comment( $post_id, array( 'comment_parent' => $first ) );
		$this->comment( $post_id );
		$third = $this->comment( $post_id );

		$query = new ThreadQuery();
		$page1 = $query->run( $post_id, array( 'per_page' => 2 ) );
		$page2 = $query->run(
			$post_id,
			array(
				'per_page' => 2,
				'page'     => 2,
			)
		);

		$this->assertSame( 5, $page1->total() );
		$this->assertSame( 2, $page1->pages() );
		$this->assertCount( 2, $page1->comments() );
		$this->assertCount( 2, $page1->comments()[0]->get_children() );
		$this->assertSame( array( (string) $third ), wp_list_pluck( $page2->comments(), 'comment_ID' ) );
	}

	public function test_page_past_the_end_loads_last_page(): void {
		$post_id = self::factory()->post->create();
		$this->comment( $post_id );
		$this->comment( $post_id );

		$thread = ( new ThreadQuery() )->run(
			$post_id,
			array(
				'per_page' => 1,
				'page'     => 99,
			)
		);

		$this->assertSame( 2, $thread->page() );
		$this->assertCount( 1, $thread->comments() );
	}

	public function test_sort_orders(): void {
		$post_id = self::factory()->post->create();
		$old     = $this->comment( $post_id );
		$mid     = $this->comment( $post_id );
		$new     = $this->comment( $post_id );
		add_comment_meta( $mid, Sort::SCORE_META_KEY, 10 );
		add_comment_meta( $new, Sort::SCORE_META_KEY, 3 );

		$ids = function ( string $sort ) use ( $post_id ) {
			return array_map( 'intval', wp_list_pluck( ( new ThreadQuery() )->run( $post_id, array( 'sort' => $sort ) )->comments(), 'comment_ID' ) );
		};

		$this->assertSame( array( $old, $mid, $new ), $ids( Sort::OLDEST ) );
		$this->assertSame( array( $new, $mid, $old ), $ids( Sort::NEWEST ) );
		$this->assertSame( array( $mid, $new, $old ), $ids( Sort::TOP ), 'Scored first, unscored last.' );
	}

	public function test_invalid_sort_falls_back(): void {
		$this->assertSame( Sort::NEWEST, Sort::resolve( 'random', Sort::NEWEST ) );
		$this->assertSame( Sort::OLDEST, Sort::resolve( array( 'top' ), 'invalid' ) );
		$this->assertSame( Sort::TOP, Sort::resolve( 'top', Sort::OLDEST ) );
	}
}
