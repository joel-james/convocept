<?php
/**
 * Tests for front-end moderation and pinning.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Rest;

use Convocept\Admin\CommentsColumn;
use Convocept\Comments\Submission;
use Convocept\Comments\ThreadQuery;
use Convocept\Plugin;

/**
 * @covers \Convocept\Rest\ModerationEndpoint
 * @covers \Convocept\Comments\ThreadQuery
 * @covers \Convocept\Admin\CommentsColumn
 */
class ModerationEndpointTest extends RestTestCase {

	/**
	 * Log in as an editor (moderate_comments).
	 *
	 * @return int User ID.
	 */
	private function moderator(): int {
		$user = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user );

		return $user;
	}

	public function test_guests_and_subscribers_cannot_moderate(): void {
		$id = $this->comment( self::factory()->post->create() );

		$this->assertSame( 401, $this->dispatch( 'POST', "comments/{$id}/moderate", array( 'action' => 'spam' ) )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, $this->dispatch( 'POST', "comments/{$id}/moderate", array( 'action' => 'spam' ) )->get_status() );
		$this->assertSame( 403, $this->dispatch( 'POST', "comments/{$id}/pin", array( 'pinned' => true ) )->get_status() );
		$this->assertSame( '1', get_comment( $id )->comment_approved );
	}

	public function test_moderator_actions(): void {
		$this->moderator();
		$post_id = self::factory()->post->create();
		$pending = $this->comment( $post_id, array( 'comment_approved' => 0 ) );
		$spam    = $this->comment( $post_id );
		$trash   = $this->comment( $post_id );

		$approved = $this->dispatch( 'POST', "comments/{$pending}/moderate", array( 'action' => 'approve' ) )->get_data();
		$this->assertSame( '1', get_comment( $pending )->comment_approved );
		$this->assertStringContainsString( 'id="comment-' . $pending . '"', $approved['html'] );

		$this->assertSame( '', $this->dispatch( 'POST', "comments/{$spam}/moderate", array( 'action' => 'spam' ) )->get_data()['html'] );
		$this->assertSame( 'spam', get_comment( $spam )->comment_approved );

		$this->dispatch( 'POST', "comments/{$trash}/moderate", array( 'action' => 'trash' ) );
		$this->assertSame( 'trash', get_comment( $trash )->comment_approved );

		$this->assertSame( 400, $this->dispatch( 'POST', "comments/{$trash}/moderate", array( 'action' => 'delete' ) )->get_status() );
	}

	public function test_pending_list_is_for_moderators_only(): void {
		$post_id = self::factory()->post->create();
		$pending = $this->comment( $post_id, array( 'comment_approved' => 0 ) );

		$this->assertSame( 401, $this->dispatch( 'GET', "posts/{$post_id}/pending" )->get_status() );

		$this->moderator();
		$items = $this->dispatch( 'GET', "posts/{$post_id}/pending" )->get_data()['items'];

		$this->assertSame( array( $pending ), wp_list_pluck( $items, 'id' ) );
		$this->assertStringContainsString( 'Awaiting moderation', $items[0]['html'] );
	}

	public function test_pinned_comment_leads_page_one_in_every_sort(): void {
		$this->moderator();
		$post_id = self::factory()->post->create();
		$first   = $this->comment( $post_id );
		$second  = $this->comment( $post_id );
		$third   = $this->comment( $post_id );
		$reply   = $this->comment( $post_id, array( 'comment_parent' => $first ) );

		$pinned = $this->dispatch( 'POST', "comments/{$second}/pin", array( 'pinned' => true ) );
		$this->assertStringContainsString( 'convocept-badge--pinned', $pinned->get_data()['html'] );
		$this->assertSame( 400, $this->dispatch( 'POST', "comments/{$reply}/pin", array( 'pinned' => true ) )->get_status() );

		$query = new ThreadQuery();

		foreach ( array( 'oldest', 'newest' ) as $sort ) {
			$ids = array_map( 'intval', wp_list_pluck( $query->run( $post_id, array( 'sort' => $sort ) )->comments(), 'comment_ID' ) );
			$this->assertSame( $second, $ids[0], $sort );
			$this->assertCount( 3, $ids, 'Pinned comment is not repeated.' );
		}

		$page2 = $query->run(
			$post_id,
			array(
				'per_page' => 1,
				'page'     => 2,
			)
		);
		$this->assertSame( array( (string) $third ), wp_list_pluck( $page2->comments(), 'comment_ID' ), 'Pagination skips the pinned comment.' );
		$this->assertSame( 0, $query->position( $post_id, $second, 'oldest' ) );

		$this->dispatch( 'POST', "comments/{$second}/pin", array( 'pinned' => false ) );
		$this->assertSame( array(), $query->pinned_ids( $post_id ) );
	}

	public function test_admin_column_shows_reasons_votes_and_pin(): void {
		$id = $this->comment( self::factory()->post->create() );
		update_comment_meta(
			$id,
			Submission::LOG_META_KEY,
			wp_json_encode(
				array(
					array(
						'verdict' => 'hold',
						'check'   => 'timing',
						'reason'  => 'Too <b>fast</b>',
					),
				)
			)
		);
		update_comment_meta( $id, ThreadQuery::PINNED_META_KEY, '1' );

		$column = new CommentsColumn( Plugin::instance()->votes() );
		$this->assertArrayHasKey( CommentsColumn::COLUMN, $column->add_column( array() ) );

		ob_start();
		$column->render( CommentsColumn::COLUMN, $id );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<strong>Held</strong>: Too &lt;b&gt;fast&lt;/b&gt;', $html );
		$this->assertStringContainsString( 'Pinned', $html );
	}
}
