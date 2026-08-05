<?php

return function () {
    global $wpdb;

    $baseSettings = [
        'connections' => [],
        'mappings'    => [],
        'misc'        => [
            'simulate_emails'        => 'yes',
            'log_emails'             => 'no',
            'log_saved_interval_days'=> '14',
            'default_connection'     => '',
        ],
    ];
    $environmentFor = function (array $settings) {
        return [
            'FSMTP_SUITE_SETTINGS_B64' => base64_encode(wp_json_encode($settings)),
        ];
    };
    $readHealthOption = function () use ($wpdb) {
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                \FluentMail\App\Services\ConnectionHealth::OPTION_KEY
            )
        );
    };

    FsmtpTest::case('wp fluent-smtp test sends only through Simulator', function () use (
        $baseSettings,
        $environmentFor
    ) {
        $before = FsmtpTest::protectedTableCounts();
        $recipient = 'fsmtp-cli-' . FsmtpTest::uniq() . '@example.test';
        $result = FsmtpTest::wpCli(
            ['fluent-smtp', 'test', '--to=' . $recipient, '--text'],
            $environmentFor($baseSettings)
        );

        FsmtpTest::assertSame(0, $result['code'], 'test command exit code');
        FsmtpTest::assert(
            strpos($result['output'], 'FluentSMTP CLI safety: Simulator active.') !== false,
            'child process did not prove Simulator resolution'
        );
        FsmtpTest::assert(
            strpos($result['output'], 'Test email handed to the provider for ' . $recipient) !== false,
            'test command success output'
        );
        FsmtpTest::assertSame($before, FsmtpTest::protectedTableCounts(), 'test command log count');
    });

    FsmtpTest::case('wp fluent-smtp health exits non-zero for a broken connection', function () use (
        $baseSettings,
        $environmentFor,
        $readHealthOption
    ) {
        $sender = 'fsmtp-broken-' . FsmtpTest::uniq() . '@example.test';
        $settings = $baseSettings;
        $settings['connections']['suite-broken'] = [
            'title' => 'Suite Broken SMTP',
            'provider_settings' => [
                'provider'     => 'smtp',
                'sender_email' => $sender,
                'auth'         => 'no',
            ],
        ];
        $settings['mappings'][$sender] = 'suite-broken';
        $settings['misc']['default_connection'] = 'suite-broken';
        $before = $readHealthOption();

        $result = FsmtpTest::wpCli(
            ['fluent-smtp', 'health', '--format=json'],
            $environmentFor($settings)
        );

        FsmtpTest::assertSame(1, $result['code'], 'broken health command exit code');
        FsmtpTest::assert(strpos($result['output'], $sender) !== false, 'health output sender');
        FsmtpTest::assert(strpos($result['output'], 'SMTP host is required') !== false, 'health output provider error');
        FsmtpTest::assert(
            strpos($result['output'], '1 of 1 connection(s) need attention') !== false,
            'health output failure count'
        );
        FsmtpTest::assertSame($before, $readHealthOption(), 'health report option write fuse');
    });

    FsmtpTest::case('wp fluent-smtp stats reports both status metrics', function () use (
        $baseSettings,
        $environmentFor
    ) {
        $result = FsmtpTest::wpCli(
            ['fluent-smtp', 'stats', '--format=json'],
            $environmentFor($baseSettings)
        );

        FsmtpTest::assertSame(0, $result['code'], 'stats command exit code');
        FsmtpTest::assert(strpos($result['stdout'], '"metric":"sent"') !== false, 'stats sent metric');
        FsmtpTest::assert(strpos($result['stdout'], '"metric":"failed"') !== false, 'stats failed metric');
    });

    FsmtpTest::case('wp fluent-smtp prune-logs fuses production log deletes in the child process', function () use (
        $baseSettings,
        $environmentFor
    ) {
        $before = FsmtpTest::protectedTableCounts();
        $result = FsmtpTest::wpCli(
            ['fluent-smtp', 'prune-logs', '--days=1', '--yes'],
            $environmentFor($baseSettings)
        );

        FsmtpTest::assertSame(0, $result['code'], 'prune command exit code');
        FsmtpTest::assert(
            strpos($result['output'], 'FluentSMTP CLI safety: production log DELETE fused.') !== false,
            'child process did not prove the production-log write fuse'
        );
        FsmtpTest::assert(strpos($result['output'], 'Deleted 0 log entries.') !== false, 'fused prune output');
        FsmtpTest::assertSame($before, FsmtpTest::protectedTableCounts(), 'prune command log count');
    });
};
