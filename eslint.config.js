const wordpress = require('@wordpress/eslint-plugin');

module.exports = [
	...wordpress.configs.recommended,
	{
		settings: {
			'import/core-modules': [
				'@wordpress/api-fetch',
				'@wordpress/block-editor',
				'@wordpress/blocks',
				'@wordpress/components',
				'@wordpress/compose',
				'@wordpress/data',
				'@wordpress/dom-ready',
				'@wordpress/element',
				'@wordpress/hooks',
				'@wordpress/i18n',
				'@wordpress/server-side-render',
				'jquery',
			],
		},
	},
];
