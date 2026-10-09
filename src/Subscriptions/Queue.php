<?php
/**
 * Notification queue.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Subscriptions;

use Convocept\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Sends notification emails in the background, in small batches.
 *
 * Approving a comment only records a job (the comment ID and a cursor), so
 * the request that approves it stays fast no matter how many subscribers
 * there are. A background run then sends up to `batch_size` emails, moves
 * the cursor on, and schedules the next run while work remains.
 *
 * Runs use Action Scheduler when it is installed (for example by
 * WooCommerce) and WP-Cron otherwise.
 *
 * @since 1.0.0
 */
class Queue {

	/**
	 * Hook that processes the queue.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const HOOK = 'convocept_send_notifications';

	/**
	 * Prefix of the options holding pending jobs, one per comment: the
	 * comment ID follows the prefix and the value is the cursor.
	 *
	 * One row per job, not one shared array: adding a job is a single
	 * insert and saving one job's cursor never rewrites another, so a web
	 * request queuing a comment while the sender runs can't lose or repeat
	 * a job.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const JOB_PREFIX = 'convocept_notification_job_';

	/**
	 * Option used as a lock so two runs never send the same batch.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const LOCK_OPTION = 'convocept_notification_lock';

	/**
	 * Longest a run may take before it stops and reschedules, in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const TIME_LIMIT = 20;

	/**
	 * Settings.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Create the queue.
	 *
	 * @since 1.0.0
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Add a job for a newly approved comment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $comment_id Comment ID.
	 * @return void
	 */
	public function add( int $comment_id ): void {
		// add_option() refuses an existing row, so a queued job keeps its cursor.
		add_option( self::JOB_PREFIX . $comment_id, 0, '', false );

		$this->schedule();
	}

	/**
	 * Get pending jobs.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, int> Comment ID => cursor.
	 */
	public function jobs(): array {
		global $wpdb;

		// Straight from the table: the options cache of a long-running sender
		// would not see jobs queued by other requests.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Must bypass the options cache; there is no API to list options by prefix.
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT option_name, option_value FROM %i WHERE option_name LIKE %s ORDER BY option_id',
				$wpdb->options,
				$wpdb->esc_like( self::JOB_PREFIX ) . '%'
			)
		);
		$jobs = array();

		foreach ( $rows as $row ) {
			$jobs[ (int) substr( (string) $row->option_name, strlen( self::JOB_PREFIX ) ) ] = (int) $row->option_value;
		}

		return $jobs;
	}

	/**
	 * Process a batch of jobs.
	 *
	 * @since 1.0.0
	 *
	 * @param callable $deliver Sends up to $limit emails for a job and returns the new
	 *                          cursor and how many it sent: fn( int $comment_id, int $cursor,
	 *                          int $limit ): array{cursor: int, sent: int, done: bool}.
	 * @return int Emails sent in this run.
	 */
	public function run( callable $deliver ): int {
		if ( ! $this->lock() ) {
			return 0;
		}

		$budget  = (int) $this->settings->get( 'batch_size' );
		$started = time();
		$sent    = 0;

		try {
			foreach ( $this->jobs() as $comment_id => $cursor ) {
				while ( $sent < $budget && ( time() - $started ) < self::TIME_LIMIT ) {
					$result = $deliver( (int) $comment_id, (int) $cursor, $budget - $sent );
					$sent  += (int) $result['sent'];
					$cursor = (int) $result['cursor'];

					$this->save( (int) $comment_id, $result['done'] ? null : $cursor );

					if ( $result['done'] ) {
						break;
					}
				}

				if ( $sent >= $budget || ( time() - $started ) >= self::TIME_LIMIT ) {
					break;
				}
			}
		} finally {
			delete_option( self::LOCK_OPTION );
		}//end try

		if ( $this->jobs() ) {
			$this->schedule();
		}

		return $sent;
	}

	/**
	 * Schedule a background run if none is pending.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function schedule(): void {
		if ( function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! as_has_scheduled_action( self::HOOK, array(), 'convocept' ) ) {
				as_enqueue_async_action( self::HOOK, array(), 'convocept' );
			}

			return;
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time(), self::HOOK );
		}
	}

	/**
	 * Store a job's cursor, or remove the job when it is finished.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $comment_id Comment ID.
	 * @param int|null $cursor     New cursor, or null when done.
	 * @return void
	 */
	private function save( int $comment_id, ?int $cursor ): void {
		if ( null === $cursor ) {
			delete_option( self::JOB_PREFIX . $comment_id );
		} else {
			update_option( self::JOB_PREFIX . $comment_id, $cursor, false );
		}
	}

	/**
	 * Take the run lock; a lock older than 10 minutes is taken over.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function lock(): bool {
		if ( add_option( self::LOCK_OPTION, time(), '', false ) ) {
			return true;
		}

		if ( time() - (int) get_option( self::LOCK_OPTION, 0 ) > 10 * MINUTE_IN_SECONDS ) {
			update_option( self::LOCK_OPTION, time(), false );

			return true;
		}

		return false;
	}
}
