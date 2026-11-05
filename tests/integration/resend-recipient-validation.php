<?php
/**
 * Guards the addresses a resend goes to.
 *
 * The Resend dialog's addresses are checked, never repaired. sanitize_email()
 * would turn taro山田@example.com into taro@example.com, or empty 山田@example.com
 * altogether, and a resend with no address left goes to the ORIGINAL recipient
 * while the admin is told it was sent. For the same reason the dialog sends only
 * its choice ("original", "self", "custom") as `target` and the server works out
 * every address except typed ones; an empty or contradictory request is refused
 * rather than read as "the original recipients".
 *
 * wp_mail() can send to a non-ASCII domain (PHPMailer converts it to punycode
 * when intl and mbstring are loaded) but not to a non-ASCII local part, because
 * it makes is_email() PHPMailer's validator. These cases call the logs/retry
 * AJAX handler in-process through FsmtpTest::ajax() (one case runs a fresh
 * WP-CLI process instead) and read the recipients PHPMailer was given at
 * phpmailer_init. PHPMailer holds a non-ASCII-domain address in its
 * RecipientsQueue until preSend(), so that queue is read as well.
 *
 * WordPress adds slashes to $_POST (wp_magic_quotes()); FsmtpTest::ajax() does
 * not, so each address is passed through wp_slash() to arrive as it would from
 * the browser. FsmtpTest::ajax() reads the status from http_response_code(),
 * which persists between calls, so status_header(200) resets it before each
 * request.
 */

return function () {

    /**
     * Resend a fresh log row through the AJAX route. A null $recipients leaves
     * the field out of the request; a null $target leaves that out too.
     * Returns the response and the To addresses PHPMailer was given (null when
     * nothing was sent).
     */
    $resendTo = function ($recipients, $target = null) {
        FsmtpTest::assertMailSimulationActive();

        $table = FsmtpFactory::emailLogTable();
        $id = FsmtpFactory::insertLog($table, [
            'to'          => serialize([['email' => 'original@example.test']]),
            'from'        => 'Suite Sender <sender@example.test>',
            'subject'     => 'resend address suite ' . FsmtpTest::uniq(),
            'body'        => 'body',
            'headers'     => serialize(['content-type' => 'text/plain']),
            'attachments' => serialize([]),
            'extra'       => serialize([]),
            'status'      => 'sent',
        ]);

        $sentTo = null;
        $listener = function ($phpmailer) use (&$sentTo) {
            $queue = new ReflectionProperty($phpmailer, 'RecipientsQueue');
            $queue->setAccessible(true);
            $queued = array_keys(array_filter($queue->getValue($phpmailer), function ($params) {
                return $params[0] === 'to';
            }));
            $sentTo = array_merge(array_map(function ($entry) {
                return $entry[0];
            }, $phpmailer->getToAddresses()), $queued);
        };
        $redirect = FsmtpFactory::productionLogTableRedirect($table);

        $params = ['id' => $id, 'type' => 'resend'];
        if (null !== $recipients) {
            $params['recipients'] = wp_slash($recipients);
        }
        if (null !== $target) {
            $params['target'] = $target;
        }

        // retry() adds a wp_mail_failed action on every call and never removes it.
        global $wp_filter;
        $failedHooks = isset($wp_filter['wp_mail_failed']) ? clone $wp_filter['wp_mail_failed'] : null;

        add_action('phpmailer_init', $listener);
        add_filter('query', $redirect, PHP_INT_MAX);
        status_header(200);
        try {
            $result = FsmtpTest::ajax('POST', '/logs/retry', $params);
        } finally {
            status_header(200);
            remove_filter('query', $redirect, PHP_INT_MAX);
            remove_action('phpmailer_init', $listener);
            if (null === $failedHooks) {
                unset($wp_filter['wp_mail_failed']);
            } else {
                $wp_filter['wp_mail_failed'] = $failedHooks;
            }
        }

        return [$result, $sentTo];
    };

    /** Assert a 422 with exactly $message, written out literally, and that nothing was sent. */
    $assertRefusedWith = function ($recipients, $target, $message, $label) use ($resendTo) {
        list($result, $sentTo) = $resendTo($recipients, $target);

        FsmtpTest::assertSame(422, $result['status'], 'status for ' . $label);
        FsmtpTest::assertSame(
            false,
            is_array($result['data']) && array_key_exists('success', $result['data']) ? $result['data']['success'] : null,
            'success flag for ' . $label
        );
        FsmtpTest::assertSame($message, FsmtpTest::ajaxMessage($result), 'message for ' . $label);
        FsmtpTest::assertSame(null, $sentTo, 'nothing sent for ' . $label);
    };

    /** Assert a 422 "Invalid email address: $message" and that nothing was sent. */
    $assertRefused = function ($recipients, $message) use ($assertRefusedWith) {
        $assertRefusedWith($recipients, null, 'Invalid email address: ' . $message, implode(', ', (array) $recipients));
    };

    /** Assert the resend succeeded and PHPMailer got exactly $expected as its To list. */
    $assertSentTo = function ($recipients, $target, array $expected, $label) use ($resendTo) {
        list($result, $sentTo) = $resendTo($recipients, $target);

        FsmtpTest::assertAjaxHealthy($result, 'resend ' . $label);
        FsmtpTest::assertSame($expected, $sentTo, 'sent to, ' . $label);
    };

    /** Assert the resend succeeded and PHPMailer got exactly $address as its only To. */
    $assertSentAsTyped = function ($address) use ($assertSentTo) {
        $assertSentTo([$address], null, [$address], 'to ' . $address);
    };

    /** Refusal text for a non-ASCII domain on a server without intl or mbstring. */
    $noIdnMessage = function ($address) {
        return 'This server cannot send to an address with a non-ASCII domain, because the PHP intl or mbstring extension is missing: ' . $address;
    };

    /** Run $callback as a temporary administrator whose email is $email, then delete that user. */
    $asTemporaryAdmin = function ($email, callable $callback) {
        $previousUser = get_current_user_id();
        $userId = wp_insert_user([
            'user_login' => FsmtpTest::uniq('resend-admin'),
            'user_pass'  => wp_generate_password(24),
            'user_email' => $email,
            'role'       => 'administrator',
        ]);
        if (is_wp_error($userId)) {
            FsmtpTest::fail('could not create a temporary user with email "' . $email . '": ' . $userId->get_error_message());
            return;
        }

        wp_set_current_user($userId);
        try {
            $callback();
        } finally {
            wp_set_current_user($previousUser);
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($userId);
        }
    };

    FsmtpTest::case('resend refuses a non-ASCII local part, which wp_mail() cannot send to', function () use ($assertRefused) {
        $addresses = [
            '用户@例子.example',
            '山田@example.com',
            'taro山田@example.com',
            'почта@пример.example',
            'ivan.иванов@example.com',
            'χρήστης@παράδειγμα.example',
            'zoë@example.com',
            'ë@example.com',
            'françois@example.com',
            'jürgen@example.com',
        ];
        foreach ($addresses as $address) {
            $assertRefused([$address], $address);
        }
    });

    FsmtpTest::case('resend refuses ASCII input that sanitize_email() would repair into another address', function () use ($assertRefused) {
        // 'a b@' only arrives in a hand-built request: the dialog and the server both split a string on whitespace.
        foreach (['joe(x)@example.com', 'a b@example.com'] as $address) {
            $assertRefused([$address], $address);
        }
    });

    FsmtpTest::case('resend escapes the refused address in its error message', function () use ($assertRefused, $assertRefusedWith, $noIdnMessage) {
        $assertRefused(['a<b>@example.com'], 'a&lt;b&gt;@example.com');

        // A non-ASCII domain: the extension refusal where intl is missing, otherwise "Invalid".
        FsmtpTest::requirePhpMailer();
        if (\PHPMailer\PHPMailer\PHPMailer::idnSupported()) {
            $assertRefused(['a<b>@例え.example'], 'a&lt;b&gt;@例え.example');
        } else {
            $assertRefusedWith(['a<b>@例え.example'], null, $noIdnMessage('a&lt;b&gt;@例え.example'), 'a<b>@例え.example, no intl');
        }
    });

    FsmtpTest::case('resend checks a recipient list sent as one string', function () use ($assertRefused, $assertSentTo) {
        $assertRefused('ok@example.test, zoë@example.com', 'zoë@example.com');
        $assertSentTo('a@example.test b@example.test', null, ['a@example.test', 'b@example.test'], 'string list');
    });

    FsmtpTest::case('resend refuses the whole list when one address would be dropped', function () use ($assertRefused) {
        $assertRefused(['ok@example.test', 'ë@example.com'], 'ë@example.com');
    });

    FsmtpTest::case('resend sends a valid address exactly as typed', function () use ($assertSentAsTyped) {
        $addresses = [
            "o'brien@example.com",
            'a+b@example.test',
            'a&b@example.test',
            'tom&jerry+news@example.test',
            'Zoe.Smith+tag@Example.TEST',
            'first.last@mail.sub.example.org',
        ];
        foreach ($addresses as $address) {
            $assertSentAsTyped($address);
        }
    });

    FsmtpTest::case('resend refuses an empty list instead of sending to the original recipients', function () use ($assertRefusedWith) {
        $noAddress = 'No email address was given for the resend.';

        // The request cleaner turns '' into null, so these arrive as null and [null, null].
        $assertRefusedWith('', null, $noAddress, 'a blank recipients field');
        $assertRefusedWith(['', '  '], null, $noAddress, 'a list of blanks');
        $assertRefusedWith(null, 'custom', $noAddress, 'a different address with none given');
        $assertRefusedWith(['', '  '], 'custom', $noAddress, 'a different address given as blanks');
    });

    FsmtpTest::case('resend refuses addresses sent with a choice the server resolves itself', function () use ($assertRefusedWith) {
        $original = 'A resend to the original recipients cannot also name other email addresses.';
        $assertRefusedWith(['a@example.test'], 'original', $original, 'original with an address');
        $assertRefusedWith([''], 'original', $original, 'original with a list holding one blank');
        $assertRefusedWith('', 'original', $original, 'original with a blank recipients field');

        $self = 'A resend to your account email cannot also name other email addresses.';
        $accountEmail = wp_get_current_user()->user_email;
        $assertRefusedWith(['someone-else@example.test'], 'self', $self, 'my account email with another address');
        $assertRefusedWith([$accountEmail], 'self', $self, 'my account email with that same address');
        $assertRefusedWith([''], 'self', $self, 'my account email with a list holding one blank');
        $assertRefusedWith('', 'self', $self, 'my account email with a blank recipients field');

        $assertRefusedWith(['a@example.test'], 'everyone', 'Unknown resend target.', 'an unknown choice');
    });

    FsmtpTest::case('resend refuses a recipient list it cannot read or that is too long', function () use ($assertRefusedWith) {
        // A hand-built request can nest arrays; (string) on one would raise a PHP warning (a notice on 7.4).
        $assertRefusedWith([['a@example.test']], 'custom', 'Could not read the recipient list.', 'a nested list');

        $cap = function () {
            return 1;
        };
        add_filter('fluentsmtp_max_resend_recipients', $cap);
        try {
            $assertRefusedWith(
                ['a@example.test', 'b@example.test'],
                'custom',
                'Please enter no more than 1 email addresses.',
                'two addresses over a cap of 1'
            );
        } finally {
            remove_filter('fluentsmtp_max_resend_recipients', $cap);
        }
    });

    FsmtpTest::case('resend goes to the original recipients without a choice, and where the dialog chose', function () use ($assertSentTo) {
        $assertSentTo(null, null, ['original@example.test'], 'with no choice and no addresses');
        $assertSentTo(null, 'original', ['original@example.test'], 'to the original recipients');
        $assertSentTo(['a@example.test'], 'custom', ['a@example.test'], 'to a different address');
    });

    FsmtpTest::case('resend to "my account email" uses the current user\'s address as their profile shows it', function () use ($asTemporaryAdmin, $assertSentTo, $assertRefusedWith) {
        // Each address differs from the site's admin email, so only the current user's can match.
        $plain = FsmtpTest::uniq('self') . '@example.test';
        $asTemporaryAdmin($plain, function () use ($assertSentTo, $plain) {
            $assertSentTo(null, 'self', [$plain], 'to my account email');
        });

        // WordPress stores "a&b@" as "a&amp;b@" (kses on save); the profile page shows "a&b@".
        $ampersand = 'a&' . FsmtpTest::uniq('b') . '@example.test';
        $asTemporaryAdmin($ampersand, function () use ($assertSentTo, $ampersand) {
            $assertSentTo(null, 'self', [$ampersand], 'to an account email with an ampersand');
        });

        $asTemporaryAdmin('', function () use ($assertRefusedWith) {
            $assertRefusedWith(null, 'self', 'Your account has no email address to resend to.', 'an account with no email');
        });

        // WordPress sanitises an email on save, so only a direct database write can store one wp_mail() refuses.
        $asTemporaryAdmin(FsmtpTest::uniq('self') . '@example.test', function () use ($assertRefusedWith) {
            global $wpdb;
            $userId = get_current_user_id();
            $wpdb->update($wpdb->users, ['user_email' => 'zoë@example.com'], ['ID' => $userId]);
            clean_user_cache($userId);
            // wp_set_current_user() keeps its cached object for an unchanged ID.
            wp_set_current_user(0);
            wp_set_current_user($userId);

            $assertRefusedWith(null, 'self', 'Invalid email address: zoë@example.com', 'an account email wp_mail() cannot send to');
        });
    });

    FsmtpTest::case('resend sends each address once and names them in its reply', function () use ($resendTo) {
        list($result, $sentTo) = $resendTo(['a@example.test', 'a@example.test', 'b@example.test'], 'custom');

        FsmtpTest::assertAjaxHealthy($result, 'resend with a duplicate');
        FsmtpTest::assertSame(['a@example.test', 'b@example.test'], $result['data']['data']['recipients'], 'recipients in the reply');
        FsmtpTest::assertSame(
            'Email sent successfully to a@example.test, b@example.test.',
            FsmtpTest::ajaxMessage($result),
            'success message'
        );
        FsmtpTest::assertSame(['a@example.test', 'b@example.test'], $sentTo, 'sent to');
    });

    FsmtpTest::case('resend refuses a non-ASCII domain when the site does not send in UTF-8', function () use ($assertRefusedWith) {
        $refusal = function ($charset, $address) {
            return 'This site sends email in ' . $charset . ', not UTF-8, so it cannot resend to an address with a non-ASCII domain: ' . $address;
        };
        // An is_email filter that accepts every address must not let the domain past the charset check.
        $acceptAll = function ($result, $email) {
            return $email;
        };

        foreach (['ISO-8859-1', 'windows-1252'] as $siteCharset) {
            // The dialog posts UTF-8; PHPMailer would read the domain in this charset and send to another domain.
            $charset = function () use ($siteCharset) {
                return $siteCharset;
            };
            add_filter('pre_option_blog_charset', $charset);
            try {
                $assertRefusedWith(
                    ['info@société.example'],
                    null,
                    $refusal($siteCharset, 'info@société.example'),
                    $siteCharset . ' site'
                );
                // Checked before the local part: the charset is the reason given.
                $assertRefusedWith(
                    ['χρήστης@παράδειγμα.example'],
                    null,
                    $refusal($siteCharset, 'χρήστης@παράδειγμα.example'),
                    $siteCharset . ' site, non-ASCII local part'
                );

                add_filter('is_email', $acceptAll, 10, 2);
                try {
                    $assertRefusedWith(
                        ['info@société.example'],
                        null,
                        $refusal($siteCharset, 'info@société.example'),
                        $siteCharset . ' site with an is_email filter accepting everything'
                    );
                } finally {
                    remove_filter('is_email', $acceptAll, 10);
                }
            } finally {
                remove_filter('pre_option_blog_charset', $charset);
            }
        }
    });

    FsmtpTest::case('resend reads the charset wp_mail() uses, through wp_mail_charset', function () use ($assertRefusedWith, $assertSentTo, $noIdnMessage) {
        FsmtpTest::requirePhpMailer();
        $siteCharset = function () {
            return 'ISO-8859-1';
        };
        add_filter('pre_option_blog_charset', $siteCharset);
        try {
            // mbstring rejects these spellings (a ValueError inside PHPMailer on PHP 8), so they are refused, not passed on.
            foreach (['utf_8', 'UTF-8-', 'U-T-F-8'] as $spelling) {
                $odd = function () use ($spelling) {
                    return $spelling;
                };
                add_filter('wp_mail_charset', $odd);
                try {
                    $assertRefusedWith(
                        ['info@例え.example'],
                        null,
                        'This site sends email in ' . $spelling . ', not UTF-8, so it cannot resend to an address with a non-ASCII domain: info@例え.example',
                        'with wp_mail_charset ' . $spelling
                    );
                } finally {
                    remove_filter('wp_mail_charset', $odd);
                }
            }

            // The charset and the address are both escaped in the refusal.
            $markup = function () {
                return 'x<y>';
            };
            add_filter('wp_mail_charset', $markup);
            try {
                $assertRefusedWith(
                    ['a<b>@例え.example'],
                    null,
                    'This site sends email in x&lt;y&gt;, not UTF-8, so it cannot resend to an address with a non-ASCII domain: a&lt;b&gt;@例え.example',
                    'with a charset and an address holding markup'
                );
            } finally {
                remove_filter('wp_mail_charset', $markup);
            }

            foreach (['utf-8', 'UTF8'] as $spelling) {
                $mailCharset = function () use ($spelling) {
                    return $spelling;
                };
                add_filter('wp_mail_charset', $mailCharset);
                try {
                    if (\PHPMailer\PHPMailer\PHPMailer::idnSupported()) {
                        $assertSentTo(['info@例え.example'], null, ['info@例え.example'], 'with wp_mail_charset ' . $spelling);
                    } else {
                        $assertRefusedWith(['info@例え.example'], null, $noIdnMessage('info@例え.example'), 'with wp_mail_charset ' . $spelling . ', no intl');
                    }
                } finally {
                    remove_filter('wp_mail_charset', $mailCharset);
                }
            }
        } finally {
            remove_filter('pre_option_blog_charset', $siteCharset);
        }
    });

    FsmtpTest::case('resend loads PHPMailer itself in a request that has not sent mail', function () {
        // A fresh process, as a real admin-ajax request is: nothing has loaded PHPMailer yet.
        $code = 'var_dump(class_exists("PHPMailer\\\\PHPMailer\\\\PHPMailer", false));'
            . '$c = new \\FluentMail\\App\\Http\\Controllers\\LoggerController();'
            . '$m = new ReflectionMethod($c, "assertResendable"); $m->setAccessible(true);'
            . 'try { $m->invoke($c, "info@例え.example"); echo "resendable"; }'
            . ' catch (Exception $e) { echo "refused: " . $e->getMessage(); }';
        $settings = ['connections' => [], 'mappings' => [], 'misc' => ['simulate_emails' => 'yes', 'log_emails' => 'no', 'default_connection' => '']];
        $result = FsmtpTest::wpCli(['eval', $code], ['FSMTP_SUITE_SETTINGS_B64' => base64_encode(wp_json_encode($settings))]);

        FsmtpTest::assertSame(0, $result['code'], 'exit code; output: ' . $result['output']);
        FsmtpTest::assert(false !== strpos($result['stdout'], 'bool(false)'), 'PHPMailer was not loaded before the check');
        FsmtpTest::assert(
            false !== strpos($result['stdout'], 'resendable')
                || false !== strpos($result['stdout'], 'refused: This server cannot send to an address with a non-ASCII domain'),
            'the check loaded PHPMailer and reached its IDN test; stdout: ' . $result['stdout']
        );
    });

    FsmtpTest::case('resend hands a non-ASCII domain to wp_mail() unchanged', function () use ($assertSentAsTyped, $assertRefused, $assertRefusedWith, $noIdnMessage) {
        FsmtpTest::requirePhpMailer();
        $addresses = [
            'info@例え.example',
            'info@例え.example.com',
            'a@bücher.example',
            'info@société.example',
            'info@пример.example',
            'info@παράδειγμα.example',
        ];

        if (!\PHPMailer\PHPMailer\PHPMailer::idnSupported()) {
            foreach (array_merge($addresses, ['info@例え..example']) as $address) {
                $assertRefusedWith([$address], null, $noIdnMessage($address), $address . ', no intl');
            }
            return;
        }

        foreach ($addresses as $address) {
            $assertSentAsTyped($address);
        }

        // An empty label: refused whether or not punycode conversion succeeds.
        $assertRefused(['info@例え..example'], 'info@例え..example');
    });
};
