<?php
/**
 * Import runner.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Import;

use Convocept\Import\Disqus\DisqusImporter;
use Convocept\Import\Disqus\Parser;
use Convocept\Privacy\Privacy;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Drives importers through dry run, batches and clean-up.
 *
 * Shared by the wp-admin screen and the WP-CLI command, so both behave
 * the same: one job at a time, resumable after a timeout, no subscriber
 * notifications for imported comments, and comment counts updated once
 * per batch instead of once per comment.
 *
 * @since 1.0.0
 */
class Runner {

	/**
	 * Transient that stops two batches running at once.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const LOCK = 'convocept_import_lock';

	/**
	 * Default items per batch.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const BATCH_SIZE = 500;

	/**
	 * Default seconds a batch may run, well under common 30-second limits.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const BATCH_SECONDS = 15;

	/**
	 * Workspace service.
	 *
	 * @since 1.0.0
	 * @var Workspace
	 */
	private $workspace;

	/**
	 * Importers created so far, keyed by ID.
	 *
	 * @since 1.0.0
	 * @var array<string, Importer>|null
	 */
	private $importers = null;

	/**
	 * Privacy service, applying the IP setting to imported comments.
	 *
	 * @since 1.0.0
	 * @var Privacy|null
	 */
	private $privacy;

	/**
	 * Create the runner.
	 *
	 * @since 1.0.0
	 *
	 * @param Workspace    $workspace Workspace service.
	 * @param Privacy|null $privacy   Privacy service; null stores IPs as found in the export.
	 */
	public function __construct( Workspace $workspace, ?Privacy $privacy = null ) {
		$this->workspace = $workspace;
		$this->privacy   = $privacy;
	}

	/**
	 * Get the available importers.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, Importer> Keyed by ID.
	 */
	public function importers(): array {
		if ( null === $this->importers ) {
			$importers = array(
				'disqus' => new DisqusImporter( new Parser(), $this->privacy ),
			);

			/**
			 * Filters the available importers.
			 *
			 * Add-ons add sources by adding an Importer instance under its ID.
			 * Each appears in Tools → Import and in `wp convocept import`.
			 *
			 * @since 1.0.0
			 *
			 * @param array<string, Importer> $importers Importers keyed by ID.
			 */
			$importers = (array) apply_filters( 'convocept_importers', $importers );

			$this->importers = array();

			foreach ( $importers as $importer ) {
				if ( $importer instanceof Importer ) {
					$this->importers[ $importer->id() ] = $importer;
				}
			}
		}//end if

		return $this->importers;
	}

	/**
	 * Get one importer.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Importer ID.
	 * @return Importer|null
	 */
	public function importer( string $id ): ?Importer {
		return $this->importers()[ $id ] ?? null;
	}

	/**
	 * Start an import: create its workspace and run the dry run.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id        Importer ID.
	 * @param string $file      Source file, or '' for importers that read the database.
	 * @param bool   $owns_file Whether the file is a copy in the workspace, deleted with it.
	 * @param string $workspace Existing workspace to use (the upload landed there), or '' to create one.
	 * @return Job|WP_Error
	 */
	public function start( string $id, string $file = '', bool $owns_file = false, string $workspace = '' ) {
		$importer = $this->importer( $id );

		if ( ! $importer ) {
			return new WP_Error( 'convocept_import_unknown', __( 'Unknown import source.', 'convocept' ) );
		}

		if ( Job::current() ) {
			return new WP_Error( 'convocept_import_busy', __( 'Another import is in progress. Finish or cancel it first.', 'convocept' ) );
		}

		if ( $importer->needs_file() && ( '' === $file || ! is_readable( $file ) ) ) {
			return new WP_Error( 'convocept_import_file', __( 'The export file could not be read.', 'convocept' ) );
		}

		if ( '' === $workspace ) {
			$workspace = $this->workspace->create();

			if ( is_wp_error( $workspace ) ) {
				return $workspace;
			}
		}

		$job            = new Job();
		$job->importer  = $id;
		$job->file      = $file;
		$job->owns_file = $owns_file;
		$job->workspace = $workspace;
		$job->created   = time();

		$this->extend_time_limit();

		$prepared = $importer->prepare( $job );

		if ( is_wp_error( $prepared ) ) {
			$this->workspace->delete( $workspace );

			return $prepared;
		}

		$prepared->status = Job::PREPARED;
		$prepared->save();

		return $prepared;
	}

	/**
	 * Import the next batch of the current job.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit   Maximum items (0 for the default).
	 * @param int $seconds Time budget in seconds (0 for the default).
	 * @return Job|WP_Error
	 */
	public function next_batch( int $limit = 0, int $seconds = 0 ) {
		$job = Job::current();

		if ( ! $job ) {
			return new WP_Error( 'convocept_import_none', __( 'There is no import in progress.', 'convocept' ) );
		}

		if ( Job::DONE === $job->status ) {
			return $job;
		}

		$importer = $this->importer( $job->importer );

		if ( ! $importer ) {
			return new WP_Error( 'convocept_import_unknown', __( 'Unknown import source.', 'convocept' ) );
		}

		// add_option() is atomic: only one request gets the lock.
		if ( ! add_option( self::LOCK, time(), '', false ) ) {
			$since = (int) get_option( self::LOCK );

			if ( $since > time() - 5 * MINUTE_IN_SECONDS ) {
				return new WP_Error( 'convocept_import_locked', __( 'A batch is already running. Try again in a moment.', 'convocept' ) );
			}

			// A stale lock from a request that died mid-batch.
			update_option( self::LOCK, time(), false );
		}

		$limit   = $limit > 0 ? $limit : self::BATCH_SIZE;
		$seconds = $seconds > 0 ? $seconds : self::BATCH_SECONDS;

		/**
		 * Filters how many items one import batch processes.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $limit    Items per batch.
		 * @param string $importer Importer ID.
		 */
		$limit = max( 1, (int) apply_filters( 'convocept_import_batch_size', $limit, $job->importer ) );

		$this->extend_time_limit();

		// Imported comments are old news: never email subscribers about them.
		add_filter( 'convocept_should_notify', '__return_false', 99 );
		// One count update per post per batch instead of one per comment.
		wp_defer_comment_counting( true );

		$job->status = Job::RUNNING;

		try {
			$result = $importer->run_batch( $job, $limit, microtime( true ) + $seconds );
		} finally {
			wp_defer_comment_counting( false );
			remove_filter( 'convocept_should_notify', '__return_false', 99 );
			delete_option( self::LOCK );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( Job::DONE === $result->status ) {
			$result->finished = time();
			$this->cleanup( $result );

			/**
			 * Fires when an import finishes.
			 *
			 * @since 1.0.0
			 *
			 * @param Job $job The finished job (counts in `$job->counts`).
			 */
			do_action( 'convocept_import_completed', $result );
		}

		$result->save();

		return $result;
	}

	/**
	 * Cancel and forget the current job, deleting its files.
	 *
	 * Comments already imported stay; running the same import again skips
	 * them.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function cancel(): void {
		$job = Job::current();

		if ( $job ) {
			$this->cleanup( $job );
		}

		Job::clear();
		delete_option( self::LOCK );
	}

	/**
	 * Delete a job's working files.
	 *
	 * @since 1.0.0
	 *
	 * @param Job $job Job.
	 * @return void
	 */
	private function cleanup( Job $job ): void {
		if ( '' !== $job->workspace ) {
			$this->workspace->delete( $job->workspace );
			$job->workspace = '';
		}

		if ( $job->owns_file ) {
			$job->file = '';
		}
	}

	/**
	 * Ask PHP for more time, where the host allows it.
	 *
	 * Batches are sized to finish well within 30 seconds anyway; this only
	 * helps the one-pass dry run of very large exports.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function extend_time_limit(): void {
		if ( function_exists( 'set_time_limit' ) && false === strpos( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Long-running admin/CLI import.
		}
	}
}
