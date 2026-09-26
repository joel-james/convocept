<?php
/**
 * Tests for the settings service.
 *
 * @package Convocept
 */

namespace Convocept\Tests;

use Convocept\Settings\Settings;
use WP_UnitTestCase;

/**
 * @covers \Convocept\Settings\Settings
 */
class SettingsTest extends WP_UnitTestCase {

	public function test_defaults_fill_missing_keys(): void {
		update_option( Settings::OPTION, array( 'edit_window' => 15 ) );

		$settings = new Settings();

		$this->assertSame( 15, $settings->get( 'edit_window' ) );
		$this->assertTrue( $settings->get( 'enabled' ) );
		$this->assertNull( $settings->get( 'unknown' ) );
	}

	public function test_corrupt_option_falls_back_to_defaults(): void {
		update_option( Settings::OPTION, 'not-an-array' );

		$settings = new Settings();

		$this->assertSame( $settings->defaults(), $settings->all() );
	}

	public function test_addons_can_register_defaults(): void {
		$filter = static function ( $defaults ) {
			$defaults['addon_key'] = 'value';
			return $defaults;
		};
		add_filter( 'convocept_settings_defaults', $filter );

		$this->assertSame( 'value', ( new Settings() )->get( 'addon_key' ) );

		remove_filter( 'convocept_settings_defaults', $filter );
	}

	public function test_flush_rereads_option(): void {
		$settings = new Settings();
		$this->assertSame( 5, $settings->get( 'edit_window' ) );

		update_option( Settings::OPTION, array( 'edit_window' => 0 ) );
		$settings->flush();

		$this->assertSame( 0, $settings->get( 'edit_window' ) );
	}
}
