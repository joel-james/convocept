<?php
/**
 * Tests for front-end asset loading.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Assets;

use Convocept\Assets\FrontAssets;
use Convocept\Tests\TestCase;

/**
 * @covers \Convocept\Assets\FrontAssets
 */
class FrontAssetsTest extends TestCase {

	public function test_comment_reply_is_dequeued_on_thread_pages(): void {
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		wp_enqueue_script( 'comment-reply' );

		$this->assets()->dequeue_comment_reply();

		$this->assertFalse( wp_script_is( 'comment-reply', 'enqueued' ) );
	}

	public function test_comment_reply_kept_where_convocept_is_disabled(): void {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( get_permalink( $page_id ) );
		wp_enqueue_script( 'comment-reply' );

		$this->assets( array( 'post_types' => array( 'post' ) ) )->dequeue_comment_reply();

		$this->assertTrue( wp_script_is( 'comment-reply', 'enqueued' ) );
		wp_dequeue_script( 'comment-reply' );
	}

	/**
	 * Build the asset service.
	 *
	 * @param array<string, mixed> $settings Settings to store first.
	 * @return FrontAssets
	 */
	private function assets( array $settings = array() ): FrontAssets {
		return new FrontAssets( $this->renderer( $settings ), new \Convocept\Comments\EditWindow( new \Convocept\Settings\Settings() ), new \Convocept\Settings\Settings() );
	}

	public function test_assets_wrap_the_first_thread_only(): void {
		$assets = $this->assets();

		$first  = $assets->wrap( '<section>one</section>' );
		$second = $assets->wrap( '<section>two</section>' );

		$this->assertStringStartsWith( '<style id="convocept-css">', $first );
		$this->assertStringContainsString( '<script type="application/json" id="convocept-config">', $first );
		$this->assertStringContainsString( '<script id="convocept-loader">', $first );
		$this->assertSame( '<section>two</section>', $second );
	}

	public function test_config_is_inert_json_without_visitor_data(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter(
			'convocept_front_config',
			static function ( $config ) {
				$config['evil'] = '</script><script>alert(1)</script>';
				return $config;
			}
		);

		$html = $this->assets()->wrap( '<section></section>' );

		remove_all_filters( 'convocept_front_config' );

		$this->assertStringNotContainsString( '</script><script>alert', $html );
		$this->assertStringNotContainsString( 'nonce', $html );
		$this->assertStringContainsString( 'convocept.js', $html );
	}

	public function test_nothing_is_added_to_the_head(): void {
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		do_action( 'wp_enqueue_scripts' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.

		$this->assertFalse( wp_style_is( 'convocept', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'convocept', 'enqueued' ) );
	}

	public function test_poll_interval_only_when_enabled(): void {
		$this->assertSame( 0, $this->assets()->config()['poll'] );
		$this->assertSame(
			30,
			$this->assets(
				array(
					'live_updates'  => true,
					'poll_interval' => 30,
				)
			)->config()['poll']
		);
	}

	public function test_modules_filter_adds_scripts(): void {
		add_filter(
			'convocept_front_modules',
			static function ( $scripts ) {
				$scripts[] = 'https://example.org/addon.js';
				return $scripts;
			}
		);

		$scripts = $this->assets()->config()['scripts'];

		remove_all_filters( 'convocept_front_modules' );

		$this->assertSame( 'https://example.org/addon.js', end( $scripts ) );
	}
}
