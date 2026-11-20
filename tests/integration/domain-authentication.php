<?php

use FluentMail\App\Services\DomainAuthentication;

return function () {
    FsmtpTest::case('invalid DNS questions are rejected by the admin route', function () {
        FsmtpTest::assertMailSimulationActive();
        foreach ([['example.test'], 'localhost', '127.0.0.1', 'https://example.test', 'a..test'] as $domain) {
            $response = FsmtpTest::ajax('POST', '/settings/domain-authentication', ['envelope_domain' => $domain]);
            FsmtpTest::assertSame(422, $response['status'], 'Invalid envelope domain');
        }
    });

    if (!class_exists(DomainAuthentication::class)) {
        FsmtpTest::case('domain authentication service exists', function () {
            FsmtpTest::fail('The domain authentication checker is missing.');
        });
        return;
    }

    $dns = new class extends DomainAuthentication {
        public $answers = [];
        public $queries = [];
        protected function query($name)
        {
            $this->queries[] = $name;
            return $this->answers[$name] ?? [];
        }
    };
    $domain = FsmtpTest::uniq('dns') . '.example.test';
    $selector = 'key1._domainkey.' . $domain;
    $names = [$domain, $selector, 'absent.' . $domain, 'duplicate.' . $domain,
        'revoked._domainkey.' . $domain, 'alias._domainkey.' . $domain, 'down.' . $domain];
    try {
        FsmtpTest::case('published records and repeated cached lookups', function () use ($dns, $domain, $selector) {
            FsmtpTest::assertMailSimulationActive();
            $dns->answers[$domain] = [['type' => 'TXT', 'txt' => 'v=spf1 -all']];
            $dns->answers[$selector] = [['type' => 'TXT', 'entries' => ['v=DKIM1; p=', 'YWJj']]];
            $result = $dns->check(strtoupper($domain) . '.', $domain, 'key1');
            FsmtpTest::assertSame('published', $result['spf']['status'], 'One SPF policy');
            FsmtpTest::assertSame('published', $result['dkim']['status'], 'Segmented DKIM publication');
            FsmtpTest::assertSame($selector, $result['dkim']['name'], 'Explicit signing name');
            $queries = $dns->queries;
            FsmtpTest::assertSame($result, $dns->check($domain, $domain, 'key1'), 'Cached result');
            FsmtpTest::assertSame($queries, $dns->queries, 'No second DNS query');
            $ajax = FsmtpTest::ajax('POST', '/settings/domain-authentication', [
                'envelope_domain' => $domain, 'signing_domain' => $domain, 'selector' => 'key1',
            ]);
            FsmtpTest::assertSame($result, $ajax['data']['data'], 'Admin AJAX returns both cached classifications');
        });
        FsmtpTest::case('ambiguous or missing records', function () use ($dns, $domain) {
            FsmtpTest::assertMailSimulationActive();
            $dns->answers['duplicate.' . $domain] = array_fill(0, 2, ['type' => 'TXT', 'txt' => 'v=spf1 -all']);
            $dns->answers['revoked._domainkey.' . $domain] = [['type' => 'TXT', 'txt' => 'v=DKIM1; p=']];
            $result = $dns->check('duplicate.' . $domain, $domain, 'revoked');
            FsmtpTest::assertSame('multiple', $result['spf']['status'], 'Duplicate SPF policies');
            FsmtpTest::assertSame('revoked', $result['dkim']['status'], 'Revoked key');
            $result = $dns->check('absent.' . $domain, '', '');
            FsmtpTest::assertSame('missing', $result['spf']['status'], 'No SPF policy');
            FsmtpTest::assertSame('skipped', $result['dkim']['status'], 'No selector supplied');
        });
        FsmtpTest::case('resolver failures and aliases', function () use ($dns, $domain) {
            FsmtpTest::assertMailSimulationActive();
            $dns->answers['down.' . $domain] = false;
            $dns->answers['alias._domainkey.' . $domain] = [['type' => 'CNAME', 'target' => 'key.' . $domain]];
            $dns->answers['key.' . $domain] = [['type' => 'TXT', 'txt' => 'p=YWJj']];
            $result = $dns->check('down.' . $domain, $domain, 'alias');
            FsmtpTest::assertSame('unavailable', $result['spf']['status'], 'Resolver error');
            FsmtpTest::assertSame('published', $result['dkim']['status'], 'Provider alias');
        });
    } finally {
        foreach ($names as $name) {
            foreach (['spf', 'dkim'] as $type) {
                delete_transient('fluentmail_dns_' . md5($type . ':' . $name));
            }
        }
    }
};
