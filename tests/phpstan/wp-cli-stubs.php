<?php
/**
 * Minimal WP-CLI stubs for PHPStan.
 *
 * php-stubs/wp-cli-stubs requires an older php-stubs/wordpress-stubs, so the
 * few WP-CLI APIs Convocept uses are declared here. Scanned, never loaded.
 *
 * @package Convocept
 */

// phpcs:ignoreFile -- Stub signatures mirror WP-CLI.

namespace {
	class WP_CLI {
		/**
		 * @param string          $name     Command name.
		 * @param callable|object $callable Command.
		 * @param array<string, mixed> $args Options.
		 * @return bool
		 */
		public static function add_command( $name, $callable, $args = array() ) {}

		/**
		 * @param string $message Message.
		 * @return void
		 */
		public static function log( $message ) {}

		/**
		 * @param string $message Message.
		 * @return void
		 */
		public static function success( $message ) {}

		/**
		 * @param string|\WP_Error $message Message.
		 * @param bool|int         $exit    Exit.
		 * @return never
		 */
		public static function error( $message, $exit = true ) {}

		/**
		 * @param string               $question   Question.
		 * @param array<string, mixed> $assoc_args Arguments.
		 * @return void
		 */
		public static function confirm( $question, $assoc_args = array() ) {}
	}
}

namespace WP_CLI\Utils {
	/**
	 * @param string $message Message.
	 * @param int    $count   Total.
	 * @return \cli\progress\Bar
	 */
	function make_progress_bar( $message, $count ) {}

	/**
	 * @param string              $format Format.
	 * @param array<int, mixed>   $items  Items.
	 * @param array<int, string>  $fields Fields.
	 * @return void
	 */
	function format_items( $format, $items, $fields ) {}
}

namespace cli\progress {
	class Bar {
		/**
		 * @param int $increment Increment.
		 * @return void
		 */
		public function tick( $increment = 1 ) {}

		/**
		 * @return void
		 */
		public function finish() {}
	}
}
