<?php
/**
 * Rendered HTML cache.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Render;

defined( 'ABSPATH' ) || exit;

/**
 * Caches rendered comment lists.
 *
 * Keys include core's "comments last changed" stamp, which core bumps
 * whenever any comment or comment meta is added, edited, approved or
 * deleted, so a cached list can never outlive a change to its comments.
 * Stale entries are simply never read again and expire on their own.
 *
 * Uses the object cache when a persistent one is installed. Otherwise each
 * entry is a transient named after what it shows (post, page, sort,
 * expanded comment) that stores the full key with the HTML: a change
 * overwrites that one row instead of adding a new one. Core bumps "last
 * changed" on every vote anywhere, so new rows per change would pile up.
 *
 * @since 1.0.0
 */
class FragmentCache {

	/**
	 * Object cache group.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const GROUP = 'convocept_html';

	/**
	 * Key parts that change while the output still shows the same thing.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	const VOLATILE = array( 'comments_changed', 'version', 'locale', 'theme', 'options', 'settings', 'modified', 'template' );

	/**
	 * Build a cache key from everything the output depends on.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $parts Values the output depends on.
	 * @return string
	 */
	public function key( array $parts ): string {
		$parts['comments_changed'] = wp_cache_get_last_changed( 'comment' );
		$parts['version']          = CONVOCEPT_VERSION;
		$parts['locale']           = get_locale();
		$parts['theme']            = get_stylesheet();

		// Site options that change how every comment renders.
		foreach ( array( 'show_avatars', 'avatar_default', 'avatar_rating', 'date_format', 'time_format', 'timezone_string', 'gmt_offset' ) as $option ) {
			$parts['options'][ $option ] = get_option( $option );
		}

		$slot = array_diff_key( $parts, array_flip( self::VOLATILE ) );

		return 'convocept_html_' . md5( (string) wp_json_encode( $slot ) ) . ':' . md5( (string) wp_json_encode( $parts ) );
	}

	/**
	 * Get cached HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Cache key from key().
	 * @return string|null HTML, or null on a miss or when caching is off.
	 */
	public function get( string $key ): ?string {
		if ( ! $this->enabled() ) {
			return null;
		}

		if ( wp_using_ext_object_cache() ) {
			$html = wp_cache_get( $key, self::GROUP );

			return is_string( $html ) ? $html : null;
		}

		list( $slot, $version ) = $this->split( $key );
		$entry                  = get_transient( $slot );

		return is_array( $entry ) && ( $entry['version'] ?? '' ) === $version && is_string( $entry['html'] ?? null ) ? $entry['html'] : null;
	}

	/**
	 * Store HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key  Cache key from key().
	 * @param string $html HTML.
	 * @return void
	 */
	public function set( string $key, string $html ): void {
		if ( ! $this->enabled() ) {
			return;
		}

		if ( wp_using_ext_object_cache() ) {
			wp_cache_set( $key, $html, self::GROUP, DAY_IN_SECONDS );
			return;
		}

		list( $slot, $version ) = $this->split( $key );

		set_transient(
			$slot,
			array(
				'version' => $version,
				'html'    => $html,
			),
			DAY_IN_SECONDS
		);
	}

	/**
	 * Split a key into its transient name and version.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Cache key from key().
	 * @return array{0: string, 1: string} Transient name, then version.
	 */
	private function split( string $key ): array {
		$parts = explode( ':', $key, 2 );

		return array( $parts[0], $parts[1] ?? '' );
	}

	/**
	 * Whether caching is on.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function enabled(): bool {
		/**
		 * Filters whether rendered comment lists are cached.
		 *
		 * Turn it off if another plugin changes comment output per request
		 * (for example through the `comment_text` filter).
		 *
		 * @since 1.0.0
		 *
		 * @param bool $enabled Whether caching is on. Default true.
		 */
		return (bool) apply_filters( 'convocept_fragment_cache', true );
	}
}
