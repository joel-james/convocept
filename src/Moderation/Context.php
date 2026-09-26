<?php
/**
 * Submission context.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Moderation;

defined( 'ABSPATH' ) || exit;

/**
 * What is known about a comment submission beyond the comment itself.
 *
 * Built for both submission paths: the REST composer and the plain form
 * posting to wp-comments-post.php.
 *
 * @since 1.0.0
 */
final class Context {

	/**
	 * Submitted through the REST API by the JavaScript composer.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SOURCE_REST = 'rest';

	/**
	 * Submitted through the plain form to wp-comments-post.php.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SOURCE_FORM = 'form';

	/**
	 * Submission path, one of the SOURCE_* constants.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $source;

	/**
	 * Post being commented on.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $post_id;

	/**
	 * Value of the honeypot field (humans leave it empty).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $honeypot;

	/**
	 * Seconds between the form appearing and the submission, or null if unknown.
	 *
	 * @since 1.0.0
	 * @var float|null
	 */
	public $elapsed;

	/**
	 * Salted hash of the submitter's IP.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $ip_hash;

	/**
	 * Content format: 'markdown' for the JavaScript composer, '' for core.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $format;

	/**
	 * Subscriptions the commenter asked for: 'replies' and/or 'post' => true.
	 *
	 * @since 1.0.0
	 * @var array<string, bool>
	 */
	public $subscribe = array();

	/**
	 * Create a context.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $source   Submission path.
	 * @param int        $post_id  Post ID.
	 * @param string     $honeypot Honeypot value.
	 * @param float|null $elapsed  Seconds taken to submit, or null if unknown.
	 * @param string     $ip_hash  Hashed IP.
	 * @param string     $format   Content format.
	 */
	public function __construct( string $source, int $post_id, string $honeypot, ?float $elapsed, string $ip_hash, string $format = '' ) {
		$this->source   = $source;
		$this->post_id  = $post_id;
		$this->honeypot = $honeypot;
		$this->elapsed  = $elapsed;
		$this->ip_hash  = $ip_hash;
		$this->format   = $format;
	}
}
