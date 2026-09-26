<?php
/**
 * Tests for plugin bootstrap and lifecycle.
 *
 * @package Convocept
 */

namespace Convocept\Tests;

use Convocept\Database\Migrator;
use Convocept\Lifecycle;
use Convocept\Plugin;
use Convocept\Settings\Settings;
use WP_UnitTestCase;

/**
 * @covers \Convocept\Plugin
 * @covers \Convocept\Lifecycle
 */
class PluginTest extends WP_UnitTestCase {

	public function test_plugin_booted_on_plugins_loaded(): void {
		$this->assertSame( 1, did_action( 'convocept_loaded' ) );
		$this->assertInstanceOf( Settings::class, Plugin::instance()->settings() );
	}

	public function test_boot_is_idempotent(): void {
		Plugin::instance()->boot();

		$this->assertSame( 1, did_action( 'convocept_loaded' ) );
	}

	public function test_activation_runs_migrations(): void {
		delete_option( Migrator::VERSION_OPTION );

		Lifecycle::activate();

		$migrator = new Migrator();
		$this->assertSame( $migrator->latest_version(), $migrator->current_version() );
	}

	public function test_deactivation_clears_registered_cron_hooks(): void {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'convocept_test_event' );
		$filter = static function ( $hooks ) {
			$hooks[] = 'convocept_test_event';
			return $hooks;
		};
		add_filter( 'convocept_cron_hooks', $filter );

		Lifecycle::deactivate();

		$this->assertFalse( wp_next_scheduled( 'convocept_test_event' ) );
		remove_filter( 'convocept_cron_hooks', $filter );
	}
}
