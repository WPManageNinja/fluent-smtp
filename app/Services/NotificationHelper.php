<?php

namespace FluentMail\App\Services;

use InvalidArgumentException;
use FluentMail\App\Models\Settings;
use FluentMail\App\Services\Notification\Manager as NotificationManager;
use FluentMail\Includes\Support\Arr;

class NotificationHelper
{
    public static function getRemoteServerUrl()
    {
        if (defined('FLUENTSMTP_SERVER_REMOTE_SERVER_URL')) {
            return FLUENTSMTP_SERVER_REMOTE_SERVER_URL;
        }

        return 'https://fluentsmtp.com/wp-json/fluentsmtp_notify/v1/';
    }

    public static function issueTelegramPinCode($data)
    {
        return self::sendTeleRequest('register-site', $data, 'POST');
    }

    public static function registerSlackSite($data)
    {
        return self::sendSlackRequest('register-site', $data, 'POST');
    }

    public static function getTelegramConnectionInfo($token)
    {
        return self::sendTeleRequest('get-site-info', [], 'GET', $token);
    }

    public static function sendTestTelegramMessage($token = '')
    {
        if (!$token) {
            $settings = (new Settings())->notificationSettings();
            $token = Arr::get($settings, 'telegram.token');
        }

        return self::sendTeleRequest('send-test', [], 'POST', $token);
    }

    /**
     * The alert channels' test message: one sentence with a placeholder, so translators can place the URL and punctuation.
     *
     * @return string
     */
    public static function getTestMessage()
    {
        return sprintf(
            /* translators: %s: the site URL */
            __('Test message from %s. If you can read this, the connection is working.', 'fluent-smtp'),
            site_url()
        );
    }

    public static function sendTestPushoverMessage($apiToken, $userKey)
    {
        return self::sendPushoverMessage(self::getTestMessage(), $apiToken, $userKey, true, 1);
    }

    public static function disconnectTelegram($token)
    {
        self::sendTeleRequest('disconnect', [], 'POST', $token);

        self::updateChannelSettings('telegram', [
            'status' => 'no',
            'token'  => ''
        ]);

        return true;
    }

    public static function getTelegramBotTokenId()
    {
        static $token = null;

        if ($token !== null) {
            return $token;
        }

        $settings = (new Settings())->notificationSettings();

        $token = Arr::get($settings, 'telegram.token', false);

        if (!$token) {
            $token = false;
        }

        return $token;
    }

    public static function getSlackWebhookUrl()
    {
        static $url = null;

        if ($url !== null) {
            return $url;
        }

        $settings = (new Settings())->notificationSettings();

        $url = Arr::get($settings, 'slack.webhook_url');

        if (!$url) {
            $url = false;
        }

        return $url;
    }

    public static function sendFailedNotificationTele($data)
    {
        wp_remote_post(self::getRemoteServerUrl() . 'telegram/send-failed-notification', array(
            'timeout'   => 0.01,
            'blocking'  => false,
            'body'      => $data,
            'cookies'   => false,
            'sslverify' => true,
        ));

        return true;
    }

    private static function sendTeleRequest($route, $data = [], $method = 'POST', $token = '')
    {
        $url = self::getRemoteServerUrl() . 'telegram/' . $route;

        if ($token) {
            $url .= '?site_token=' . $token;
        }

        if ($method == 'POST') {
            $response = wp_remote_post($url, [
                'body'      => $data,
                'sslverify' => true,
                'timeout'   => 50
            ]);
        } else {
            $response = wp_remote_get($url, [
                'sslverify' => true,
                'timeout'   => 50
            ]);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $responseCode = wp_remote_retrieve_response_code($response);

        $body = wp_remote_retrieve_body($response);
        $responseData = json_decode($body, true);

        if (!$responseData || empty($responseData['success']) || $responseCode !== 200) {
            return new \WP_Error('invalid_data', __('Something went wrong', 'fluent-smtp'), $responseData);
        }

        return $responseData;
    }

    private static function sendSlackRequest($route, $data = [], $method = 'POST', $token = '')
    {
        $url = self::getRemoteServerUrl() . 'slack/' . $route;

        if ($token) {
            $url .= '?site_token=' . $token;
        }

        if ($method == 'POST') {
            $response = wp_remote_post($url, [
                'body'      => $data,
                'sslverify' => true,
                'timeout'   => 50
            ]);
        } else {
            $response = wp_remote_get($url, [
                'sslverify' => true,
                'timeout'   => 50
            ]);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $responseCode = wp_remote_retrieve_response_code($response);

        $body = wp_remote_retrieve_body($response);

        $responseData = json_decode($body, true);

        if (!$responseData || empty($responseData['success']) || $responseCode !== 200) {
            return new \WP_Error('invalid_data', __('Something went wrong', 'fluent-smtp'), $responseData);
        }

        return $responseData;
    }

    public static function sendSlackMessage($message, $webhookUrl, $blocking = false)
    {

        if (is_array($message)) {
            $body = wp_json_encode($message);
        } else {
            $body = wp_json_encode(array('text' => $message));
        }

        $args = array(
            'body'        => $body,
            'headers'     => array(
                'Content-Type' => 'application/json',
            ),
            'cookies'     => null,
            'timeout'     => 60,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => true,
            'data_format' => 'body',
        );

        if (!$blocking) {
            $args['blocking'] = false;
            $args['timeout'] = 0.01;
        }

        $response = wp_remote_post($webhookUrl, $args);

        if (!$blocking) {
            return true;
        }

        if (is_wp_error($response)) {
            return $response;
        }


        $body = wp_remote_retrieve_body($response);


        return json_decode($body, true);
    }

    public static function sendDiscordMessage($message, $webhookUrl, $blocking = false)
    {
        $body = wp_json_encode(array(
            'content'  => $message,
            'username' => 'FluentSMTP'
        ));

        $args = array(
            'body'        => $body,
            'headers'     => array(
                'Content-Type' => 'application/json',
            ),
            'timeout'     => 60,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => true,
            'data_format' => 'body',
        );

        if (!$blocking) {
            $args['blocking'] = false;
            $args['timeout'] = 0.01;
        }

        $response = wp_remote_post($webhookUrl, $args);

        if (!$blocking) {
            return true;
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    public static function sendPushoverMessage($message, $apiToken, $userKey, $blocking = false, $priority = 1)
    {
        $title = sprintf(__('[%s] Failed to send email', 'fluent-smtp'), fluentMailSiteTitle());

        $args = array(
            'body'        => array(
                'token'   => $apiToken,
                'user'    => $userKey,
                'message' => $message,
                'title'   => $title,
                'priority' => $priority,
                'html'    => 1, // Enable HTML formatting
            ),
            'timeout'     => 60,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => true,
        );

        if (!$blocking) {
            $args['blocking'] = false;
            $args['timeout'] = 0.01;
        }

        $response = wp_remote_post('https://api.pushover.net/1/messages.json', $args);

        if (!$blocking) {
            return true;
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $responseData = json_decode($body, true);

        if ($responseCode !== 200 || empty($responseData['status']) || $responseData['status'] != 1) {
            $errorMessage = __('Pushover API error', 'fluent-smtp');
            if (!empty($responseData['errors'])) {
                $errorMessage = is_array($responseData['errors']) ? implode(', ', $responseData['errors']) : $responseData['errors'];
            }
            return new \WP_Error('pushover_api_error', $errorMessage, $responseData);
        }

        return $responseData;
    }

    public static function formatSlackMessageBlock($handler, $logData = [])
    {
        $sendingTo = self::unserialize(Arr::get($logData, 'to'));

        if (is_array($sendingTo)) {
            $sendingTo = Arr::get($sendingTo, '0.email', '');
        }

        if (is_array($sendingTo) || !$sendingTo) {
            $sendingTo = Arr::get($logData, 'to');
        }

        $heading = sprintf(__('[%s] Failed to send email', 'fluent-smtp'), fluentMailSiteTitle());

        return [
            'text'   => $heading,
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type'  => 'plain_text',
                        'text'  => $heading,
                        "emoji" => true
                    ]
                ],
                [
                    'type'   => 'section',
                    'fields' => [
                        [
                            'type' => "mrkdwn",
                            'text' => '*' . __('Website URL:', 'fluent-smtp') . "*\n " . site_url()
                        ],
                        [
                            'type' => "mrkdwn",
                            'text' => '*' . __('Email Service:', 'fluent-smtp') . "*\n " . strtoupper($handler->getSetting('provider'))
                        ],
                        [
                            'type' => "mrkdwn",
                            'text' => '*' . __('To Email Address:', 'fluent-smtp') . "*\n " . $sendingTo
                        ],
                        [
                            'type' => "mrkdwn",
                            'text' => '*' . __('Email Subject:', 'fluent-smtp') . "*\n " . Arr::get($logData, 'subject')
                        ]
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => "mrkdwn",
                        'text' => '*' . __('Error Message:', 'fluent-smtp') . "*\n ```" . self::getErrorMessageFromResponse(self::unserialize(Arr::get($logData, 'response'))) . "```"
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => "mrkdwn",
                        'text' => "<" . admin_url('options-general.php?page=fluent-mail#/logs?per_page=10&page=1&status=failed&search=') . '|' . __('View Failed Email(s)', 'fluent-smtp') . '>'
                    ]
                ]
            ]
        ];
    }

    public static function formatDiscordMessageBlock($handler, $logData = [])
    {
        $sendingTo = self::unserialize(Arr::get($logData, 'to'));

        if (is_array($sendingTo)) {
            $sendingTo = Arr::get($sendingTo, '0.email', '');
        }

        if (is_array($sendingTo) || !$sendingTo) {
            $sendingTo = Arr::get($logData, 'to');
        }

        $heading = sprintf(__('[%s] Failed to send email', 'fluent-smtp'), fluentMailSiteTitle());

        $content = '## ' . $heading . "\n";
        $content .= __('**Website URL:** ', 'fluent-smtp') . site_url() . "\n";
        $content .= __('**Email Service:** ', 'fluent-smtp') . strtoupper($handler->getSetting('provider')) . "\n";
        $content .= __('**To Email Address:** ', 'fluent-smtp') . $sendingTo . "\n";
        $content .= __('**Email Subject:** ', 'fluent-smtp') . Arr::get($logData, 'subject') . "\n";
        $content .= __('**Error Message:** ```', 'fluent-smtp') . self::getErrorMessageFromResponse(self::unserialize(Arr::get($logData, 'response'))) . "```\n";
        $content .= __('[View Failed Email(s)](', 'fluent-smtp') . admin_url('options-general.php?page=fluent-mail#/logs?per_page=10&page=1&status=failed&search=') . ')';

        return $content;
    }

    public static function formatPushoverMessage($handler, $logData = [])
    {
        $sendingTo = self::unserialize(Arr::get($logData, 'to'));

        if (is_array($sendingTo)) {
            $sendingTo = Arr::get($sendingTo, '0.email', '');
        }

        if (is_array($sendingTo) || !$sendingTo) {
            $sendingTo = Arr::get($logData, 'to');
        }

        $provider = strtoupper($handler->getSetting('provider'));
        $subject = Arr::get($logData, 'subject');

        $message = '<b>' . __('Website URL:', 'fluent-smtp') . '</b> ' . esc_html(site_url()) . "<br>";
        $message .= '<b>' . __('Email Service:', 'fluent-smtp') . '</b> ' . esc_html($provider) . "<br>";
        $message .= '<b>' . __('To Email Address:', 'fluent-smtp') . '</b> ' . esc_html($sendingTo) . "<br>";
        $message .= '<b>' . __('Email Subject:', 'fluent-smtp') . '</b> ' . esc_html($subject) . "<br>";
        $message .= '<b>' . __('Error Message:', 'fluent-smtp') . '</b><br>';
        $message .= '<code>' . esc_html(self::getErrorMessageFromResponse(
            self::unserialize(Arr::get($logData, 'response'))
        )) . '</code><br>';
        $logsUrl = admin_url(
            'options-general.php?page=fluent-mail#/logs?per_page=10&page=1&status=failed&search='
        );
        $message .= '<a href="' . esc_url($logsUrl) . '">' . __('View Failed Email(s)', 'fluent-smtp') . '</a>';

        return $message;
    }

    public static function getErrorMessageFromResponse($response)
    {
        if (!$response || !is_array($response)) {
            return '';
        }

        if (!empty($response['fallback_response']['message'])) {
            $message = $response['fallback_response']['message'];
        } else {
            $message = Arr::get($response, 'message');
        }

        if (!$message) {
            return '';
        }

        if (!is_string($message)) {
            $message = json_encode($message);
        }

        return $message;
    }

    protected static function unserialize($data)
    {
        if (is_serialized($data)) {
            if (preg_match('/(^|;)O:[0-9]+:/', $data)) {
                return '';
            }
            return unserialize(trim($data), ['allowed_classes' => false]);
        }

        return $data;
    }

    public static function updateChannelSettings($channelName, $channelSettings)
    {
        $settings = (new Settings())->notificationSettings();

        // `status` means configured; `active_channel` holds the Alerts list toggle.
        // Switch on only when newly configured, so a re-save keeps an admin's "off".
        $wasConfigured = Arr::get($settings, $channelName . '.status') === 'yes';

        $settings[$channelName] = $channelSettings;

        $isActive = Arr::get($channelSettings, 'status') === 'yes' ? true : false;

        $activeChannels = $settings['active_channel'];

        if ($isActive) {
            if (!$wasConfigured && !in_array($channelName, $activeChannels)) {
                $activeChannels[] = $channelName;
            }
        } else {
            if (($key = array_search($channelName, $activeChannels)) !== false) {
                unset($activeChannels[$key]);
            }
        }

        $activeChannels = array_values($activeChannels);
        $settings['active_channel'] = $activeChannels;

        update_option('_fluent_smtp_notify_settings', $settings, false);

        return true;
    }

    /**
     * Every key any event can supply, so an unset placeholder renders empty.
     */
    protected static function eventContextDefaults()
    {
        return [
            'site_url'     => site_url(),
            'site_title'   => fluentMailSiteTitle(),
            'provider'     => '',
            'to'           => '',
            'sender_email' => '',
            'subject'      => '',
            'error'        => '',
            'logs_url'     => admin_url(
                'options-general.php?page=fluent-mail#/logs?per_page=10&page=1&status=failed&search='
            ),
            'sent_at'      => gmdate('c'),
            'message'      => '',
            'message_markdown' => '',
            // Empty when the site has no Site Icon; receivers then use their own default.
            'site_icon_url' => (string) get_site_icon_url(512),
        ];
    }

    /**
     * Normalise a failed send into the flat scalar context a channel needs.
     */
    public static function eventContextFromLog($handler, $logData = [])
    {
        $sendingTo = self::unserialize(Arr::get($logData, 'to'));

        if (is_array($sendingTo)) {
            $sendingTo = Arr::get($sendingTo, '0.email', '');
        }

        if (is_array($sendingTo) || !$sendingTo) {
            $sendingTo = Arr::get($logData, 'to');
        }

        if (!is_string($sendingTo)) {
            $sendingTo = '';
        }

        $context = array_merge(self::eventContextDefaults(), [
            'provider'     => (string) $handler->getSetting('provider'),
            // The connection's sender, not the log's `from`, which force_from_email may have rewritten.
            'sender_email' => (string) $handler->getSetting('sender_email'),
            'to'           => $sendingTo,
            'subject'      => (string) Arr::get($logData, 'subject'),
            'error'        => (string) self::getErrorMessageFromResponse(
                self::unserialize(Arr::get($logData, 'response'))
            ),
        ]);

        $context['message'] = self::failedEmailMessage($context);
        $context['message_markdown'] = self::failedEmailMarkdown($context);

        return $context;
    }

    /**
     * The failure as plain text, one field per line; the error keeps its own
     * line breaks, as in the other channels. Plain because a webhook receiver
     * may not render Markdown; Discord keeps its own formatter.
     */
    protected static function failedEmailMessage($context)
    {
        return implode("\n", [
            /* translators: %s: the site title */
            sprintf(__('[%s] Failed to send email', 'fluent-smtp'), $context['site_title']),
            /* translators: %s: the site URL */
            sprintf(__('Website URL: %s', 'fluent-smtp'), $context['site_url']),
            /* translators: %s: the email service, for example SMTP */
            sprintf(__('Email Service: %s', 'fluent-smtp'), strtoupper($context['provider'])),
            /* translators: %s: the recipient's email address */
            sprintf(__('To Email Address: %s', 'fluent-smtp'), $context['to']),
            /* translators: %s: the email subject */
            sprintf(__('Email Subject: %s', 'fluent-smtp'), $context['subject']),
            /* translators: %s: the error the email service returned */
            sprintf(__('Error Message: %s', 'fluent-smtp'), $context['error']),
            /* translators: %s: the URL of the failed emails in the log */
            sprintf(__('View failed emails: %s', 'fluent-smtp'), $context['logs_url']),
        ]);
    }

    /**
     * The failure in CommonMark, laid out like the Discord message: one field per
     * line (two trailing spaces, CommonMark's hard line break; Mattermost shows a
     * trailing backslash literally) and the error in a fenced block. Values are
     * escaped so they show as typed.
     */
    protected static function failedEmailMarkdown($context)
    {
        $error = rtrim(str_replace(["\r\n", "\r"], "\n", $context['error']), "\n");

        // A fence longer than any run of tildes in the error, so the error cannot close it.
        preg_match_all('/~+/', $error, $runs);
        $longest = $runs[0] ? max(array_map('strlen', $runs[0])) : 0;
        $fence = str_repeat('~', max(3, $longest + 1));

        return implode("\n", [
            /* translators: %s: the site title */
            '## ' . self::markdownEscape(sprintf(__('[%s] Failed to send email', 'fluent-smtp'), $context['site_title'])),
            // The Discord message's own translatable labels (all but the error label), so existing translations apply.
            __('**Website URL:** ', 'fluent-smtp') . '<' . $context['site_url'] . '>  ',
            __('**Email Service:** ', 'fluent-smtp') . self::markdownEscape(strtoupper($context['provider'])) . '  ',
            __('**To Email Address:** ', 'fluent-smtp') . self::markdownEscape($context['to']) . '  ',
            __('**Email Subject:** ', 'fluent-smtp') . self::markdownEscape($context['subject']) . '  ',
            __('**Error Message:**', 'fluent-smtp'),
            $fence,
            $error,
            $fence,
            __('[View Failed Email(s)](', 'fluent-smtp') . $context['logs_url'] . ')',
        ]);
    }

    /**
     * Backslash-escape the punctuation that can start CommonMark inline formatting,
     * and fold line breaks into spaces so a value cannot start a new block.
     */
    protected static function markdownEscape($text)
    {
        $text = (string) preg_replace('/\r\n|\r|\n/', ' ', (string) $text);

        return (string) preg_replace('/([\\\\`*_\[\]<>~|])/', '\\\\$1', $text);
    }

    /**
     * Normalise a connection that has just failed its health check.
     */
    public static function eventContextFromConnection($connection, $message)
    {
        return array_merge(self::eventContextDefaults(), [
            'provider'     => (string) Arr::get($connection, 'provider'),
            'sender_email' => (string) Arr::get($connection, 'sender_email'),
            'error'        => (string) Arr::get($connection, 'message'),
            'message'      => (string) $message,
            'message_markdown' => self::markdownEscape($message),
        ]);
    }

    /**
     * The default body template.
     */
    public static function defaultWebhookTemplate()
    {
        return '{
    "event": "{{event}}",
    "site_url": "{{site_url}}",
    "site_title": "{{site_title}}",
    "provider": "{{provider}}",
    "to": "{{to}}",
    "sender_email": "{{sender_email}}",
    "subject": "{{subject}}",
    "error": "{{error}}",
    "logs_url": "{{logs_url}}",
    "sent_at": "{{sent_at}}"
}';
    }

    /**
     * Every placeholder name, in webhookPlaceholders() order, for the admin form's hint.
     * A constant so the channel config stays data; the suite checks it against the builder.
     */
    const WEBHOOK_PLACEHOLDER_KEYS = [
        'site_url', 'site_title', 'provider', 'to', 'sender_email', 'subject',
        'error', 'logs_url', 'sent_at', 'message', 'message_markdown',
        'site_icon_url', 'event',
    ];

    /**
     * A {{placeholder}}. Shared by the renderer and the save-time check so they agree on names.
     */
    const WEBHOOK_PLACEHOLDER_PATTERN = '/\{\{\s*([a-z0-9_]+)\s*\}\}/i';

    /**
     * Every value a template can name for one event. The event name cannot be overridden.
     *
     * @param string $event   email_failed, connection_unhealthy or test
     * @param array  $context the channel-neutral event context
     * @return array
     */
    public static function webhookPlaceholders($event, $context = [])
    {
        return array_merge(
            self::eventContextDefaults(),
            is_array($context) ? $context : [],
            ['event' => (string) $event]
        );
    }

    /**
     * Substitute {{placeholders}} into the template, JSON-escaping every value.
     */
    public static function renderWebhookTemplate($template, $placeholders = [])
    {
        $template = (string) $template;

        if (!$template) {
            $template = self::defaultWebhookTemplate();
        }

        return preg_replace_callback(
            self::WEBHOOK_PLACEHOLDER_PATTERN,
            function ($matches) use ($placeholders) {
                $key = strtolower($matches[1]);
                $value = isset($placeholders[$key]) ? $placeholders[$key] : '';

                if (!is_scalar($value)) {
                    return '';
                }

                // A stray invalid byte (an SMTP error, a subject) becomes U+FFFD
                // instead of json_encode() failing and the value going out empty.
                $encoded = json_encode(
                    (string) $value,
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                );

                // substr, not trim(): trim would also strip an escaped trailing quote.
                return is_string($encoded) ? substr($encoded, 1, -1) : '';
            },
            $template
        );
    }

    /**
     * Hostile values (quotes, newlines, backslashes) that a template must render as valid
     * JSON before it can be saved. Fixed rather than site-derived, so every site gets the same check.
     */
    protected static function webhookSampleContext()
    {
        return [
            'site_url'     => 'https://example.test/?q="x"&p=C:\\path',
            'site_title'   => "A \"quoted\" site\nC:\\title",
            'provider'     => 'smtp "quoted"',
            'to'           => '"Some One" <someone@example.test>',
            'sender_email' => '"Sender" <sender@example.test>',
            'subject'      => 'A "quoted" subject',
            'error'        => "SMTP connect() failed\nC:\\path \"quoted\"",
            'logs_url'     => 'https://example.test/wp-admin/?page="logs"&dir=C:\\logs',
            'sent_at'      => "2026-01-01T00:00:00+00:00 \"quoted\"\n\\",
            'message'      => "**Sample** \"quoted\"\nline two C:\\path",
            'message_markdown' => "## Sample \"quoted\"\n~~~\nC:\\path\n~~~",
            'site_icon_url' => 'https://example.test/icon.png?name="x"&dir=C:\\icons',
        ];
    }

    /**
     * Realistic values for a test send, so a receiver's field mapping can be built from it.
     */
    protected static function webhookTestContext($message)
    {
        return array_merge(self::eventContextDefaults(), [
            'provider'     => 'smtp',
            'to'           => 'recipient@example.com',
            'sender_email' => 'sender@example.com',
            'subject'      => __('Sample subject line', 'fluent-smtp'),
            'error'        => __('Sample error message. This is a test, no email failed.', 'fluent-smtp'),
            'message'      => $message,
            'message_markdown' => self::markdownEscape($message),
        ]);
    }

    /**
     * Send a test message, blocking so the real result can be reported.
     *
     * @return array|\WP_Error
     */
    public static function sendTestWebhookMessage($channelSettings)
    {
        return self::sendWebhookEvent(
            'test',
            self::webhookTestContext(self::getTestMessage()),
            $channelSettings,
            true
        );
    }

    /**
     * Validate submitted webhook settings.
     *
     * @param array $input  the submitted settings
     * @param array $stored the channel's current stored settings
     * @return array|\WP_Error
     */
    public static function validateWebhookSettings($input, $stored = [])
    {
        $input = is_array($input) ? $input : [];
        $stored = is_array($stored) ? $stored : [];

        $label = sanitize_text_field(Arr::get($input, 'label', ''));

        if (!$label) {
            return new \WP_Error(
                'webhook_label_required',
                __('A name for this webhook is required.', 'fluent-smtp')
            );
        }

        // The mask posted back becomes the stored URL, as for every other secret.
        $input = SecretMasker::resolve($input, $stored, ['webhook_url']);
        $webhookUrl = trim((string) Arr::get($input, 'webhook_url', ''));

        // Blank keeps the saved URL, so the template can be edited without re-pasting it.
        if (!$webhookUrl) {
            $webhookUrl = trim((string) Arr::get($stored, 'webhook_url', ''));
        }

        if (!$webhookUrl) {
            return new \WP_Error(
                'webhook_url_required',
                __('A webhook URL is required.', 'fluent-smtp')
            );
        }

        if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            return new \WP_Error(
                'webhook_url_invalid',
                __('Please provide a valid webhook URL.', 'fluent-smtp')
            );
        }

        $scheme = strtolower((string) wp_parse_url($webhookUrl, PHP_URL_SCHEME));
        $host = (string) wp_parse_url($webhookUrl, PHP_URL_HOST);

        // FILTER_VALIDATE_URL on its own accepts ftp:// and more.
        if (!in_array($scheme, ['http', 'https'], true) || !$host) {
            return new \WP_Error(
                'webhook_url_scheme',
                __('The webhook URL must start with http:// or https://.', 'fluent-smtp')
            );
        }

        // Not sanitize_text_field: it strips the newlines the template is made of.
        $template = trim((string) Arr::get($input, 'body_template', ''));

        if (!$template) {
            $template = self::defaultWebhookTemplate();
        }

        // An unknown placeholder renders empty and still gives valid JSON, so refuse it here.
        preg_match_all(self::WEBHOOK_PLACEHOLDER_PATTERN, $template, $matches);

        $unknown = array_diff(
            array_unique(array_map('strtolower', $matches[1])),
            array_keys(self::webhookPlaceholders('test'))
        );

        if ($unknown) {
            return new \WP_Error(
                'webhook_placeholder_unknown',
                sprintf(
                    /* translators: %s: the unrecognised placeholders, comma separated */
                    __('The body template uses placeholders that do not exist: %s', 'fluent-smtp'),
                    '{{' . implode('}}, {{', $unknown) . '}}'
                )
            );
        }

        $rendered = self::renderWebhookTemplate(
            $template,
            self::webhookPlaceholders('test', self::webhookSampleContext())
        );

        json_decode($rendered, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return new \WP_Error(
                'webhook_template_invalid',
                sprintf(
                    /* translators: %s: the JSON parser's own reason */
                    __('The body template does not produce valid JSON: %s', 'fluent-smtp'),
                    json_last_error_msg()
                )
            );
        }

        return [
            'status'        => 'yes',
            'label'         => $label,
            'webhook_url'   => esc_url_raw($webhookUrl, ['http', 'https']),
            'body_template' => $template,
        ];
    }

    /**
     * The settings a disconnect writes.
     *
     * The template resets to the default, not empty: the Settings defaults only apply
     * when the webhook key is absent (wp_parse_args is a shallow merge).
     */
    public static function disconnectedWebhookSettings()
    {
        return [
            'status'        => 'no',
            'label'         => '',
            'webhook_url'   => '',
            'body_template' => self::defaultWebhookTemplate(),
        ];
    }

    /**
     * POST a rendered body to the endpoint.
     *
     * wp_remote_post, not wp_safe_remote_post, so receivers on private addresses
     * (a self-hosted n8n, for example) work, as for the other channels.
     *
     * @return true|array|\WP_Error
     */
    public static function sendWebhookMessage($body, $webhookUrl, $blocking = false)
    {
        if (!$webhookUrl) {
            return new \WP_Error(
                'webhook_invalid_settings',
                __('A webhook URL is required.', 'fluent-smtp')
            );
        }

        $args = array(
            'body'        => $body,
            'headers'     => array(
                'Content-Type' => 'application/json',
            ),
            'timeout'     => 60,
            /*
             * Never follow redirects. Non-blocking alerts never do, so a test that
             * followed one (re-sent as a GET) would pass while real alerts are lost.
             */
            'redirection' => 0,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => true,
            'data_format' => 'body',
        );

        if (!$blocking) {
            $args['blocking'] = false;
            $args['timeout'] = 0.01;
        }

        $response = wp_remote_post($webhookUrl, $args);

        if (!$blocking) {
            return true;
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $result = [
            'code' => (int) wp_remote_retrieve_response_code($response),
            'body' => (string) wp_remote_retrieve_body($response),
        ];

        // Judge by status alone: receivers often answer 200 or 204 with an empty body.
        if ($result['code'] < 200 || $result['code'] >= 300) {
            return new \WP_Error(
                'webhook_api_error',
                sprintf(
                    /* translators: %d: the HTTP status the endpoint returned */
                    __('The endpoint returned HTTP %d.', 'fluent-smtp'),
                    $result['code']
                ),
                $result
            );
        }

        return $result;
    }

    /**
     * Render one event and send it.
     *
     * @return true|array|\WP_Error
     */
    public static function sendWebhookEvent($event, $context, $channelSettings, $blocking = false)
    {
        $body = self::renderWebhookTemplate(
            Arr::get($channelSettings, 'body_template'),
            self::webhookPlaceholders($event, $context)
        );

        /**
         * Filter the rendered webhook body before it is sent, for shapes the
         * template cannot express (an Adaptive Card, for example).
         *
         * @param string $body    the rendered JSON body
         * @param string $event   email_failed, connection_unhealthy or test
         * @param array  $context the channel-neutral event context
         */
        $body = apply_filters('fluentsmtp_webhook_payload', $body, $event, $context);

        return self::sendWebhookMessage(
            $body,
            Arr::get($channelSettings, 'webhook_url'),
            $blocking
        );
    }
}
