<?php

use FluentMail\App\Services\Documentation;

return function () {
    $names = ['_transient_' . Documentation::CACHE_KEY, '_transient_timeout_' . Documentation::CACHE_KEY, Documentation::BACKUP_KEY];
    $saved = [];
    foreach ($names as $name) {
        $saved[$name] = get_option($name);
    }
    $reset = function () {
        delete_transient(Documentation::CACHE_KEY);
        delete_option(Documentation::BACKUP_KEY);
    };
    $doc = [
        'id' => FsmtpTest::uniq('doc'), 'title' => 'Test documentation',
        'description' => 'Setup help', 'content' => '# Configure SMTP',
        'link' => 'https://fluentsmtp.com/docs/test-document/',
        'category' => ['value' => 'testing', 'label' => 'Testing']
    ];
    $service = new Documentation();
    try {
        FsmtpTest::case('documentation caches a successful feed', function () use ($reset, $doc, $service) {
            FsmtpTest::assertMailSimulationActive();
            $reset();
            $calls = 0;
            FsmtpTest::interceptHttp(function ($url) use ($doc, &$calls) {
                FsmtpTest::assertSame('https://fluentsmtp.com/docs/api/v1/docs.json', $url, 'worker endpoint');
                $calls++;
                return ['response' => ['code' => 200], 'body' => wp_json_encode(['version' => 1, 'docs' => [$doc]]), 'headers' => []];
            });
            FsmtpTest::assertSame([$doc], $service->getDocs(), 'fresh docs');
            FsmtpTest::assertSame([$doc], $service->getDocs(), 'cached docs');
            FsmtpTest::assertSame(1, $calls, 'cache avoids a second request');
            FsmtpTest::assertSame([$doc], get_option(Documentation::BACKUP_KEY), 'persistent fallback');
        });
        FsmtpTest::case('documentation keeps last success on failed or malformed refresh', function () use ($reset, $doc, $service) {
            FsmtpTest::assertMailSimulationActive();
            $reset();
            update_option(Documentation::BACKUP_KEY, [$doc], false);
            $badDoc = $doc;
            $badDoc['link'] = 'https://example.test/docs/test-document/';
            foreach ([new WP_Error('offline', 'Offline'),
                ['response' => ['code' => 503], 'body' => '{}'],
                ['response' => ['code' => 200], 'body' => '<html>Not JSON</html>'],
                ['response' => ['code' => 200], 'body' => wp_json_encode(['version' => 1, 'docs' => [$badDoc]])]
            ] as $response) {
                delete_transient(Documentation::CACHE_KEY);
                FsmtpTest::interceptHttp(function () use ($response) { return $response; });
                FsmtpTest::assertSame([$doc], $service->getDocs(), 'fallback survives refresh');
                FsmtpTest::assertSame([$doc], get_option(Documentation::BACKUP_KEY), 'bad response does not overwrite backup');
            }
        });
        FsmtpTest::case('documentation suggests one article per connected provider', function () use ($service) {
            $gmail = ['id' => 'gmail-guide', 'link' => Documentation::providerLinks()['gmail']];
            $outlook = ['id' => 'outlook-guide', 'link' => Documentation::providerLinks()['outlook']];
            $other = ['id' => 'other', 'link' => 'https://fluentsmtp.com/docs/other/'];
            $suggested = $service->suggestedFor([$other, $gmail, $outlook], ['outlook', 'outlook', 'gmail', 'default', null, 'unknown']);
            FsmtpTest::assertSame(['outlook-guide' => 'outlook', 'gmail-guide' => 'gmail'], $suggested, 'connection order, deduplicated, unmapped providers skipped');
            FsmtpTest::assertSame([], $service->suggestedFor([$gmail], []), 'no connections, no suggestions');
            foreach (Documentation::providerLinks() as $link) {
                FsmtpTest::assert((bool) preg_match('#^https://fluentsmtp\\.com/docs/[a-z0-9-]+/$#D', $link), 'provider link shape: ' . $link);
            }
        });
        FsmtpTest::case('documentation reports failure without a cached copy', function () use ($reset, $service) {
            FsmtpTest::assertMailSimulationActive();
            $reset();
            FsmtpTest::interceptHttp(function () { return new WP_Error('offline', 'Offline'); });
            FsmtpTest::assert(is_wp_error($service->getDocs()), 'uncached outage must report an error');
        });
    } finally {
        $reset();
        foreach ($saved as $name => $value) {
            if ($value !== false) {
                update_option($name, $value, false);
            }
        }
        FsmtpTest::interceptHttp();
    }
};
