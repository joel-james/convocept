<?php
/**
 * Import screen.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Admin;

use Convocept\Import\Importer;
use Convocept\Import\Job;
use Convocept\Import\Runner;
use Convocept\Import\Workspace;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Convocept's importers to Tools → Import and runs them.
 *
 * Each importer gets a core importer page. The steps are plain forms
 * (upload → dry-run report → start → progress → report), so the screen
 * works without JavaScript: every "Continue" click runs one batch. With
 * JavaScript, a small script runs the batches one after another through
 * admin-ajax and shows the progress.
 *
 * @since 1.0.0
 */
class ImportScreen {

	/**
	 * Nonce action for every import request.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NONCE = 'convocept_import';

	/**
	 * Import runner.
	 *
	 * @since 1.0.0
	 * @var Runner
	 */
	private $runner;

	/**
	 * Workspace service, for uploads.
	 *
	 * @since 1.0.0
	 * @var Workspace
	 */
	private $workspace;

	/**
	 * Create the screen.
	 *
	 * @since 1.0.0
	 *
	 * @param Runner    $runner    Import runner.
	 * @param Workspace $workspace Workspace service.
	 */
	public function __construct( Runner $runner, Workspace $workspace ) {
		$this->runner    = $runner;
		$this->workspace = $workspace;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_importers' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_convocept_import_upload', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_convocept_import_batch', array( $this, 'handle_batch' ) );
		add_action( 'admin_post_convocept_import_cancel', array( $this, 'handle_cancel' ) );
		add_action( 'wp_ajax_convocept_import_batch', array( $this, 'ajax_batch' ) );
	}

	/**
	 * Add each importer to Tools → Import.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_importers(): void {
		// Core defines WP_LOAD_IMPORTERS on the Import screens only.
		if ( ! defined( 'WP_LOAD_IMPORTERS' ) || ! function_exists( 'register_importer' ) ) {
			return;
		}

		foreach ( $this->runner->importers() as $importer ) {
			register_importer(
				$this->slug( $importer ),
				$importer->label(),
				$importer->description(),
				function () use ( $importer ) {
					$this->render( $importer );
				}
			);
		}
	}

	/**
	 * Get an importer's page slug.
	 *
	 * @since 1.0.0
	 *
	 * @param Importer $importer Importer.
	 * @return string
	 */
	private function slug( Importer $importer ): string {
		return 'convocept-' . $importer->id();
	}

	/**
	 * Get an importer page URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Importer ID.
	 * @return string
	 */
	private function page_url( string $id ): string {
		return admin_url( 'admin.php?import=convocept-' . $id );
	}

	/**
	 * Whether the current user may run imports.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function can_import(): bool {
		return current_user_can( 'import' ) && current_user_can( 'moderate_comments' );
	}

	/**
	 * Load the progress script on Convocept importer pages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Admin page hook.
	 * @return void
	 */
	public function enqueue( $hook_suffix ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only chooses whether to load a script.
		$page = isset( $_GET['import'] ) ? sanitize_key( wp_unslash( $_GET['import'] ) ) : '';

		if ( 'admin.php' !== $hook_suffix || 0 !== strpos( $page, 'convocept-' ) ) {
			return;
		}

		wp_enqueue_script( 'convocept-import', CONVOCEPT_URL . 'assets/dist/admin-import.js', array(), CONVOCEPT_VERSION, true );
	}

	/**
	 * Render an importer page.
	 *
	 * @since 1.0.0
	 *
	 * @param Importer $importer Importer.
	 * @return void
	 */
	public function render( Importer $importer ): void {
		echo '<div class="wrap convocept-import">';
		echo '<h1>' . esc_html( $importer->label() ) . '</h1>';

		if ( ! $this->can_import() ) {
			echo '<p>' . esc_html__( 'You are not allowed to import comments.', 'convocept' ) . '</p></div>';
			return;
		}

		$this->render_notice();

		$job = Job::current();

		if ( $job && $job->importer !== $importer->id() ) {
			$other = $this->runner->importer( $job->importer );

			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div></div>',
				esc_html__( 'Another import is in progress. Finish or cancel it first.', 'convocept' ),
				esc_url( $this->page_url( $job->importer ) ),
				esc_html( $other ? $other->label() : $job->importer )
			);
			return;
		}

		if ( ! $job ) {
			$this->render_start( $importer );
		} elseif ( Job::PREPARED === $job->status ) {
			$this->render_dry_run( $job );
		} elseif ( Job::RUNNING === $job->status ) {
			$this->render_progress( $job );
		} else {
			$this->render_report( $job );
		}

		echo '</div>';
	}

	/**
	 * Show and forget the message left by the last form action.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_notice(): void {
		$key     = 'convocept_import_notice_' . get_current_user_id();
		$message = get_transient( $key );

		if ( is_string( $message ) && '' !== $message ) {
			delete_transient( $key );
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		}
	}

	/**
	 * Render the first step: upload a file or analyse the source.
	 *
	 * @since 1.0.0
	 *
	 * @param Importer $importer Importer.
	 * @return void
	 */
	private function render_start( Importer $importer ): void {
		echo '<p>' . esc_html( $importer->description() ) . '</p>';

		if ( ! $importer->needs_file() ) {
			return;
		}

		echo '<p>' . esc_html__( 'In Disqus, open Admin → Moderation → Export, download the file it emails you, and upload it here (.xml or .xml.gz). Nothing is imported until you have reviewed a dry run. Spam and deleted comments are skipped, and running the same export again never duplicates comments.', 'convocept' ) . '</p>';
		?>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="convocept_import_upload">
			<input type="hidden" name="importer" value="<?php echo esc_attr( $importer->id() ); ?>">
			<?php wp_nonce_field( self::NONCE ); ?>
			<p>
				<label for="convocept-import-file"><?php esc_html_e( 'Export file', 'convocept' ); ?></label><br>
				<input type="file" id="convocept-import-file" name="convocept_import_file" accept=".xml,.gz" required>
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: 1: Maximum upload size, 2: WP-CLI command. */
					esc_html__( 'Maximum upload size: %1$s. For larger exports, use WP-CLI: %2$s', 'convocept' ),
					esc_html( size_format( wp_max_upload_size() ) ),
					'<code>wp convocept import ' . esc_html( $importer->id() ) . ' export.xml</code>'
				);
				?>
			</p>
			<?php submit_button( __( 'Upload and analyse', 'convocept' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render the dry-run report with Start and Cancel.
	 *
	 * @since 1.0.0
	 *
	 * @param Job $job Prepared job.
	 * @return void
	 */
	private function render_dry_run( Job $job ): void {
		echo '<h2>' . esc_html__( 'Dry run', 'convocept' ) . '</h2>';
		echo '<p>' . esc_html__( 'Nothing has been imported yet. Review what the export contains:', 'convocept' ) . '</p>';

		$this->render_counts(
			array(
				'found'     => array( __( 'Comments to import', 'convocept' ), $job->total ),
				'matched'   => array( __( 'On posts found on this site', 'convocept' ), $job->count( 'matched' ) ),
				'unmatched' => array(
					/* translators: %d: Number of Disqus threads. */
					sprintf( _n( 'On %d page not found on this site (skipped)', 'On %d pages not found on this site (skipped)', $job->count( 'threads_unmatched' ), 'convocept' ), $job->count( 'threads_unmatched' ) ),
					$job->count( 'unmatched' ),
				),
				'spam'      => array( __( 'Spam (skipped)', 'convocept' ), $job->count( 'spam' ) ),
				'deleted'   => array( __( 'Deleted (skipped)', 'convocept' ), $job->count( 'deleted' ) ),
			)
		);

		if ( $job->sample ) {
			echo '<h3>' . esc_html__( 'Sample', 'convocept' ) . '</h3>';
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Author', 'convocept' ) . '</th><th>' . esc_html__( 'Date', 'convocept' ) . '</th><th>' . esc_html__( 'Comment', 'convocept' ) . '</th><th>' . esc_html__( 'Post', 'convocept' ) . '</th></tr></thead><tbody>';

			foreach ( $job->sample as $row ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( $row['author'] ?? '' ),
					esc_html( $row['date'] ?? '' ),
					esc_html( $row['excerpt'] ?? '' ),
					esc_html( $row['post'] ?? '' )
				);
			}

			echo '</tbody></table>';
		}

		echo '<p class="description">' . esc_html__( 'Imported comments do not email your subscribers. Comments already on this site (from an earlier import or the Disqus plugin\'s sync) are skipped.', 'convocept' ) . '</p>';

		$this->render_actions( $job, __( 'Start import', 'convocept' ) );
	}

	/**
	 * Render the progress of a running import.
	 *
	 * @since 1.0.0
	 *
	 * @param Job $job Running job.
	 * @return void
	 */
	private function render_progress( Job $job ): void {
		echo '<h2>' . esc_html__( 'Importing…', 'convocept' ) . '</h2>';

		printf(
			'<p><progress max="100" value="%1$d" data-convocept-import-bar>%1$d%%</progress></p>',
			(int) $job->percent()
		);

		echo '<p class="description">' . esc_html__( 'Keep this page open. If it closes, come back and click Continue: the import resumes where it stopped.', 'convocept' ) . '</p>';

		$this->render_actions( $job, __( 'Continue', 'convocept' ), $this->progress_text( $job ) );
	}

	/**
	 * Get the progress sentence.
	 *
	 * @since 1.0.0
	 *
	 * @param Job $job Job.
	 * @return string
	 */
	private function progress_text( Job $job ): string {
		return sprintf(
			/* translators: 1: Comments processed, 2: Total comments, 3: Comments imported. */
			__( 'Processed %1$s of %2$s comments; %3$s imported.', 'convocept' ),
			number_format_i18n( $job->processed ),
			number_format_i18n( $job->total ),
			number_format_i18n( $job->count( 'imported' ) )
		);
	}

	/**
	 * Render the final report.
	 *
	 * @since 1.0.0
	 *
	 * @param Job $job Finished job.
	 * @return void
	 */
	private function render_report( Job $job ): void {
		echo '<h2>' . esc_html__( 'Import complete', 'convocept' ) . '</h2>';

		$this->render_counts(
			array(
				'imported'   => array( __( 'Imported', 'convocept' ), $job->count( 'imported' ) ),
				'existing'   => array( __( 'Already on this site (skipped)', 'convocept' ), $job->count( 'existing' ) ),
				'no_post'    => array( __( 'Post not found (skipped)', 'convocept' ), $job->count( 'no_post' ) ),
				'spam'       => array( __( 'Spam (skipped)', 'convocept' ), $job->count( 'spam' ) ),
				'deleted'    => array( __( 'Deleted (skipped)', 'convocept' ), $job->count( 'deleted' ) ),
				'empty'      => array( __( 'Empty (skipped)', 'convocept' ), $job->count( 'empty' ) + $job->count( 'invalid' ) + $job->count( 'filtered' ) ),
				'failed'     => array( __( 'Failed', 'convocept' ), $job->count( 'failed' ) ),
				'reparented' => array( __( 'Replies attached after their parent arrived', 'convocept' ), $job->count( 'reparented' ) ),
			)
		);

		printf(
			'<p><a class="button button-primary" href="%s">%s</a> ',
			esc_url( admin_url( 'edit-comments.php' ) ),
			esc_html__( 'View comments', 'convocept' )
		);
		echo '</p>';

		$this->render_actions( $job, '' );
	}

	/**
	 * Render a table of labelled counts, leaving out zero rows except the first.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array{0: string, 1: int}> $rows Rows keyed by counter name.
	 * @return void
	 */
	private function render_counts( array $rows ): void {
		echo '<table class="widefat striped" style="max-width:40em"><tbody>';

		$first = true;

		foreach ( $rows as $key => $row ) {
			if ( ! $first && 0 === $row[1] ) {
				continue;
			}

			$first = false;

			printf(
				'<tr data-convocept-count="%s"><th scope="row">%s</th><td>%s</td></tr>',
				esc_attr( $key ),
				esc_html( $row[0] ),
				esc_html( number_format_i18n( $row[1] ) )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * Render the step's buttons: run a batch, and cancel / start over.
	 *
	 * @since 1.0.0
	 *
	 * @param Job    $job    Job.
	 * @param string $label  Label of the batch button, or '' for none.
	 * @param string $status Progress sentence, updated by the script while batches run.
	 * @return void
	 */
	private function render_actions( Job $job, string $label, string $status = '' ): void {
		printf( '<p role="status" aria-live="polite" data-convocept-import-status>%s</p>', esc_html( $status ) );

		echo '<div style="display:flex;gap:8px;align-items:center">';

		if ( '' !== $label ) {
			printf(
				'<form method="post" action="%1$s" data-convocept-import data-ajax-url="%2$s" data-nonce="%3$s" data-running="%4$s" data-error="%5$s">',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_url( admin_url( 'admin-ajax.php' ) ),
				esc_attr( wp_create_nonce( self::NONCE ) ),
				Job::RUNNING === $job->status ? '1' : '0',
				esc_attr__( 'The import stopped. Click Continue to resume.', 'convocept' )
			);
			echo '<input type="hidden" name="action" value="convocept_import_batch">';
			wp_nonce_field( self::NONCE );
			submit_button( $label, 'primary', 'submit', false );
			echo '</form>';
		}

		printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
		echo '<input type="hidden" name="action" value="convocept_import_cancel">';
		echo '<input type="hidden" name="importer" value="' . esc_attr( $job->importer ) . '">';
		wp_nonce_field( self::NONCE );
		submit_button( Job::DONE === $job->status ? __( 'Start another import', 'convocept' ) : __( 'Cancel import', 'convocept' ), 'secondary', 'submit', false );
		echo '</form></div>';
	}

	/**
	 * Handle the upload form: store the file privately and run the dry run.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_upload(): void {
		$this->verify_request();

		$id = isset( $_POST['importer'] ) ? sanitize_key( wp_unslash( $_POST['importer'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in verify_request().

		if ( ! $this->runner->importer( $id ) ) {
			wp_die( esc_html__( 'Unknown import source.', 'convocept' ), '', array( 'response' => 400 ) );
		}

		$result = $this->store_upload();

		if ( ! is_wp_error( $result ) ) {
			$result = $this->runner->start( $id, $result['file'], true, $result['workspace'] );
		}

		$this->finish( $id, $result );
	}

	/**
	 * Move the uploaded export into a new private workspace.
	 *
	 * The file keeps a fixed name (`export.xml` / `export.xml.gz`), never the
	 * visitor's, and only .xml and .gz files are accepted; the parser then
	 * checks the content is really a Disqus export.
	 *
	 * @since 1.0.0
	 *
	 * @return array{file: string, workspace: string}|WP_Error
	 */
	private function store_upload() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in verify_request(); the file array is validated by wp_handle_upload().
		$file = isset( $_FILES['convocept_import_file'] ) && is_array( $_FILES['convocept_import_file'] ) ? $_FILES['convocept_import_file'] : null;

		if ( ! $file || empty( $file['name'] ) ) {
			return new WP_Error( 'convocept_import_file', __( 'Choose an export file to upload.', 'convocept' ) );
		}

		$name = strtolower( (string) $file['name'] );
		$gzip = '.gz' === substr( $name, -3 );

		if ( ! $gzip && '.xml' !== substr( $name, -4 ) ) {
			return new WP_Error( 'convocept_import_file', __( 'Upload the .xml or .xml.gz file Disqus sent you.', 'convocept' ) );
		}

		$workspace = $this->workspace->create();

		if ( is_wp_error( $workspace ) ) {
			return $workspace;
		}

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
				'action'                   => 'convocept_import_upload',
				'test_form'                => false,
				// Content is validated by the parser; XML and gzip aren't in core's upload MIME list.
				'test_type'                => false,
				'unique_filename_callback' => static function () use ( $target ) {
					return $target;
				},
			)
		);

		remove_filter( 'upload_dir', $dir );

		if ( isset( $moved['error'] ) || empty( $moved['file'] ) ) {
			$this->workspace->delete( $workspace );

			return new WP_Error( 'convocept_import_upload', isset( $moved['error'] ) ? (string) $moved['error'] : __( 'The upload failed.', 'convocept' ) );
		}

		return array(
			'file'      => (string) $moved['file'],
			'workspace' => $workspace,
		);
	}

	/**
	 * Handle the Start / Continue form (no JavaScript): run one batch.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_batch(): void {
		$this->verify_request();

		$job    = Job::current();
		$result = $this->runner->next_batch();

		$this->finish( $job ? $job->importer : 'disqus', $result );
	}

	/**
	 * Handle Cancel / Start another import.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_cancel(): void {
		$this->verify_request();

		$id = isset( $_POST['importer'] ) ? sanitize_key( wp_unslash( $_POST['importer'] ) ) : 'disqus'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in verify_request().

		$this->runner->cancel();
		$this->finish( $id, true );
	}

	/**
	 * Run one batch for the progress script.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_batch(): void {
		check_ajax_referer( self::NONCE );

		if ( ! $this->can_import() ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to import comments.', 'convocept' ) ), 403 );
		}

		$job = $this->runner->next_batch();

		if ( is_wp_error( $job ) ) {
			wp_send_json_error( array( 'message' => $job->get_error_message() ), 'convocept_import_locked' === $job->get_error_code() ? 409 : 400 );
		}

		wp_send_json_success(
			array(
				'done'    => Job::DONE === $job->status,
				'percent' => $job->percent(),
				'text'    => $this->progress_text( $job ),
			)
		);
	}

	/**
	 * Check the nonce and capabilities of a form request, or stop.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function verify_request(): void {
		check_admin_referer( self::NONCE );

		if ( ! $this->can_import() ) {
			wp_die( esc_html__( 'You are not allowed to import comments.', 'convocept' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Redirect back to the importer page, keeping an error message for it.
	 *
	 * @since 1.0.0
	 *
	 * @param string            $id     Importer ID.
	 * @param Job|WP_Error|true $result Result of the action.
	 * @return void
	 */
	private function finish( string $id, $result ): void {
		if ( is_wp_error( $result ) ) {
			set_transient( 'convocept_import_notice_' . get_current_user_id(), $result->get_error_message(), MINUTE_IN_SECONDS );
		}

		wp_safe_redirect( $this->page_url( $id ) );
		exit;
	}
}
