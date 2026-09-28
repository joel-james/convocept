<?php
/**
 * Tests for the settings screen (M9).
 *
 * @package Convocept
 */

namespace Convocept\Tests\Admin;

use Convocept\Admin\SettingsPage;
use Convocept\Settings\Settings;
use Convocept\Tests\TestCase;

/**
 * @covers \Convocept\Admin\SettingsPage
 */
class SettingsPageTest extends TestCase {

	/**
	 * Build the page as an administrator in wp-admin.
	 *
	 * @return SettingsPage
	 */
	private function page(): SettingsPage {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );

		$page = new SettingsPage( new Settings() );
		$page->add_page();

		return $page;
	}

	public function tear_down(): void {
		wp_dequeue_script( SettingsPage::HANDLE );
		wp_deregister_script( SettingsPage::HANDLE );
		wp_dequeue_style( SettingsPage::HANDLE );
		wp_deregister_style( SettingsPage::HANDLE );
		parent::tear_down();
	}

	public function test_page_is_added_under_settings(): void {
		$this->page();

		$this->assertSame( admin_url( 'edit-comments.php?page=convocept&tab=import' ), SettingsPage::url( 'import' ) );
		$this->assertNotEmpty( get_plugin_page_hookname( 'convocept', 'edit-comments.php' ) );
	}

	public function test_app_loads_on_its_own_screen_only(): void {
		$page = $this->page();

		$page->enqueue( 'index.php' );
		$this->assertFalse( wp_script_is( SettingsPage::HANDLE, 'enqueued' ), 'Not on the Dashboard.' );

		// The hook name depends on the admin menu globals, which tests don't build.
		$page->enqueue( get_plugin_page_hookname( 'convocept', 'edit-comments.php' ) );
		$this->assertTrue( wp_script_is( SettingsPage::HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_style_is( SettingsPage::HANDLE, 'enqueued' ) );
		$this->assertStringContainsString( 'window.convoceptAdminBoot', implode( '', (array) wp_scripts()->get_data( SettingsPage::HANDLE, 'before' ) ) );
	}

	public function test_boot_data_has_what_the_app_needs(): void {
		$data = $this->page()->boot_data();

		$this->assertSame( ( new Settings() )->all(), $data['settings'] );
		$this->assertSame( 1, $data['schema']['per_page']['minimum'] );
		$this->assertContains( 'post', wp_list_pluck( $data['postTypes'], 'value' ) );
		$this->assertNotContains( 'attachment', wp_list_pluck( $data['postTypes'], 'value' ) );
		$this->assertTrue( $data['canImport'] );
		$this->assertArrayHasKey( 'requireNameEmail', $data['discussion'] );
	}

	public function test_mount_point_and_plugin_link(): void {
		$page = $this->page();

		ob_start();
		$page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="convocept-admin"', $html );
		$this->assertStringContainsString( '<noscript>', $html );
		$this->assertStringContainsString( 'page=convocept', $page->action_links( array() )['settings'] );
	}
}
