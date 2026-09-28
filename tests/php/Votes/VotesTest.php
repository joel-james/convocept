<?php
/**
 * Tests for votes and the reactions route.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Votes;

use Convocept\Comments\Sort;
use Convocept\Database\Migrations\ReactionsTable;
use Convocept\Plugin;
use Convocept\Tests\Rest\RestTestCase;
use Convocept\Votes\Votes;

/**
 * @covers \Convocept\Votes\Votes
 * @covers \Convocept\Rest\ReactionsEndpoint
 * @covers \Convocept\Database\Migrations\ReactionsTable
 */
class VotesTest extends RestTestCase {

	/**
	 * Vote over REST.
	 *
	 * @param int  $comment_id Comment ID.
	 * @param bool $active     Vote or unvote.
	 * @return \WP_REST_Response
	 */
	private function vote( int $comment_id, bool $active = true ): \WP_REST_Response {
		return $this->dispatch( 'POST', "comments/{$comment_id}/reactions", array( 'active' => $active ) );
	}

	public function test_table_exists(): void {
		global $wpdb;

		$this->assertSame( ReactionsTable::name(), $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', ReactionsTable::name() ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test.
	}

	public function test_vote_counts_once_per_voter_and_updates_score(): void {
		$id = $this->comment( self::factory()->post->create() );

		$this->assertSame( 1, $this->vote( $id )->get_data()['count'] );
		$this->assertSame( 1, $this->vote( $id )->get_data()['count'], 'Voting twice changes nothing.' );
		$this->assertSame( '1', get_comment_meta( $id, Sort::SCORE_META_KEY, true ) );

		wp_set_current_user( self::factory()->user->create() );
		$this->assertSame( 2, $this->vote( $id )->get_data()['count'], 'Another voter counts.' );

		$this->assertSame( 1, $this->vote( $id, false )->get_data()['count'] );
		$this->assertSame( 1, ( new Votes( Plugin::instance()->ips() ) )->count( get_comment( $id ) ) );
	}

	public function test_votes_drive_top_sort(): void {
		$post_id = self::factory()->post->create();
		$first   = $this->comment( $post_id );
		$second  = $this->comment( $post_id );
		$this->vote( $second );

		$ids = wp_list_pluck( ( new \Convocept\Comments\ThreadQuery() )->run( $post_id, array( 'sort' => Sort::TOP ) )->comments(), 'comment_ID' );

		$this->assertSame( array( (string) $second, (string) $first ), $ids );
	}

	public function test_cannot_vote_on_pending_or_when_disabled(): void {
		$post_id = self::factory()->post->create();
		$pending = $this->comment( $post_id, array( 'comment_approved' => 0 ) );
		$visible = $this->comment( $post_id );

		$this->assertSame( 404, $this->vote( $pending )->get_status() );

		update_option( 'convocept_settings', array( 'votes' => false ) );
		Plugin::instance()->settings()->flush();
		$status = $this->vote( $visible )->get_status();
		Plugin::instance()->settings()->flush();

		$this->assertSame( 404, $status );
	}

	public function test_session_reports_the_visitors_votes(): void {
		$post_id = self::factory()->post->create();
		$id      = $this->comment( $post_id );
		$this->comment( $post_id );
		$this->vote( $id );

		$this->assertSame( array( $id ), $this->dispatch( 'GET', 'session', array( 'post_id' => $post_id ) )->get_data()['votes'] );
	}

	public function test_deleting_a_comment_removes_its_votes(): void {
		global $wpdb;

		$id = $this->comment( self::factory()->post->create() );
		$this->vote( $id );
		wp_delete_comment( $id, true );

		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . ReactionsTable::name() . ' WHERE comment_id = %d', $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	public function test_vote_button_renders_count(): void {
		$post_id = self::factory()->post->create();
		$id      = $this->comment( $post_id );
		$this->vote( $id );
		$this->view_post( $post_id );

		$html = $this->renderer()->render( $post_id );

		$this->assertMatchesRegularExpression( '/data-convocept-vote-count="1">1</', $html );
	}

	public function test_a_guest_is_one_voter_whatever_the_browser(): void {
		$id                     = $this->comment( self::factory()->post->create() );
		$_SERVER['REMOTE_ADDR'] = '198.51.100.44';

		$_SERVER['HTTP_USER_AGENT'] = 'Browser A';
		$this->vote( $id );
		$_SERVER['HTTP_USER_AGENT'] = 'Browser B';
		$count                      = $this->vote( $id )->get_data()['count'];
		unset( $_SERVER['HTTP_USER_AGENT'] );

		$this->assertSame( 1, $count );
	}

	public function test_cannot_vote_on_comments_of_private_posts(): void {
		$id = $this->comment( self::factory()->post->create( array( 'post_status' => 'private' ) ) );

		$this->assertSame( 404, $this->vote( $id )->get_status() );
	}
}
