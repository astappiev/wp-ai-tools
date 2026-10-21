const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'admin/settings': path.resolve( __dirname, 'src/admin/settings.js' ),
		'admin/blocks': path.resolve( __dirname, 'src/admin/blocks.js' ),
		'admin/media-button': path.resolve(
			__dirname,
			'src/admin/media-button.js'
		),
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) => plugin.constructor.name !== 'RtlCssPlugin'
		),
	],
};
