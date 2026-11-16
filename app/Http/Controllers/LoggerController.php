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

    /**
     * Attachment states for the logs a resend is about to send, checked on disk.
     */
    public function attachmentStatus(Request $request, Logger $logger)
    {
        $this->verify();

        $ids = $request->get('ids', []);
        $ids = is_array($ids) ? $ids : [$ids];

        return $this->sendSuccess([
            'attachments' => (object) $logger->attachmentStatus(array_filter($ids, 'is_scalar'))
        ]);
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
                $logger->updateLog(['retries' => $log['retries'] + 1], ['id' => $id]);

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

                $skipped = isset($resentEmail['skipped_attachments']) ? $resentEmail['skipped_attachments'] : [];

                if ($skipped) {
                    $message .= ' ' . static::skippedAttachmentsMessage($skipped);
                }

                return $this->sendSuccess([
                    'email'               => $resentEmail,
                    'recipients'          => $recipients,
                    'message'             => $message,
                    'skipped_attachments' => $skipped
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
        $withoutAttachments = 0;
        foreach ($logIds as $logId) {
            try {
                $email = $logger->resendEmailFromLog($logId, 'check_realtime');
                $successCount++;
                if (!empty($email['skipped_attachments']) && !empty($email['resend_succeeded'])) {
                    $withoutAttachments++;
                }
            } catch (\Exception $exception) {
                $failedInitiated++;
            }
        }
        $message = __('The selected emails have been resent.', 'fluent-smtp');

        if ($withoutAttachments) {
            $message .= sprintf(
                /* translators: %d: number of emails */
                _n(
                    ' %d was sent without one or more attachments that could not be included again.',
                    ' %d were sent without one or more attachments that could not be included again.',
                    $withoutAttachments,
                    'fluent-smtp'
                ),
                $withoutAttachments
            );
        }

        if ($failedCount) {
            $message .= sprintf(__(' %d of them failed to send.', 'fluent-smtp'), $failedCount);
        }

        if ($failedInitiated) {
            $message .= sprintf(__(' %d could not be prepared for sending.', 'fluent-smtp'), $failedInitiated);
        }

        return $this->sendSuccess([
            'message'             => $message,
            'without_attachments' => $withoutAttachments
        ]);

    }

    /**
     * One sentence naming the attachments a resend could not include, and why.
     *
     * @param array $skipped [['name' => string, 'status' => 'in_memory'|'missing'], ...]
     * @return string
     */
    protected static function skippedAttachmentsMessage(array $skipped)
    {
        $parts = [];

        foreach ($skipped as $item) {
            $reason = $item['status'] === 'in_memory'
                ? __('created in memory by the sending plugin, not stored', 'fluent-smtp')
                : __('file no longer exists or can\'t be read', 'fluent-smtp');

            $parts[] = sprintf('%s (%s)', $item['name'], $reason);
        }

        return sprintf(
            /* translators: %s: list of attachment names with the reason each was left out */
            _n(
                'This attachment could not be included: %s.',
                'These attachments could not be included: %s.',
                count($skipped),
                'fluent-smtp'
            ),
            implode(', ', $parts)
        );
    }
}
