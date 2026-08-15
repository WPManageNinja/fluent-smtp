<?php

use FluentMail\App\Http\Controllers\SettingsController;
use FluentMail\App\Services\ConnectionHealth;
use FluentMail\App\Services\Mailer\Providers\Simulator\Handler as SimulatorHandler;
use FluentMail\App\Services\Reporting;
use FluentMail\Includes\Support\ValidationException;

return function () {
    /** Invoke a non-public deterministic helper without copying its logic. */
    $invoke = function ($target, $method, array $arguments = []) {
        $reflection = new ReflectionMethod($target, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs(is_object($target) ? $target : null, $arguments);
    };

    $withoutConstructor = function ($class) {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    };

    FsmtpTest::case('reporting frequency changes at the daily weekly and monthly boundaries', function () use ($invoke) {
        $reporting = new Reporting();
        $from = new DateTime('2026-01-01 00:00:00', new DateTimeZone('UTC'));
        $frequencyFor = function ($days) use ($invoke, $reporting, $from) {
            $to = clone $from;
            $to->modify('+' . $days . ' days');
            return $invoke($reporting, 'getFrequency', [$from, $to]);
        };

        FsmtpTest::assertSame('P1D', $frequencyFor(62), '62-day reporting frequency');
        FsmtpTest::assertSame('P1W', $frequencyFor(63), '63-day reporting frequency');
        FsmtpTest::assertSame('P1W', $frequencyFor(181), '181-day reporting frequency');
        FsmtpTest::assertSame('P1M', $frequencyFor(182), '182-day reporting frequency');
    });

    FsmtpTest::case('reporting basic formatter normalizes strings and date objects to ISO dates', function () use ($invoke) {
        $reporting = new Reporting();

        FsmtpTest::assertSame(
            '2026-08-04',
            $invoke($reporting, 'basicFormatter', ['2026-08-04 19:45:00']),
            'basic formatter string input'
        );
        FsmtpTest::assertSame(
            '2026-08-04',
            $invoke($reporting, 'basicFormatter', [new DateTime('2026-08-04 01:15:00', new DateTimeZone('UTC'))]),
            'basic formatter DateTime input'
        );
    });

    FsmtpTest::case('reporting month formatter produces a stable abbreviated month and year', function () use ($invoke) {
        $reporting = new Reporting();

        FsmtpTest::assertSame(
            'Aug 2026',
            $invoke($reporting, 'monYearFormatter', ['2026-08-04']),
            'month-year formatter string input'
        );
        FsmtpTest::assertSame(
            'Jan 2027',
            $invoke($reporting, 'monYearFormatter', [new DateTime('2027-01-31', new DateTimeZone('UTC'))]),
            'month-year formatter DateTime input'
        );
    });

    FsmtpTest::case('test-send duration uses milliseconds below one second and seconds at the boundary', function () use (
        $invoke,
        $withoutConstructor
    ) {
        $controller = $withoutConstructor(SettingsController::class);

        FsmtpTest::assertSame(
            sprintf(__('Delivered in %s milliseconds', 'fluent-smtp'), number_format_i18n(125)),
            $invoke($controller, 'formatDuration', [0.125]),
            'sub-second duration'
        );
        FsmtpTest::assertSame(
            sprintf(__('Delivered in %s seconds', 'fluent-smtp'), number_format_i18n(1, 2)),
            $invoke($controller, 'formatDuration', [1.0]),
            'one-second duration'
        );
    });

    FsmtpTest::case('connection health preserves the message from an ordinary exception', function () use ($invoke) {
        $message = 'provider unavailable ' . FsmtpTest::uniq();

        FsmtpTest::assertSame(
            $message,
            $invoke(new ConnectionHealth(), 'flattenMessage', [new Exception($message)]),
            'ordinary health exception message'
        );
    });

    FsmtpTest::case('attachment names prefer the caller supplied name and stay a bare file name', function () use ($invoke, $withoutConstructor) {
        // getAttachmentName() lives on BaseHandler; every provider reads it, so
        // any concrete handler proves the shared behavior.
        $handler = $withoutConstructor(SimulatorHandler::class);

        $nameFor = function ($path, $name) use ($invoke, $handler) {
            // The shape PHPMailer::getAttachments() returns.
            return $invoke($handler, 'getAttachmentName', [[
                0 => $path,
                1 => basename($path),
                2 => $name,
                3 => 'base64',
                4 => 'application/pdf',
                5 => false,
                6 => 'attachment',
                7 => $name
            ]]);
        };

        FsmtpTest::assertSame(
            'January Invoice.pdf',
            $nameFor('/var/uploads/9f2c1ab7e4.pdf', 'January Invoice.pdf'),
            'custom wp_mail() attachment name'
        );
        FsmtpTest::assertSame(
            '9f2c1ab7e4.pdf',
            $nameFor('/var/uploads/9f2c1ab7e4.pdf', ''),
            'fallback to the stored file name'
        );
        FsmtpTest::assertSame(
            'passwd',
            $nameFor('/var/uploads/9f2c1ab7e4.pdf', '../../../etc/passwd'),
            'directory part stripped from the supplied name'
        );
        FsmtpTest::assertSame(
            'invoice.pdf',
            $nameFor('/var/uploads/9f2c1ab7e4.pdf', "in\r\nvoice.pdf"),
            'header break stripped from the supplied name'
        );
        FsmtpTest::assertSame(
            'invoice.pdf',
            $nameFor('/var/uploads/9f2c1ab7e4.pdf', 'in"voice.pdf'),
            'quote stripped from the supplied name'
        );
    });

    FsmtpTest::case('connection health flattens every validation error in field order', function () use ($invoke) {
        $exception = new ValidationException('Unprocessable Entity', 422, null, [
            'sender_email' => [
                'Sender is required.',
                ['Sender must be valid.', 'Use a mailbox address.'],
            ],
            'api_key' => 'API key is missing.',
        ]);

        FsmtpTest::assertSame(
            'Sender is required. Sender must be valid. Use a mailbox address. API key is missing.',
            $invoke(new ConnectionHealth(), 'flattenMessage', [$exception]),
            'flattened health validation message'
        );
    });
};
