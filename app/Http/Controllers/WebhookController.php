<?php

namespace FluentMail\App\Http\Controllers;

use FluentMail\App\Models\Settings;
use FluentMail\App\Services\NotificationHelper;
use FluentMail\Includes\Request\Request;
use FluentMail\Includes\Support\Arr;

/**
 * The generic webhook alert channel. Validation lives in NotificationHelper::validateWebhookSettings().
 */
class WebhookController extends Controller
{
    /**
     * Validate and store the channel's settings, connecting it or saving edits.
     *
     * @param Request $request
     * @return void sends the JSON response and exits
     */
    public function registerSite(Request $request)
    {
        $this->verify();

        $stored = Arr::get((new Settings())->notificationSettings(), 'webhook', []);

        // WordPress slashes $_POST and Request does not undo it; the validator expects raw input.
        $validated = NotificationHelper::validateWebhookSettings(
            wp_unslash($request->get('settings', [])),
            $stored
        );

        if (is_wp_error($validated)) {
            return $this->sendError([
                'message' => $validated->get_error_message()
            ], 422);
        }

        NotificationHelper::updateChannelSettings('webhook', $validated);

        return $this->sendSuccess([
            'message' => __('Settings saved.', 'fluent-smtp'),
        ]);
    }

    /**
     * Send a blocking test event with the stored settings and report the reply.
     *
     * @param Request $request
     * @return void sends the JSON response and exits
     */
    public function sendTestMessage(Request $request)
    {
        $this->verify();

        $settings = (new Settings())->notificationSettings();

        if (Arr::get($settings, 'webhook.status') != 'yes') {
            return $this->sendError([
                'message' => __('Webhook notifications are not enabled.', 'fluent-smtp')
            ], 422);
        }

        // Blocking, unlike real alerts, so the button reports whether the endpoint received it.
        $result = NotificationHelper::sendTestWebhookMessage(Arr::get($settings, 'webhook', []));

        // The receiver's reply goes in the message, the only field the test button shows.
        if (is_wp_error($result)) {
            $data = $result->get_error_data();

            // Transport failures (DNS, TLS, timeout) carry no HTTP reply.
            if (!is_array($data) || !isset($data['code'])) {
                return $this->sendError([
                    'message' => $result->get_error_message(),
                ], 422);
            }

            $reply = $this->receiverReply(Arr::get($data, 'body'));

            return $this->sendError([
                'message' => $reply
                    ? sprintf(
                        /* translators: 1: HTTP status code, 2: the start of the endpoint's reply */
                        __('The endpoint returned HTTP %1$d and replied: %2$s', 'fluent-smtp'),
                        (int) $data['code'],
                        $reply
                    )
                    : $result->get_error_message(),
                'response_code' => (int) $data['code'],
                'response_body' => $reply,
            ], 422);
        }

        $code = (int) Arr::get($result, 'code');
        $reply = $this->receiverReply(Arr::get($result, 'body'));

        return $this->sendSuccess([
            'message'       => $reply
                ? sprintf(
                    /* translators: 1: HTTP status code, 2: the start of the endpoint's reply */
                    __('Test message sent. The endpoint answered HTTP %1$d: %2$s', 'fluent-smtp'),
                    $code,
                    $reply
                )
                : sprintf(
                    /* translators: %d: HTTP status code */
                    __('Test message sent. The endpoint answered HTTP %d with an empty body.', 'fluent-smtp'),
                    $code
                ),
            'response_code' => $code,
            'response_body' => $reply,
        ]);
    }

    /**
     * The start of the endpoint's reply as plain text: tags stripped (each leaving a
     * space, so adjacent elements don't run together), whitespace collapsed, cut to
     * 300 characters. wp_html_excerpt() rather than substr(), which can split a multibyte character.
     *
     * @param mixed $body the raw response body
     * @return string
     */
    protected function receiverReply($body)
    {
        $text = wp_strip_all_tags(str_replace('<', ' <', (string) $body));
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        return wp_html_excerpt($text, 300, '...');
    }

    /**
     * Clear the channel's settings and switch it off.
     *
     * @return void sends the JSON response and exits
     */
    public function disconnect()
    {
        $this->verify();

        NotificationHelper::updateChannelSettings('webhook', NotificationHelper::disconnectedWebhookSettings());

        return $this->sendSuccess([
            'message' => __('Webhook notifications have been disconnected.', 'fluent-smtp')
        ]);
    }
}
