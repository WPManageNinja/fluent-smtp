<?php

use FluentMail\App\Hooks\Handlers\AdminMenuHandler;

return function () {
    FsmtpTest::case('FluentSMTP loads its own DOMPurify when the legacy handle belongs to another plugin', function () {
        global $wp_scripts, $wp_styles, $wp_filter;

        $originalScripts = wp_scripts();
        $originalStyles = wp_styles();
        $originalHooks = [];
        foreach (['init', 'wp_print_scripts', 'admin_footer_text'] as $hook) {
            $originalHooks[$hook] = $wp_filter[$hook] ?? null;
            if (isset($wp_filter[$hook])) {
                $wp_filter[$hook] = clone $wp_filter[$hook];
            }
        }

        try {
            $wp_scripts = new WP_Scripts();
            $wp_styles = new WP_Styles();
            $otherSource = plugins_url('woocommerce/assets/js/dompurify/purify.min.js');
            wp_register_script('dompurify', $otherSource);

            (new AdminMenuHandler(fluentMail()))->enqueueAssets();

            $handle = 'fluent_mail_dompurify';
            FsmtpTest::assert(!wp_script_is('dompurify'), 'FluentSMTP leaves the legacy script off the queue');
            FsmtpTest::assertSame($otherSource, $wp_scripts->registered['dompurify']->src, 'original registration survives');
            FsmtpTest::assert(wp_script_is($handle), 'plugin sanitizer is enqueued');
            FsmtpTest::assertSame(fluentMailMix('libs/purify/purify.min.js'), $wp_scripts->registered[$handle]->src ?? null, 'plugin sanitizer URL');
            FsmtpTest::assert(in_array($handle, $wp_scripts->registered['fluent_mail_admin_app']->deps, true), 'admin app depends on its sanitizer');
        } finally {
            $wp_scripts = $originalScripts;
            $wp_styles = $originalStyles;
            foreach ($originalHooks as $hook => $original) {
                if ($original === null) {
                    unset($wp_filter[$hook]);
                } else {
                    $wp_filter[$hook] = $original;
                }
            }
        }
    });
};
