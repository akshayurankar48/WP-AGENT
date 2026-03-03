module.exports = function ( grunt ) {
	// Project configuration.
	grunt.initConfig( {
		pkg: grunt.file.readJSON( 'package.json' ),

		copy: {
			main: {
				options: {
					mode: true,
				},
				src: [
					'**',
					'!.git/**',
					'!.gitignore',
					'!.gitattributes',
					'!*.sh',
					'!*.zip',
					'!eslintrc.json',
					'!README.md',
					'!Gruntfile.js',
					'!package.json',
					'!package-lock.json',
					'!composer.json',
					'!composer.lock',
					'!phpcs.xml',
					'!phpcs.xml.dist',
					'!phpunit.xml.dist',
					'!node_modules/**',
					'!vendor/**',
					'!tests/**',
					'!scripts/**',
					'!config/**',
					'!bin/**',
					'!src/**',
					'!postcss.config.js',
					'!tailwind.config.js',
					'!webpack.config.js',
					'!phpstan.neon',
					'!phpstan-baseline.neon',
					'!JARVIS-AI-PRD.md',
					'!JARVIS-AI-PR.md',
					'!WP-AGENT-PRD.md',
					'!WP-Agent-PR.md',
					'!CLAUDE.md',
					'!FEATURES.md',
					'!.claude/**',
				],
				dest: 'jarvis-ai/',
			},
		},
		compress: {
			main: {
				options: {
					archive: 'jarvis-ai-<%= pkg.version %>.zip',
					mode: 'zip',
				},
				files: [
					{
						src: [ './jarvis-ai/**' ],
					},
				],
			},
		},
		clean: {
			main: [ 'jarvis-ai' ],
			zip: [ '*.zip' ],
		},
	} );

	/* Load Tasks */
	grunt.loadNpmTasks( 'grunt-contrib-copy' );
	grunt.loadNpmTasks( 'grunt-contrib-compress' );
	grunt.loadNpmTasks( 'grunt-contrib-clean' );

	/* Register task started */
	grunt.registerTask( 'release', [
		'clean:zip',
		'copy',
		'compress',
		'clean:main',
	] );
};
