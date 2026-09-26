<?php
/**
 * Tests for the migration runner and migration 1.
 *
 * @package Convocept
 */

namespace Convocept\Tests;

use Convocept\Database\Migrator;
use Convocept\Settings\Settings;
use WP_UnitTestCase;

/**
 * @covers \Convocept\Database\Migrator
 * @covers \Convocept\Database\Migrations\PrototypeData
 */
class MigratorTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( Migrator::VERSION_OPTION );
		delete_option( Migrator::LOCK_OPTION );
	}

	public function test_fresh_site_reaches_latest_version(): void {
		$migrator = new Migrator();

		$this->assertTrue( $migrator->maybe_migrate() );
		$this->assertSame( $migrator->latest_version(), $migrator->current_version() );
		$this->assertFalse( get_option( Migrator::LOCK_OPTION ) );
	}

	public function test_prototype_edit_meta_is_renamed(): void {
		$comment_id = self::factory()->comment->create();
		add_comment_meta( $comment_id, '_convocept_edited', 1700000000 );

		( new Migrator() )->maybe_migrate();

		$this->assertSame( '', get_comment_meta( $comment_id, '_convocept_edited', true ) );
		$this->assertSame( '1700000000', get_comment_meta( $comment_id, 'convocept_edited_at', true ) );
	}

	public function test_prototype_only_settings_are_dropped(): void {
		update_option(
			Settings::OPTION,
			array(
				'enabled'       => false,
				'edit_window'   => 10,
				'live_updates'  => 'polling',
				'poll_interval' => 30,
				'load_styles'   => true,
			)
		);

		( new Migrator() )->maybe_migrate();

		$this->assertSame(
			array(
				'enabled'     => false,
				'edit_window' => 10,
			),
			get_option( Settings::OPTION )
		);
	}

	public function test_does_not_run_while_locked(): void {
		add_option( Migrator::LOCK_OPTION, time() );

		$migrator = new Migrator();

		$this->assertFalse( $migrator->maybe_migrate() );
		$this->assertSame( 0, $migrator->current_version() );
	}

	public function test_takes_over_stale_lock(): void {
		add_option( Migrator::LOCK_OPTION, time() - Migrator::LOCK_TIMEOUT - 1 );

		$this->assertTrue( ( new Migrator() )->maybe_migrate() );
		$this->assertFalse( get_option( Migrator::LOCK_OPTION ) );
	}

	public function test_up_to_date_site_skips_work(): void {
		$migrator = new Migrator();
		$migrator->maybe_migrate();

		$fired = did_action( 'convocept_migrated' );
		$migrator->maybe_migrate();

		$this->assertSame( $fired, did_action( 'convocept_migrated' ) );
	}
}
