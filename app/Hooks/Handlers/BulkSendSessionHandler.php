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
 * throughput win on SMTP relays. Sockets are still recycled after
 * MAX_CONNECTION_AGE seconds: the handshake savings only need tens of sends
 * per connection, and short-lived sockets stay clear of relay idle timeouts,
 * message-per-connection caps, and any state an old connection could carry.
 *
 * The HTTP API providers need nothing here: their connections are already
 * reused via statically cached service/request objects holding persistent
 * cURL handles (see fluentMailSesConnection() and the ToSend handler).
 *
 * The session actions may fire repeatedly or unpaired (FluentCRM documents
 * them as such), so every method here is idempotent. Keep-alive itself is
 * enabled per send by the SMTP provider handler — the only path that
 * declares a connection identity via ensureConnectionFor() — so mail sent
 * by other phpmailer_init integrations never rides an unguarded kept-alive
 * socket. A shutdown close is registered as a crash net; an orphaned socket
 * dies with the PHP process anyway, and one-off emails sent outside a
 * session keep the normal connect-per-send behavior.
 */
class BulkSendSessionHandler
{
    /**
     * Hard cap on how long one kept-alive socket may be reused, in seconds.
     */
    const MAX_CONNECTION_AGE = 15;

    protected static $active = false;

    /**
     * Unix timestamp of the most recent session start, for self-expiry.
     */
    protected static $activeSince = 0;

    protected static $hooked = false;

    /**
     * Identity of the SMTP connection the kept-alive socket belongs to.
     * FluentSMTP routes per From address, so one bulk session can interleave
     * emails bound for DIFFERENT relays/credentials — and PHPMailer's
     * smtpConnect() reuses an open socket without re-checking Host or auth.
     * Reusing across identities would send mail through the wrong relay.
     */
    protected static $connectionFingerprint = null;

    /**
     * Unix timestamp of when the current kept-alive connection was opened,
     * for the MAX_CONNECTION_AGE recycle.
     */
    protected static $connectionOpenedAt = 0;

    /**
     * Whether the current send is riding a socket left open by a previous
     * send. The SMTP handler's dead-socket retry only re-sends when the
     * failure came from a reused connection — a fresh-connect failure is a
     * real error (relay down, bad credentials) a retry would not fix.
     */
    protected static $socketReused = false;

    public function register()
    {
        add_action('fluent_crm/email_sender_session_started', [$this, 'startSession']);
        add_action('fluent_crm/email_sender_session_ended', [$this, 'endSession']);
    }

    public function startSession()
    {
        // Kill switch: some relays cap messages-per-connection, so sites must
        // be able to disable connection reuse at runtime. Everything else in
        // this class is inert while no session is marked active.
        if (!apply_filters('fluentmail_smtp_bulk_keep_alive', true)) {
            return;
        }

        self::$active = true;
        self::$activeSince = time();

        if (self::$hooked) {
            return;
        }
        self::$hooked = true;

        register_shutdown_function([$this, 'endSession']);
    }

    public function endSession()
    {
        // Deliberately checks the raw flag (not isActive()): a session_ended
        // arriving after the 5-minute self-expiry must still close the socket.
        if (!self::$active) {
            return;
        }

        self::$active = false;

        self::closeConnection();
    }

    /**
     * Whether a FluentCRM bulk sending session is currently active.
     *
     * Sessions self-expire after 5 minutes: real sessions last ~50s and every
     * new lock-winning sender run re-fires session_started (refreshing the
     * stamp), so only an orphaned session — an unpaired start in a long-lived
     * process that never received its session_ended — can reach the limit.
     * Expiry degrades gracefully to connect-per-send, and the next declared
     * send's hygiene pass in ensureConnectionFor() closes whatever socket
     * the expired session left open.
     *
     * @return bool
     */
    public static function isActive()
    {
        return self::$active && (time() - self::$activeSince) < 300;
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
        global $phpmailer;

        $connected = $phpmailer
            && method_exists($phpmailer, 'getSMTPInstance')
            && $phpmailer->getSMTPInstance()->connected();

        // Hygiene runs BEFORE the active check on purpose: PHPMailer's
        // smtpConnect() silently reuses any connected socket without
        // re-checking Host or credentials, so a socket left open by an
        // ended/expired session — or one past its age cap — must be closed
        // here, before this send gets a chance to adopt it.
        if ($connected && (!self::isActive() || (time() - self::$connectionOpenedAt) >= self::MAX_CONNECTION_AGE)) {
            self::closeConnection();
            $connected = false;
        }

        if (!self::isActive()) {
            return;
        }

        $fingerprint = md5(wp_json_encode($config));

        // Close on ANY identity change — including from the unknown (null)
        // state, so a socket some other integration left open is never
        // adopted as ours. Closing an already-closed socket is a cheap no-op.
        if (self::$connectionFingerprint !== $fingerprint) {
            self::closeConnection();
            $connected = false;
        }

        self::$connectionFingerprint = $fingerprint;
        self::$socketReused = $connected;

        if (!$connected) {
            // This send opens a fresh connection right after this call;
            // stamp its birth so the age cap above can recycle it.
            self::$connectionOpenedAt = time();
        }
    }

    /**
     * Whether the send being declared reuses an already-open kept-alive
     * socket (vs establishing a fresh connection).
     *
     * @return bool
     */
    public static function wasSocketReused()
    {
        return self::$socketReused;
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
        self::$connectionOpenedAt = 0;
        self::$socketReused = false;

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
