const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		main: path.resolve( __dirname, 'src/index.js' ),
		editor: path.resolve( __dirname, 'src/editor.js' ),
		drawer: path.resolve( __dirname, 'src/drawer.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
