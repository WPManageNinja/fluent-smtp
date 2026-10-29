<?php

use FluentMail\App\Services\Mailer\Providers\AmazonSes\SimpleEmailService;
use FluentMail\App\Services\Mailer\Providers\AmazonSes\SimpleEmailServiceRequest;
use FluentMail\Includes\Support\ValidationException;

return function () {
    $config = require FLUENTMAIL_PLUGIN_PATH . 'app/Services/Mailer/Providers/config.php';
    foreach (array_keys($config['providers']['ses']['regions']) as $region) {
        FsmtpTest::case('SES configured region ' . $region, function () use ($region) {
            $suffix = strpos($region, 'eusc-') === 0 ? 'amazonaws.eu' : (strpos($region, 'cn-') === 0 ? 'amazonaws.com.cn' : 'amazonaws.com');
            FsmtpTest::assertSame('email.' . $region . '.' . $suffix, SimpleEmailService::regionToHost($region), 'configured endpoint preserved');
        });
    }

    foreach (['unsupported-region', '', null, false, 1, [], new stdClass()] as $index => $region) {
        FsmtpTest::case('SES unsupported region representation ' . $index, function () use ($region) {
            FsmtpTest::assertSame('', SimpleEmailService::regionToHost($region), 'unsupported region has no endpoint');
        });
        FsmtpTest::case('SES validates region representation ' . $index, function () use ($region) {
            $errors = [];
            try {
                fluentMail('ses')->validateProviderInformation([
                    'key_store' => 'db', 'access_key' => 'test', 'secret_key' => 'test', 'region' => $region
                ]);
            } catch (ValidationException $e) {
                $errors = $e->errors();
            }
            FsmtpTest::assertSame(true, isset($errors['region']), 'field validation error returned');
        });
    }

    FsmtpTest::case('SES request rejects unsupported endpoints without allocating transport', function () {
        $ses = new SimpleEmailService('test', 'test', 'email.unsupported-region.amazonaws.com', false);
        $request = new class extends SimpleEmailServiceRequest {
            protected function getCurlHandler() {
                throw new RuntimeException('Unsupported endpoint reached transport allocation');
            }
        };
        $ses->setRequestHandler($request);
        foreach (['listVerifiedEmailAddresses', 'getSendQuota', 'sendRawEmail'] as $method) {
            $result = $method === 'sendRawEmail' ? $ses->$method('test') : $ses->$method();
            FsmtpTest::assertSame(true, is_wp_error($result), 'standard error for ' . $method);
            FsmtpTest::assertSame([], $request->getParametersEncoded(), 'rejected request parameters cleared');
        }
    });

    FsmtpTest::case('SES reports a non-success HTTP status without a PHP warning', function () {
        $ses = new SimpleEmailService();
        FsmtpTest::assertSame(
            'SimpleEmailService::getSendQuota(): 307 Unexpected HTTP status',
            $ses->getErrorMessage('getSendQuota', ['code' => 307, 'message' => 'Unexpected HTTP status']),
            'plain HTTP status error remains readable'
        );
    });

    FsmtpTest::case('SES checks region before reusing a sender client', function () {
        $connection = [
            'sender_email' => 'ses-region-test@example.test', 'region' => 'us-east-1',
            'access_key' => 'test', 'secret_key' => 'test'
        ];
        $valid = fluentMailSesConnection($connection);
        FsmtpTest::assertSame($valid, fluentMailSesConnection($connection), 'valid connection reused');
        $connection['region'] = 'unsupported-region';
        $invalid = fluentMailSesConnection($connection);
        FsmtpTest::assertSame('', $invalid->getHost(), 'invalid input does not reuse valid endpoint');
        $connection['region'] = 'us-east-1';
        FsmtpTest::assertSame($valid, fluentMailSesConnection($connection), 'invalid input does not replace valid cache');
    });
};
