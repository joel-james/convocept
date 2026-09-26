<?php
/**
 * Importer interface.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Import;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A source Convocept can import comments or subscriptions from.
 *
 * Every importer follows the same steps, driven by Runner from wp-admin
 * (Tools → Import) or WP-CLI:
 *
 * 1. detect()   — is there anything to import from this source?
 * 2. prepare()  — dry run: read the source, fill in counts and a sample,
 *                 and set up whatever the batches need. Nothing is written
 *                 to comments yet.
 * 3. run_batch() — import the next batch and move the job's cursor, until
 *                 the job is marked done. Must be safe to call again after
 *                 a timeout (resume) and to re-run on the same source
 *                 (idempotent: source IDs are stored in meta).
 *
 * Importer classes are only loaded in wp-admin and WP-CLI, never on
 * front-end requests.
 *
 * @since 1.0.0
 */
interface Importer {

	/**
	 * Get the importer's ID (lowercase letters, digits and dashes).
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Get the name shown in Tools → Import.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Get the one-line description shown in Tools → Import.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function description(): string;

	/**
	 * Whether the importer reads an uploaded or given file.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function needs_file(): bool;

	/**
	 * Whether this source has anything to import.
	 *
	 * File importers can't know before a file is given and return true;
	 * database importers (another plugin's tables) check for its data.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function detect(): bool;

	/**
	 * Analyse the source (dry run) and set the job up for batches.
	 *
	 * @since 1.0.0
	 *
	 * @param Job $job New job with `file` and `workspace` set.
	 * @return Job|WP_Error The prepared job, or an error explaining why the source can't be imported.
	 */
	public function prepare( Job $job );

	/**
	 * Import the next batch.
	 *
	 * @since 1.0.0
	 *
	 * @param Job   $job      Running job.
	 * @param int   $limit    Maximum items to process.
	 * @param float $deadline Unix time (microtime) to stop by, even with items left.
	 * @return Job|WP_Error The updated job (status DONE when finished), or an error.
	 */
	public function run_batch( Job $job, int $limit, float $deadline );
}
