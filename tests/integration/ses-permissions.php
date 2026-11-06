<?php

use FluentMail\App\Services\Mailer\Providers\AmazonSes\Handler as SesHandler;
use FluentMail\App\Services\Mailer\Providers\AmazonSes\SimpleEmailService;
use FluentMail\App\Services\Mailer\Providers\AmazonSes\SimpleEmailServiceRequest;

return function () {
    $connection = function ($accessKey, $senderEmail) {
        return [
            'key_store'        => 'db',
            'access_key'       => $accessKey,
            'secret_key'       => 'suite-secret-' . $accessKey,
            'region'           => 'us-east-1',
            'sender_email'     => $senderEmail,
            'sender_name'      => 'Suite',
            'provider'         => 'ses',
            'force_from_name'  => 'no',
            'force_from_email' => 'yes',
        ];
    };

    $client = function ($lookupResult) {
        return new class($lookupResult) extends SimpleEmailService {
            public $calls = 0;

            private $lookupResult;

            public function __construct($lookupResult)
            {
                $this->lookupResult = $lookupResult;
            }

            public function listVerifiedEmailAddresses()
            {
                $this->calls++;

                return $this->lookupResult;
            }
        };
    };

    $handler = function (array $clients) {
        return new class($clients) extends SesHandler {
            private $clients;

            public function __construct(array $clients)
            {
                $this->clients = $clients;
            }

            protected function makeSesClient($connection, $triggerError)
            {
                return $this->clients[$connection['access_key']];
            }
        };
    };

    $accessDenied = function () {
        $request = new class extends SimpleEmailServiceRequest {
            public function setParameter($key, $value, $replace = true)
            {
                return $this;
            }

            public function getResponse()
            {
                return (object) [
                    'body'  => '',
                    'code'  => 403,
                    'error' => [
                        'Error' => [
                            'Type'    => 'Sender',
                            'Code'    => 'AccessDenied',
                            'Message' => 'Not authorized to perform ses:ListIdentities',
                        ],
                        'RequestId' => 'suite-request',
                    ],
                ];
            }
        };

        $ses = new SimpleEmailService('suite-access', 'suite-secret', SimpleEmailService::AWS_US_EAST_1, false);
        $ses->setRequestHandler($request);

        return $ses->listVerifiedEmailAddresses();
    };

    FsmtpTest::case('SES connection validation runs the identity lookup error path', function () {
        $handler = new SesHandler();
        $rejected = false;

        try {
            $handler->checkConnection([
                'key_store'  => 'db',
                'access_key' => 'suite-access',
                'secret_key' => 'suite-secret',
                'region'     => 'invalid-suite-region',
            ]);
        } catch (\FluentMail\Includes\Support\ValidationException $exception) {
            $rejected = true;
        }

        FsmtpTest::assert($rejected, 'the live SES checkConnection path rejects a non-permission lookup error');
    });

    FsmtpTest::case('SES accepts denied optional identity lookup without retrying during sender discovery', function () use ($connection, $client, $handler, $accessDenied) {
        $lookup = $accessDenied();
        FsmtpTest::assert(is_wp_error($lookup), 'the fixture must reproduce the denied ListIdentities response');

        $config = $connection('access-a', 'sender-a@example.test');
        $ses = $client($lookup);
        $provider = $handler(['access-a' => $ses]);

        FsmtpTest::assert($provider->checkConnection($config), 'access-denied identity lookup is optional');

        $senders = $provider->getValidSenders($config);
        FsmtpTest::assert(in_array('sender-a@example.test', $senders, true), 'configured sender is retained');
        FsmtpTest::assertSame(1, $ses->calls, 'sender discovery does not retry the denied lookup');
    });

    FsmtpTest::case('SES accepts a successful identity lookup during connection validation', function () use ($connection, $client, $handler) {
        $config = $connection('access-success', 'sender@example.test');
        $ses = $client([
            'Addresses' => ['sender@example.test'],
            'domains'   => ['example.test'],
            'RequestId' => 'suite-request',
        ]);
        $provider = $handler(['access-success' => $ses]);

        FsmtpTest::assert($provider->checkConnection($config), 'successful identity lookup');
        FsmtpTest::assertSame(1, $ses->calls, 'one validation lookup');
    });

    FsmtpTest::case('SES still rejects non-permission identity lookup errors', function () use ($connection, $client, $handler) {
        $config = $connection('access-invalid', 'sender@example.test');
        $ses = $client(new WP_Error(
            403,
            'SimpleEmailService::ListIdentities(): Sender - InvalidClientTokenId: invalid token',
            [
                'Error' => [
                    'Type'    => 'Sender',
                    'Code'    => 'InvalidClientTokenId',
                    'Message' => 'The security token included in the request is invalid.',
                ],
                'RequestId' => 'suite-request',
            ]
        ));
        $provider = $handler(['access-invalid' => $ses]);
        $rejected = false;

        try {
            $provider->checkConnection($config);
        } catch (\FluentMail\Includes\Support\ValidationException $exception) {
            $rejected = true;
        }

        FsmtpTest::assert($rejected, 'invalid credentials remain a blocking validation error');
        FsmtpTest::assertSame(1, $ses->calls, 'one rejected validation lookup');
    });

    FsmtpTest::case('SES denied lookup state does not leak to another connection', function () use ($connection, $client, $handler, $accessDenied) {
        $configA = $connection('access-a', 'sender-a@example.test');
        $configB = $connection('access-b', 'sender-b@example.test');
        $sesA = $client($accessDenied());
        $sesB = $client([
            'Addresses' => ['verified-b@example.test'],
            'domains'   => ['example.test'],
            'RequestId' => 'suite-request-b',
        ]);
        $provider = $handler([
            'access-a' => $sesA,
            'access-b' => $sesB,
        ]);

        FsmtpTest::assert($provider->checkConnection($configA), 'connection A accepts denied optional lookup');

        $identitiesB = $provider->getValidSendingIdentities($configB);
        FsmtpTest::assertSame(['verified-b@example.test'], $identitiesB['emails'], 'connection B performs its own lookup');
        FsmtpTest::assertSame(1, $sesB->calls, 'connection B lookup count');

        $identitiesA = $provider->getValidSendingIdentities($configA);
        FsmtpTest::assert(!empty($identitiesA['lookup_failed']), 'connection A later uses the normal failed-lookup fallback');
        FsmtpTest::assertSame(2, $sesA->calls, 'connection A denial state was consumed when connection B intervened');
    });

    FsmtpTest::case('SES repository guidance distinguishes required and optional IAM actions', function () {
        $readme = file_get_contents(dirname(__DIR__, 2) . '/readme.txt');

        FsmtpTest::assert(strpos($readme, '`ses:SendRawEmail` is required') !== false, 'required SendRawEmail guidance');
        FsmtpTest::assert(strpos($readme, '`ses:ListIdentities` and `ses:GetSendQuota` are optional') !== false, 'optional read-action guidance');
    });
};
