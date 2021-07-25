const path = require('path');
const mix = require('laravel-mix');
mix.options({ processCssUrls: false });

mix.setPublicPath('assets');
mix.setResourceRoot('../');

mix.alias({
    '@': path.join(__dirname, 'resources/admin')
});

mix
    .js('resources/admin/boot.js', 'assets/admin/js/boot.js').vue({ version: 2 })
    .js('resources/admin/start.js', 'assets/admin/js/fluent-mail-admin-app.js').vue({ version: 2 })
    .sass('resources/scss/fluent-mail-admin.scss', 'assets/admin/css/fluent-mail-admin.css')
    .copy('node_modules/element-ui/lib/theme-chalk/fonts', 'assets/admin/css/fonts')
    .copy('resources/images', 'assets/images')
    .copy('resources/libs', 'assets/libs');
