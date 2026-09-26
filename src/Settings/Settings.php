<?php
/**
 * Settings service.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Reads plugin settings from a single option.
 *
 * The full schema, REST route and admin UI arrive in M9; this class is the
 * one place every other service reads settings from.
 *
 * @since 1.0.0
 */
class Settings {

	/**
	 * Option name holding every plugin setting.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION = 'convocept_settings';

	/**
	 * Settings cached for the current request.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>|null
	 */
	private $cache = null;

	/**
	 * Get the default value for every setting.
	 *
	 * Also the canonical list of valid keys.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> {
	 *     Default settings.
	 *
	 *     @type bool $enabled     Whether Convocept replaces the theme's comments.
	 *     @type int  $edit_window Minutes an author may edit a comment; 0 disables.
	 * }
	 */
	public function defaults(): array {
		$defaults = array(
			'enabled'     => true,
			'edit_window' => 5,
		);

		/**
		 * Filters the default settings.
		 *
		 * Add-ons register their own keys here.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $defaults Default settings.
		 */
		return (array) apply_filters( 'convocept_settings_defaults', $defaults );
	}

	/**
	 * Get all settings, with defaults for missing keys.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$saved = get_option( self::OPTION, array() );

			// Guard against a corrupted or hand-edited option value.
			$this->cache = wp_parse_args(
				is_array( $saved ) ? $saved : array(),
				$this->defaults()
			);
		}

		return $this->cache;
	}

	/**
	 * Get a single setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Setting key, as listed in defaults().
	 * @return mixed Setting value, or null for an unknown key.
	 */
	public function get( string $key ) {
		$all = $this->all();

		return $all[ $key ] ?? null;
	}

	/**
	 * Forget the per-request cache, e.g. after the option changes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->cache = null;
	}
}
