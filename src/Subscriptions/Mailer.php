<?php
/**
 * Mailer.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Subscriptions;

defined( 'ABSPATH' ) || exit;

/**
 * Sends Convocept's emails as HTML with a plain-text alternative.
 *
 * Uses wp_mail(), so SMTP and transactional-email plugins keep working.
 * Add-ons can take over delivery entirely with the `convocept_mailer`
 * filter (for example to send through an email service's API in bulk).
 *
 * @since 1.0.0
 */
class Mailer {

	/**
	 * Send one email.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $to      Recipient address.
	 * @param string   $subject Subject (plain text).
	 * @param string   $html    HTML body.
	 * @param string   $text    Plain-text body.
	 * @param string[] $headers Extra headers, e.g. List-Unsubscribe.
	 * @return bool Whether the email was accepted for delivery.
	 */
	public function send( string $to, string $subject, string $html, string $text, array $headers = array() ): bool {
		$message = array(
			'to'      => $to,
			'subject' => $subject,
			'html'    => $html,
			'text'    => $text,
			'headers' => $headers,
		);

		/**
		 * Filters email delivery, letting an add-on send the email itself.
		 *
		 * Return true or false (sent or failed) to skip wp_mail(); return
		 * null to let Convocept send it.
		 *
		 * @since 1.0.0
		 *
		 * @param bool|null            $sent    Null to use wp_mail().
		 * @param array<string, mixed> $message 'to', 'subject', 'html', 'text' and 'headers'.
		 */
		$sent = apply_filters( 'convocept_mailer', null, $message );

		if ( null !== $sent ) {
			return (bool) $sent;
		}

		// PHPMailer builds multipart/alternative when an AltBody is set.
		$alternative = static function ( $phpmailer ) use ( $text ) {
			$phpmailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer API.
		};

		add_action( 'phpmailer_init', $alternative );
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		$result    = wp_mail( $to, $subject, $html, $headers );
		remove_action( 'phpmailer_init', $alternative );

		return (bool) $result;
	}
}
