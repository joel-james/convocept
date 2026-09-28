/**
 * Playwright config for the WordPress.org assets generator.
 *
 * `npm run wporg:assets` renders the plugin icon, banner and screenshots into
 * `.wordpress-org/` from the wp-env tests site. Not part of the test suite.
 */
const { defineConfig } = require( '@playwright/test' );
const baseConfig = require( './playwright.config.js' );

module.exports = defineConfig( {
	...baseConfig,
	testDir: './tests/e2e/wporg',
	retries: 0,
	timeout: 180000,
} );
