const mix = require('./resources/admin/Bits/mix');

mix
    .js('resources/admin/boot.js', 'assets/admin/js/boot.js')
    .js('resources/admin/start.js', 'assets/admin/js/fluent-mail-admin-app.js')
    .sass('resources/scss/fluent-mail-admin.scss', 'assets/admin/css/fluent-mail-admin.css')
    .copy('node_modules/element-ui/lib/theme-chalk/fonts', 'assets/admin/css/fonts')
    .copy('resources/images', 'assets/images')
    .copy('resources/libs', 'assets/libs');
