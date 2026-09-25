<?php

return function () {
    /*
     * Resending a log rebuilds its Cc, Bcc and Reply-To headers and passes
     * them to wp_mail(), which splits each one on commas. These cases resend a
     * real log from an isolated table and read the recipients PHPMailer was
     * actually given, at phpmailer_init, so they check what would be sent
     * rather than the header string that was built.
     */
    $resend = function (array $row, $type, array $recipients = []) {
        $table = FsmtpFactory::emailLogTable();
        $id = FsmtpFactory::insertLog($table, array_merge([
            'to'          => serialize([['email' => 'to1@example.test']]),
            'from'        => 'Suite Sender <sender@example.test>',
            'body'        => 'body',
            'attachments' => serialize([]),
            'extra'       => serialize([]),
        ], $row));

        $seen = null;
        $listener = function ($phpmailer) use (&$seen) {
            $addresses = function ($list) {
                $emails = array_map(function ($entry) {
                    return $entry[0];
                }, $list);
                sort($emails);
                return $emails;
            };
            $seen = [
                'to'       => $addresses($phpmailer->getToAddresses()),
                'cc'       => $addresses($phpmailer->getCcAddresses()),
                'bcc'      => $addresses($phpmailer->getBccAddresses()),
                'reply-to' => $addresses($phpmailer->getReplyToAddresses()),
            ];
        };
        $redirect = FsmtpFactory::productionLogTableRedirect($table);

        add_action('phpmailer_init', $listener);
        add_filter('query', $redirect, PHP_INT_MAX);
        try {
            FsmtpFactory::loggerForTable($table)->resendEmailFromLog($id, $type, $recipients);
        } finally {
            remove_filter('query', $redirect, PHP_INT_MAX);
            remove_action('phpmailer_init', $listener);
        }

        FsmtpTest::assert($seen !== null, 'phpmailer_init did not fire during the resend');
        return $seen;
    };

    $headers = function (array $lists) {
        $entries = function ($emails) {
            return array_map(function ($email) {
                return ['email' => $email];
            }, $emails);
        };
        return serialize([
            'reply-to'     => $entries(isset($lists['reply-to']) ? $lists['reply-to'] : []),
            'cc'           => $entries(isset($lists['cc']) ? $lists['cc'] : []),
            'bcc'          => $entries(isset($lists['bcc']) ? $lists['bcc'] : []),
            'content-type' => 'text/plain',
        ]);
    };

    FsmtpTest::case('resend to the original recipients keeps every Cc and Bcc address', function () use ($resend, $headers) {
        FsmtpTest::assertMailSimulationActive();

        $seen = $resend([
            'headers' => $headers([
                'cc'  => ['cc1@example.test', 'cc2@example.test'],
                'bcc' => ['bcc1@example.test', 'bcc2@example.test'],
            ]),
        ], 'resend');

        FsmtpTest::assertSame(['to1@example.test'], $seen['to'], 'To');
        FsmtpTest::assertSame(['cc1@example.test', 'cc2@example.test'], $seen['cc'], 'Cc');
        FsmtpTest::assertSame(['bcc1@example.test', 'bcc2@example.test'], $seen['bcc'], 'Bcc');
    });

    FsmtpTest::case('retrying a failed log keeps every Cc and Bcc address', function () use ($resend, $headers) {
        FsmtpTest::assertMailSimulationActive();

        $seen = $resend([
            'status'  => 'failed',
            'headers' => $headers([
                'cc'  => ['cc1@example.test', 'cc2@example.test'],
                'bcc' => ['bcc1@example.test', 'bcc2@example.test'],
            ]),
        ], 'retry');

        FsmtpTest::assertSame(['cc1@example.test', 'cc2@example.test'], $seen['cc'], 'Cc');
        FsmtpTest::assertSame(['bcc1@example.test', 'bcc2@example.test'], $seen['bcc'], 'Bcc');
    });

    FsmtpTest::case('resend to a different address keeps every Reply-To address and still drops Cc and Bcc', function () use ($resend, $headers) {
        FsmtpTest::assertMailSimulationActive();

        $seen = $resend([
            'headers' => $headers([
                'reply-to' => ['reply1@example.test', 'reply2@example.test'],
                'cc'       => ['cc1@example.test', 'cc2@example.test'],
                'bcc'      => ['bcc1@example.test'],
            ]),
        ], 'resend', ['redirect@example.test']);

        FsmtpTest::assertSame(['redirect@example.test'], $seen['to'], 'To');
        FsmtpTest::assertSame(['reply1@example.test', 'reply2@example.test'], $seen['reply-to'], 'Reply-To');
        FsmtpTest::assertSame([], $seen['cc'], 'Cc on a redirected resend');
        FsmtpTest::assertSame([], $seen['bcc'], 'Bcc on a redirected resend');
    });
};
