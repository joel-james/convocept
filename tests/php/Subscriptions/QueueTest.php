<?php
/**
 * Tests for the notification queue.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Tests\Subscriptions;

use Convocept\Settings\Settings;
use Convocept\Subscriptions\Queue;
use Convocept\Tests\TestCase;

/**
 * Notification queue tests.
 */
class QueueTest extends TestCase {

	/**
	 * Queue under test.
	 *
	 * @var Queue
	 */
	private $queue;

	/**
	 * Start every test with an empty queue.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->queue = new Queue( new Settings() );

		foreach ( array_keys( $this->queue->jobs() ) as $comment_id ) {
			delete_option( Queue::JOB_PREFIX . $comment_id );
		}

		delete_option( Queue::LOCK_OPTION );
	}

	public function test_adding_a_queued_comment_keeps_its_cursor(): void {
		$this->queue->add( 11 );
		$this->queue->run(
			static function () {
				return array(
					'sent'   => 1,
					'cursor' => 40,
					'done'   => false,
				);
			}
		);

		$this->queue->add( 11 );

		$this->assertSame( array( 11 => 40 ), $this->queue->jobs() );
	}

	public function test_a_job_queued_while_the_sender_runs_is_kept(): void {
		$this->queue->add( 21 );
		$queue = $this->queue;

		$queue->run(
			static function () use ( $queue ) {
				// Another request approves a comment mid-run.
				$queue->add( 22 );

				return array(
					'sent'   => 1,
					'cursor' => 0,
					'done'   => true,
				);
			}
		);

		$this->assertSame( array( 22 => 0 ), $this->queue->jobs() );
	}
}
