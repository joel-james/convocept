<?php
/**
 * Tests for the settings REST route and validation (M9).
 *
 * @package Convocept
 */

namespace Convocept\Tests\Rest;

use Convocept\Plugin;
use Convocept\Settings\Settings;

/**
 * @covers \Convocept\Rest\SettingsEndpoint
 * @covers \Convocept\Settings\Settings::validate
 * @covers \Convocept\Settings\Settings::schema
 * @covers \Convocept\Settings\Settings::save
 */
class SettingsEndpointTest extends RestTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( Settings::OPTION );
		Plugin::instance()->settings()->flush();
	}

	/**
	 * Log in as an administrator.
	 *
	 * @return void
	 */
	private function as_admin(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function test_only_site_managers_can_read_or_save(): void {
		$this->assertSame( 401, $this->dispatch( 'GET', 'settings' )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 403, $this->dispatch( 'GET', 'settings' )->get_status() );
		$this->assertSame( 403, $this->dispatch( 'POST', 'settings', array( 'settings' => array( 'votes' => false ) ) )->get_status() );
	}

	public function test_get_returns_every_setting_uncached(): void {
		$this->as_admin();

		$response = $this->dispatch( 'GET', 'settings' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( ( new Settings() )->defaults(), $response->get_data()['settings'] );
		$this->assertStringContainsString( 'no-store', $response->get_headers()['Cache-Control'] );
	}

	public function test_saving_changes_only_the_given_keys(): void {
		$this->as_admin();
		update_option( Settings::OPTION, array( 'per_page' => 30 ) );
		$saved = array();

		add_action(
			'convocept_settings_saved',
			static function ( $current, $previous ) use ( &$saved ) {
				$saved = array( $current, $previous );
			},
			10,
			2
		);

		$response = $this->dispatch(
			'POST',
			'settings',
			array(
				'settings' => array(
					'votes'           => false,
					'accent_color'    => '#112233',
					'trusted_proxies' => array( '10.0.0.0/8', '2001:db8::1' ),
				),
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$stored = get_option( Settings::OPTION );
		$this->assertFalse( $stored['votes'] );
		$this->assertSame( '#112233', $stored['accent_color'] );
		$this->assertSame( 30, $stored['per_page'], 'Untouched keys keep their value.' );
		$this->assertSame( array( '10.0.0.0/8', '2001:db8::1' ), $stored['trusted_proxies'] );
		$this->assertSame( $stored, $response->get_data()['settings'] );
		$this->assertTrue( $saved[1]['votes'] );
		$this->assertFalse( $saved[0]['votes'] );
	}

	public function test_invalid_values_are_explained_and_nothing_is_saved(): void {
		$this->as_admin();

		$response = $this->dispatch(
			'POST',
			'settings',
			array(
				'settings' => array(
					'per_page'        => 0,
					'votes'           => 'yes',
					'default_sort'    => 'random',
					'accent_color'    => 'blue',
					'post_types'      => array( 'post', 'nope' ),
					'trusted_proxies' => array( '10.0.0.0/8', 'example.com' ),
					'made_up'         => 1,
					'enabled'         => false,
				),
			)
		);
		$fields   = $response->get_data()['data']['fields'];

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'Enter a number from 1 to 100.', $fields['per_page'] );
		$this->assertSame( 'Choose on or off.', $fields['votes'] );
		$this->assertSame( 'Choose one of the listed options.', $fields['default_sort'] );
		$this->assertStringContainsString( '#2563eb', $fields['accent_color'] );
		$this->assertStringContainsString( '"nope"', $fields['post_types'] );
		$this->assertStringContainsString( '"example.com"', $fields['trusted_proxies'] );
		$this->assertArrayHasKey( 'made_up', $fields );
		$this->assertArrayNotHasKey( 'enabled', $fields );
		$this->assertFalse( get_option( Settings::OPTION ), 'A valid key in a bad request is not saved either.' );
	}

	public function test_add_ons_can_describe_their_keys(): void {
		$this->as_admin();
		add_filter(
			'convocept_settings_defaults',
			static function ( $defaults ) {
				$defaults['addon_limit'] = 5;
				return $defaults;
			}
		);
		add_filter(
			'convocept_settings_schema',
			static function ( $schema ) {
				$schema['addon_limit'] = array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 9,
				);
				return $schema;
			}
		);

		$bad  = $this->dispatch( 'POST', 'settings', array( 'settings' => array( 'addon_limit' => 20 ) ) );
		$good = $this->dispatch( 'POST', 'settings', array( 'settings' => array( 'addon_limit' => 7 ) ) );

		$this->assertSame( 'Enter a number from 1 to 9.', $bad->get_data()['data']['fields']['addon_limit'] );
		$this->assertSame( 7, $good->get_data()['settings']['addon_limit'] );
	}
}
