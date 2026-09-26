<?php
/**
 * Migration contract.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Database;

defined( 'ABSPATH' ) || exit;

/**
 * A single, idempotent schema or data migration.
 *
 * Migrations run once, in version order, and must be safe to re-run if a
 * previous attempt stopped half way.
 *
 * @since 1.0.0
 */
interface Migration {

	/**
	 * Apply the migration.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function up(): void;
}
