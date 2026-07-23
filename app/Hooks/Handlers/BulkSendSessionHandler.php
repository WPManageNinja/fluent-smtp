<?php

namespace FluentMail\App\Hooks\Handlers;

/**
 * Reuses one SMTP connection across a FluentCRM bulk sending session.
 *
 * FluentCRM fires fluent_crm/email_sender_session_started / _ended around each
 * lock-winning sender run (up to ~50 seconds spanning many claimed batches).
 * For the SMTP provider, the per-email connect + EHLO + STARTTLS + AUTH + QUIT
 * handshake is typically 100-300ms — often more than the MAIL/RCPT/DATA
 * exchange itself — so holding the connection open for the session is a 2-5x
 * throughput win on SMTP relays.
 *
 * The HTTP API providers need nothing here: their connections are already
 * reused via statically cached service/request objects holding persistent
 * cURL handles (see fluentMailSesConnection() and the ToSend handler).
 *
 * The session actions may fire repeatedly or unpaired (FluentCRM documents
 * them as such), so every method here is idempotent. A shutdown close is
 * registered as a crash net; an orphaned socket dies with the PHP process
 * anyway, and one-off emails sent outside a session keep the normal
 * connect-per-send behavior.
 */
class BulkSendSessionHandler
{
    protected static $active = false;

    protected static $hooked = false;

    /**
     * Identity of the SMTP connection the kept-alive socket belongs to.
     * FluentSMTP routes per From address, so one bulk session can interleave
     * emails bound for DIFFERENT relays/credentials — and PHPMailer's
     * smtpConnect() reuses an open socket without re-checking Host or auth.
     * Reusing across identities would send mail through the wrong relay.
     */
    protected static $connectionFingerprint = null;

    public function register()
    {
        add_action('fluent_crm/email_sender_session_started', [$this, 'startSession']);
        add_action('fluent_crm/email_sender_session_ended', [$this, 'endSession']);
    }

    public function startSession()
    {
        self::$active = true;

        if (self::$hooked) {
            return;
        }
        self::$hooked = true;

        // Both WP core's wp_mail() and fluent-smtp's override fire
        // phpmailer_init on every send, so this flips keep-alive on the shared
        // PHPMailer instance only while a bulk session is active.
        add_action('phpmailer_init', [$this, 'enableKeepAlive']);
        register_shutdown_function([$this, 'endSession']);
    }

    public function enableKeepAlive($phpMailer)
    {
        if (self::$active) {
            $phpMailer->SMTPKeepAlive = true;
        }
    }

    public function endSession()
    {
        if (!self::$active) {
            return;
        }

        self::$active = false;

        self::closeConnection();
    }

    /**
     * Whether a FluentCRM bulk sending session is currently active.
     *
     * @return bool
     */
    public static function isActive()
    {
        return self::$active;
    }

    /**
     * Guard the kept-alive socket against connection switches mid-session.
     *
     * The SMTP provider calls this with its resolved connection settings
     * before each send. Same identity as the open socket -> keep reusing it.
     * Different identity (another relay or credentials, routed by From
     * address) -> close first, so PHPMailer reconnects with the new settings
     * instead of pushing mail through the previous relay's session. A streak
     * of same-connection emails keeps the full keep-alive benefit; alternating
     * connections degrade gracefully to connect-per-send.
     *
     * @param array $config Connection-identifying settings (host, port,
     *                      username, encryption, auth, auto_tls, and a hash
     *                      of the credential so rotations force a reconnect).
     */
    public static function ensureConnectionFor($config)
    {
        if (!self::$active) {
            return;
        }

        $fingerprint = md5(wp_json_encode($config));

        if (self::$connectionFingerprint !== null && self::$connectionFingerprint !== $fingerprint) {
            self::closeConnection();
        }

        self::$connectionFingerprint = $fingerprint;
    }

    /**
     * Close the kept-alive SMTP connection, if one is open.
     *
     * Also called by the SMTP provider handler when a send fails mid-session:
     * a relay may drop the idle socket in a way PHPMailer only notices on the
     * next command, so closing here makes the following email reconnect fresh
     * instead of failing on the same dead connection.
     */
    public static function closeConnection()
    {
        // Whatever socket existed no longer does; the next send establishes
        // (and re-fingerprints) its own connection.
        self::$connectionFingerprint = null;

        global $phpmailer;

        if ($phpmailer && method_exists($phpmailer, 'smtpClose')) {
            try {
                $phpmailer->SMTPKeepAlive = false;
                $phpmailer->smtpClose();
            } catch (\Throwable $e) {
                // Closing a dead socket must never break the send loop.
            }
        }
    }
}
