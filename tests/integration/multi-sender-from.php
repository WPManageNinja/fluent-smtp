<?php

use FluentMail\App\Models\Settings;
use FluentMail\App\Services\Mailer\Providers\AmazonSes\Handler as SesHandler;
use FluentMail\App\Services\Mailer\Providers\AmazonSes\SimpleEmailService;
use FluentMail\App\Services\Mailer\Providers\AmazonSes\SimpleEmailServiceRequest;
use FluentMail\App\Services\Mailer\Providers\SendGrid\Handler as SendGridHandler;
use FluentMail\App\Services\Mailer\Providers\ToSend\Handler as ToSendHandler;

return function () {
    /*
     * Amazon SES and toSend verify a whole domain, so one connection can send as
     * several addresses: its own sender plus the additional senders mapped to it.
     * Forcing the sender email must only replace addresses that are not among
     * them. These cases pin that, for routing, the provider request and the log.
     */

    $sesKey = md5('primary@ses-suite.example');
    $toSendKey = md5('primary@tosend-suite.example');

    $settings = [
        'connections' => [
            $sesKey    => [
                'title'             => 'Amazon SES',
                'provider_settings' => [
                    'provider'         => 'ses',
                    'sender_email'     => 'primary@ses-suite.example',
                    'sender_name'      => 'Suite',
                    'force_from_email' => 'yes',
                    'force_from_name'  => 'no',
                    'key_store'        => 'db',
                    'access_key'       => 'suite-access',
                    'secret_key'       => 'suite-secret',
                    'region'           => 'us-east-1',
                ],
            ],
            $toSendKey => [
                'title'             => 'toSend',
                'provider_settings' => [
                    'provider'           => 'tosend',
                    'sender_email'       => 'primary@tosend-suite.example',
                    'sender_name'        => 'Suite',
                    'force_from_email'   => 'yes',
                    'force_from_name'    => 'no',
                    'key_store'          => 'db',
                    'api_key'            => 'suite-key',
                    'additional_senders' => ['billing@tosend-suite.example'],
                ],
            ],
        ],
        'mappings'    => [
            'primary@ses-suite.example'    => $sesKey,
            'Contact@ses-suite.example'    => $sesKey,
            'primary@tosend-suite.example' => $toSendKey,
            'billing@tosend-suite.example' => $toSendKey,
        ],
        'misc'        => [
            'simulate_emails'    => 'yes',
            'default_connection' => $sesKey,
        ],
    ];

    $withSettings = function (callable $callback) use ($settings) {
        $settingsFilter = function () use ($settings) {
            return $settings;
        };
        $writeFuse = function ($newValue, $oldValue) {
            return $oldValue;
        };

        add_filter('pre_option_fluentmail-settings', $settingsFilter, PHP_INT_MAX);
        add_filter('pre_update_option_fluentmail-settings', $writeFuse, PHP_INT_MAX, 2);
        wp_cache_delete('fluentmail-settings', 'options');
        fluentMailGetSettings([], false);

        try {
            return $callback();
        } finally {
            remove_filter('pre_option_fluentmail-settings', $settingsFilter, PHP_INT_MAX);
            remove_filter('pre_update_option_fluentmail-settings', $writeFuse, PHP_INT_MAX);
            wp_cache_delete('fluentmail-settings', 'options');
            wp_cache_delete('alloptions', 'options');
            fluentMailGetSettings([], false);
        }
    };

    $mailer = function ($fromEmail) {
        FsmtpTest::requirePhpMailer();
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->setFrom($fromEmail, 'Team', false);
        $mailer->addAddress('to@example.test');
        $mailer->Subject = 'multi sender suite';
        $mailer->Body = 'body';
        $mailer->ContentType = 'text/plain';

        return $mailer;
    };

    // Run the handler's own preSend() and return the params a provider reads.
    $prepared = function ($handler, $connectionKey, $fromEmail, array $overrides = []) use ($settings, $mailer) {
        $handler->setSettings(array_merge($settings['connections'][$connectionKey]['provider_settings'], $overrides));
        $handler->setPhpMailer($mailer($fromEmail));

        $preSend = new ReflectionMethod($handler, 'preSend');
        $preSend->setAccessible(true);
        $preSend->invoke($handler);

        $attributes = new ReflectionProperty($handler, 'attributes');
        $attributes->setAccessible(true);
        $attributes = $attributes->getValue($handler);

        return [
            'from'         => $attributes['from'],
            'sender_email' => $attributes['sender_email'],
        ];
    };

    FsmtpTest::case('a mapped sender is found whatever the case of its address', function () use ($withSettings, $sesKey) {
        FsmtpTest::assertSame($sesKey, Settings::findMappingKey(['Contact@ses-suite.example' => $sesKey], 'contact@SES-suite.example'), 'case-insensitive key');
        FsmtpTest::assertSame('exact', Settings::findMappingKey(['a@x.example' => 'loose', 'A@x.example' => 'exact'], 'A@x.example'), 'the exact key wins');
        FsmtpTest::assertSame(null, Settings::findMappingKey(['a@x.example' => 'k'], 'b@x.example'), 'an unmapped address');

        $withSettings(function () use ($sesKey) {
            $connection = (new Settings)->getConnection('contact@ses-suite.example');
            FsmtpTest::assertSame('primary@ses-suite.example', $connection['provider_settings']['sender_email'] ?? null, 'getConnection() resolved the SES connection');
            FsmtpTest::assert(fluentMailIsListedSenderEmail('CONTACT@ses-suite.example'), 'an upper-case additional sender is listed');
        });
    });

    FsmtpTest::case('SES keeps an additional sender as the From of a forced connection', function () use ($withSettings, $prepared, $sesKey) {
        $withSettings(function () use ($prepared, $sesKey) {
            $params = $prepared(new SesHandler(), $sesKey, 'contact@ses-suite.example');
            FsmtpTest::assertSame('Team <contact@ses-suite.example>', $params['from'], 'logged from of an additional sender');

            $params = $prepared(new SesHandler(), $sesKey, 'stranger@elsewhere.example');
            FsmtpTest::assertSame('Team <primary@ses-suite.example>', $params['from'], 'an unlisted From still logs the primary sender');

            // Mapped, but to another connection: not this connection's sender.
            $params = $prepared(new SesHandler(), $sesKey, 'billing@tosend-suite.example');
            FsmtpTest::assertSame('primary@ses-suite.example', $params['sender_email'], 'a sender of another connection');
        });
    });

    FsmtpTest::case('a connection that does not force its sender keeps any From', function () use ($withSettings, $prepared, $sesKey) {
        $withSettings(function () use ($prepared, $sesKey) {
            $params = $prepared(new SendGridHandler(), $sesKey, 'stranger@elsewhere.example', ['force_from_email' => 'no']);
            FsmtpTest::assertSame('stranger@elsewhere.example', $params['sender_email'], 'unforced, an unlisted From is sent as itself');
            FsmtpTest::assertSame('Team <stranger@elsewhere.example>', $params['from'], 'and logged as itself');
        });
    });

    FsmtpTest::case('an API provider sends an additional sender as itself', function () use ($withSettings, $prepared, $sesKey) {
        // Any provider building its request from sender_email follows the same path.
        $withSettings(function () use ($prepared, $sesKey) {
            $params = $prepared(new SendGridHandler(), $sesKey, 'contact@ses-suite.example');
            FsmtpTest::assertSame('contact@ses-suite.example', $params['sender_email'], 'SendGrid-shaped sender_email');
        });
    });

    FsmtpTest::case('a provider without the force option always sends from its own sender', function () use ($withSettings, $prepared, $sesKey) {
        // A connection switched from SMTP keeps a stale force_from_email = no,
        // but SendGrid has no such option and must send from its sender as before.
        $withSettings(function () use ($prepared, $sesKey) {
            $params = $prepared(new SendGridHandler(), $sesKey, 'stranger@elsewhere.example', [
                'provider'         => 'sendgrid',
                'force_from_email' => 'no',
            ]);
            FsmtpTest::assertSame('primary@ses-suite.example', $params['sender_email'], 'stale force off is ignored');
        });
    });

    FsmtpTest::case('an SES connection with forcing off keeps any From', function () use ($withSettings, $prepared, $sesKey) {
        $withSettings(function () use ($prepared, $sesKey) {
            $params = $prepared(new SesHandler(), $sesKey, 'stranger@elsewhere.example', ['force_from_email' => 'no']);
            FsmtpTest::assertSame('stranger@elsewhere.example', $params['sender_email'], 'force off on a provider that offers it');
        });
    });

    FsmtpTest::case('toSend forces only addresses that are not its own senders', function () use ($withSettings, $prepared, $toSendKey) {
        $withSettings(function () use ($prepared, $toSendKey) {
            $handler = new ToSendHandler();

            $prepared($handler, $toSendKey, 'billing@tosend-suite.example');
            FsmtpTest::assertSame('billing@tosend-suite.example', $handler->getFrom()['email'], 'additional sender kept');

            $prepared($handler, $toSendKey, 'contact@ses-suite.example');
            FsmtpTest::assertSame('primary@tosend-suite.example', $handler->getFrom()['email'], 'a sender of another connection is forced');

            $prepared($handler, $toSendKey, 'stranger@elsewhere.example');
            FsmtpTest::assertSame('primary@tosend-suite.example', $handler->getFrom()['email'], 'an unlisted sender is forced');
        });
    });

    FsmtpTest::case('SES keeps its additional senders when the identity lookup fails', function () use ($withSettings, $settings, $sesKey) {
        $handler = new class extends SesHandler {
            public function getValidSendingIdentities($config)
            {
                return [
                    'emails'          => [$config['sender_email']],
                    'verified_domain' => '',
                    'lookup_failed'   => true,
                ];
            }
        };

        $senders = $withSettings(function () use ($handler, $settings, $sesKey) {
            return $handler->getValidSenders($settings['connections'][$sesKey]['provider_settings']);
        });

        sort($senders);
        FsmtpTest::assertSame(['Contact@ses-suite.example', 'primary@ses-suite.example'], $senders, 'senders kept for the next save');
    });

    FsmtpTest::case('SES reads every page of its verified identities', function () {
        // Answers from canned pages and, like the real request, forgets its parameters after each call.
        $request = new class extends SimpleEmailServiceRequest {
            public $sent = [];

            private $params = [];

            public function setParameter($key, $value, $replace = true)
            {
                $this->params[$key] = $value;

                return $this;
            }

            public function getResponse()
            {
                $this->sent[] = $this->params;
                $page = count($this->sent);
                $this->params = [];

                $pages = [
                    1 => ['<member>first@ses-suite.example</member>', '<NextToken>page-2</NextToken>'],
                    2 => ['<member>ses-suite.example</member><member>last@ses-suite.example</member>', ''],
                ];

                if (empty($this->sent[$page - 1]['Action']) || !isset($pages[$page])) {
                    return (object)['code' => 400, 'body' => '', 'error' => ['code' => 400, 'message' => 'missing Action']];
                }

                return (object)[
                    'code'  => 200,
                    'error' => false,
                    'body'  => simplexml_load_string(
                        '<ListIdentitiesResponse><ListIdentitiesResult><Identities>' . $pages[$page][0] . '</Identities>'
                        . $pages[$page][1] . '</ListIdentitiesResult><ResponseMetadata><RequestId>r' . $page
                        . '</RequestId></ResponseMetadata></ListIdentitiesResponse>'
                    ),
                ];
            }
        };

        $ses = new SimpleEmailService('suite-access', 'suite-secret', SimpleEmailService::AWS_US_EAST_1, false);
        $ses->setRequestHandler($request);
        $result = $ses->listVerifiedEmailAddresses();

        FsmtpTest::assert(!is_wp_error($result), 'the identity lookup failed: ' . (is_wp_error($result) ? $result->get_error_message() : ''));
        FsmtpTest::assertSame(['first@ses-suite.example', 'last@ses-suite.example'], $result['Addresses'], 'addresses from both pages');
        FsmtpTest::assertSame(['ses-suite.example'], $result['domains'], 'a domain listed on the second page');
        FsmtpTest::assertSame('page-2', $request->sent[1]['NextToken'] ?? null, 'the second request asks for the next page');
    });
    FsmtpTest::case('SES stops paging when the endpoint repeats its page token', function () {
        // Every page hands back the same token; without a guard this loops forever.
        $request = new class extends SimpleEmailServiceRequest {
            public $calls = 0;

            public function setParameter($key, $value, $replace = true)
            {
                return $this;
            }

            public function getResponse()
            {
                $this->calls++;

                if ($this->calls > 60) {
                    throw new \RuntimeException('paging did not stop');
                }

                return (object)[
                    'code'  => 200,
                    'error' => false,
                    'body'  => simplexml_load_string(
                        '<ListIdentitiesResponse><ListIdentitiesResult><Identities><member>loop@ses-suite.example</member></Identities>'
                        . '<NextToken>same-token</NextToken></ListIdentitiesResult><ResponseMetadata><RequestId>r</RequestId></ResponseMetadata></ListIdentitiesResponse>'
                    ),
                ];
            }
        };

        $ses = new SimpleEmailService('suite-access', 'suite-secret', SimpleEmailService::AWS_US_EAST_1, false);
        $ses->setRequestHandler($request);
        $result = $ses->listVerifiedEmailAddresses();

        FsmtpTest::assert(!is_wp_error($result), 'repeated token returned an error');
        FsmtpTest::assertSame(2, $request->calls, 'stops on the second answer that repeats the token');
    });
};
