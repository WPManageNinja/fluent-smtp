<?php

use FluentMail\App\Services\Converter;
use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\Factory;
use FluentMail\App\Services\SecretMasker;
use FluentMail\Includes\Support\Arr;

return function () {
    FsmtpTest::case('the provider factory builds only registered mail handlers', function () {
        $factory = fluentMail(Factory::class);

        FsmtpTest::assert($factory->make('smtp') instanceof BaseHandler, 'smtp did not resolve to a mail handler');

        $rejected = [
            'WP_User',
            'stdClass',
            'view',
            'manager',
            'FluentMail\App\Services\Mailer\Providers\Smtp\Handler',
            '',
        ];
        foreach ($rejected as $abstract) {
            $threw = false;
            try {
                $factory->make($abstract);
            } catch (\InvalidArgumentException $e) {
                $threw = true;
            }
            FsmtpTest::assert($threw, 'factory built a non-provider: ' . var_export($abstract, true));
        }
    });

    FsmtpTest::case('an import suggestion is offered per provider and its credential masks like any other', function () {
        $wpMailSmtp = function () {
            return [
                'mail'     => ['mailer' => 'sendgrid', 'from_email' => 'suite@example.test', 'from_name' => 'Suite'],
                'sendgrid' => ['api_key' => 'suite-sendgrid-key'],
            ];
        };
        add_filter('pre_option_wp_mail_smtp', $wpMailSmtp, PHP_INT_MAX);

        try {
            $converter = new Converter();

            $sendgrid = $converter->suggestedSettingsFor('sendgrid');
            FsmtpTest::assertSame('suite-sendgrid-key', Arr::get($sendgrid, 'api_key'), 'suggested api key for its own provider');
            FsmtpTest::assertSame([], $converter->suggestedSettingsFor('smtp'), 'suggestion offered for a different provider');
            FsmtpTest::assertSame([], $converter->suggestedSettingsFor(null), 'suggestion offered for no provider');

            $masked = SecretMasker::maskFields($sendgrid);
            FsmtpTest::assertSame(SecretMasker::MASK, Arr::get($masked, 'api_key'), 'suggested api key on its way to the page');

            $resolved = SecretMasker::resolve(['provider' => 'sendgrid', 'api_key' => SecretMasker::MASK], $sendgrid);
            FsmtpTest::assertSame('suite-sendgrid-key', Arr::get($resolved, 'api_key'), 'mask resolved back from the suggestion');
        } finally {
            remove_filter('pre_option_wp_mail_smtp', $wpMailSmtp, PHP_INT_MAX);
        }
    });
};
