<?php
/**
 * Tests for the uninstall routine.
 *
 * @package Convocept
 */

namespace Convocept\Tests;

use Convocept\Import\Workspace;
use Convocept\Subscriptions\Queue;
use Convocept\Uninstaller;

/**
 * @covers \Convocept\Uninstaller
 * @covers \Convocept\Import\Workspace::delete_all
 */
class UninstallerTest extends TestCase {

	/**
	 * Table drops seen during the test.
	 *
	 * @var string[]
	 */
	private $drops = array();

	/**
	 * Record DROP TABLE queries.
	 *
	 * The test framework turns them into drops of temporary tables, so the
	 * real tables survive for the other tests.
	 *
	 * @param string $query SQL.
	 * @return string
	 */
	public function record_drop( $query ) {
		if ( 0 === stripos( ltrim( (string) $query ), 'DROP TABLE' ) ) {
			$this->drops[] = (string) $query;
		}

		return $query;
	}

	/**
	 * Set up state a working site would have.
	 *
	 * @return array{comment: int, workspace: string}
	 */
	private function seed(): array {
		$comment = $this->comment( self::factory()->post->create() );

		update_option( 'convocept_db_version', 3 );
		update_option( 'convocept_import_lock', time() );
		update_option( Queue::JOBS_OPTION, array( $comment ) );
		set_transient( 'convocept_html_test', 'x' );
		set_transient( 'convocept_rl_test', 1 );
		set_transient( 'other_plugin_test', 1 );
		update_comment_meta( $comment, 'convocept_pinned', 1 );
		update_comment_meta( $comment, 'convocept_format', 'markdown' );
		update_comment_meta( $comment, '_convocept_edit_token', 'token' );
		update_comment_meta( $comment, '_convocept_disqus_id', '123' );
		wp_schedule_single_event( time() + 60, Queue::HOOK );

		$workspace = ( new Workspace() )->create();
		$this->assertIsString( $workspace );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		file_put_contents( $workspace . '/work.ndjson', '{"email":"a@example.com"}' );

		add_filter( 'query', array( $this, 'record_drop' ), 5 );

		return array(
			'comment'   => $comment,
			'workspace' => $workspace,
		);
	}

	/**
	 * Remove the query recorder.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'query', array( $this, 'record_drop' ), 5 );
		parent::tear_down();
	}

	public function test_by_default_only_working_state_goes(): void {
		update_option( 'convocept_settings', array( 'per_page' => 12 ) );
		$seed = $this->seed();

		( new Uninstaller() )->run();

		$this->assertFalse( get_option( 'convocept_import_lock' ) );
		$this->assertFalse( get_option( Queue::JOBS_OPTION ) );
		$this->assertFalse( get_transient( 'convocept_html_test' ) );
		$this->assertFalse( get_transient( 'convocept_rl_test' ) );
		$this->assertSame( 1, get_transient( 'other_plugin_test' ), 'Other plugins’ transients stay.' );
		$this->assertFalse( wp_next_scheduled( Queue::HOOK ) );
		$this->assertSame( '', get_comment_meta( $seed['comment'], '_convocept_edit_token', true ) );
		$this->assertDirectoryDoesNotExist( $seed['workspace'] );
		$this->assertDirectoryDoesNotExist( ( new Workspace() )->root() );

		$this->assertSame( array( 'per_page' => 12 ), get_option( 'convocept_settings' ) );
		$this->assertSame( '3', (string) get_option( 'convocept_db_version' ) );
		$this->assertSame( '1', get_comment_meta( $seed['comment'], 'convocept_pinned', true ) );
		$this->assertSame( '123', get_comment_meta( $seed['comment'], '_convocept_disqus_id', true ) );
		$this->assertSame( array(), $this->drops, 'Tables stay.' );
		$this->assertInstanceOf( \WP_Comment::class, get_comment( $seed['comment'] ) );
	}

	public function test_delete_data_removes_everything_but_comments(): void {
		global $wpdb;

		update_option( 'convocept_settings', array( 'delete_data' => true ) );
		$seed = $this->seed();

		$this->assertTrue( ( new Uninstaller() )->deletes_data() );
		( new Uninstaller() )->run();

		$this->assertFalse( get_option( 'convocept_settings' ) );
		$this->assertFalse( get_option( 'convocept_db_version' ) );
		$this->assertSame( '', get_comment_meta( $seed['comment'], 'convocept_pinned', true ) );
		$this->assertSame( '', get_comment_meta( $seed['comment'], 'convocept_format', true ) );
		$this->assertSame( '', get_comment_meta( $seed['comment'], '_convocept_disqus_id', true ) );
		$this->assertCount( 2, $this->drops );
		$this->assertStringContainsString( $wpdb->prefix . 'convocept_reactions', $this->drops[0] );
		$this->assertStringContainsString( $wpdb->prefix . 'convocept_subscriptions', $this->drops[1] );
		$this->assertInstanceOf( \WP_Comment::class, get_comment( $seed['comment'] ), 'Comments stay.' );
		$this->assertSame( 1, did_action( 'convocept_uninstalled' ) );
	}
}
