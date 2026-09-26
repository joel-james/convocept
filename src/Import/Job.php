<?php
/**
 * Import job state.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Import;

defined( 'ABSPATH' ) || exit;

/**
 * The state of the one import that can run at a time.
 *
 * Stored in a non-autoloaded option so an import survives page loads,
 * timeouts and a WP-CLI run being interrupted, and resumes from its
 * cursor. Only one job exists at a time; starting another requires
 * cancelling or finishing the current one.
 *
 * @since 1.0.0
 */
final class Job {

	/**
	 * Option holding the job.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION = 'convocept_import_job';

	/**
	 * Status: analysed (dry run done), nothing imported yet.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PREPARED = 'prepared';

	/**
	 * Status: batches are running.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const RUNNING = 'running';

	/**
	 * Status: finished.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DONE = 'done';

	/**
	 * Importer ID, for example 'disqus'.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $importer = '';

	/**
	 * One of the status constants.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $status = self::PREPARED;

	/**
	 * Source file, when the importer reads one.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $file = '';

	/**
	 * Private working directory for this job.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $workspace = '';

	/**
	 * Whether the source file lives in the workspace (uploaded) and is deleted with it.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	public $owns_file = false;

	/**
	 * Importer-defined position to resume from (for Disqus, a byte offset).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $cursor = 0;

	/**
	 * Items to process in total.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $total = 0;

	/**
	 * Items processed so far (imported or skipped).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $processed = 0;

	/**
	 * Counters, for example 'imported', 'existing', 'spam'.
	 *
	 * @since 1.0.0
	 * @var array<string, int>
	 */
	public $counts = array();

	/**
	 * A few items previewed by the dry run, each with 'author', 'date', 'excerpt' and 'post'.
	 *
	 * @since 1.0.0
	 * @var array<int, array<string, string>>
	 */
	public $sample = array();

	/**
	 * Unix time the job was created.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $created = 0;

	/**
	 * Unix time the job finished, or 0.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $finished = 0;

	/**
	 * Add to a counter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name  Counter name.
	 * @param int    $value Amount to add.
	 * @return void
	 */
	public function add( string $name, int $value = 1 ): void {
		$this->counts[ $name ] = ( $this->counts[ $name ] ?? 0 ) + $value;
	}

	/**
	 * Get a counter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Counter name.
	 * @return int
	 */
	public function count( string $name ): int {
		return (int) ( $this->counts[ $name ] ?? 0 );
	}

	/**
	 * Get the progress as a whole percentage.
	 *
	 * @since 1.0.0
	 *
	 * @return int 0–100.
	 */
	public function percent(): int {
		if ( self::DONE === $this->status ) {
			return 100;
		}

		return $this->total > 0 ? (int) min( 99, floor( $this->processed * 100 / $this->total ) ) : 0;
	}

	/**
	 * Get the job as a plain array, for storage and JSON responses.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return get_object_vars( $this );
	}

	/**
	 * Build a job from stored data.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data Stored data.
	 * @return Job
	 */
	public static function from_array( array $data ): Job {
		$job = new self();

		foreach ( get_object_vars( $job ) as $key => $default ) {
			if ( array_key_exists( $key, $data ) ) {
				$value = $data[ $key ];
				settype( $value, gettype( $default ) );
				$job->$key = $value;
			}
		}

		return $job;
	}

	/**
	 * Load the current job.
	 *
	 * @since 1.0.0
	 *
	 * @return Job|null Null when no import exists.
	 */
	public static function current(): ?Job {
		$data = get_option( self::OPTION );

		return is_array( $data ) && ! empty( $data['importer'] ) ? self::from_array( $data ) : null;
	}

	/**
	 * Save the job.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function save(): void {
		update_option( self::OPTION, $this->to_array(), false );
	}

	/**
	 * Forget the current job.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function clear(): void {
		delete_option( self::OPTION );
	}
}
