<?php

namespace FluentMail\App\Services\Mailer;

use FluentMail\App\Hooks\Handlers\BulkSendSessionHandler;
use FluentMail\App\Services\Mailer\Providers\Factory;
use FluentMail\App\Services\Mailer\Providers\Simulator\Handler as Simulator;

class FluentPHPMailer
{
    protected $app = null;

    protected $phpMailer = null;

    /** @var self|null The send whose provider and fallback are currently executing. */
    private static $activeSend;
    private static $notifying = false;
    private $failedLogId = 0;
    private $sentViaFallback = false;
    private $fallbackConnection;

    public function __construct($phpMailer, $fallbackConnection = null)
    {
        $this->app = fluentMail();

        $this->phpMailer = $phpMailer;
        $this->fallbackConnection = $fallbackConnection;
    }

    public function send()
    {
        if (defined('FLUENTMAIL_EMAIL_TESTING') || self::$notifying) {
            return $this->sendPrimary();
        }

        $previous = self::$activeSend;
        self::$activeSend = $this;
        $this->failedLogId = 0;
        $this->sentViaFallback = false;
        $sent = false;
        try {
            $sent = $this->sendPrimary();
            return $sent;
        } finally {
            // A listener may invoke wp_mail() itself. Finish this send before
            // calling it, then resume whichever send was active on entry.
            self::$activeSend = null;
            try {
                if (!$sent || $this->sentViaFallback) {
                    self::$notifying = true;
                    /**
                     * Reports a transport failure or acceptance by the backup.
                     *
                     * @param string $outcome 'failed' or 'sent_via_fallback'.
                     * @param int $logId Primary failed log ID, or zero if unavailable.
                     */
                    do_action('fluentmail_delivery_outcome', $this->sentViaFallback ? 'sent_via_fallback' : 'failed', $this->failedLogId);
                }
            } finally {
                self::$notifying = false;
                self::$activeSend = $previous;
            }
        }
    }

    /** @internal Associate the primary provider's log with this specific send. */
    public static function recordFailedLog($mailer, $id)
    {
        if (self::$activeSend && self::$activeSend->phpMailer === $mailer && is_numeric($id)) {
            self::$activeSend->failedLogId = max(0, (int) $id);
        }
    }

    private function sendPrimary()
    {
        if ($driver = fluentMailGetProvider($this->phpMailer->From)) {
            if ($forceFromEmail = $driver->getSetting('force_from_email_id')) {
                $this->phpMailer->From = $forceFromEmail;
            }
            return $driver->setPhpMailer($this->phpMailer)->send();
        }

        // No connection routes this address, so PHPMailer sends it however the
        // request left it configured. That is not a send this plugin is
        // routing, so it must not go out over a relay socket a bulk session
        // left open.
        BulkSendSessionHandler::releaseForeignSend();

        return $this->phpMailer->send();
    }

    public function sendViaFallback($rowId)
    {
        if ($this->fallbackConnection === null) {
            // Preserve the original single-argument entry point for extensions.
            $driver = fluentMailGetProvider($this->phpMailer->From);
            if (!$driver) {
                return false;
            }
            $driver = clone $driver;
        } elseif (fluentMail(Manager::class)->isSimulating()) {
            $driver = new Simulator();
        } else {
            // The scheduler already selected the saved connection. Looking up
            // its From address again can quietly choose the primary instead.
            $driver = clone fluentMail(Factory::class)->make($this->fallbackConnection['provider']);
            $driver->setSettings($this->fallbackConnection);
        }
        $driver->setRowId(is_numeric($rowId) ? (int) $rowId : 0);
        $sent = $driver->setPhpMailer($this->phpMailer)->send();
        if ($sent && self::$activeSend && self::$activeSend->phpMailer === $this->phpMailer) {
            self::$activeSend->sentViaFallback = true;
        }
        return $sent;
    }

    public function __get($key)
    {
        return $this->phpMailer->{$key};
    }

    public function __set($key, $value)
    {
        $this->phpMailer->{$key} = $value;
    }

    public function __call($method, $params)
    {
        return call_user_func_array([$this->phpMailer, $method], $params);
    }
}
