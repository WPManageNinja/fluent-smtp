const webpack = require('webpack');
let mix = require('laravel-mix');
mix.setPublicPath('assets');
mix.setResourceRoot('../');

/* global Mix path */
mix.webpackConfig({
    module: {
        rules: [
            {
                enforce: 'pre',
                test: /\.(js|vue)$/,
                loader: 'eslint-loader',
                exclude: /node_modules/
            }
        ]
    },
    output: {
        publicPath: Mix.isUsing('hmr') ? '/' : '/wp-content/plugins/fluent-mail/assets/',
        chunkFilename: 'admin/js/[name].js'
    },
    plugins: [
        // Ignore all locale files of moment.js
        new webpack.IgnorePlugin(/^\.\/locale$/, /moment$/)
    ],
    resolve: {
        extensions: ['.js', '.vue', '.json'],
        alias: {
            '@': path.resolve(__dirname, '../')
        }
    },
    optimization: {
        splitChunks: {
            cacheGroups: {
                default: false,
                vendors: {
                    chunks: 'initial',
                    name: 'vendor',
                    filename: 'admin/js/[name].js'
                }
            }
        }
    }
});

mix.options({ processCssUrls: false });

module.exports = mix;
