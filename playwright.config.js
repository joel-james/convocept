/**
 * Playwright config: @wordpress/scripts defaults with Convocept's spec folder.
 *
 * Runs against the wp-env tests site (http://localhost:8889 by default).
 */
const { defineConfig } = require( '@playwright/test' );
const baseConfig = require( '@wordpress/scripts/config/playwright.config.js' );

module.exports = defineConfig( {
	...baseConfig,
	testDir: './tests/e2e/specs',
} );
