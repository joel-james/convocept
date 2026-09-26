<?php
/**
 * Plugin Name: Convocept E2E helpers
 * Description: Test-only helpers, mapped into the wp-env tests site only.
 *
 * @package Convocept
 */

// PHPUnit runs on the same tests site and must see real behaviour.
if ( defined( 'DIR_TESTDATA' ) ) {
	return;
}

/*
 * Akismet stand-in: a comment containing this marker is spam, decided at the
 * same hook and priority Akismet uses.
 */
add_filter(
	'pre_comment_approved',
	static function ( $approved, $commentdata ) {
		return false !== strpos( (string) ( $commentdata['comment_content'] ?? '' ), 'convocept-spam-test' ) ? 'spam' : $approved;
	},
	10,
	2
);

// Playwright types faster than a person; timing and rate limits have PHPUnit coverage.
add_filter( 'convocept_min_submit_seconds', '__return_zero' );
add_filter( 'convocept_rate_limit', '__return_zero' );

// Every Playwright visitor shares one IP, which core's flood control would block.
add_action(
	'init',
	static function () {
		remove_action( 'check_comment_flood', 'check_comment_flood_db' );
	}
);
