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
 * Every setting lives in one autoloaded array option (`convocept_settings`),
 * so a page view costs no extra database query. Values are sanitized on
 * read, which means a corrupted or hand-edited option can never feed an
 * unexpected type into the renderer. The admin UI and REST route that write
 * this option arrive in M9 and will reuse sanitize().
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
	 * Allowed values for the `default_sort` setting.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const SORTS = array( 'oldest', 'newest', 'top' );

	/**
	 * Allowed values for the `color_scheme` setting.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const COLOR_SCHEMES = array( 'auto', 'system', 'light', 'dark' );

	/**
	 * Allowed values for the `proxy_header` setting.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const PROXY_HEADERS = array( 'X-Forwarded-For', 'CF-Connecting-IP', 'X-Real-IP', 'True-Client-IP' );

	/**
	 * Sanitized settings cached for the current request.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>|null
	 */
	private $cache = null;

	/**
	 * Get the default value for every setting.
	 *
	 * Also the canonical list of valid keys: sanitize() drops anything not
	 * listed here, and uses each default's type to coerce saved values.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> {
	 *     Default settings.
	 *
	 *     @type bool     $enabled      Whether Convocept replaces the theme's comments.
	 *     @type string[] $post_types   Post types Convocept renders comments for.
	 *     @type int      $per_page     Top-level comments per page (1–100).
	 *     @type int      $max_depth    Maximum reply nesting depth shown (1–10).
	 *     @type int      $replies_shown Replies shown per reply list before "Show more"
	 *                                  (0–50; 0 shows all).
	 *     @type string   $default_sort Initial sort order: 'oldest', 'newest' or 'top'.
	 *     @type string   $color_scheme 'auto' inherits the theme's colors, 'system' follows the
	 *                                  visitor's OS light/dark preference, 'light' and
	 *                                  'dark' force a palette.
	 *     @type string   $accent_color Hex color used for links, buttons and focus rings.
	 *     @type int      $edit_window  Minutes an author may edit a comment; 0 disables.
	 *     @type string[] $trusted_proxies IPs or CIDR ranges of reverse proxies whose
	 *                                  forwarding header may be trusted. Empty by default:
	 *                                  only REMOTE_ADDR is used.
	 *     @type string   $proxy_header Header a trusted proxy puts the client IP in.
	 *     @type bool     $votes        Whether readers can upvote comments.
	 *     @type bool     $subscriptions Whether readers can subscribe to comments by email.
	 *     @type bool     $double_opt_in Whether new addresses must confirm by email first.
	 *     @type int      $batch_size   Notification emails sent per background run (10–500).
	 *     @type bool     $live_updates Whether open threads check for new comments while
	 *                                  the tab is visible. Off by default.
	 *     @type int      $poll_interval Seconds between checks (15–600).
	 *     @type bool     $structured_data Whether comments are described in schema.org
	 *                                  JSON-LD (added to Yoast SEO / Rank Math's graph
	 *                                  when one is active).
	 * }
	 */
	public function defaults(): array {
		$defaults = array(
			'enabled'         => true,
			'post_types'      => array( 'post', 'page' ),
			'per_page'        => 20,
			'max_depth'       => 3,
			'replies_shown'   => 5,
			'default_sort'    => 'oldest',
			'color_scheme'    => 'auto',
			'accent_color'    => '#2563eb',
			'edit_window'     => 5,
			'trusted_proxies' => array(),
			'proxy_header'    => 'X-Forwarded-For',
			'votes'           => true,
			'subscriptions'   => true,
			'double_opt_in'   => true,
			'batch_size'      => 50,
			'live_updates'    => false,
			'poll_interval'   => 60,
			'structured_data' => true,
		);

		/**
		 * Filters the default settings.
		 *
		 * Add-ons register their own keys here. A key must have a default
		 * to survive sanitize(), and its default's type decides how saved
		 * values are coerced.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $defaults Default settings.
		 */
		return (array) apply_filters( 'convocept_settings_defaults', $defaults );
	}

	/**
	 * Get all settings, sanitized, with defaults for missing keys.
	 *
	 * The result is cached for the rest of the request; call flush() after
	 * changing the option.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Complete, sanitized settings.
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$saved = get_option( self::OPTION, array() );

			$this->cache = $this->sanitize( is_array( $saved ) ? $saved : array() );
		}

		return $this->cache;
	}

	/**
	 * Get a single setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Setting key, as listed in defaults().
	 * @return mixed Sanitized value, or null for an unknown key.
	 */
	public function get( string $key ) {
		$all = $this->all();

		return $all[ $key ] ?? null;
	}

	/**
	 * Sanitize a settings array.
	 *
	 * Unknown keys are dropped, missing keys get their default, and every
	 * value is coerced to its default's type. Known keys additionally get
	 * range or allow-list checks; an invalid value falls back to the default
	 * rather than being "fixed", so bad input never produces a surprising
	 * setting.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $input Raw settings, e.g. from the database or a REST request.
	 * @return array<string, mixed> Sanitized settings containing every known key.
	 */
	public function sanitize( array $input ): array {
		$defaults = $this->defaults();
		$clean    = array();

		foreach ( $defaults as $key => $fallback ) {
			$value = array_key_exists( $key, $input ) ? $input[ $key ] : $fallback;

			$clean[ $key ] = $this->sanitize_value( $key, $value, $fallback );
		}

		return $clean;
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

	/**
	 * Sanitize one setting value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $value   Raw value.
	 * @param mixed  $fallback Default value; its type drives coercion for unknown keys.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_value( string $key, $value, $fallback ) {
		switch ( $key ) {
			case 'post_types':
				$types = is_array( $value ) ? array_map( 'sanitize_key', $value ) : $fallback;

				return array_values( array_unique( array_filter( $types ) ) );

			case 'per_page':
				return $this->int_in_range( $value, 1, 100, $fallback );

			case 'max_depth':
				return $this->int_in_range( $value, 1, 10, $fallback );

			case 'batch_size':
				return $this->int_in_range( $value, 10, 500, $fallback );

			case 'poll_interval':
				return $this->int_in_range( $value, 15, 600, $fallback );

			case 'replies_shown':
				return $this->int_in_range( $value, 0, 50, $fallback );

			case 'edit_window':
				return $this->int_in_range( $value, 0, 1440, $fallback );

			case 'default_sort':
				return in_array( $value, self::SORTS, true ) ? $value : $fallback;

			case 'color_scheme':
				return in_array( $value, self::COLOR_SCHEMES, true ) ? $value : $fallback;

			case 'trusted_proxies':
				return $this->ip_ranges( $value );

			case 'proxy_header':
				return in_array( $value, self::PROXY_HEADERS, true ) ? $value : $fallback;

			case 'accent_color':
				$color = is_string( $value ) ? sanitize_hex_color( $value ) : null;

				return $color ? $color : $fallback;
		}//end switch

		// Add-on keys: coerce to the default's type.
		if ( is_bool( $fallback ) ) {
			return (bool) $value;
		}

		if ( is_int( $fallback ) ) {
			return is_numeric( $value ) ? (int) $value : $fallback;
		}

		if ( is_string( $fallback ) ) {
			return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : $fallback;
		}

		if ( is_array( $fallback ) ) {
			return is_array( $value ) ? $value : $fallback;
		}

		return $value;
	}

	/**
	 * Keep only valid IP addresses and CIDR ranges.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw list.
	 * @return string[]
	 */
	private function ip_ranges( $value ): array {
		$ranges = array();

		foreach ( is_array( $value ) ? $value : array() as $range ) {
			$parts = explode( '/', trim( (string) $range ), 2 );
			$ip    = filter_var( $parts[0], FILTER_VALIDATE_IP );
			$max   = false !== $ip && false !== strpos( (string) $ip, ':' ) ? 128 : 32;

			if ( false === $ip ) {
				continue;
			}

			if ( isset( $parts[1] ) && ( ! ctype_digit( $parts[1] ) || (int) $parts[1] > $max ) ) {
				continue;
			}

			$ranges[] = isset( $parts[1] ) ? $ip . '/' . (int) $parts[1] : $ip;
		}

		return array_values( array_unique( $ranges ) );
	}

	/**
	 * Return an integer within a range, or the default when out of range.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value   Raw value.
	 * @param int   $min     Smallest allowed value.
	 * @param int   $max     Largest allowed value.
	 * @param mixed $fallback Fallback when the value is not numeric or out of range.
	 * @return int
	 */
	private function int_in_range( $value, int $min, int $max, $fallback ): int {
		if ( ! is_numeric( $value ) ) {
			return (int) $fallback;
		}

		$value = (int) $value;

		return ( $value >= $min && $value <= $max ) ? $value : (int) $fallback;
	}
}
