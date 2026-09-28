<?php
/**
 * Import REST endpoint.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Rest;

use Convocept\Import\Job;
use Convocept\Import\Workspace;
use Convocept\Plugin;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Runs imports for the settings screen's Import page.
 *
 * - `GET    /import`        the current job (or null) and the importers;
 * - `POST   /import`        upload an export (multipart `file`, `importer`)
 *                           and run the dry run;
 * - `POST   /import/batch`  import the next batch;
 * - `DELETE /import`        cancel, or clear a finished job.
 *
 * Importer classes are only touched inside the callbacks, so registering
 * these routes on other REST requests loads none of them.
 *
 * @since 1.0.0
 */
class ImportEndpoint {

	/**
	 * Plugin instance, for the import runner.
	 *
	 * @since 1.0.0
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Create the endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Whether the current user may run imports.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function can_import(): bool {
		return current_user_can( 'import' ) && current_user_can( 'moderate_comments' );
	}

	/**
	 * Return the current job and the available importers.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response
	 */
	public function status(): WP_REST_Response {
		$importers = array();

		foreach ( $this->plugin->import_runner()->importers() as $importer ) {
			$importers[] = array(
				'id'          => $importer->id(),
				'label'       => $importer->label(),
				'description' => $importer->description(),
				'needs_file'  => $importer->needs_file(),
			);
		}

		return Controller::response(
			array(
				'job'        => $this->job_data( Job::current() ),
				'importers'  => $importers,
				'max_upload' => (int) wp_max_upload_size(),
			)
		);
	}

	/**
	 * Store an uploaded export privately and run the dry run.
	 *
	 * The file keeps a fixed name (`export.xml` / `export.xml.gz`), never the
	 * visitor's, and only .xml and .gz names are accepted; the importer then
	 * checks the content itself.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function start( WP_REST_Request $request ) {
		$runner   = $this->plugin->import_runner();
		$id       = sanitize_key( (string) $request->get_param( 'importer' ) );
		$importer = $runner->importer( $id );

		if ( ! $importer ) {
			return new WP_Error( 'convocept_import_unknown', __( 'Unknown import source.', 'convocept' ), array( 'status' => 400 ) );
		}

		if ( Job::current() ) {
			return new WP_Error( 'convocept_import_busy', __( 'Another import is in progress. Finish or cancel it first.', 'convocept' ), array( 'status' => 409 ) );
		}

		$file      = '';
		$workspace = '';

		if ( $importer->needs_file() ) {
			$stored = $this->store_upload( $request );

			if ( is_wp_error( $stored ) ) {
				return $stored;
			}

			$file      = $stored['file'];
			$workspace = $stored['workspace'];
		}

		$job = $runner->start( $id, $file, '' !== $file, $workspace );

		if ( is_wp_error( $job ) ) {
			$job->add_data( array( 'status' => 400 ) );

			return $job;
		}

		return Controller::response( array( 'job' => $this->job_data( $job ) ) );
	}

	/**
	 * Move the uploaded file into a new private workspace.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{file: string, workspace: string}|WP_Error
	 */
	private function store_upload( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		$file  = isset( $files['file'] ) && is_array( $files['file'] ) ? $files['file'] : null;

		if ( ! $file || empty( $file['name'] ) ) {
			return new WP_Error( 'convocept_import_file', __( 'Choose an export file to upload.', 'convocept' ), array( 'status' => 400 ) );
		}

		$name = strtolower( (string) $file['name'] );
		$gzip = '.gz' === substr( $name, -3 );

		if ( ! $gzip && '.xml' !== substr( $name, -4 ) ) {
			return new WP_Error( 'convocept_import_file', __( 'Upload the .xml or .xml.gz file Disqus sent you.', 'convocept' ), array( 'status' => 400 ) );
		}

		$workspaces = new Workspace();
		$workspace  = $workspaces->create();

		if ( is_wp_error( $workspace ) ) {
			$workspace->add_data( array( 'status' => 500 ) );

			return $workspace;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$target = $gzip ? 'export.xml.gz' : 'export.xml';
		$dir    = static function ( $uploads ) use ( $workspace ) {
			$uploads['path']   = $workspace;
			$uploads['subdir'] = '';

			return $uploads;
		};

		add_filter( 'upload_dir', $dir );

		$moved = wp_handle_upload(
			$file,
			array(
				'test_form'                => false,
				// The importer validates the content; XML and gzip aren't in core's upload MIME list.
				'test_type'                => false,
				'unique_filename_callback' => static function () use ( $target ) {
					return $target;
				},
			)
		);

		remove_filter( 'upload_dir', $dir );

		if ( isset( $moved['error'] ) || empty( $moved['file'] ) ) {
			$workspaces->delete( $workspace );

			return new WP_Error( 'convocept_import_upload', isset( $moved['error'] ) ? (string) $moved['error'] : __( 'The upload failed.', 'convocept' ), array( 'status' => 400 ) );
		}

		return array(
			'file'      => (string) $moved['file'],
			'workspace' => $workspace,
		);
	}

	/**
	 * Import the next batch.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function batch() {
		$job = $this->plugin->import_runner()->next_batch();

		if ( is_wp_error( $job ) ) {
			$job->add_data( array( 'status' => 'convocept_import_locked' === $job->get_error_code() ? 409 : 400 ) );

			return $job;
		}

		return Controller::response( array( 'job' => $this->job_data( $job ) ) );
	}

	/**
	 * Cancel the current import, or clear a finished one.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response
	 */
	public function cancel(): WP_REST_Response {
		$this->plugin->import_runner()->cancel();

		return Controller::response( array( 'job' => null ) );
	}

	/**
	 * Describe a job for the screen. Never includes file paths.
	 *
	 * @since 1.0.0
	 *
	 * @param Job|null $job Job.
	 * @return array<string, mixed>|null
	 */
	private function job_data( ?Job $job ): ?array {
		if ( ! $job ) {
			return null;
		}

		return array(
			'importer'  => $job->importer,
			'status'    => $job->status,
			'total'     => $job->total,
			'processed' => $job->processed,
			'percent'   => $job->percent(),
			'counts'    => (object) $job->counts,
			'sample'    => $job->sample,
		);
	}
}
