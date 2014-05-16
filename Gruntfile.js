module.exports = function(grunt) {

  // Project configuration.
  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    makepot: {
           target: {
               options: {
                   domainPath: '/languages',
                   potFilename: 'yoast-local-seo.pot',
                   type: 'wp-plugin'
               }
           }
       }
  });

  grunt.loadNpmTasks( 'grunt-wp-i18n' );

};