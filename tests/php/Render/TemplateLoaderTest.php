<?php
/**
 * Tests for the template loader.
 *
 * @package Convocept
 */

namespace Convocept\Tests\Render;

use Convocept\Render\TemplateLoader;
use Convocept\Tests\TestCase;

/**
 * @covers \Convocept\Render\TemplateLoader
 */
class TemplateLoaderTest extends TestCase {

	/**
	 * Temporary theme directory created by a test.
	 *
	 * @var string
	 */
	private $theme_dir = '';

	/**
	 * Point the active theme at a temporary directory.
	 *
	 * @return string The directory.
	 */
	private function fake_theme_dir(): string {
		$this->theme_dir = get_temp_dir() . 'convocept-theme-' . wp_generate_password( 8, false );
		wp_mkdir_p( $this->theme_dir . '/convocept' );

		$dir = $this->theme_dir;
		add_filter(
			'stylesheet_directory',
			static function () use ( $dir ) {
				return $dir;
			}
		);

		return $dir;
	}

	public function tear_down(): void {
		remove_all_filters( 'stylesheet_directory' );

		if ( $this->theme_dir ) {
			array_map( 'unlink', (array) glob( $this->theme_dir . '/convocept/*' ) );
			rmdir( $this->theme_dir . '/convocept' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test cleanup.
			rmdir( $this->theme_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test cleanup.
		}

		parent::tear_down();
	}

	public function test_falls_back_to_plugin_template(): void {
		$this->assertSame( CONVOCEPT_DIR . 'templates/comment.php', ( new TemplateLoader() )->locate( 'comment' ) );
	}

	public function test_theme_override_wins(): void {
		$override = $this->fake_theme_dir() . '/convocept/thread-header.php';
		file_put_contents( $override, '<?php echo "THEME OVERRIDE";' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.

		$loader = new TemplateLoader();

		$this->assertSame( $override, $loader->locate( 'thread-header' ) );
		$this->assertSame( 'THEME OVERRIDE', $loader->render( 'thread-header' ) );
	}

	public function test_path_traversal_is_stripped(): void {
		$this->assertSame( '', ( new TemplateLoader() )->locate( '../../../wp-config' ) );
	}

	public function test_missing_template_renders_empty_string(): void {
		$this->assertSame( '', ( new TemplateLoader() )->render( 'does-not-exist' ) );
	}
}
