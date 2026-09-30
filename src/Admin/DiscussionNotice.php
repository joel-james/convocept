<?php
/**
 * Notice on Settings → Discussion.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Admin;

use Convocept\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Says on Settings → Discussion which options Convocept replaces.
 *
 * Convocept uses its own settings for threading, comment pages and comment
 * order, and leaves pingbacks and trackbacks out of the thread. Core's
 * options for those stay on the Discussion screen, and must: they still
 * apply to post types Convocept doesn't handle and again when it is turned
 * off. An info notice at the top of that screen only, so changing them
 * without effect doesn't go unexplained. Nothing on the screen is hidden
 * or changed.
 *
 * @since 1.0.0
 */
class DiscussionNotice {

	/**
	 * Settings service.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Create the notice.
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
		add_action( 'load-options-discussion.php', array( $this, 'load' ) );
	}

	/**
	 * Queue the notice while the Discussion screen loads.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function load(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Print the notice, when Convocept shows comments anywhere.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render(): void {
		$types = $this->post_type_names();

		if ( ! $this->settings->get( 'enabled' ) || array() === $types ) {
			return;
		}

		$message = sprintf(
			/* translators: %s: List of post type names, e.g. "Posts and Pages". */
			esc_html__( 'Convocept shows the comments on %s. There, its own settings for threading, comment pages and comment order replace the ones below, and pingbacks and trackbacks are not shown. Everything else on this screen still applies.', 'convocept' ),
			esc_html( wp_sprintf( '%l', $types ) )
		);

		$link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( SettingsPage::url( 'general' ) ),
			esc_html__( 'Open Convocept settings', 'convocept' )
		);

		wp_admin_notice(
			$message . ' ' . $link,
			array(
				'type'               => 'info',
				'additional_classes' => array( 'convocept-discussion-notice' ),
			)
		);
	}

	/**
	 * Get the plural names of the post types Convocept handles.
	 *
	 * Post types that are no longer registered are skipped.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	private function post_type_names(): array {
		$names = array();

		foreach ( (array) $this->settings->get( 'post_types' ) as $type ) {
			$object = get_post_type_object( (string) $type );

			if ( $object ) {
				$names[] = (string) $object->labels->name;
			}
		}

		return $names;
	}
}
