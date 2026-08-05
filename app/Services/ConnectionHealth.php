<?php

namespace FluentMail\App\Services;

use FluentMail\App\Services\Mailer\Providers\Factory;
use FluentMail\Includes\Support\Arr;

/**
 * Periodic health check for the configured connections.
 *
 * The failure this exists for is the silent one: an OAuth refresh token that
 * ages out while the site is quiet, or an API key removed from wp-config.php
 * months after it was set. Nothing surfaces either until the first email that
 * needed to go out does not, and by then the send is already lost.
 */
class ConnectionHealth
{
    const OPTION_KEY = '_fluentsmtp_connection_health';

    const STATUS_HEALTHY = 'healthy';
    const STATUS_ERROR = 'error';

    /**
     * Run a check across every configured connection and store the outcome.
     *
     * @return array keyed by connection unique key
     */
    public function checkAll()
    {
        $settings = fluentMailGetSettings();
        $connections = Arr::get($settings, 'connections', []);

        $previous = $this->getReport();
        $report = [];

        foreach ($connections as $key => $connection) {
            $providerSettings = Arr::get($connection, 'provider_settings', []);

            if (!$providerSettings) {
                continue;
            }

            $result = $this->checkConnection($providerSettings);

            $report[$key] = [
                'sender_email' => Arr::get($providerSettings, 'sender_email'),
                'provider'     => Arr::get($providerSettings, 'provider'),
                'status'       => $result['status'],
                'message'      => $result['message'],
                'checked_at'   => gmdate('Y-m-d H:i:s')
            ];
        }

        update_option(self::OPTION_KEY, $report, false);

        $this->notifyNewFailures($previous, $report);

        return $report;
    }

    /**
     * The stored report is always read through the current connection list.
     *
     * A connection deleted between two daily runs leaves its last result
     * behind in the option, and warning about a connection the admin has
     * already removed is worse than saying nothing at all - there is no
     * action left to take, and the warning cannot be dismissed. Reconciling
     * on read means the dashboard is correct the moment a connection goes
     * away, rather than at the next scheduled run.
     *
     * @return array
     */
    public function getReport()
    {
        $report = get_option(self::OPTION_KEY);

        if (!is_array($report)) {
            return [];
        }

        $connections = Arr::get(fluentMailGetSettings(), 'connections', []);

        return array_intersect_key($report, $connections);
    }

    /**
     * Drop a single connection's stored result.
     *
     * Called when a connection is deleted so the option does not accumulate
     * results for connections that no longer exist. getReport() already hides
     * them; this stops them being carried around forever.
     *
     * @param string $key connection unique key
     * @return void
     */
    public function forget($key)
    {
        $report = get_option(self::OPTION_KEY);

        if (!is_array($report) || !array_key_exists($key, $report)) {
            return;
        }

        unset($report[$key]);

        update_option(self::OPTION_KEY, $report, false);
    }

    /**
     * Connections that are currently failing their check.
     *
     * @return array
     */
    public function getFailing()
    {
        return array_filter($this->getReport(), function ($item) {
            return Arr::get($item, 'status') === self::STATUS_ERROR;
        });
    }

    /**
     * @param array $providerSettings
     * @return array{status: string, message: string}
     */
    public function checkConnection($providerSettings)
    {
        $provider = Arr::get($providerSettings, 'provider');

        try {
            /*
             * The OAuth providers are checked by actually renewing the token.
             * That is the operation which silently rots, and it is the only way
             * to learn that the grant is still good - a stored token that has
             * not expired yet says nothing about whether it was revoked.
             */
            if ($provider == 'outlook') {
                $result = (new \FluentMail\App\Services\Mailer\Providers\Outlook\Handler())
                    ->renewToken($providerSettings);
            } elseif ($provider == 'gmail') {
                $result = (new \FluentMail\App\Hooks\Handlers\SchedulerHandler())
                    ->callGmailApiForNewToken($providerSettings);
            } else {
                /*
                 * Everything else gets a credential check rather than a live
                 * API probe. A probe per provider per day would be better
                 * signal, but it burns provider rate limits and each one needs
                 * its own endpoint - so that is left to this filter.
                 */
                $result = apply_filters(
                    'fluentmail_connection_health_probe',
                    true,
                    $providerSettings
                );

                if ($result === true) {
                    $handler = fluentMail(Factory::class)->make($provider);
                    $handler->validateProviderInformation($providerSettings);
                }
            }

            if (is_wp_error($result)) {
                return [
                    'status'  => self::STATUS_ERROR,
                    'message' => $result->get_error_message()
                ];
            }
        } catch (\Exception $e) {
            return [
                'status'  => self::STATUS_ERROR,
                'message' => $this->flattenMessage($e)
            ];
        } catch (\Throwable $e) {
            return [
                'status'  => self::STATUS_ERROR,
                'message' => $e->getMessage()
            ];
        }

        return [
            'status'  => self::STATUS_HEALTHY,
            'message' => ''
        ];
    }

    /**
     * A ValidationException carries the useful part in its error bag rather
     * than in the message, which is always "Unprocessable Entity".
     *
     * @param \Exception $e
     * @return string
     */
    private function flattenMessage($e)
    {
        if (!method_exists($e, 'errors')) {
            return $e->getMessage();
        }

        $messages = [];

        foreach ((array)$e->errors() as $field => $fieldErrors) {
            foreach ((array)$fieldErrors as $error) {
                $messages[] = is_array($error) ? implode(' ', $error) : $error;
            }
        }

        return $messages ? implode(' ', $messages) : $e->getMessage();
    }

    /**
     * Only connections that have just started failing are announced. A
     * connection that has been broken for a week should not re-notify daily.
     *
     * @param array $previous
     * @param array $current
     * @return void
     */
    private function notifyNewFailures($previous, $current)
    {
        $newlyFailing = [];

        foreach ($current as $key => $item) {
            if (Arr::get($item, 'status') !== self::STATUS_ERROR) {
                continue;
            }

            if (Arr::get($previous, $key . '.status') === self::STATUS_ERROR) {
                continue;
            }

            $newlyFailing[$key] = $item;
        }

        if (!$newlyFailing) {
            return;
        }

        do_action('fluentmail_connection_health_failed', $newlyFailing);
    }
}
