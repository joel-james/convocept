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

// ?convocept_e2e_poll=1 turns on live updates with a 1-second interval for one page view.
add_filter(
	'convocept_front_config',
	static function ( $config ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test-only switch.
		if ( isset( $_GET['convocept_e2e_poll'] ) ) {
			$config['poll'] = 1;
		}

		return $config;
	}
);

/*
 * Mail catcher: emails are stored instead of sent, and exposed to the
 * tests through an admin-only REST route, which can also run the
 * notification queue (WP-Cron can't loop back inside the container).
 */
add_filter(
	'pre_wp_mail',
	static function ( $short_circuit, $atts ) {
		$mails   = (array) get_option( 'convocept_e2e_mails', array() );
		$mails[] = array(
			'to'      => (array) $atts['to'],
			'subject' => $atts['subject'],
			'message' => $atts['message'],
			'headers' => (array) $atts['headers'],
		);
		update_option( 'convocept_e2e_mails', $mails, false );

		return true;
	},
	10,
	2
);

add_action(
	'rest_api_init',
	static function () {
		$admin = static function () {
			return current_user_can( 'manage_options' );
		};

		register_rest_route(
			'convocept-e2e/v1',
			'/mails',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $admin,
					'callback'            => static function () {
						return (array) get_option( 'convocept_e2e_mails', array() );
					},
				),
				array(
					'methods'             => 'DELETE',
					'permission_callback' => $admin,
					'callback'            => static function () {
						delete_option( 'convocept_e2e_mails' );
						return true;
					},
				),
			)
		);

		register_rest_route(
			'convocept-e2e/v1',
			'/run-queue',
			array(
				'methods'             => 'POST',
				'permission_callback' => $admin,
				'callback'            => static function () {
					do_action( 'convocept_send_notifications' );
					return true;
				},
			)
		);
	}
);
