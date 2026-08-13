<?php

namespace FluentMail\App\Services\Mailer\Providers\MailChannels;

use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\Includes\Support\Arr;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    const API_BASE = 'https://api.mailchannels.net/tx/v1';
    const MAX_RECIPIENTS = 1000;
    const MAX_ATTACHMENTS = 1000;
    const MAX_REQUEST_BYTES = 30000000;

    protected $emailSentCode = 202;

    public function send()
    {
        if ($this->preSend() && $this->phpMailer->preSend()) {
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(422, __('Unable to prepare the email for MailChannels.', 'fluent-smtp')));
    }

    public function postSend()
    {
        $payload = $this->buildPayload();

        if (is_wp_error($payload)) {
            return $this->handleResponse($payload);
        }

        $mode = $this->getSendMode();
        $endpoint = $mode === 'queued' ? '/send-async' : '/send';
        $json = wp_json_encode($payload);

        if (!is_string($json)) {
            return $this->handleResponse(new \WP_Error(422, __('MailChannels could not encode the email payload.', 'fluent-smtp')));
        }

        if (strlen($json) > self::MAX_REQUEST_BYTES) {
            return $this->handleResponse(new \WP_Error(413, __('The MailChannels request exceeds the 30 MB API limit.', 'fluent-smtp')));
        }

        $response = wp_safe_remote_post(
            self::API_BASE . $endpoint,
            [
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-Api-Key'    => $this->getSetting('api_key'),
                ],
                'body'        => $json,
                'timeout'     => 30,
                'redirection' => 0,
                'sslverify'   => true,
                'blocking'    => true,
                'httpversion' => '1.1',
            ]
        );

        $result = $this->parseSendResponse($response, $mode);
        $this->response = $result;

        return $this->handleResponse($result);
    }

    public function setSettings($settings)
    {
        if (Arr::get($settings, 'key_store', 'db') === 'wp_config') {
            $settings['api_key'] = $this->getConfiguredApiKey();
        }

        $this->settings = $settings;

        return $this;
    }

    public function checkConnection($connection)
    {
        $this->setSettings($connection);
        $domain = $this->senderDomain(Arr::get($connection, 'sender_email'));

        if (!$domain) {
            $this->throwValidationException([
                'sender_email' => ['required' => __('A valid sender domain is required.', 'fluent-smtp')],
            ]);
        }

        $result = $this->checkDomain($this->getSetting('api_key'), $domain);

        if (is_wp_error($result)) {
            $this->throwValidationException([
                'api_key' => ['required' => $result->get_error_message()],
            ]);
        }

        // DNS checks can still be propagating. A successful authenticated response
        // proves the credential; incomplete DNS is shown as a warning after save.
        return true;
    }

    public function getConnectionInfo($connection)
    {
        $this->setSettings($connection);
        $domain = $this->senderDomain(Arr::get($connection, 'sender_email'));
        $check = $domain ? $this->checkDomain($this->getSetting('api_key'), $domain) : null;
        $rows = [
            [
                'title'   => __('Submission mode', 'fluent-smtp'),
                'content' => $this->getSendMode() === 'queued' ? esc_html__('Queued', 'fluent-smtp') : esc_html__('Direct', 'fluent-smtp'),
            ],
        ];

        if (is_wp_error($check)) {
            $rows[] = [
                'title'   => __('Domain check', 'fluent-smtp'),
                'content' => '<span style="color:#dc3232;">' . esc_html($check->get_error_message()) . '</span>',
            ];
        } else {
            $ready = $this->domainChecksPassed($check);
            $rows[] = [
                'title'   => __('Domain check', 'fluent-smtp'),
                'content' => $ready
                    ? '<span style="color:#46b450;">' . esc_html__('API key is valid and domain checks pass.', 'fluent-smtp') . '</span>'
                    : '<span style="color:#dba617;">' . esc_html__('API key is valid. One or more DNS checks are incomplete; sending may be affected while DNS propagates.', 'fluent-smtp') . '</span>',
            ];
        }

        $connection['extra_rows'] = $rows;

        return [
            'info' => (string) fluentMail('view')->make('admin.general_connection_info', [
                'connection' => $connection,
            ]),
        ];
    }

    public function checkDomain($apiKey, $domain)
    {
        if (!$apiKey || !$domain) {
            return new \WP_Error(422, __('API key and sender domain are required.', 'fluent-smtp'));
        }

        $response = wp_safe_remote_post(
            self::API_BASE . '/check-domain',
            [
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-Api-Key'    => $apiKey,
                ],
                'body'        => wp_json_encode(['domain' => $domain]),
                'timeout'     => 15,
                'redirection' => 0,
                'sslverify'   => true,
				'blocking'    => true,
				'httpversion' => '1.1',
				'data_format' => 'body',
            ]
        );

        if (is_wp_error($response)) {
            return new \WP_Error('mailchannels_transport', __('MailChannels could not be reached. Check outbound HTTPS connectivity and try again.', 'fluent-smtp'));
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200 && is_array($body)) {
            return $body;
        }

        return new \WP_Error($code ?: 400, $this->errorMessage($code, $body, $response));
    }

    protected function buildPayload()
    {
        $to = $this->formatRecipients($this->getParam('to'));
        $cc = $this->formatRecipients($this->getParam('headers.cc'));
        $bcc = $this->formatRecipients($this->getParam('headers.bcc'));
        $recipientCount = count($to) + count($cc) + count($bcc);

        if (!$to) {
            return new \WP_Error(422, __('MailChannels requires at least one valid To recipient.', 'fluent-smtp'));
        }

        if ($recipientCount > self::MAX_RECIPIENTS) {
            return new \WP_Error(422, __('The MailChannels request exceeds the 1,000-recipient limit.', 'fluent-smtp'));
        }

        $personalization = ['to' => $to];
        if ($cc) {
            $personalization['cc'] = $cc;
        }
        if ($bcc) {
            $personalization['bcc'] = $bcc;
        }

        $payload = [
            'from'             => $this->getFrom(),
            'personalizations' => [$personalization],
            'subject'          => $this->getSubject(),
            'content'          => $this->getContent(),
            'transactional'    => $this->isTransactional(),
        ];

        if ($replyTo = $this->getReplyTo()) {
            $payload['reply_to'] = $replyTo;
        }

        if (!empty($this->phpMailer->Sender) && is_email($this->phpMailer->Sender)) {
            $payload['envelope_from'] = ['email' => $this->phpMailer->Sender];
        }

        $headers = $this->getSafeHeaders();
        if ($headers) {
            $payload['headers'] = $headers;
        }

        $attachments = $this->getAttachments();
        if (is_wp_error($attachments)) {
            return $attachments;
        }
        if ($attachments) {
            $payload['attachments'] = $attachments;
        }

        if ($payload['transactional'] === false && $recipientCount !== 1) {
            return new \WP_Error(422, __('MailChannels non-transactional email requires exactly one recipient.', 'fluent-smtp'));
        }

		$payload = apply_filters('fluentsmtp_mailchannels_payload', $payload, $this->phpMailer, $this->settings);
		if (!is_array($payload)) {
			return new \WP_Error(422, __('The FluentSMTP MailChannels payload filter returned an invalid value.', 'fluent-smtp'));
		}

		$validation = $this->validateFilteredPayload($payload);

		return is_wp_error($validation) ? $validation : $payload;
    }

    protected function getFrom()
    {
        $email = $this->phpMailer->From;
        if ($this->isForcedEmail() && !fluentMailIsListedSenderEmail($email)) {
            $email = $this->getSetting('sender_email');
        }

        $name = $this->phpMailer->FromName;
        if ($this->getSetting('force_from_name') === 'yes' && $this->getSetting('sender_name')) {
            $name = $this->getSetting('sender_name');
        }

        return array_filter([
            'email' => $email,
            'name'  => $name,
        ]);
    }

    protected function getReplyTo()
    {
        $replyTo = $this->formatRecipients($this->getParam('headers.reply-to'));

        return $replyTo ? reset($replyTo) : null;
    }

    protected function formatRecipients($recipients)
    {
        $formatted = [];

        foreach ((array) $recipients as $recipient) {
            $email = Arr::get($recipient, 'email');
            if (!$email || !is_email($email)) {
                continue;
            }
            $formatted[] = array_filter([
                'email' => $email,
                'name'  => Arr::get($recipient, 'name', ''),
            ]);
        }

        return $formatted;
    }

    protected function getContent()
    {
        $type = strtolower((string) $this->getParam('headers.content-type', 'text/plain'));
        $body = (string) $this->getParam('message', '');
        $alt = (string) $this->getParam('alt_body', '');

        if ($type === 'multipart/alternative') {
            $content = [];
            if ($alt !== '') {
                $content[] = ['type' => 'text/plain', 'value' => $alt];
            }
            $content[] = ['type' => 'text/html', 'value' => $body];
            return $content;
        }

        return [[
            'type'  => $type === 'text/html' ? 'text/html' : 'text/plain',
            'value' => $body,
        ]];
    }

    protected function getAttachments()
    {
        $attachments = (array) $this->getParam('attachments', []);
        if (count($attachments) > self::MAX_ATTACHMENTS) {
            return new \WP_Error(422, __('The MailChannels request exceeds the 1,000-attachment limit.', 'fluent-smtp'));
        }

        $result = [];
        $contentIds = [];

        foreach ($attachments as $attachment) {
            $path = isset($attachment[0]) ? $attachment[0] : '';
            try {
                $content = $this->secureFileRead($path);
            } catch (\Exception $e) {
                $this->logAttachmentFailure('MailChannels', $e);
                return new \WP_Error(422, __('MailChannels could not read an attachment; the email was not submitted.', 'fluent-smtp'));
            }

            $item = [
                'filename' => !empty($attachment[2]) ? $attachment[2] : (!empty($attachment[1]) ? $attachment[1] : basename($path)),
                'type'     => !empty($attachment[4]) ? $attachment[4] : 'application/octet-stream',
                'content'  => base64_encode($content),
            ];

            $disposition = isset($attachment[6]) ? strtolower((string) $attachment[6]) : 'attachment';
            $contentId = isset($attachment[7]) ? trim((string) $attachment[7], "<> \t\r\n") : '';
            if ($disposition === 'inline' && $contentId !== '') {
                if (isset($contentIds[$contentId])) {
                    return new \WP_Error(422, __('MailChannels attachment content IDs must be unique.', 'fluent-smtp'));
                }
                $contentIds[$contentId] = true;
                $item['content_id'] = $contentId;
            }

            $result[] = $item;
        }

        return $result;
    }

    protected function getSafeHeaders()
    {
        $reserved = [
            'authentication-results', 'bcc', 'cc', 'content-transfer-encoding',
			'content-type', 'date', 'dkim-signature', 'from', 'message-id',
			'mime-version', 'received', 'reply-to', 'return-path', 'sender',
			'subject', 'to', 'x-api-key',
            'x-mailchannels-transactional',
        ];
        $headers = [];

        foreach ((array) $this->getParam('custom_headers', []) as $header) {
            $name = trim((string) Arr::get($header, 'key', ''));
            $value = trim((string) Arr::get($header, 'value', ''));
            $lower = strtolower($name);
            if (!$name || in_array($lower, $reserved, true) || preg_match('/[^A-Za-z0-9-]/', $name)) {
                continue;
            }
            if (preg_match('/[\r\n]/', $value)) {
                continue;
            }
            $headers[$name] = $value;
        }

        return $headers;
    }

    protected function isTransactional()
    {
        $transactional = true;
        foreach ((array) $this->getParam('custom_headers', []) as $header) {
            if (strtolower((string) Arr::get($header, 'key', '')) === 'x-mailchannels-transactional') {
                $value = strtolower(trim((string) Arr::get($header, 'value', '')));
                $transactional = !in_array($value, ['0', 'false', 'no', 'off'], true);
            }
        }

        return (bool) apply_filters('fluentsmtp_mailchannels_transactional', $transactional, $this->phpMailer, $this->settings);
    }

    protected function getSendMode()
    {
        $mode = $this->getSetting('send_mode', 'direct');
        $mode = apply_filters('fluentsmtp_mailchannels_send_mode', $mode, $this->phpMailer, $this->settings);

        return $mode === 'queued' ? 'queued' : 'direct';
    }

    protected function parseSendResponse($response, $mode)
    {
        if (is_wp_error($response)) {
            return new \WP_Error('mailchannels_transport', __('MailChannels could not be reached over HTTPS. The email was not submitted.', 'fluent-smtp'));
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== $this->emailSentCode || !is_array($body)) {
            return new \WP_Error($code ?: 400, $this->errorMessage($code, $body, $response), $this->sanitizeErrorData($body));
        }

		$requestId = sanitize_text_field((string) Arr::get($body, 'request_id', ''));

		if ($mode === 'queued') {
			if (!$requestId) {
				return new \WP_Error(502, __('MailChannels returned an invalid queued acceptance response.', 'fluent-smtp'));
			}

            return [
                'id'         => $requestId,
                'request_id' => $requestId,
                'message'    => __('Email accepted into the MailChannels queue.', 'fluent-smtp'),
            ];
        }

        $results = Arr::get($body, 'results', []);
        if (!is_array($results) || !$results) {
            return new \WP_Error(502, __('MailChannels returned no direct-send results.', 'fluent-smtp'));
        }

        $messageIds = [];
        foreach ($results as $result) {
            if (Arr::get($result, 'status') !== 'sent') {
                $reason = Arr::get($result, 'reason', __('MailChannels rejected part of the request.', 'fluent-smtp'));
                return new \WP_Error(502, sanitize_text_field($reason), $this->sanitizeErrorData($body));
            }
            if ($messageId = Arr::get($result, 'message_id')) {
                $messageIds[] = sanitize_text_field($messageId);
            }
        }

		return [
			'id'          => $requestId ?: (isset($messageIds[0]) ? $messageIds[0] : ''),
            'request_id'  => $requestId,
            'message_ids' => $messageIds,
            'message'     => __('Email accepted by MailChannels.', 'fluent-smtp'),
        ];
    }

    protected function errorMessage($code, $body, $response = null)
    {
        $messages = [];
        foreach ((array) Arr::get((array) $body, 'errors', []) as $error) {
            if (is_string($error)) {
                $messages[] = sanitize_text_field($error);
            } elseif (is_array($error)) {
                $messages[] = sanitize_text_field(Arr::get($error, 'message', wp_json_encode($error)));
            }
        }

        $defaults = [
            400 => __('MailChannels rejected the request. Check sender, recipient, and message settings.', 'fluent-smtp'),
            401 => __('MailChannels authentication failed. Check the API key.', 'fluent-smtp'),
            403 => __('The MailChannels API key does not have permission for this request.', 'fluent-smtp'),
            413 => __('The MailChannels request exceeds the 30 MB API limit.', 'fluent-smtp'),
            429 => __('The MailChannels account or API rate limit was reached.', 'fluent-smtp'),
        ];
        $message = $messages ? implode(' ', array_slice($messages, 0, 3)) : Arr::get($defaults, $code);

        if (!$message && $code >= 500) {
            $message = __('MailChannels is temporarily unavailable. The email was not submitted.', 'fluent-smtp');
        }
        if (!$message) {
            $message = __('MailChannels returned an unexpected response. The email was not submitted.', 'fluent-smtp');
        }

        if ($response && $code === 429) {
            $retryAfter = wp_remote_retrieve_header($response, 'retry-after');
            if ($retryAfter) {
                $message .= ' ' . sprintf(__('Try again after %s; FluentSMTP did not retry automatically.', 'fluent-smtp'), sanitize_text_field($retryAfter));
            }
        }

        return $message;
    }

    protected function sanitizeErrorData($body)
    {
        if (!is_array($body)) {
            return [];
        }

        $allowed = [];
        foreach (['errors', 'request_id', 'results'] as $key) {
            if (isset($body[$key])) {
				$allowed[$key] = $this->sanitizeErrorValue($body[$key]);
            }
        }

        return $allowed;
    }

	protected function sanitizeErrorValue($value, $key = '')
	{
		$normalizedKey = strtolower(str_replace('-', '_', (string) $key));
		if (in_array($normalizedKey, ['api_key', 'x_api_key', 'content', 'body', 'html', 'text'], true)) {
			return '[redacted]';
		}
		if (is_array($value)) {
			$sanitized = [];
			foreach ($value as $childKey => $childValue) {
				$sanitized[$childKey] = $this->sanitizeErrorValue($childValue, $childKey);
			}
			return $sanitized;
		}

		return is_string($value) ? sanitize_text_field($value) : $value;
	}

	protected function validateFilteredPayload($payload)
	{
		$personalizations = isset($payload['personalizations']) ? $payload['personalizations'] : null;
		if (!is_array($personalizations) || count($personalizations) !== 1 || !is_array(isset($personalizations[0]) ? $personalizations[0] : null)) {
			return new \WP_Error(422, __('MailChannels requires exactly one personalization.', 'fluent-smtp'));
		}

		$count = 0;
		foreach (['to', 'cc', 'bcc'] as $group) {
			$addresses = isset($personalizations[0][$group]) ? $personalizations[0][$group] : [];
			if (!is_array($addresses)) {
				return new \WP_Error(422, __('The filtered MailChannels recipient list is invalid.', 'fluent-smtp'));
			}
			foreach ($addresses as $address) {
				if (!is_array($address) || empty($address['email']) || !is_email($address['email'])) {
					return new \WP_Error(422, __('The filtered MailChannels recipient list contains an invalid address.', 'fluent-smtp'));
				}
				$count++;
			}
		}

		if (empty($personalizations[0]['to']) || !is_array($personalizations[0]['to'])) {
			return new \WP_Error(422, __('MailChannels requires at least one valid To recipient.', 'fluent-smtp'));
		}
		if ($count > self::MAX_RECIPIENTS) {
			return new \WP_Error(422, __('The MailChannels request exceeds the 1,000-recipient limit.', 'fluent-smtp'));
		}
		$attachments = isset($payload['attachments']) ? $payload['attachments'] : [];
		if (!is_array($attachments) || count($attachments) > self::MAX_ATTACHMENTS) {
			return new \WP_Error(422, __('The MailChannels request exceeds the 1,000-attachment limit.', 'fluent-smtp'));
		}
		if (isset($payload['transactional']) && $payload['transactional'] === false && $count !== 1) {
			return new \WP_Error(422, __('MailChannels non-transactional email requires exactly one recipient.', 'fluent-smtp'));
		}

		return true;
	}

    protected function domainChecksPassed($check)
    {
        $results = Arr::get((array) $check, 'check_results', []);
        if (!$results) {
            return false;
        }

        $verdicts = [];
        $this->collectVerdicts($results, $verdicts);

        if (!$verdicts) {
            return false;
        }

        foreach ($verdicts as $verdict) {
            if ($verdict !== 'passed') {
                return false;
            }
        }

        return true;
    }

    protected function collectVerdicts($value, &$verdicts)
    {
        if (!is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            if ($key === 'verdict' && is_string($item)) {
                $verdicts[] = strtolower($item);
            } else {
                $this->collectVerdicts($item, $verdicts);
            }
        }
    }

    protected function senderDomain($email)
    {
        if (!$email || !is_email($email)) {
            return '';
        }

        return strtolower(substr(strrchr($email, '@'), 1));
    }

    protected function getConfiguredApiKey()
    {
        if (defined('FLUENTMAIL_MAILCHANNELS_API_KEY') && FLUENTMAIL_MAILCHANNELS_API_KEY) {
            return FLUENTMAIL_MAILCHANNELS_API_KEY;
        }

        return defined('MAILCHANNELS_API_KEY') ? MAILCHANNELS_API_KEY : '';
    }
}
