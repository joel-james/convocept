<?php
/**
 * Settings screen.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Admin;

use Convocept\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Comments → Convocept and loads the settings app on it.
 *
 * It sits under Comments, next to the screens it works with, rather than
 * under Settings. The screen is a React app (assets/src/admin) built on the
 * Foxe Plugin Admin UI: a light sidebar of pages grouped as Settings,
 * Manage and More.
 * PHP only prints the mount point and the data the app starts with, so
 * the first paint needs no REST request. The app's script and styles load
 * on this screen only.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Menu slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SLUG = 'convocept';

	/**
	 * Script and style handle.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const HANDLE = 'convocept-admin';

	/**
	 * Settings service.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Admin page hook suffix, set when the page is added.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Create the page.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'redirect_old_location' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CONVOCEPT_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Get the URL of the screen, optionally at a page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $page App page ID, e.g. 'import'.
	 * @return string
	 */
	public static function url( string $page = '' ): string {
		$url = admin_url( 'edit-comments.php?page=' . self::SLUG );

		return '' !== $page ? $url . '#' . rawurlencode( $page ) : $url;
	}

	/**
	 * Add Comments → Convocept.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_page(): void {
		$this->hook_suffix = (string) add_comments_page(
			__( 'Convocept', 'convocept' ),
			__( 'Convocept', 'convocept' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Send the screen's earlier address (under Settings) to its new place.
	 *
	 * Keeps bookmarks and links in older docs or notices working.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function redirect_old_location(): void {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect.
		if ( 'options-general.php' === $pagenow && isset( $_GET['page'] ) && self::SLUG === $_GET['page'] ) {
			wp_safe_redirect( self::url() );
			exit;
		}
	}

	/**
	 * Add a Settings link to the plugin's row on the Plugins screen.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $links Action links.
	 * @return string[]
	 */
	public function action_links( $links ): array {
		$settings = sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Settings', 'convocept' ) );

		return array_merge( array( 'settings' => $settings ), (array) $links );
	}

	/**
	 * Print the mount point.
	 *
	 * Without JavaScript the app can't run, so the fallback says so; the
	 * comment thread itself never depends on this screen.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render(): void {
		echo '<div id="convocept-admin" class="convocept-admin">';
		echo '<noscript><div class="notice notice-error"><p>' . esc_html__( 'The Convocept settings need JavaScript. Turn it on in your browser to change them.', 'convocept' ) . '</p></div></noscript>';
		echo '</div>';
	}

	/**
	 * Load the app on this screen only.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook_suffix ): void {
		if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		$asset_file = CONVOCEPT_DIR . 'assets/dist/admin/index.asset.php';
		$asset      = is_readable( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => CONVOCEPT_VERSION,
		);

		wp_enqueue_script( self::HANDLE, CONVOCEPT_URL . 'assets/dist/admin/index.js', (array) $asset['dependencies'], (string) $asset['version'], true );
		wp_enqueue_style( self::HANDLE, CONVOCEPT_URL . 'assets/dist/admin/index.css', array( 'wp-components' ), (string) $asset['version'] );
		wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
		wp_set_script_translations( self::HANDLE, 'convocept', CONVOCEPT_DIR . 'languages' );

		// Before the app script, so it can read the data when it mounts.
		wp_add_inline_script(
			self::HANDLE,
			'window.convoceptAdminBoot = ' . wp_json_encode( $this->boot_data(), JSON_HEX_TAG | JSON_HEX_AMP ) . ';',
			'before'
		);
	}

	/**
	 * Build the data the app starts with.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function boot_data(): array {
		$counts = wp_count_comments();

		$data = array(
			'settings'   => $this->settings->all(),
			'defaults'   => $this->settings->defaults(),
			'schema'     => $this->settings->schema(),
			'postTypes'  => $this->post_types(),
			'version'    => CONVOCEPT_VERSION,
			'wpVersion'  => get_bloginfo( 'version' ),
			'phpVersion' => PHP_VERSION,
			'canImport'  => current_user_can( 'import' ) && current_user_can( 'moderate_comments' ),
			'discussion' => array(
				'requireNameEmail' => (bool) get_option( 'require_name_email' ),
				'registration'     => (bool) get_option( 'comment_registration' ),
				'manualApproval'   => (bool) get_option( 'comment_moderation' ),
				'previouslyOk'     => (bool) get_option( 'comment_previously_approved' ),
				'closeAfterDays'   => get_option( 'close_comments_for_old_posts' ) ? (int) get_option( 'close_comments_days_old' ) : 0,
			),
			'spam'       => array(
				'akismet'          => defined( 'AKISMET_VERSION' ),
				/** This filter is documented in src/Moderation/Checks/TimingCheck.php */
				'minSubmitSeconds' => (float) apply_filters( 'convocept_min_submit_seconds', 3.0 ),
			),
			'pending'    => (int) $counts->moderated,
			'urls'       => array(
				'discussion' => admin_url( 'options-discussion.php' ),
				'pending'    => admin_url( 'edit-comments.php?comment_status=moderated' ),
				'comments'   => admin_url( 'edit-comments.php' ),
				'plugins'    => admin_url( 'plugins.php' ),
				'docs'       => 'https://docs.foxelabs.com/software/convocept/getting-started',
				'support'    => 'https://wordpress.org/support/plugin/convocept/',
				'website'    => 'https://convocept.com/',
			),
		);

		/**
		 * Filters the data the settings app starts with.
		 *
		 * Add-ons add what their own pages need.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $data Boot data.
		 */
		return (array) apply_filters( 'convocept_admin_boot_data', $data );
	}

	/**
	 * List the post types Convocept can show comments on.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private function post_types(): array {
		$types = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' === $type->name || ! post_type_supports( $type->name, 'comments' ) ) {
				continue;
			}

			$types[] = array(
				'value' => $type->name,
				'label' => (string) $type->labels->name,
			);
		}

		return $types;
	}
}
