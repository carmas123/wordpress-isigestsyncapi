module.exports = function (grunt) {
	'use strict';

	// Carica automaticamente tutti i task di Grunt
	require('load-grunt-tasks')(grunt);

	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),

		// Pulisce le directory
		clean: {
			build: ['build/'],
			release: ['release/']
		},

		// Copia i file nella directory build
		copy: {
			build: {
				files: [
					{
						expand: true,
						src: [
							'**',
							'!node_modules/**',
							'!build/**',
							'!release/**',
							'!tools/**',
							'!vendor/**',
							'!.git/**',
							'!.github/**',
							'!tests/**',
							'!Gruntfile.js',
							'!package.json',
							'!package-lock.json',
							'!pnpm-lock.yaml',
							'!phpcs.xml',
							'!phpunit.xml',
							'!composer.json',
							'!composer.lock',
							'!.gitignore',
							'!.editorconfig',
							'!.eslintrc',
							'!README.md',
							'!CHANGELOG.md',
							'!.php-cs-fixer.php',
							'!php-cs-fixer.php',
							'!.tabnine_root'
						],
						dest: 'build/'
					}
				]
			}
		},

		// Minifica i file CSS
		cssmin: {
			options: {
				mergeIntoShorthands: false,
				roundingPrecision: -1
			},
			target: {
				files: {
					'build/assets/css/admin.min.css': ['assets/css/admin.css']
				}
			}
		},

		// Minifica i file JavaScript
		uglify: {
			options: {
				banner: '/*! <%= pkg.name %> <%= grunt.template.today("yyyy-mm-dd") %> */\n'
			},
			build: {
				files: {
					'build/assets/js/admin.min.js': ['assets/js/admin.js']
				}
			}
		},

		// Crea il file .pot per le traduzioni
		makepot: {
			target: {
				options: {
					domainPath: '/languages',
					exclude: ['build/.*', 'node_modules/.*'],
					mainFile: 'isigestsyncapi.php',
					potFilename: 'isigestsyncapi.pot',
					potHeaders: {
						poedit: true,
						'x-poedit-keywordslist': true
					},
					type: 'wp-plugin',
					updateTimestamp: true
				}
			}
		},

		// Controlla la qualità del codice PHP
		phpcs: {
			application: {
				src: ['**/*.php', '!node_modules/**', '!build/**', '!vendor/**']
			},
			options: {
				bin: 'vendor/bin/phpcs',
				standard: 'WordPress'
			}
		},

		// Crea il pacchetto di rilascio
		compress: {
			release: {
				options: {
					archive: 'release/isigestsyncapi-<%= pkg.version %>.zip'
				},
				files: [
					{
						expand: true,
						cwd: 'build/',
						src: ['**/*'],
						dest: 'isigestsyncapi/'
					}
				]
			}
		},

		// Monitora i cambiamenti nei file
		watch: {
			css: {
				files: ['assets/css/**/*.css'],
				tasks: ['cssmin']
			},
			js: {
				files: ['assets/js/**/*.js'],
				tasks: ['uglify']
			},
			php: {
				files: ['**/*.php', '!node_modules/**', '!build/**', '!vendor/**'],
				tasks: ['phpcs']
			}
		}
	});

	// Task personalizzati
	grunt.registerTask('build', ['clean:build', 'copy:build', 'cssmin', 'uglify', 'makepot']);

	grunt.registerTask('package', ['build', 'clean:release', 'compress:release']);

	grunt.registerTask('default', ['build']);
};
