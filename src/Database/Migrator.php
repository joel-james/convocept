<?php
/**
 * Migration runner.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Database;

use Convocept\Database\Migrations\PrototypeData;
use Convocept\Database\Migrations\ReactionsTable;

defined( 'ABSPATH' ) || exit;

/**
 * Runs pending migrations in order and records the schema version.
 *
 * The version is stored per site, so on multisite each site migrates on
 * its own first request after an update.
 *
 * @since 1.0.0
 */
class Migrator {

	/**
	 * Option storing the last applied migration version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const VERSION_OPTION = 'convocept_db_version';

	/**
	 * Option used as a lock so concurrent requests don't migrate twice.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const LOCK_OPTION = 'convocept_migration_lock';

	/**
	 * Seconds after which a lock is considered stale (crashed run).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const LOCK_TIMEOUT = 600;

	/**
	 * Get every migration, keyed by version.
	 *
	 * Versions are integers that only ever grow. Never renumber or remove
	 * a shipped migration.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, class-string<Migration>>
	 */
	public function migrations(): array {
		return array(
			1 => PrototypeData::class,
			2 => ReactionsTable::class,
		);
	}

	/**
	 * Get the latest known migration version.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function latest_version(): int {
		$versions = array_keys( $this->migrations() );

		return $versions ? (int) max( $versions ) : 0;
	}

	/**
	 * Get the version applied on this site.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function current_version(): int {
		return (int) get_option( self::VERSION_OPTION, 0 );
	}

	/**
	 * Run pending migrations, if any.
	 *
	 * Cheap when nothing is pending: one autoloaded option read.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when the schema is up to date afterwards.
	 */
	public function maybe_migrate(): bool {
		if ( $this->current_version() >= $this->latest_version() ) {
			return true;
		}

		if ( ! $this->acquire_lock() ) {
			return false;
		}

		try {
			$this->run_pending();
		} finally {
			delete_option( self::LOCK_OPTION );
		}

		return true;
	}

	/**
	 * Apply every migration newer than the current version.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function run_pending(): void {
		foreach ( $this->migrations() as $version => $class ) {
			if ( $version <= $this->current_version() ) {
				continue;
			}

			( new $class() )->up();

			// Record each step so a failure resumes from the next one.
			update_option( self::VERSION_OPTION, $version, true );

			/**
			 * Fires after a migration is applied.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $version Migration version.
			 * @param string $class   Migration class name.
			 */
			do_action( 'convocept_migrated', $version, $class );
		}
	}

	/**
	 * Take the migration lock.
	 *
	 * The add_option() call is atomic at the database level (unique option_name),
	 * so only one request can create the lock row.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when this request holds the lock.
	 */
	private function acquire_lock(): bool {
		if ( add_option( self::LOCK_OPTION, time(), '', false ) ) {
			return true;
		}

		$locked_at = (int) get_option( self::LOCK_OPTION, 0 );

		if ( $locked_at && ( time() - $locked_at ) < self::LOCK_TIMEOUT ) {
			return false;
		}

		// Stale lock from a crashed run: take it over.
		update_option( self::LOCK_OPTION, time(), false );

		return true;
	}
}
