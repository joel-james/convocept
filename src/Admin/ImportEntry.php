<?php
/**
 * Tools → Import entries.
 *
 * @package Convocept
 * @since   1.0.0
 */

namespace Convocept\Admin;

use Convocept\Import\Runner;

defined( 'ABSPATH' ) || exit;

/**
 * Lists Convocept's importers in Tools → Import and sends them to the app.
 *
 * Imports run on the settings screen's Import page (Manage → Import).
 * People also look for them in Tools → Import, so each importer is listed
 * there too, and opening it redirects to the Import page before any
 * output.
 *
 * @since 1.0.0
 */
class ImportEntry {

	/**
	 * Import runner.
	 *
	 * @since 1.0.0
	 * @var Runner
	 */
	private $runner;

	/**
	 * Create the entry.
	 *
	 * @since 1.0.0
	 *
	 * @param Runner $runner Import runner.
	 */
	public function __construct( Runner $runner ) {
		$this->runner = $runner;
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
			$slug = 'convocept-' . $importer->id();

			register_importer( $slug, $importer->label(), $importer->description(), '__return_null' );

			// Core fires this before printing the admin header, so a redirect still works.
			add_action( 'load-importer-' . $slug, array( $this, 'redirect' ) );
		}
	}

	/**
	 * Send the visitor to the Import page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function redirect(): void {
		wp_safe_redirect( SettingsPage::url( 'import' ) );
		exit;
	}
}
