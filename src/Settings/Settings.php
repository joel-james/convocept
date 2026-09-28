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
 * unexpected type into the renderer. The settings screen writes through the
 * REST settings route, which checks input against schema() with validate()
 * and stores it through sanitize().
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
	 * Allowed values for the `commenter_ip` setting.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const IP_MODES = array( 'keep', 'anonymize', 'none' );

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
	 *     @type string   $commenter_ip What is stored of a new comment's IP address: 'keep'
	 *                                  (the full address, as WordPress does), 'anonymize'
	 *                                  (last part zeroed) or 'none'.
	 *     @type bool     $delete_data  Whether deleting the plugin also deletes its settings,
	 *                                  subscriptions, votes and comment meta. Comments stay.
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
			'commenter_ip'    => 'keep',
			'delete_data'     => false,
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
	 * Get the JSON schema of every setting.
	 *
	 * Drives validation of the REST settings route and the limits the
	 * settings screen shows (minimum, maximum, allowed values). Keys must
	 * match defaults(); a key with no schema entry is still accepted and
	 * coerced to its default's type.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>> JSON schema per setting key.
	 */
	public function schema(): array {
		$schema = array(
			'enabled'         => array( 'type' => 'boolean' ),
			'post_types'      => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
			'per_page'        => self::integer( 1, 100 ),
			'max_depth'       => self::integer( 1, 10 ),
			'replies_shown'   => self::integer( 0, 50 ),
			'default_sort'    => array(
				'type' => 'string',
				'enum' => self::SORTS,
			),
			'color_scheme'    => array(
				'type' => 'string',
				'enum' => self::COLOR_SCHEMES,
			),
			'accent_color'    => array(
				'type'    => 'string',
				'pattern' => '^#[0-9a-fA-F]{6}$',
			),
			'edit_window'     => self::integer( 0, 1440 ),
			'trusted_proxies' => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
			'proxy_header'    => array(
				'type' => 'string',
				'enum' => self::PROXY_HEADERS,
			),
			'votes'           => array( 'type' => 'boolean' ),
			'subscriptions'   => array( 'type' => 'boolean' ),
			'double_opt_in'   => array( 'type' => 'boolean' ),
			'batch_size'      => self::integer( 10, 500 ),
			'live_updates'    => array( 'type' => 'boolean' ),
			'poll_interval'   => self::integer( 15, 600 ),
			'structured_data' => array( 'type' => 'boolean' ),
			'commenter_ip'    => array(
				'type' => 'string',
				'enum' => self::IP_MODES,
			),
			'delete_data'     => array( 'type' => 'boolean' ),
		);

		/**
		 * Filters the JSON schema of the settings.
		 *
		 * Add-ons describe their own keys (registered through
		 * `convocept_settings_defaults`) here, so the REST route validates
		 * them and the settings screen knows their limits.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, array<string, mixed>> $schema JSON schema per setting key.
		 */
		return (array) apply_filters( 'convocept_settings_schema', $schema );
	}

	/**
	 * Build the schema of an integer setting.
	 *
	 * @since 1.0.0
	 *
	 * @param int $min Smallest allowed value.
	 * @param int $max Largest allowed value.
	 * @return array<string, mixed>
	 */
	private static function integer( int $min, int $max ): array {
		return array(
			'type'    => 'integer',
			'minimum' => $min,
			'maximum' => $max,
		);
	}

	/**
	 * Check submitted settings and explain every invalid value.
	 *
	 * Unlike sanitize(), which quietly falls back to defaults, this reports
	 * what is wrong in words a site owner can act on, so the settings screen
	 * can show the message under the field.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $input Submitted settings (a subset of keys is fine).
	 * @return array<string, string> Error message per invalid key; empty when all are valid.
	 */
	public function validate( array $input ): array {
		$defaults = $this->defaults();
		$schema   = $this->schema();
		$errors   = array();

		foreach ( $input as $key => $value ) {
			$key = (string) $key;

			if ( ! array_key_exists( $key, $defaults ) ) {
				$errors[ $key ] = __( 'This setting does not exist.', 'convocept' );
				continue;
			}

			$error = $this->validate_value( $key, $value, $schema[ $key ] ?? array() );

			if ( '' !== $error ) {
				$errors[ $key ] = $error;
			}
		}

		return $errors;
	}

	/**
	 * Check one value against its schema and the setting's own rules.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $key    Setting key.
	 * @param mixed                $value  Submitted value.
	 * @param array<string, mixed> $schema The key's schema.
	 * @return string Error message, or '' when valid.
	 */
	private function validate_value( string $key, $value, array $schema ): string {
		$type = $schema['type'] ?? '';

		if ( 'boolean' === $type && ! is_bool( $value ) ) {
			return __( 'Choose on or off.', 'convocept' );
		}

		if ( 'integer' === $type ) {
			$min = (int) ( $schema['minimum'] ?? PHP_INT_MIN );
			$max = (int) ( $schema['maximum'] ?? PHP_INT_MAX );

			if ( ! is_int( $value ) || $value < $min || $value > $max ) {
				/* translators: 1: Smallest allowed number, 2: Largest allowed number. */
				return sprintf( __( 'Enter a number from %1$d to %2$d.', 'convocept' ), $min, $max );
			}
		}

		if ( 'string' === $type ) {
			if ( ! is_string( $value ) ) {
				return __( 'Enter text.', 'convocept' );
			}

			if ( isset( $schema['enum'] ) && ! in_array( $value, (array) $schema['enum'], true ) ) {
				return __( 'Choose one of the listed options.', 'convocept' );
			}

			if ( isset( $schema['pattern'] ) && ! preg_match( '/' . str_replace( '/', '\/', (string) $schema['pattern'] ) . '/', $value ) ) {
				return 'accent_color' === $key ? __( 'Enter a colour as # and six hex digits, like #2563eb.', 'convocept' ) : __( 'This value is not in the expected format.', 'convocept' );
			}
		}

		if ( 'array' === $type ) {
			if ( ! is_array( $value ) || array_filter( $value, 'is_string' ) !== $value ) {
				return __( 'Enter a list of values.', 'convocept' );
			}

			if ( 'post_types' === $key ) {
				foreach ( $value as $post_type ) {
					if ( ! post_type_exists( $post_type ) ) {
						/* translators: %s: Post type name. */
						return sprintf( __( 'There is no post type called "%s".', 'convocept' ), $post_type );
					}
				}
			}

			if ( 'trusted_proxies' === $key ) {
				foreach ( $value as $range ) {
					if ( ! $this->ip_ranges( array( $range ) ) ) {
						/* translators: %s: The invalid line. */
						return sprintf( __( '"%s" is not an IP address or CIDR range.', 'convocept' ), $range );
					}
				}
			}
		}//end if

		return '';
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
	 * Store changed settings.
	 *
	 * Merges the changes into the current settings, sanitizes the result
	 * and saves it. Call validate() first: this never fails, it only falls
	 * back to defaults for invalid values.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $changes Changed keys and their new values.
	 * @return array<string, mixed> The complete settings now stored.
	 */
	public function save( array $changes ): array {
		$old = $this->all();
		$new = $this->sanitize( array_merge( $old, $changes ) );

		update_option( self::OPTION, $new );
		$this->flush();

		/**
		 * Fires after the settings are saved.
		 *
		 * Rendered comment lists are keyed by the settings and refresh on
		 * their own; full-page caches may still serve older pages until
		 * they expire. Cache integrations can purge here.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $new New settings.
		 * @param array<string, mixed> $old Previous settings.
		 */
		do_action( 'convocept_settings_saved', $new, $old );

		return $new;
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

			case 'commenter_ip':
				return in_array( $value, self::IP_MODES, true ) ? $value : $fallback;

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
