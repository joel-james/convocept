/**
 * ESLint flat config: @wordpress/scripts defaults plus Convocept overrides.
 */
const globals = require( 'globals' );
const wpPlugin = require( '@wordpress/eslint-plugin' );
const defaults = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	{
		ignores: [ 'assets/dist/**' ],
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
	{
		// WordPress provides @wordpress/* packages at runtime (the build
		// externalises them), so they are not installed as dependencies.
		files: [ 'assets/src/blocks/**/*.js', 'assets/src/admin/**/*.js' ],
		rules: {
			'import/no-unresolved': [ 'error', { ignore: [ '^@wordpress/' ] } ],
			'import/no-extraneous-dependencies': 'off',
		},
	},
	...wpPlugin.configs[ 'test-playwright' ].map( ( config ) => ( {
		...config,
		files: [ 'tests/e2e/**/*.js' ],
	} ) ),
];
