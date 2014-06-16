module.exports = function(grunt) {

  // Project configuration.
  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    uglify: {
      options: {
        mangle: false
      },
      frontEnd: {
        files: {
          'js/wp-seo-local-frontend.min.js': ['js/wp-seo-local-frontend.js']
        }
      }
    },
    watch: {
      files: ['js/wp-seo-local-frontend.js'],
      tasks: ['uglify']
    },
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
  grunt.loadNpmTasks('grunt-contrib-uglify');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.registerTask('default', ['watch']);

};