/*const mix = require('laravel-mix');
require('laravel-mix-merge-manifest');

mix.setPublicPath('../../public').mergeManifest();
mix.inProduction();
mix.js(__dirname + '/Resources/assets/js/app.js', 'dist').setPublicPath('dist');

let mix = require('laravel-mix');*/

mix.js('./Resources/assets/js/app.js', 'dist').setPublicPath('dist');

