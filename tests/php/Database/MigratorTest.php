<?php
/**
 * Tests for the migration runner.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Database;

use Convocept\Database\Migrator;
use WP_UnitTestCase;

/**
 * @covers \Convocept\Database\Migrator
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
