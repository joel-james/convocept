/**
 * ESLint flat config: @wordpress/scripts defaults plus Convocept overrides.
 */
const globals = require( 'globals' );
const wpPlugin = require( '@wordpress/eslint-plugin' );
const defaults = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	{
		ignores: [ 'assets/dist/**', 'src/**' ],
	},
	...defaults,
	{
		// Public front end: plain browser JS, no WordPress globals.
		files: [ 'assets/src/front/**/*.js' ],
		languageOptions: { globals: globals.browser },
	},
	{
		files: [ 'bin/**/*.mjs' ],
		languageOptions: { globals: globals.node, sourceType: 'module' },
	},
	...wpPlugin.configs[ 'test-playwright' ].map( ( config ) => ( {
		...config,
		files: [ 'tests/e2e/**/*.js' ],
	} ) ),
];
