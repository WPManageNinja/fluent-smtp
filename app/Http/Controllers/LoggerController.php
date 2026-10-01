<?php

namespace FluentMail\App\Http\Controllers;

use FluentMail\App\Models\Logger;
use FluentMail\Includes\Request\Request;

class LoggerController extends Controller
{
    public function get(Request $request, Logger $logger)
    {
        $this->verify();

        return $this->send(
            $logger->get(
                $request->except(['nonce', 'action'])
            )
        );
    }

    public function show(Request $request, Logger $logger)
    {
        $this->verify();

        $result = $logger->navigate($request->all());

        return $this->sendSuccess($result);
    }

    public function delete(Request $request, Logger $logger)
    {
        $this->verify();

        $id = (array) $request->get('id');

        $logger->delete($id);

        if ($id && $id[0] == 'all') {
            $subject = 'All logs';
        } else {
            $count = count($id);
            $subject = $count > 1 ? "{$count} Logs" : 'Log';
        }
        
        return $this->sendSuccess([
            'message' => sprintf(__('%s deleted successfully.', 'fluent-smtp'), $subject)
        ]);
    }

    public function retry(Request $request, Logger $logger)
    {
        $this->verify();

        try {
            $this->app->addAction('wp_mail_failed', function($response) use ($logger, $request) {
                $log = $logger->find($id = $request->get('id'));
                $log['retries'] = $log['retries'] + 1;
                $logger->updateLog($log, ['id' => $id]);

                return $this->sendError([
                    'message' => $response->get_error_message(),
                    'errors' => $response->get_error_data()
                ], $response->get_error_code());
            });

            $recipients = $this->resolveRecipients(
                $request->get('target'),
                $request->exists('recipients'),
                $request->get('recipients')
            );

            $resentEmail = $logger->resendEmailFromLog(
                $request->get('id'),
                $request->get('type'),
                $recipients
            );

            if ($resentEmail) {
                $message = __('Email sent successfully.', 'fluent-smtp');

                if (!empty($recipients)) {
                    $message = sprintf(
                        /* translators: %s: comma separated list of email addresses */
                        __('Email sent successfully to %s.', 'fluent-smtp'),
                        implode(', ', $recipients)
                    );
                }

                return $this->sendSuccess([
                    'email'      => $resentEmail,
                    'recipients' => $recipients,
                    'message'    => $message
                ]);
            }

            throw new \Exception(esc_html__('Something went wrong.', 'fluent-smtp'), 400);

        } catch (\Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ], $e->getCode());
        }
    }

    /**
     * Validate the list of recipient email addresses typed into the resend
     * dialog. Returns the addresses as typed, or an empty array when none are
     * given; resolveRecipients() decides whether that is allowed.
     *
     * @param  mixed $rawRecipients Array of addresses, or one string separated
     *                              by whitespace, commas or semicolons.
     * @return array<int, string>
     * @throws \Exception When the list cannot be read, is too long, or holds an
     *                    address that cannot be resent as typed.
     */
    protected function sanitizeRecipients($rawRecipients)
    {
        if (empty($rawRecipients)) {
            return [];
        }

        if (is_string($rawRecipients)) {
            $rawRecipients = preg_split('/[\s,;]+/', $rawRecipients);
        }

        // Refuse rather than guess what a value that is neither a list nor a string meant.
        if (!is_array($rawRecipients)) {
            throw new \Exception(
                esc_html__('Could not read the recipient list.', 'fluent-smtp'),
                422
            );
        }

        /*
         * A resend is a manual, one-at-a-time action. A list this long is a
         * mistake or a pasted address book, and either way it is better caught
         * than delivered.
         */
        $maxRecipients = apply_filters('fluentsmtp_max_resend_recipients', 25);

        if (count($rawRecipients) > $maxRecipients) {
            throw new \Exception(
                sprintf(
                    /* translators: %d: maximum number of recipients allowed */
                    esc_html__('Please enter no more than %d email addresses.', 'fluent-smtp'),
                    (int) $maxRecipients
                ),
                422
            );
        }

        $recipients = [];

        foreach ($rawRecipients as $recipient) {
            if (is_array($recipient) || is_object($recipient)) {
                throw new \Exception(
                    esc_html__('Could not read the recipient list.', 'fluent-smtp'),
                    422
                );
            }

            $typed = trim(wp_unslash((string) $recipient));

            if ($typed === '') {
                continue;
            }

            $this->assertResendable($typed);

            $recipients[] = $typed;
        }

        return array_values(array_unique($recipients));
    }

    /**
     * Work out where a resend goes from the choice made in the dialog. Of the
     * three choices, only "custom" takes its addresses from the request;
     * "original" and "self" are resolved here, so an address sent with either
     * contradicts the choice.
     * An empty result means the original recipients, and only "original" or
     * a request with no choice and no recipients field gets one: an empty
     * list of typed addresses is refused.
     *
     * @param mixed $target         "original", "self" or "custom"; null when
     *                              the request has none or it is blank.
     * @param bool  $recipientsSent Whether the request carried a recipients
     *                              field, even a blank one.
     * @param mixed $rawRecipients  The request's recipients field.
     * @return array<int, string>
     * @throws \Exception When the request does not match the choice, or an
     *                    address cannot be resent as typed.
     */
    protected function resolveRecipients($target, $recipientsSent, $rawRecipients)
    {
        if (null === $target) {
            if (!$recipientsSent) {
                return [];
            }
            return $this->requireRecipients($rawRecipients);
        }

        if ('original' === $target) {
            if ($recipientsSent) {
                throw new \Exception(
                    esc_html__('A resend to the original recipients cannot also name other email addresses.', 'fluent-smtp'),
                    422
                );
            }
            return [];
        }

        if ('self' === $target) {
            if ($recipientsSent) {
                throw new \Exception(
                    esc_html__('A resend to your account email cannot also name other email addresses.', 'fluent-smtp'),
                    422
                );
            }
            return [$this->accountEmail()];
        }

        if ('custom' === $target) {
            return $this->requireRecipients($rawRecipients);
        }

        throw new \Exception(esc_html__('Unknown resend target.', 'fluent-smtp'), 422);
    }

    /**
     * Validate typed addresses and refuse a list that holds none.
     *
     * @param mixed $rawRecipients The request's recipients field.
     * @return array<int, string>
     * @throws \Exception When no address is given, or one cannot be resent.
     */
    protected function requireRecipients($rawRecipients)
    {
        $recipients = $this->sanitizeRecipients($rawRecipients);

        if (!$recipients) {
            throw new \Exception(
                esc_html__('No email address was given for the resend.', 'fluent-smtp'),
                422
            );
        }

        return $recipients;
    }

    /**
     * The current user's email address, as their profile page shows it.
     *
     * Saving a user runs the email through kses, which stores "a&b@" as
     * "a&amp;b@"; the address the user knows is the decoded one.
     *
     * @return string
     * @throws \Exception When the account has no address that can be resent to.
     */
    protected function accountEmail()
    {
        $email = trim(html_entity_decode((string) wp_get_current_user()->user_email, ENT_QUOTES, 'UTF-8'));

        if ('' === $email) {
            throw new \Exception(
                esc_html__('Your account has no email address to resend to.', 'fluent-smtp'),
                422
            );
        }

        $this->assertResendable($email);

        return $email;
    }

    /**
     * Refuse an address that wp_mail() could not send to exactly as typed.
     *
     * The address is checked, never repaired: sanitize_email() deletes the
     * characters it does not allow, which would resend to a different mailbox.
     * wp_mail() makes is_email() PHPMailer's address validator, so a non-ASCII
     * local part is refused unless an is_email filter accepts it. A non-ASCII
     * domain can be sent to once PHPMailer converts it to punycode; that needs
     * intl and mbstring, and here also a UTF-8 mail charset (wp_mail_charset).
     *
     * @param string $address Trimmed, and unslashed if it came from the request.
     * @throws \Exception When the address cannot be sent to as typed.
     */
    protected function assertResendable($address)
    {
        $at = strrpos($address, '@');
        $domainIsNonAscii = false !== $at && preg_match('/[^\x00-\x7F]/', substr($address, $at + 1));

        /*
         * Checked before is_email(), so an is_email filter cannot skip this
         * refusal. The dialog always posts UTF-8, but PHPMailer
         * converts the domain from the charset wp_mail() gives it; on any other
         * charset that sends to a different domain or fails.
         */
        if ($domainIsNonAscii) {
            $charset = apply_filters('wp_mail_charset', get_bloginfo('charset'));

            // Only the spellings mbstring accepts: PHPMailer passes the charset to
            // mb_check_encoding(), which throws a ValueError for others on PHP 8.
            if (!in_array(strtolower((string) $charset), ['utf-8', 'utf8'], true)) {
                throw new \Exception(
                    sprintf(
                        /* translators: 1: character set name, 2: email address */
                        esc_html__('This site sends email in %1$s, not UTF-8, so it cannot resend to an address with a non-ASCII domain: %2$s', 'fluent-smtp'),
                        esc_html($charset),
                        esc_html($address)
                    ),
                    422
                );
            }
        }

        if (is_email($address)) {
            return;
        }

        if ($domainIsNonAscii && !preg_match('/[^\x00-\x7F]/', substr($address, 0, $at))) {
            // wp_mail() loads PHPMailer only when it sends.
            if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
                require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            }

            if (!\PHPMailer\PHPMailer\PHPMailer::idnSupported()) {
                throw new \Exception(
                    sprintf(
                        /* translators: %s: email address */
                        esc_html__('This server cannot send to an address with a non-ASCII domain, because the PHP intl or mbstring extension is missing: %s', 'fluent-smtp'),
                        esc_html($address)
                    ),
                    422
                );
            }

            $mailer = new \PHPMailer\PHPMailer\PHPMailer();
            $mailer->CharSet = $charset;

            // punyencodeAddress() returns the address unchanged when it cannot
            // convert it; the result must pass is_email() as it will at send time.
            if (is_email($mailer->punyencodeAddress($address))) {
                return;
            }
        }

        throw new \Exception(
            sprintf(
                /* translators: %s: email address */
                esc_html__('Invalid email address: %s', 'fluent-smtp'),
                esc_html($address)
            ),
            422
        );
    }

    public function retryBulk(Request $request, Logger $logger)
    {
        $this->verify();
        $logIds = $request->get('log_ids', []);

        $failedCount = 0;
        $this->app->addAction('wp_mail_failed', function($response) use (&$failedCount) {
            $failedCount++;
        });

        $failedInitiated = 0;
        $successCount = 0;
        foreach ($logIds as $logId) {
            try {
                $email = $logger->resendEmailFromLog($logId, 'check_realtime');
                $successCount++;
            } catch (\Exception $exception) {
                $failedInitiated++;
            }
        }
        $message = __('The selected emails have been resent.', 'fluent-smtp');

        if ($failedCount) {
            $message .= sprintf(__(' %d of them failed to send.', 'fluent-smtp'), $failedCount);
        }

        if ($failedInitiated) {
            $message .= sprintf(__(' %d could not be prepared for sending.', 'fluent-smtp'), $failedInitiated);
        }

        return $this->sendSuccess([
            'message' => $message
        ]);

    }
}
