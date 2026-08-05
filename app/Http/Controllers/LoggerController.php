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

            $recipients = $this->sanitizeRecipients($request->get('recipients'));

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

            throw new \Exception(esc_html__('Something went wrong', 'fluent-smtp'), 400);

        } catch (\Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ], $e->getCode());
        }
    }

    /**
     * Sanitize and validate a list of recipient email addresses coming
     * from the resend dialog. Returns an array of valid email addresses
     * or an empty array when none are provided / valid.
     *
     * @param  mixed $rawRecipients
     * @return array<int, string>
     * @throws \Exception When an invalid email address is supplied.
     */
    protected function sanitizeRecipients($rawRecipients)
    {
        if (empty($rawRecipients)) {
            return [];
        }

        if (is_string($rawRecipients)) {
            $rawRecipients = preg_split('/[\s,;]+/', $rawRecipients);
        }

        /*
         * Refuse rather than fall through. Returning an empty list here would
         * mean a malformed request quietly resends to the ORIGINAL recipient
         * instead of the one that was asked for - the worst possible answer,
         * since the caller is told the send succeeded and never learns it went
         * somewhere else.
         */
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
            $recipient = sanitize_email(trim((string) $recipient));

            if ($recipient === '') {
                continue;
            }

            if (!is_email($recipient)) {
                throw new \Exception(
                    sprintf(
                        /* translators: %s: email address */
                        esc_html__('Invalid email address: %s', 'fluent-smtp'),
                        esc_html($recipient)
                    ),
                    422
                );
            }

            $recipients[] = $recipient;
        }

        return array_values(array_unique($recipients));
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
        $message = __('Selected Emails have been proceed to send.', 'fluent-smtp');

        if ($failedCount) {
            $message .= sprintf(__(' But %d emails are reported to failed to send.', 'fluent-smtp'), $failedCount);
        }

        if ($failedInitiated) {
            $message .= sprintf(__(' And %d emails are failed to init the emails', 'fluent-smtp'), $failedInitiated);
        }

        return $this->sendSuccess([
            'message' => $message
        ]);

    }
}
