// Webpack settings exports.
module.exports = {
	entries: {
		// JS files.
		taxonomies: './assets/js/taxonomies.js',
		folders: './assets/js/folders/init.js',
		edit: './assets/js/edit.js',

		// CSS files.
		'taxonomies-styles': './assets/css/taxonomies.css',
		'edit-styles': './assets/css/edit.css',
		'admin-styles': './assets/css/admin.css',
		'folders-styles': './assets/css/folders.css',
	},
	filename: {
		js: 'js/[name].js',
		css: 'css/[name].css',
	},
	paths: {
		src: {
			base: './assets/',
			css: './assets/css/',
			js: './assets/js/',
		},
		dist: {
			base: './dist/',
			clean: ['./images', './css', './js'],
		},
	},
	stats: {
		// Copied from `'minimal'`.
		all: false,
		errors: true,
		maxModules: 0,
		modules: true,
		warnings: true,
		// Our additional options.
		assets: true,
		errorDetails: true,
		excludeAssets: /\.(jpe?g|png|gif|svg|woff|woff2)$/i,
		moduleTrace: true,
		performance: true,
	},
	copyWebpackConfig: {
		from: '**/*.{jpg,jpeg,png,gif,svg,eot,ttf,woff,woff2}',
		to: '[path][name].[ext]',
	},
	ImageminPlugin: {
		test: /\.(jpe?g|png|gif|svg)$/i,
	},
	performance: {
		maxAssetSize: 100000,
	},
	manifestConfig: {
		basePath: '',
	},
	externals: {
		jquery: 'jQuery',
		window: 'window',
	},
};
