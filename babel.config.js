/**
 * Babel config for the wp-admin builds (settings app, blocks).
 *
 * @wordpress/scripts compiles JSX with React's automatic runtime, which
 * needs the `react-jsx-runtime` script handle that only exists from
 * WordPress 6.6. Convocept supports 6.5, so JSX compiles to
 * `createElement` from @wordpress/element instead (imported automatically
 * into every file that uses JSX), as WordPress itself did before 6.6.
 */
module.exports = ( api ) => {
	api.cache( true );

	return {
		presets: [ '@wordpress/babel-preset-default' ],
		// Plugins run before presets, so JSX is already compiled when the preset's automatic runtime would see it.
		plugins: [
			[
				'@wordpress/babel-plugin-import-jsx-pragma',
				{
					scopeVariable: 'createElement',
					scopeVariableFrag: 'Fragment',
					source: '@wordpress/element',
					isDefault: false,
				},
			],
			[
				'@babel/plugin-transform-react-jsx',
				{
					runtime: 'classic',
					pragma: 'createElement',
					pragmaFrag: 'Fragment',
					// Native object spread for JSX spread props, instead of a @babel/runtime helper.
					useSpread: true,
				},
			],
		],
	};
};
