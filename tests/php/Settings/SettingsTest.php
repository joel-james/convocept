<?php
/**
 * Tests for the settings service.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Settings;

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

	public function test_sanitize_drops_unknown_keys_and_coerces_types(): void {
		$clean = ( new Settings() )->sanitize(
			array(
				'enabled'  => '0',
				'per_page' => '15',
				'rogue'    => 'value',
			)
		);

		$this->assertFalse( $clean['enabled'] );
		$this->assertSame( 15, $clean['per_page'] );
		$this->assertArrayNotHasKey( 'rogue', $clean );
	}

	public function test_sanitize_rejects_out_of_range_and_invalid_values(): void {
		$settings = new Settings();
		$clean    = $settings->sanitize(
			array(
				'per_page'     => 500,
				'max_depth'    => 0,
				'default_sort' => 'random',
				'color_scheme' => 'neon',
				'accent_color' => 'red; background:url(x)',
				'post_types'   => 'post',
			)
		);
		$defaults = $settings->defaults();

		foreach ( array( 'per_page', 'max_depth', 'default_sort', 'color_scheme', 'accent_color', 'post_types' ) as $key ) {
			$this->assertSame( $defaults[ $key ], $clean[ $key ], $key );
		}
	}

	public function test_sanitize_keeps_valid_values(): void {
		$clean = ( new Settings() )->sanitize(
			array(
				'default_sort' => 'top',
				'color_scheme' => 'system',
				'accent_color' => '#E11D48',
				'post_types'   => array( 'post', 'Post Type!', 'post' ),
			)
		);

		$this->assertSame( 'top', $clean['default_sort'] );
		$this->assertSame( 'system', $clean['color_scheme'] );
		$this->assertSame( '#E11D48', $clean['accent_color'] );
		$this->assertSame( array( 'post', 'posttype' ), $clean['post_types'] );
	}
}
