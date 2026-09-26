<?php
/**
 * Rate limiter.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Counts actions per key in fixed time windows.
 *
 * Uses the object cache when a persistent one is installed (atomic
 * increments, no database writes) and falls back to transients.
 *
 * @since 1.0.0
 */
class RateLimiter {

	/**
	 * Object cache group.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const GROUP = 'convocept_rate_limit';

	/**
	 * Record an attempt and report whether it is within the limit.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action Action name, e.g. 'comment'.
	 * @param string $key    Who is acting, e.g. a hashed IP. Never a raw IP or email.
	 * @param int    $limit  Attempts allowed per window.
	 * @param int    $window Window length in seconds.
	 * @return bool True when allowed, false when the limit is exceeded.
	 */
	public function attempt( string $action, string $key, int $limit, int $window ): bool {
		/**
		 * Filters a rate limit before it is applied.
		 *
		 * Return 0 to disable the limit.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $limit  Attempts allowed per window.
		 * @param string $action Action name.
		 * @param int    $window Window length in seconds.
		 */
		$limit = (int) apply_filters( 'convocept_rate_limit', $limit, $action, $window );

		if ( $limit <= 0 ) {
			return true;
		}

		$window = max( 1, $window );
		$bucket = 'convocept_rl_' . md5( $action . '|' . $key . '|' . intdiv( time(), $window ) );

		return $this->increment( $bucket, $window ) <= $limit;
	}

	/**
	 * Increment a counter and return its new value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $bucket Counter key.
	 * @param int    $ttl    Seconds the counter lives.
	 * @return int
	 */
	private function increment( string $bucket, int $ttl ): int {
		if ( wp_using_ext_object_cache() ) {
			wp_cache_add( $bucket, 0, self::GROUP, $ttl );

			$count = wp_cache_incr( $bucket, 1, self::GROUP );

			return false === $count ? 1 : (int) $count;
		}

		$count = (int) get_transient( $bucket ) + 1;
		set_transient( $bucket, $count, $ttl );

		return $count;
	}
}
