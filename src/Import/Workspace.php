<?php
/**
 * Private working directory for imports.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Import;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and removes an import job's working directory.
 *
 * Exports contain commenters' email and IP addresses, so the uploaded
 * file and the files derived from it must never be downloadable. They
 * live in `uploads/convocept-import/{random}/`: the random directory name
 * can't be guessed, and `index.php` plus a deny-all `.htaccess` block
 * listing and direct access on servers that honour them. The directory is
 * deleted when the import finishes or is cancelled.
 *
 * @since 1.0.0
 */
class Workspace {

	/**
	 * Directory name inside uploads.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DIR = 'convocept-import';

	/**
	 * Get the parent directory of all workspaces.
	 *
	 * @since 1.0.0
	 *
	 * @return string Absolute path without trailing slash.
	 */
	public function root(): string {
		$uploads = wp_upload_dir( null, false );

		return untrailingslashit( (string) $uploads['basedir'] ) . '/' . self::DIR;
	}

	/**
	 * Create a new, empty workspace.
	 *
	 * @since 1.0.0
	 *
	 * @return string|WP_Error Absolute path, or an error when the directory can't be created.
	 */
	public function create() {
		$root = $this->root();
		$path = $root . '/' . wp_generate_password( 32, false );

		if ( ! wp_mkdir_p( $path ) ) {
			return new WP_Error( 'convocept_import_workspace', __( 'Could not create a working folder in the uploads directory. Check that it is writable.', 'convocept' ) );
		}

		$this->protect( $root );
		$this->protect( $path );

		return $path;
	}

	/**
	 * Add files that stop directory listing and direct downloads.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dir Directory.
	 * @return void
	 */
	private function protect( string $dir ): void {
		$files = array(
			'index.php' => "<?php\n// Silence is golden.\n",
			'.htaccess' => "Require all denied\nDeny from all\n",
		);

		foreach ( $files as $name => $content ) {
			if ( ! file_exists( $dir . '/' . $name ) ) {
				file_put_contents( $dir . '/' . $name, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Tiny static file in our own uploads folder; WP_Filesystem may need credentials.
			}
		}
	}

	/**
	 * Delete a workspace and everything in it.
	 *
	 * Only removes directories inside the workspace root, so a corrupted
	 * job can never point this at another folder.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Workspace path.
	 * @return void
	 */
	public function delete( string $path ): void {
		$root = realpath( $this->root() );
		$real = '' !== $path ? realpath( $path ) : false;

		if ( ! $root || ! $real || dirname( $real ) !== $root ) {
			return;
		}

		foreach ( (array) scandir( $real ) as $name ) {
			if ( '.' === $name || '..' === $name || ! is_string( $name ) ) {
				continue;
			}

			if ( is_file( $real . '/' . $name ) ) {
				wp_delete_file( $real . '/' . $name );
			}
		}

		rmdir( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Empty directory we created; WP_Filesystem may need credentials.
	}
}
