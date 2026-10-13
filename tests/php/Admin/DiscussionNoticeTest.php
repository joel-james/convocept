<?php
/**
 * Tests for the notice on Settings → Discussion.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Admin;

use Convocept\Admin\DiscussionNotice;
use Convocept\Settings\Settings;
use Convocept\Tests\TestCase;

/**
 * @covers \Convocept\Admin\DiscussionNotice
 */
class DiscussionNoticeTest extends TestCase {

	/**
	 * Render the notice with the given settings.
	 *
	 * @param array<string, mixed> $settings Stored settings.
	 * @return string Printed HTML.
	 */
	private function render( array $settings = array() ): string {
		update_option( Settings::OPTION, $settings );

		ob_start();
		( new DiscussionNotice( new Settings() ) )->render();

		return (string) ob_get_clean();
	}

	public function test_it_is_queued_on_the_discussion_screen_only(): void {
		$notice = new DiscussionNotice( new Settings() );
		$notice->register();

		$this->assertFalse( has_action( 'admin_notices', array( $notice, 'render' ) ) );

		do_action( 'load-options-discussion.php' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.NamingConventions.ValidHookName.UseUnderscores -- Core's screen load hook.
		$this->assertNotFalse( has_action( 'admin_notices', array( $notice, 'render' ) ) );
	}

	public function test_it_names_the_post_types_and_what_is_replaced(): void {
		$html = $this->render( array( 'post_types' => array( 'post', 'page' ) ) );

		$this->assertStringContainsString( 'notice-info', $html );
		$this->assertStringContainsString( 'Posts and Pages', $html );
		$this->assertStringContainsString( 'threading, comment pages and comment order', $html );
		// esc_url() writes the ampersand as &#038; on older WordPress versions (6.5) and &amp; on newer ones.
		$this->assertMatchesRegularExpression( '/edit-comments\.php\?page=convocept&(amp|#038);tab=general/', $html );
	}

	public function test_it_skips_post_types_that_are_gone(): void {
		$html = $this->render( array( 'post_types' => array( 'page', 'no_such_type' ) ) );

		$this->assertStringContainsString( 'on Pages.', $html );
	}

	public function test_nothing_when_convocept_shows_no_comments(): void {
		$this->assertSame( '', $this->render( array( 'enabled' => false ) ) );
		$this->assertSame( '', $this->render( array( 'post_types' => array() ) ) );
	}
}
