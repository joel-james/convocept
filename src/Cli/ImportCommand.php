<?php
/**
 * WP-CLI import command.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Cli;

use Convocept\Import\Job;
use Convocept\Import\Runner;
use WP_CLI;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Imports comments into Convocept from other comment systems.
 *
 * ## EXAMPLES
 *
 *     # Review what a Disqus export contains, without importing anything.
 *     $ wp convocept import disqus export.xml --dry-run
 *
 *     # Import it.
 *     $ wp convocept import disqus export.xml --yes
 *
 *     # Resume an import that was interrupted.
 *     $ wp convocept import resume
 *
 * @since 1.0.0
 */
class ImportCommand {

	/**
	 * Import runner.
	 *
	 * @since 1.0.0
	 * @var Runner
	 */
	private $runner;

	/**
	 * Create the command.
	 *
	 * @since 1.0.0
	 *
	 * @param Runner $runner Import runner.
	 */
	public function __construct( Runner $runner ) {
		$this->runner = $runner;
	}

	/**
	 * Import comments from a source.
	 *
	 * Runs a dry run first and shows what will be imported. Spam and deleted
	 * comments are skipped, imported comments don't notify subscribers, and
	 * running the same export again never creates duplicates.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : Importer ID, e.g. `disqus`. `resume` continues the current import;
	 * `status` shows it; `cancel` forgets it.
	 *
	 * [<file>]
	 * : Export file (.xml or .xml.gz) for file-based sources.
	 *
	 * [--dry-run]
	 * : Only analyse the source; import nothing.
	 *
	 * [--batch-size=<number>]
	 * : Comments per batch.
	 * ---
	 * default: 1000
	 * ---
	 *
	 * [--yes]
	 * : Start without asking for confirmation after the dry run.
	 *
	 * @since 1.0.0
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 * @return void
	 */
	public function __invoke( $args, $assoc_args ): void {
		$source = (string) ( $args[0] ?? '' );
		$batch  = max( 1, (int) ( $assoc_args['batch-size'] ?? 1000 ) );

		switch ( $source ) {
			case 'status':
				$job = Job::current();
				$job ? $this->report( $job ) : WP_CLI::log( 'No import in progress.' );
				return;

			case 'cancel':
				$this->runner->cancel();
				WP_CLI::success( 'Import cancelled. Comments already imported are kept.' );
				return;

			case 'resume':
				$job = Job::current();

				if ( ! $job ) {
					WP_CLI::error( 'No import in progress.' );
				}

				$this->run( $job, $batch );
				return;
		}//end switch

		if ( ! $this->runner->importer( $source ) ) {
			WP_CLI::error( sprintf( 'Unknown source "%s". Available: %s.', $source, implode( ', ', array_keys( $this->runner->importers() ) ) ) );
		}

		if ( Job::current() ) {
			WP_CLI::error( 'Another import is in progress. Run `wp convocept import resume` or `wp convocept import cancel`.' );
		}

		$file = isset( $args[1] ) ? (string) realpath( $args[1] ) : '';

		WP_CLI::log( 'Analysing…' );

		$job = $this->runner->start( $source, $file );
		$this->check( $job );

		$this->report( $job );

		if ( isset( $assoc_args['dry-run'] ) ) {
			$this->runner->cancel();
			WP_CLI::success( 'Dry run finished. Nothing was imported.' );
			return;
		}

		WP_CLI::confirm( 'Start the import?', $assoc_args );

		$this->run( $job, $batch );
	}

	/**
	 * Run batches until the job is done, with a progress bar.
	 *
	 * @since 1.0.0
	 *
	 * @param Job $job   Current job.
	 * @param int $batch Items per batch.
	 * @return void
	 */
	private function run( Job $job, int $batch ): void {
		$progress = \WP_CLI\Utils\make_progress_bar( 'Importing', max( 1, $job->total ) );
		$progress->tick( $job->processed );

		while ( Job::DONE !== $job->status ) {
			$before = $job->processed;
			$result = $this->runner->next_batch( $batch, 60 );
			$this->check( $result );
			$job = $result;

			$progress->tick( max( 0, $job->processed - $before ) );

			// Imports hold every inserted comment in the runtime cache; free it between batches.
			if ( function_exists( 'wp_cache_flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}
		}

		$progress->finish();
		$this->report( $job );
		WP_CLI::success( 'Import complete.' );
	}

	/**
	 * Print a job's counters.
	 *
	 * @since 1.0.0
	 *
	 * @param Job $job Job.
	 * @return void
	 */
	private function report( Job $job ): void {
		$rows = array(
			array(
				'item'  => 'status',
				'count' => $job->status,
			),
			array(
				'item'  => 'to_import',
				'count' => $job->total,
			),
			array(
				'item'  => 'processed',
				'count' => $job->processed,
			),
		);

		foreach ( $job->counts as $name => $count ) {
			$rows[] = array(
				'item'  => $name,
				'count' => $count,
			);
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'item', 'count' ) );
	}

	/**
	 * Stop with the error message when a step failed.
	 *
	 * @since 1.0.0
	 *
	 * @param Job|WP_Error $result Step result.
	 * @return void
	 *
	 * @phpstan-assert Job $result
	 */
	private function check( $result ): void {
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
	}
}
