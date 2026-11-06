<?php

use FluentMail\App\Services\Mailer\Providers\AmazonSes\Handler as SesHandler;
use FluentMail\App\Services\Mailer\Providers\AmazonSes\SimpleEmailService;
use FluentMail\App\Services\Mailer\Providers\AmazonSes\SimpleEmailServiceRequest;

return function () {
    FsmtpTest::case('SES accepts a denied optional identity lookup during connection validation', function () {
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
        $lookup = $ses->listVerifiedEmailAddresses();

        FsmtpTest::assert(is_wp_error($lookup), 'the fixture must reproduce the denied ListIdentities response');

        $handler = new class extends SesHandler {
            public function validateIdentityLookupForTest($lookup)
            {
                if (!method_exists($this, 'validateIdentityLookupResult')) {
                    throw new RuntimeException('SES setup rejected AccessDenied from optional ListIdentities lookup');
                }

                return $this->validateIdentityLookupResult($lookup);
            }
        };

        FsmtpTest::assert($handler->validateIdentityLookupForTest($lookup), 'access-denied identity lookup is optional');
    });

    FsmtpTest::case('SES accepts a successful identity lookup during connection validation', function () {
        $handler = new class extends SesHandler {
            public function validateIdentityLookupForTest($lookup)
            {
                return $this->validateIdentityLookupResult($lookup);
            }
        };

        FsmtpTest::assert($handler->validateIdentityLookupForTest([
            'Addresses' => ['sender@example.test'],
            'domains'   => ['example.test'],
            'RequestId' => 'suite-request',
        ]), 'successful identity lookup');
    });

    FsmtpTest::case('SES still rejects non-permission identity lookup errors', function () {
        $handler = new class extends SesHandler {
            public function validateIdentityLookupForTest($lookup)
            {
                return $this->validateIdentityLookupResult($lookup);
            }
        };

        $rejected = false;

        try {
            $handler->validateIdentityLookupForTest(new WP_Error(
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
        } catch (\FluentMail\Includes\Support\ValidationException $exception) {
            $rejected = true;
        }

        FsmtpTest::assert($rejected, 'invalid credentials remain a blocking validation error');
    });

    FsmtpTest::case('SES repository guidance distinguishes required and optional IAM actions', function () {
        $readme = file_get_contents(dirname(__DIR__, 2) . '/readme.txt');

        FsmtpTest::assert(strpos($readme, '`ses:SendRawEmail` is required') !== false, 'required SendRawEmail guidance');
        FsmtpTest::assert(strpos($readme, '`ses:ListIdentities` and `ses:GetSendQuota` are optional') !== false, 'optional read-action guidance');
    });
};
