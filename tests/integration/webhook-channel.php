<?php

use FluentMail\App\Services\NotificationHelper;
use FluentMail\App\Services\Mailer\Providers\Simulator\Handler as SimulatorHandler;
use FluentMail\App\Services\SecretMasker;

/**
 * The generic webhook channel.
 *
 * Every case here passes the channel settings in as an argument rather than
 * storing them, so none of it writes _fluent_smtp_notify_settings. That is a
 * property of the channel's design, not a shortcut: validateWebhookSettings(),
 * renderWebhookTemplate() and sendWebhookEvent() all take what they need.
 *
 * The two dispatch branches in SchedulerHandler, a few lines each, are
 * deliberately not driven from here. Manager::getActiveChannels() caches into a
 * function-scope static the first time an active channel exists, which no test
 * can reset, and maybeSendNotification() is also gated on a 60 second throttle
 * that it writes to a protected option.
 */
return function () {
    $fixtureUrl = 'https://example.test/hook';
    $settings = [
        'webhook_url'   => $fixtureUrl,
        'body_template' => NotificationHelper::defaultWebhookTemplate(),
    ];

    /** Decode a rendered body, failing with the parser's reason rather than null. */
    // The default template's fields, as a receiver gets them.
    $defaultFields = ['event', 'site_url', 'site_title', 'provider', 'to', 'sender_email', 'subject', 'error', 'logs_url', 'sent_at'];

    $decode = function ($body, $label) {
        $decoded = json_decode($body, true);
        FsmtpTest::assert(
            JSON_ERROR_NONE === json_last_error(),
            $label . ' did not parse: ' . json_last_error_msg()
        );
        return $decoded;
    };

    FsmtpTest::case('webhook template escapes a quote, a newline and a backslash', function () use ($decode) {
        $error = "SMTP connect() failed\nC:\\path \"quoted\"";

        $body = NotificationHelper::renderWebhookTemplate(
            NotificationHelper::defaultWebhookTemplate(),
            NotificationHelper::webhookPlaceholders('email_failed', ['error' => $error])
        );

        $decoded = $decode($body, 'hostile error body');
        FsmtpTest::assertSame($error, $decoded['error'], 'error value after a JSON round trip');
    });

    FsmtpTest::case('webhook template renders an unknown placeholder as an empty string', function () use ($decode) {
        $body = NotificationHelper::renderWebhookTemplate(
            '{"known": "{{subject}}", "unknown": "{{no_such_field}}"}',
            NotificationHelper::webhookPlaceholders('test', ['subject' => 'a subject'])
        );

        $decoded = $decode($body, 'unknown placeholder body');
        FsmtpTest::assertSame('a subject', $decoded['known'], 'known placeholder');
        FsmtpTest::assertSame('', $decoded['unknown'], 'unknown placeholder');
    });

    FsmtpTest::case('webhook template renders the chat-app shape chat apps accept', function () use ($decode) {
        $body = NotificationHelper::renderWebhookTemplate(
            '{"text": "{{message}}"}',
            NotificationHelper::webhookPlaceholders('email_failed', ['message' => "**Failed**\nsecond line"])
        );

        $decoded = $decode($body, 'chat-app body');
        FsmtpTest::assertSame(['text'], array_keys($decoded), 'chat-app body keys');
        FsmtpTest::assertSame("**Failed**\nsecond line", $decoded['text'], 'chat-app text value');
    });

    FsmtpTest::case('webhook settings substitute the default template when none is given', function () use ($fixtureUrl) {
        $validated = NotificationHelper::validateWebhookSettings([
            'label'         => 'fixture',
            'webhook_url'   => $fixtureUrl,
            'body_template' => '',
        ]);

        FsmtpTest::assert(!is_wp_error($validated), 'a blank template should be accepted');
        FsmtpTest::assertSame(
            NotificationHelper::defaultWebhookTemplate(),
            $validated['body_template'],
            'template stored for a blank submission'
        );
        FsmtpTest::assertSame('yes', $validated['status'], 'status after a register');
    });

    FsmtpTest::case('webhook settings do not store the host', function () use ($fixtureUrl) {
        // For some receivers (a per-endpoint subdomain) the host is the credential.
        $validated = NotificationHelper::validateWebhookSettings([
            'label'         => 'fixture',
            'webhook_url'   => $fixtureUrl,
            'body_template' => '',
        ]);

        FsmtpTest::assert(!is_wp_error($validated), 'valid settings should be accepted');
        FsmtpTest::assertSame(['status', 'label', 'webhook_url', 'body_template'], array_keys($validated), 'the stored keys');
    });

    FsmtpTest::case('webhook settings refuse a template that does not render to JSON', function () use ($fixtureUrl) {
        $validated = NotificationHelper::validateWebhookSettings([
            'label'         => 'fixture',
            'webhook_url'   => $fixtureUrl,
            'body_template' => '{"error": {{error}}}',
        ]);

        FsmtpTest::assert(is_wp_error($validated), 'an unquoted placeholder should be refused');
        FsmtpTest::assertSame(
            'webhook_template_invalid',
            $validated->get_error_code(),
            'error code for a template that does not parse'
        );
    });

    FsmtpTest::case('webhook settings refuse a placeholder that does not exist', function () use ($fixtureUrl) {
        $validated = NotificationHelper::validateWebhookSettings([
            'label'         => 'fixture',
            'webhook_url'   => $fixtureUrl,
            'body_template' => '{"subject": "{{subjct}}", "to": "{{to}}"}',
        ]);

        FsmtpTest::assert(is_wp_error($validated), 'a misspelled placeholder should be refused');
        FsmtpTest::assertSame(
            'webhook_placeholder_unknown',
            $validated->get_error_code(),
            'error code for an unknown placeholder'
        );
        FsmtpTest::assert(
            false !== strpos($validated->get_error_message(), '{{subjct}}'),
            'the error names the unknown placeholder'
        );
    });

    FsmtpTest::case('webhook settings keep a template backslash when called directly', function () use ($fixtureUrl) {
        // A JSON "\\d" is a literal backslash then d; unslashed once more it is "\d", which is invalid.
        $template = '{"path": "a\\\\d", "to": "{{to}}"}';
        $validated = NotificationHelper::validateWebhookSettings([
            'label'         => 'fixture',
            'webhook_url'   => $fixtureUrl,
            'body_template' => $template,
        ]);

        FsmtpTest::assert(!is_wp_error($validated), 'a template with an escaped backslash should be accepted');
        FsmtpTest::assertSame($template, $validated['body_template'], 'template stored byte for byte');
    });

    // Through the real route, with input slashed as WordPress slashes $_POST. The write is intercepted, not made.
    FsmtpTest::case('webhook register unslashes the label and template once', function () use ($fixtureUrl) {
        $captured = null;
        $writeFuse = function ($newValue, $oldValue) use (&$captured) {
            $captured = $newValue;
            return $oldValue;
        };
        add_filter('pre_update_option__fluent_smtp_notify_settings', $writeFuse, PHP_INT_MAX, 2);

        $template = '{"path": "a\\\\d", "to": "{{to}}"}';

        try {
            $result = FsmtpTest::ajax('POST', 'settings/webhook/register', wp_slash([
                'settings' => [
                    'label'         => "Ops team's hook",
                    'webhook_url'   => $fixtureUrl,
                    'body_template' => $template,
                ],
            ]));
        } finally {
            remove_filter('pre_update_option__fluent_smtp_notify_settings', $writeFuse, PHP_INT_MAX);
        }

        FsmtpTest::assertSame(200, $result['status'], 'register response status');
        FsmtpTest::assert(is_array($captured), 'register should have attempted the write');
        FsmtpTest::assertSame("Ops team's hook", $captured['webhook']['label'], 'label without a stray backslash');
        FsmtpTest::assertSame($template, $captured['webhook']['body_template'], 'template unslashed exactly once');
    });

    // The save-time check must accept exactly what the renderer substitutes (any case, inner spaces).
    FsmtpTest::case('webhook settings accept a placeholder in any case and with inner spaces', function () use ($fixtureUrl, $decode) {
        $validated = NotificationHelper::validateWebhookSettings([
            'label'         => 'fixture',
            'webhook_url'   => $fixtureUrl,
            'body_template' => '{"subject": "{{ Subject }}", "event": "{{EVENT}}"}',
        ]);

        FsmtpTest::assert(!is_wp_error($validated), 'a known placeholder in another case should be accepted');

        // Accepted is not enough: a placeholder left unmatched would still be valid JSON, sent literally.
        $rendered = $decode(NotificationHelper::renderWebhookTemplate(
            $validated['body_template'],
            NotificationHelper::webhookPlaceholders('test', ['subject' => 'a subject'])
        ), 'any-case template');
        FsmtpTest::assertSame(['subject' => 'a subject', 'event' => 'test'], $rendered, 'values substituted');
    });

    FsmtpTest::case('webhook settings refuse a URL that is not http or https', function () {
        $validated = NotificationHelper::validateWebhookSettings([
            'label'       => 'fixture',
            'webhook_url' => 'ftp://example.test/hook',
        ]);

        FsmtpTest::assert(is_wp_error($validated), 'an ftp URL should be refused');
        FsmtpTest::assertSame('webhook_url_scheme', $validated->get_error_code(), 'error code for a bad scheme');
    });

    FsmtpTest::case('webhook settings refuse a malformed http URL', function () {
        $validated = NotificationHelper::validateWebhookSettings([
            'label'       => 'fixture',
            'webhook_url' => 'https://exa mple.test/hook',
        ]);

        FsmtpTest::assert(is_wp_error($validated), 'a URL with a space in the host should be refused');
        FsmtpTest::assertSame('webhook_url_invalid', $validated->get_error_code(), 'error code for a malformed URL');
    });

    FsmtpTest::case('webhook settings refuse a blank URL when nothing is stored', function () {
        $validated = NotificationHelper::validateWebhookSettings(['label' => 'fixture', 'webhook_url' => '']);

        FsmtpTest::assert(is_wp_error($validated), 'a blank URL with nothing stored should be refused');
        FsmtpTest::assertSame('webhook_url_required', $validated->get_error_code(), 'error code for a missing URL');
    });

    FsmtpTest::case('webhook settings keep the stored URL when the browser sends the mask', function () use ($fixtureUrl) {
        // The admin form posts the URL blank (next case); a client that posts the mask back gets the same.
        $validated = NotificationHelper::validateWebhookSettings(
            ['label' => 'fixture', 'webhook_url' => SecretMasker::MASK],
            ['webhook_url' => $fixtureUrl]
        );

        FsmtpTest::assert(!is_wp_error($validated), 'a masked URL with one stored should be accepted');
        FsmtpTest::assertSame($fixtureUrl, $validated['webhook_url'], 'URL kept when the mask is posted back');
    });

    FsmtpTest::case('webhook settings keep the stored URL when the URL field is left blank', function () use ($fixtureUrl) {
        // The form never shows the saved URL, so a re-save posts it blank.
        $validated = NotificationHelper::validateWebhookSettings(
            ['label' => 'renamed', 'webhook_url' => '', 'body_template' => ''],
            ['webhook_url' => $fixtureUrl]
        );

        FsmtpTest::assert(!is_wp_error($validated), 'a blank URL with one stored should be accepted');
        FsmtpTest::assertSame($fixtureUrl, $validated['webhook_url'], 'URL kept when the field is blank');
    });

    FsmtpTest::case('webhook disconnect clears the channel and resets the template', function () {
        $disconnected = NotificationHelper::disconnectedWebhookSettings();

        FsmtpTest::assertSame('no', $disconnected['status'], 'status after a disconnect');
        FsmtpTest::assertSame('', $disconnected['webhook_url'], 'URL after a disconnect');
        FsmtpTest::assertSame('', $disconnected['label'], 'label after a disconnect');

        /*
         * The template resets rather than persisting, because the row's delete
         * confirmation promises to remove the channel's settings. It resets to the
         * default rather than to an empty string, so setting the channel up again
         * starts where a first-time setup starts.
         */
        FsmtpTest::assertSame(
            NotificationHelper::defaultWebhookTemplate(),
            $disconnected['body_template'],
            'template after a disconnect'
        );
    });

    FsmtpTest::case('webhook context takes the first recipient from a serialized to field', function () {
        // The real Simulator handler, so the case doesn't depend on this site's connections.
        $handler = new SimulatorHandler();
        $handler->setSettings(['provider' => 'simulator', 'sender_email' => 'from@example.test']);

        $context = NotificationHelper::eventContextFromLog($handler, [
            'to'       => serialize([['email' => 'someone@example.test', 'name' => 'Someone']]),
            'subject'  => 'a subject',
            'response' => serialize(['message' => 'a failure reason']),
        ]);

        FsmtpTest::assertSame('someone@example.test', $context['to'], 'recipient from a serialized to field');
        FsmtpTest::assertSame('a failure reason', $context['error'], 'error from a serialized response');
        FsmtpTest::assertSame('simulator', $context['provider'], 'provider slug is carried raw, not uppercased');
        /*
         * Regression. eventContextFromLog() set every placeholder except
         * sender_email, so {{sender_email}} rendered empty on every email_failed
         * alert, while eventContextFromConnection() filled it for
         * connection_unhealthy. The admin test button hides the bug, because it
         * fills the field from webhookSampleContext() instead.
         */
        FsmtpTest::assertSame(
            'from@example.test',
            $context['sender_email'],
            'sender_email comes from the failing connection'
        );
    });

    /*
     * The two cases below guard every field the default template ships, not just
     * the one instance above (the message placeholders have cases of their own). The sender_email bug was a context builder silently not
     * filling a field the default template ships, and nothing stopped the next
     * placeholder regressing the same way. Asserting on array_keys() would not
     * have caught it: the key was always present from eventContextDefaults() and
     * merely empty, so these assert on values.
     *
     * They are split per event because the two events own different fields. A
     * health check has no email, so its empty `to` and `subject` are correct, and
     * a blanket "nothing is empty" rule applied to both would be wrong.
     */
    FsmtpTest::case('a fully populated send failure leaves no template field empty', function () use ($decode, $defaultFields) {
        $handler = new SimulatorHandler();
        $handler->setSettings(['provider' => 'simulator', 'sender_email' => 'from@example.test']);

        $body = NotificationHelper::renderWebhookTemplate(
            NotificationHelper::defaultWebhookTemplate(),
            NotificationHelper::webhookPlaceholders(
                'email_failed',
                NotificationHelper::eventContextFromLog($handler, [
                    'to'       => serialize([['email' => 'someone@example.test', 'name' => 'Someone']]),
                    'subject'  => 'a subject',
                    'response' => serialize(['message' => 'a failure reason']),
                ])
            )
        );

        $decoded = $decode($body, 'fully populated failure');
        // A field dropped from the template would otherwise just vanish from this loop.
        FsmtpTest::assertSame($defaultFields, array_keys($decoded), 'the default template\'s fields');

        foreach ($decoded as $field => $value) {
            FsmtpTest::assert(
                '' !== $value,
                $field . ' rendered empty although the log row carried everything a real failure carries'
            );
        }
    });

    FsmtpTest::case('an unhealthy connection fills every field that event owns', function () {
        $context = NotificationHelper::eventContextFromConnection([
            'provider'     => 'smtp',
            'sender_email' => 'from@example.test',
            'message'      => 'SMTP host is required.',
        ], 'a human readable summary');

        FsmtpTest::assertSame('smtp', $context['provider'], 'provider from the connection');
        FsmtpTest::assertSame('from@example.test', $context['sender_email'], 'sender from the connection');
        FsmtpTest::assertSame('SMTP host is required.', $context['error'], 'error from the connection\'s own message');
        FsmtpTest::assertSame('a human readable summary', $context['message'], 'message is the summary sentence');

        // Correctly empty: a health check is not about a particular email.
        FsmtpTest::assertSame('', $context['to'], 'to is empty for a health event');
        FsmtpTest::assertSame('', $context['subject'], 'subject is empty for a health event');
    });

    /*
     * The save-time check is only as good as its sample. A key missing from it
     * renders empty during validation, and a key left to the site's own value is
     * harmless text on most sites, so either would let through a template that
     * breaks on a real value. The method is protected, so it is read through
     * reflection.
     */
    FsmtpTest::case('the save-time sample gives every placeholder a hostile value', function () {
        $defaults = new ReflectionMethod(NotificationHelper::class, 'eventContextDefaults');
        $sample = new ReflectionMethod(NotificationHelper::class, 'webhookSampleContext');
        $defaults->setAccessible(true);
        $sample->setAccessible(true);

        $expectedKeys = array_keys($defaults->invoke(null));
        $values = $sample->invoke(null);
        $actualKeys = array_keys($values);
        sort($expectedKeys);
        sort($actualKeys);

        FsmtpTest::assertSame($expectedKeys, $actualKeys, 'sample keys match the event context keys');

        foreach ($values as $key => $value) {
            FsmtpTest::assert(false !== strpos((string) $value, '"'), $key . ' sample must carry a double quote');
        }
    });

    FsmtpTest::case('webhook send posts the rendered body to the configured URL', function () use ($settings, $fixtureUrl, $decode) {
        FsmtpTest::interceptHttp(function () {
            return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => '{"ok":true}'];
        });

        try {
            $result = NotificationHelper::sendWebhookEvent(
                'email_failed',
                ['provider' => 'smtp', 'to' => 'someone@example.test', 'subject' => 'a subject'],
                $settings,
                true
            );

            $requests = FsmtpTest::httpRequests();
            FsmtpTest::assertSame(1, count($requests), 'requests made');
            FsmtpTest::assertSame($fixtureUrl, $requests[0]['url'], 'request URL');
            FsmtpTest::assertSame(
                'application/json',
                $requests[0]['args']['headers']['Content-Type'],
                'request content type'
            );
            FsmtpTest::assert($requests[0]['args']['sslverify'] === true, 'TLS verification must stay on');

            $decoded = $decode($requests[0]['args']['body'], 'dispatched body');
            FsmtpTest::assertSame('email_failed', $decoded['event'], 'event name on the wire');
            FsmtpTest::assertSame('someone@example.test', $decoded['to'], 'recipient on the wire');
            // ISO 8601 in UTC, now. WordPress runs PHP in UTC, so this cannot tell gmdate() from date().
            FsmtpTest::assert(1 === preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\+00:00$/', $decoded['sent_at']), 'sent_at format: ' . $decoded['sent_at']);
            FsmtpTest::assert(abs(strtotime($decoded['sent_at']) - time()) <= 5, 'sent_at is the send time: ' . $decoded['sent_at']);
            FsmtpTest::assertSame(200, $result['code'], 'status code returned to the caller');
        } finally {
            FsmtpTest::interceptHttp();
        }
    });

    FsmtpTest::case('webhook send reports a non-2xx response as an error', function () use ($settings) {
        FsmtpTest::interceptHttp(function () {
            return ['response' => ['code' => 500, 'message' => 'Server Error'], 'body' => 'nope'];
        });

        try {
            $result = NotificationHelper::sendWebhookEvent('test', [], $settings, true);

            FsmtpTest::assert(is_wp_error($result), 'a 500 must not be reported as success');
            FsmtpTest::assertSame('webhook_api_error', $result->get_error_code(), 'error code for a 500');
        } finally {
            FsmtpTest::interceptHttp();
        }
    });

    /*
     * The HTTP interceptor answers before Requests runs, so this cannot watch a
     * redirect being followed or not. It pins the two things that decide it: the
     * argument both paths hand WP_Http, where a redirection of 0 turns
     * follow_redirects off (wp-includes/class-wp-http.php, WordPress 7.1.2), and
     * that a 3xx the test send receives is reported as a failure rather than
     * passed.
     */
    FsmtpTest::case('webhook send never follows a redirect and reports a 3xx as an error', function () use ($settings) {
        FsmtpTest::interceptHttp(function () {
            return [
                'response' => ['code' => 301, 'message' => 'Moved Permanently'],
                'headers'  => ['location' => 'https://example.test/hook/'],
                'body'     => '',
            ];
        });

        try {
            $result = NotificationHelper::sendWebhookEvent('test', [], $settings, true);
            NotificationHelper::sendWebhookEvent('email_failed', [], $settings);

            FsmtpTest::assert(is_wp_error($result), 'a 301 on the test send must not be reported as success');
            FsmtpTest::assertSame('webhook_api_error', $result->get_error_code(), 'error code for a 301');

            $requests = FsmtpTest::httpRequests();
            FsmtpTest::assertSame(2, count($requests), 'requests made');
            FsmtpTest::assertSame(0, $requests[0]['args']['redirection'], 'redirection on the blocking test send');
            FsmtpTest::assertSame(0, $requests[1]['args']['redirection'], 'redirection on the non-blocking alert');
        } finally {
            FsmtpTest::interceptHttp();
        }
    });

    // Through the real send-test route; settings come from a pre_option filter and the HTTP interceptor answers.
    $sendTestAgainst = function ($code, $body) use ($settings) {
        $stored = function () use ($settings) {
            return [
                'active_channel' => ['webhook'],
                'webhook'        => array_merge($settings, ['status' => 'yes', 'label' => 'fixture']),
            ];
        };
        add_filter('pre_option__fluent_smtp_notify_settings', $stored, PHP_INT_MAX);
        FsmtpTest::interceptHttp(function () use ($code, $body) {
            return ['response' => ['code' => $code, 'message' => ''], 'body' => $body];
        });

        try {
            return FsmtpTest::ajax('POST', 'settings/webhook/send-test');
        } finally {
            FsmtpTest::interceptHttp();
            remove_filter('pre_option__fluent_smtp_notify_settings', $stored, PHP_INT_MAX);
        }
    };

    FsmtpTest::case('webhook test shows the receiver reply when it refuses the body', function () use ($sendTestAgainst) {
        // Adjacent blocks with no whitespace between them, as most error pages send.
        $result = $sendTestAgainst(400, "<html><body><h1>Bad</h1><p>request:\n\n  text is required</p></body></html>");
        $message = FsmtpTest::ajaxMessage($result);

        FsmtpTest::assertSame(422, $result['status'], 'response status for a refused test');
        FsmtpTest::assert(false !== strpos($message, 'HTTP 400'), 'message names the status: ' . $message);
        FsmtpTest::assert(false !== strpos($message, 'Bad request: text is required'), 'message carries the reply, whitespace collapsed: ' . $message);
        FsmtpTest::assert(false === strpos($message, '<h1>'), 'message carries no markup');
    });

    FsmtpTest::case('webhook test cuts a long reply without splitting a character', function () use ($sendTestAgainst) {
        $result = $sendTestAgainst(200, str_repeat('é', 1000));
        $reply = $result['data']['data']['response_body'];

        FsmtpTest::assertSame(200, $result['status'], 'response status for an accepted test');
        // The exact value: JSON encoding would repair a split byte into '?', so length and
        // validity checks alone cannot see a cut in the middle of a character.
        FsmtpTest::assertSame(str_repeat('é', 300) . '...', $reply, 'reply cut to 300 whole characters plus the ellipsis');
        FsmtpTest::assertSame('Test message sent. The endpoint answered HTTP 200: ' . $reply, FsmtpTest::ajaxMessage($result), 'the message quotes the reply');
    });

    FsmtpTest::case('webhook test refused with an empty reply gives the status alone', function () use ($sendTestAgainst) {
        $result = $sendTestAgainst(500, '');

        FsmtpTest::assertSame(422, $result['status'], 'response status for a refused test');
        FsmtpTest::assertSame('The endpoint returned HTTP 500.', FsmtpTest::ajaxMessage($result), 'message without a reply');
    });

    FsmtpTest::case('webhook test says so when the receiver answers with an empty body', function () use ($sendTestAgainst) {
        $message = FsmtpTest::ajaxMessage($sendTestAgainst(204, ''));

        FsmtpTest::assert(false !== strpos($message, 'HTTP 204 with an empty body'), 'message for an empty reply: ' . $message);
    });

    FsmtpTest::case('webhook payload filter can replace the whole body', function () use ($settings) {
        $seen = null;
        $filter = function ($body, $event, $context) use (&$seen) {
            $seen = [$event, $context];
            return '{"replaced":true}';
        };
        add_filter('fluentsmtp_webhook_payload', $filter, 10, 3);
        FsmtpTest::interceptHttp(function () {
            return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => ''];
        });

        try {
            NotificationHelper::sendWebhookEvent('test', ['subject' => 'a subject'], $settings, true);

            $requests = FsmtpTest::httpRequests();
            FsmtpTest::assertSame('{"replaced":true}', $requests[0]['args']['body'], 'filtered body on the wire');
            FsmtpTest::assertSame(['test', ['subject' => 'a subject']], $seen, 'the filter gets the event and its context');
        } finally {
            remove_filter('fluentsmtp_webhook_payload', $filter, 10);
            FsmtpTest::interceptHttp();
        }
    });

    FsmtpTest::case('webhook template keeps a value that has an invalid UTF-8 byte', function () use ($decode) {
        // One stray byte used to make json_encode() fail, and the whole value went out empty.
        $body = NotificationHelper::renderWebhookTemplate('{"error": "{{error}}"}', ['error' => "before \xff after"]);

        FsmtpTest::assertSame("before \u{FFFD} after", $decode($body, 'invalid UTF-8')['error'], 'error with the byte replaced');
    });

    FsmtpTest::case('a failed send message is plain text, one field per line, the error keeping its own lines', function () {
        $handler = new SimulatorHandler();
        $handler->setSettings(['provider' => 'simulator', 'sender_email' => 'from@example.test']);

        $message = NotificationHelper::eventContextFromLog($handler, [
            'to'       => serialize([['email' => 'someone@example.test', 'name' => 'Someone']]),
            'subject'  => 'a subject',
            'response' => serialize(['message' => "a failure reason\nsecond line"]),
        ])['message'];
        $lines = explode("\n", $message);

        foreach (['**', '##', '```', '](' ] as $markup) {
            FsmtpTest::assert(false === strpos($message, $markup), 'no Markdown ' . $markup . ' in: ' . $message);
        }
        FsmtpTest::assertSame(8, count($lines), 'one line per field, plus the error\'s second line');
        FsmtpTest::assertSame(
            ['Email Service: SIMULATOR', 'To Email Address: someone@example.test', 'Email Subject: a subject', 'Error Message: a failure reason', 'second line'],
            array_slice($lines, 2, 5),
            'the fields in order, the error on its own lines'
        );
        FsmtpTest::assert(0 === strpos($lines[7], 'View failed emails: '), 'the log link last: ' . $lines[7]);
    });

    FsmtpTest::case('the admin form lists exactly the placeholders the template accepts', function () use ($fixtureUrl) {
        $result = FsmtpTest::ajax('GET', 'settings/notification-channels');
        FsmtpTest::assertAjaxHealthy($result, 'notification channels');
        $data = isset($result['data']['channels']) ? $result['data'] : $result['data']['data'];
        $listed = $data['channels']['webhook']['placeholders'];

        FsmtpTest::assertSame(array_keys(NotificationHelper::webhookPlaceholders('test')), $listed, 'placeholders sent to the form');

        $template = '{' . implode(', ', array_map(function ($key) {
            return '"' . $key . '": "{{' . $key . '}}"';
        }, $listed)) . '}';
        $validated = NotificationHelper::validateWebhookSettings(['label' => 'fixture', 'webhook_url' => $fixtureUrl, 'body_template' => $template]);
        FsmtpTest::assert(!is_wp_error($validated), 'a template naming every listed placeholder is accepted');
    });

    /* The send-test route against a stored channel and an HTTP responder. */
    $sendTestWith = function (array $stored, callable $responder) {
        $option = function () use ($stored) {
            return $stored;
        };
        add_filter('pre_option__fluent_smtp_notify_settings', $option, PHP_INT_MAX);
        FsmtpTest::interceptHttp($responder);

        try {
            return FsmtpTest::ajax('POST', 'settings/webhook/send-test');
        } finally {
            FsmtpTest::interceptHttp();
            remove_filter('pre_option__fluent_smtp_notify_settings', $option, PHP_INT_MAX);
        }
    };

    FsmtpTest::case('webhook test reports a transport failure without an HTTP status', function () use ($sendTestWith, $settings) {
        $result = $sendTestWith(
            ['active_channel' => ['webhook'], 'webhook' => array_merge($settings, ['status' => 'yes', 'label' => 'fixture'])],
            function () {
                return new \WP_Error('http_request_failed', 'cURL error 6: Could not resolve host: example.test');
            }
        );
        $message = FsmtpTest::ajaxMessage($result);

        FsmtpTest::assertSame(422, $result['status'], 'response status for a transport failure');
        FsmtpTest::assertSame('cURL error 6: Could not resolve host: example.test', $message, 'the transport error, as the admin sees it');
    });

    FsmtpTest::case('webhook test is refused while the channel is not enabled', function () use ($sendTestWith, $settings) {
        $calls = 0;
        $result = $sendTestWith(
            ['active_channel' => [], 'webhook' => array_merge($settings, ['status' => 'no', 'label' => 'fixture'])],
            function () use (&$calls) {
                $calls++;
                return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => ''];
            }
        );

        FsmtpTest::assertSame(422, $result['status'], 'response status while not enabled');
        FsmtpTest::assertSame('Webhook notifications are not enabled.', FsmtpTest::ajaxMessage($result), 'message while not enabled');
        FsmtpTest::assertSame(0, $calls, 'nothing sent while not enabled');
    });

    /* Run a webhook route with a stored channel, capturing what it writes instead of writing it. */
    $routeWrites = function ($route, array $params, array $stored) {
        $captured = null;
        $option = function () use ($stored) {
            return $stored;
        };
        $writeFuse = function ($newValue, $oldValue) use (&$captured) {
            $captured = $newValue;
            return $oldValue;
        };
        add_filter('pre_option__fluent_smtp_notify_settings', $option, PHP_INT_MAX);
        add_filter('pre_update_option__fluent_smtp_notify_settings', $writeFuse, PHP_INT_MAX, 2);
        FsmtpTest::interceptHttp();

        try {
            $result = FsmtpTest::ajax('POST', $route, $params);
            $requests = count(FsmtpTest::httpRequests());
        } finally {
            remove_filter('pre_update_option__fluent_smtp_notify_settings', $writeFuse, PHP_INT_MAX);
            remove_filter('pre_option__fluent_smtp_notify_settings', $option, PHP_INT_MAX);
        }

        return [$result, $captured, $requests];
    };

    FsmtpTest::case('webhook register answers a refused setting with 422 and writes nothing', function () use ($routeWrites, $fixtureUrl) {
        list($result, $captured, $requests) = $routeWrites('settings/webhook/register', [
            'settings' => ['label' => '', 'webhook_url' => $fixtureUrl, 'body_template' => ''],
        ], []);

        FsmtpTest::assertSame(422, $result['status'], 'response status for a missing name');
        FsmtpTest::assertSame('A name for this webhook is required.', FsmtpTest::ajaxMessage($result), 'the validator\'s reason');
        FsmtpTest::assertSame(null, $captured, 'nothing written');
        FsmtpTest::assertSame(0, $requests, 'nothing sent');
    });

    FsmtpTest::case('webhook disconnect route writes the disconnected settings', function () use ($routeWrites, $settings) {
        list($result, $captured, $requests) = $routeWrites('settings/webhook/disconnect', [], [
            'active_channel' => ['webhook'],
            'webhook'        => array_merge($settings, ['status' => 'yes', 'label' => 'fixture']),
        ]);

        FsmtpTest::assertSame(200, $result['status'], 'disconnect response status');
        FsmtpTest::assert(is_array($captured), 'disconnect wrote the settings');
        FsmtpTest::assertSame(NotificationHelper::disconnectedWebhookSettings(), $captured['webhook'], 'webhook settings after the route');
        FsmtpTest::assert(!in_array('webhook', (array) $captured['active_channel'], true), 'switched off on the Alerts list');
        FsmtpTest::assertSame(0, $requests, 'nothing sent');
    });

    FsmtpTest::case('a real alert is sent without waiting, with the short timeout', function () use ($settings) {
        FsmtpTest::interceptHttp(function () {
            return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => ''];
        });

        try {
            $result = NotificationHelper::sendWebhookEvent('email_failed', [], $settings);
            $requests = FsmtpTest::httpRequests();

            FsmtpTest::assertSame(true, $result, 'a real alert returns without a response');
            FsmtpTest::assertSame(1, count($requests), 'requests made');
            FsmtpTest::assertSame(false, $requests[0]['args']['blocking'], 'blocking on a real alert');
            FsmtpTest::assertSame(0.01, $requests[0]['args']['timeout'], 'timeout on a real alert');
        } finally {
            FsmtpTest::interceptHttp();
        }
    });

    FsmtpTest::case('a failed send has a CommonMark message laid out like the Discord one', function () {
        $handler = new SimulatorHandler();
        $handler->setSettings(['provider' => 'simulator', 'sender_email' => 'from@example.test']);

        $context = NotificationHelper::eventContextFromLog($handler, [
            'to'       => serialize([['email' => 'someone@example.test', 'name' => 'Someone']]),
            'subject'  => 'Order *1001* for <b>you</b>',
            'response' => serialize(['message' => "Relay said ~~~ no\nsecond line"]),
        ]);
        $lines = explode("\n", $context['message_markdown']);

        FsmtpTest::assertSame(11, count($lines), 'line count');
        FsmtpTest::assert(0 === strpos($lines[0], '## '), 'a heading first: ' . $lines[0]);
        FsmtpTest::assertSame('**Website URL:** <' . site_url() . '>  ', $lines[1], 'site URL as an autolink, hard break');
        FsmtpTest::assertSame('**Email Service:** SIMULATOR  ', $lines[2], 'service line');
        FsmtpTest::assertSame('**To Email Address:** someone@example.test  ', $lines[3], 'recipient line');
        FsmtpTest::assertSame('**Email Subject:** Order \\*1001\\* for \\<b\\>you\\</b\\>  ', $lines[4], 'subject escaped, hard break (two trailing spaces)');
        FsmtpTest::assertSame('**Error Message:**', $lines[5], 'error label');
        // The error has a run of three tildes, so the fence is four.
        FsmtpTest::assertSame(['~~~~', 'Relay said ~~~ no', 'second line', '~~~~'], array_slice($lines, 6, 4), 'error fenced as typed');
        FsmtpTest::assertSame('[View Failed Email(s)](' . $context['logs_url'] . ')', $lines[10], 'link to the failed emails');
    });

    FsmtpTest::case('a health alert and a test carry their sentence, escaped, in the Markdown placeholder', function () use ($settings, $decode) {
        $health = NotificationHelper::eventContextFromConnection(['provider' => 'smtp', 'message' => 'x'], 'Relay said *5.7.0* # retry');
        FsmtpTest::assertSame('Relay said *5.7.0* # retry', $health['message'], 'health alert, plain');
        FsmtpTest::assertSame('Relay said \\*5.7.0\\* # retry', $health['message_markdown'], 'health alert, escaped');

        // A site URL with an underscore, so the test sentence has something to escape.
        $siteUrl = function () {
            return 'https://my_site.example.test';
        };
        add_filter('site_url', $siteUrl);
        FsmtpTest::interceptHttp(function () {
            return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => ''];
        });
        try {
            NotificationHelper::sendTestWebhookMessage(array_merge($settings, ['body_template' => '{"a": "{{message}}", "b": "{{message_markdown}}"}']));
            $body = $decode(FsmtpTest::httpRequests()[0]['args']['body'], 'test body');
        } finally {
            FsmtpTest::interceptHttp();
            remove_filter('site_url', $siteUrl);
        }
        FsmtpTest::assert(false !== strpos($body['a'], 'https://my_site.example.test'), 'test send, plain: ' . $body['a']);
        FsmtpTest::assertSame(str_replace('my_site', 'my\\_site', $body['a']), $body['b'], 'test send, escaped');
    });

    FsmtpTest::case('the site icon placeholder asks for the Site Icon at 512 pixels, or is empty', function () {
        $asked = null;
        $icon = function ($url, $size) use (&$asked) {
            $asked = $size;
            return 'https://example.test/icon-' . $size . '.png';
        };
        add_filter('get_site_icon_url', $icon, 10, 2);
        try {
            $value = NotificationHelper::webhookPlaceholders('test')['site_icon_url'];
        } finally {
            remove_filter('get_site_icon_url', $icon, 10);
        }
        FsmtpTest::assertSame(512, $asked, 'size requested');
        FsmtpTest::assertSame('https://example.test/icon-512.png', $value, 'the Site Icon URL');

        $none = function () {
            return '';
        };
        add_filter('get_site_icon_url', $none, 10, 0);
        try {
            FsmtpTest::assertSame('', NotificationHelper::webhookPlaceholders('test')['site_icon_url'], 'no Site Icon');
        } finally {
            remove_filter('get_site_icon_url', $none, 10);
        }
    });

    FsmtpTest::case('the admin form starts from the default template', function () use ($decode, $defaultFields) {
        $nothingStored = function () {
            return [];
        };
        add_filter('pre_option__fluent_smtp_notify_settings', $nothingStored, PHP_INT_MAX);
        try {
            $template = (new \FluentMail\App\Models\Settings())->notificationSettings()['webhook']['body_template'];
        } finally {
            remove_filter('pre_option__fluent_smtp_notify_settings', $nothingStored, PHP_INT_MAX);
        }

        // The template itself, not a rendering of it: the renderer would fill an empty one back in.
        FsmtpTest::assertSame($defaultFields, array_keys($decode($template, 'prefilled template')), 'the prefilled template\'s fields');
    });

    FsmtpTest::case('webhook template falls back to the default when empty, and renders a non-text value empty', function () use ($decode, $defaultFields) {
        $rendered = $decode(NotificationHelper::renderWebhookTemplate('', NotificationHelper::webhookPlaceholders('test')), 'empty template');
        FsmtpTest::assertSame($defaultFields, array_keys($rendered), 'the default template\'s fields');
        FsmtpTest::assertSame('test', $rendered['event'], 'event rendered');

        FsmtpTest::assertSame('{"to":""}', NotificationHelper::renderWebhookTemplate('{"to":"{{to}}"}', ['to' => ['x']]), 'an array value renders empty');
    });

    FsmtpTest::case('webhook send without a URL is refused and sends nothing', function () {
        FsmtpTest::interceptHttp();
        $result = NotificationHelper::sendWebhookMessage('{}', '', true);

        FsmtpTest::assert(is_wp_error($result), 'no URL must not be reported as success');
        FsmtpTest::assertSame('webhook_invalid_settings', $result->get_error_code(), 'error code without a URL');
        FsmtpTest::assertSame(0, count(FsmtpTest::httpRequests()), 'nothing sent');
    });

    FsmtpTest::case('webhook context has an empty recipient when the log row\'s to is not text', function () {
        // Defensive: a log row's to is serialized text, but a caller could pass an array.
        $handler = new SimulatorHandler();
        $handler->setSettings(['provider' => 'simulator', 'sender_email' => 'from@example.test']);

        $context = NotificationHelper::eventContextFromLog($handler, ['to' => ['not an address list'], 'subject' => 'a subject']);
        FsmtpTest::assertSame('', $context['to'], 'recipient');
    });

    FsmtpTest::case('the save-time check renders the template with the hostile sample values', function () use ($fixtureUrl) {
        // Valid JSON with ordinary values, broken by a quote in the error: only the hostile sample refuses it.
        $validated = NotificationHelper::validateWebhookSettings([
            'label'         => 'fixture',
            'webhook_url'   => $fixtureUrl,
            'body_template' => '{"a": 1{{error}}}',
        ]);

        FsmtpTest::assert(is_wp_error($validated), 'a template only the hostile values break is refused');
        FsmtpTest::assertSame('webhook_template_invalid', is_wp_error($validated) ? $validated->get_error_code() : null, 'refused as invalid JSON');
    });

    FsmtpTest::case('the Markdown message escapes every formatting character and keeps each value on one line', function () {
        $title = function () {
            return 'Ops *team* [site]';
        };
        add_filter('pre_option_blogname', $title);
        try {
            $handler = new SimulatorHandler();
            $handler->setSettings(['provider' => 'simulator', 'sender_email' => 'from@example.test']);
            $lines = explode("\n", NotificationHelper::eventContextFromLog($handler, [
                'to'       => serialize([['email' => 'someone@example.test', 'name' => 'Someone']]),
                'subject'  => "a\\b `c` *d* _e_ [f] <g> ~h~ |i|\r\n# not a heading",
                'response' => serialize(['message' => "line one\r\nline two\n"]),
            ])['message_markdown']);
        } finally {
            remove_filter('pre_option_blogname', $title);
        }

        FsmtpTest::assertSame('## \[Ops \*team\* \[site\]\] Failed to send email', $lines[0], 'heading escaped');
        FsmtpTest::assertSame('**Email Subject:** a\\\\b \`c\` \*d\* \_e\_ \[f\] \<g\> \~h\~ \|i\| # not a heading  ', $lines[4], 'every formatting character escaped, the line break folded');
        FsmtpTest::assertSame(['~~~', 'line one', 'line two', '~~~'], array_slice($lines, 6, 4), 'error line endings normalised, trailing break trimmed, three-tilde fence');
    });
};
