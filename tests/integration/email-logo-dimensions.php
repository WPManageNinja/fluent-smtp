<?php

use FluentMail\App\Hooks\Handlers\SchedulerHandler;
use FluentMail\App\Models\Settings;

/**
 * Image dimension attributes in the HTML emails FluentSMTP sends itself.
 *
 * HTML width/height attributes are unitless integers. Classic Outlook for
 * Windows ignores a value such as "164px" and falls back to the image's natural
 * width (2018px for the white logo), which breaks the digest layout.
 *
 * Each email is built by its real sender and captured from pre_wp_mail, so the
 * assertions read the final message body and nothing is delivered.
 */
return function () {
    /** Run $send and return the message bodies it handed to wp_mail. */
    $captureMail = function (callable $send) {
        $bodies = [];
        $capture = function ($short, $atts) use (&$bodies) {
            $bodies[] = (string) $atts['message'];
            return true;
        };
        add_filter('pre_wp_mail', $capture, PHP_INT_MAX, 2);

        try {
            $send();
        } finally {
            remove_filter('pre_wp_mail', $capture, PHP_INT_MAX);
        }

        return $bodies;
    };

    /**
     * Assert every img dimension attribute is unitless and matches the inline
     * CSS width, and return the logo tags. Classic Outlook sizes the image by
     * the attribute alone, so a mismatch renders differently there.
     */
    $assertImageDimensions = function ($html, $label) {
        preg_match_all('/<img\b[^>]*>/i', $html, $images);
        FsmtpTest::assert(count($images[0]) > 0, $label . ': no img tags rendered');

        foreach ($images[0] as $tag) {
            preg_match_all('/\s(width|height)\s*=\s*"([^"]*)"/i', $tag, $attrs, PREG_SET_ORDER);
            foreach ($attrs as $attr) {
                FsmtpTest::assert(
                    (bool) preg_match('/^\d+$/', $attr[2]),
                    $label . ': img ' . $attr[1] . '="' . $attr[2] . '" is not a unitless integer'
                );
            }

            if (preg_match('/\swidth\s*=\s*"(\d+)"/i', $tag, $widthAttr)
                && preg_match('/\sstyle\s*=\s*"([^"]*)"/i', $tag, $style)
                && preg_match('/(?:^|;)\s*width\s*:\s*(\d+)px/i', $style[1], $cssWidth)) {
                FsmtpTest::assertSame(
                    $cssWidth[1],
                    $widthAttr[1],
                    $label . ': img width attribute matches its inline CSS width'
                );
            }
        }

        return array_values(array_filter($images[0], function ($tag) {
            return (bool) preg_match('#/images/fluentsmtp(-white)?\.png#', $tag);
        }));
    };

    FsmtpTest::case('health digest logos carry a unitless width attribute', function () use ($captureMail, $assertImageDimensions) {
        FsmtpTest::assertMailSimulationActive();

        $settings = function () {
            return [
                'enabled'      => 'yes',
                'notify_email' => FsmtpTest::uniq('digest') . '@example.test',
                'notify_days'  => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            ];
        };
        $lastSent = function () {
            return gmdate('Y-m-d H:i:s', strtotime('-3 days'));
        };
        $keepLastSent = function ($value, $oldValue) {
            return $oldValue;
        };
        add_filter('pre_option__fluent_smtp_notify_settings', $settings, PHP_INT_MAX);
        add_filter('pre_option__fluentmail_last_email_digest', $lastSent, PHP_INT_MAX);
        add_filter('pre_update_option__fluentmail_last_email_digest', $keepLastSent, PHP_INT_MAX, 2);

        try {
            $bodies = $captureMail(function () {
                (new SchedulerHandler())->sendDailyDigest();
            });
        } finally {
            remove_filter('pre_update_option__fluentmail_last_email_digest', $keepLastSent, PHP_INT_MAX);
            remove_filter('pre_option__fluentmail_last_email_digest', $lastSent, PHP_INT_MAX);
            remove_filter('pre_option__fluent_smtp_notify_settings', $settings, PHP_INT_MAX);
        }

        FsmtpTest::assertSame(1, count($bodies), 'digest emails handed to wp_mail');

        $logos = $assertImageDimensions($bodies[0], 'digest');
        FsmtpTest::assertSame(2, count($logos), 'digest logo count');
        // The header logo is 164px; the footer logo fits its 100px cell.
        FsmtpTest::assert(strpos($logos[0], ' width="164"') !== false, 'digest header logo width attribute is 164');
        FsmtpTest::assert(strpos($logos[1], ' width="100"') !== false, 'digest footer logo width attribute is 100');
    });

    FsmtpTest::case('HTML test email logo carries a unitless width attribute', function () use ($captureMail, $assertImageDimensions) {
        FsmtpTest::assertMailSimulationActive();

        $bodies = $captureMail(function () {
            (new Settings())->sendTestEmail(
                ['email' => FsmtpTest::uniq('test') . '@example.test', 'isHtml' => 'true'],
                ['suite' => true]
            );
        });

        FsmtpTest::assertSame(1, count($bodies), 'test emails handed to wp_mail');

        $logos = $assertImageDimensions($bodies[0], 'test email');
        FsmtpTest::assertSame(1, count($logos), 'test email logo count');
        FsmtpTest::assert(strpos($logos[0], ' width="164"') !== false, 'test email logo width attribute is 164');
        FsmtpTest::assert(strpos($logos[0], 'width: 164px;') !== false, 'test email logo inline width is 164px');
    });
};
