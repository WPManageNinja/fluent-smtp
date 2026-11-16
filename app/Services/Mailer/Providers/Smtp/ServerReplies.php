<?php

namespace FluentMail\App\Services\Mailer\Providers\Smtp;

use PHPMailer\PHPMailer\SMTP;

/**
 * PHPMailer's SMTP connection, keeping what the server said when a send fails.
 *
 * PHPMailer reports SMTP failures with its own fixed sentences, such as
 * "Could not authenticate.", and the server's reply is lost on the way: after
 * a failed login PHPMailer sends QUIT, whose reply replaces the one that
 * explained the failure, and PHP's connection warnings (TLS, DNS) are
 * replaced by "Failed to connect to server". This class notes them as they
 * happen so the SMTP handler can add the server's words to its message.
 * A refusal in a 554 greeting is noted the same way, at the QUIT that follows,
 * and so is a refused recipient, which PHPMailer moves past to the next one:
 * a server that closes the connection after too many errors gives its 421
 * there, and the commands that follow overwrite it.
 *
 * Only what the server sends, PHPMailer's own errors and PHP's connection
 * warnings are kept. What we send, the login included, goes out through
 * client_send(), which is not touched here.
 *
 * Installed with PHPMailer::setSMTPInstance(). It relies on connect(),
 * recipient() and quit() (public) and errorHandler() (protected), the same in
 * PHPMailer 6.9.1 and 7.1.1; if they change, nothing is recorded and the
 * message stays PHPMailer's own.
 */
class ServerReplies extends SMTP
{
    /**
     * PHPMailer's error and the server's last reply, noted at a refused recipient or before QUIT, last three.
     *
     * @var array<int, array{error: array, reply: string}>
     */
    protected $refusals = [];

    /**
     * PHP warnings since the last connection attempt, last five.
     *
     * @var string[]
     */
    protected $connectionWarnings = [];

    /**
     * Start a connection attempt with no warnings from an earlier one (another host, an earlier send).
     *
     * @param string $host
     * @param int    $port
     * @param int    $timeout
     * @param array  $options
     * @return bool
     */
    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        $this->connectionWarnings = [];

        return parent::connect($host, $port, $timeout, $options);
    }

    /**
     * Note a recipient the server refused before the next command's reply replaces it.
     *
     * Only a refusal the server sent (4xx or 5xx) is noted: after a server hangs
     * up, PHPMailer still tries every remaining recipient, and those errors (an
     * empty reply, then "not connected") would push the server's closing reply
     * out of the list, or stand in for a refusal the server never sent.
     *
     * @param string $address
     * @param string $dsn
     * @return bool
     */
    public function recipient($address, $dsn = '')
    {
        $accepted = parent::recipient($address, $dsn);

        if (!$accepted && (int) $this->getError()['smtp_code'] >= 400) {
            $this->noteState();
        }

        return $accepted;
    }

    /**
     * Note PHPMailer's error and the server's reply before QUIT's own reply replaces them.
     *
     * @param bool $close_on_error
     * @return bool
     */
    public function quit($close_on_error = true)
    {
        $this->noteState();

        return parent::quit($close_on_error);
    }

    /**
     * Note a PHP warning PHPMailer catches, such as a TLS certificate failure while connecting.
     *
     * @param int    $errno
     * @param string $errmsg
     * @param string $errfile
     * @param int    $errline
     * @return void
     */
    protected function errorHandler($errno, $errmsg, $errfile = '', $errline = 0)
    {
        $this->connectionWarnings[] = (string) $errmsg;
        $this->connectionWarnings = array_slice($this->connectionWarnings, -5);

        parent::errorHandler($errno, $errmsg, $errfile, $errline);
    }

    /**
     * Note PHPMailer's error and the server's last reply.
     *
     * @return void
     */
    protected function noteState()
    {
        $this->refusals[] = ['error' => $this->getError(), 'reply' => (string) $this->getLastReply()];
        $this->refusals = array_slice($this->refusals, -3);
    }

    /**
     * Forget everything from earlier sends, PHPMailer's own last error and reply included.
     *
     * Not named reset(): that is PHPMailer's RSET command.
     *
     * @return void
     */
    public function clearRecorded()
    {
        $this->refusals = [];
        $this->connectionWarnings = [];
        $this->setError('');
        $this->last_reply = '';
    }

    /**
     * The failure message with the server's reason added, unless it already holds it.
     *
     * @param string   $message PHPMailer's message.
     * @param string[] $secrets Values that must not appear in a log or an alert (the login as sent, base64).
     * @return string
     */
    public function withReason($message, array $secrets = [])
    {
        list($code, $detail) = $this->failureReason();

        // A server that echoes its input could put the login, as sent, in its reply.
        foreach ($secrets as $secret) {
            if ('' !== (string) $secret && false !== strpos((string) $detail, (string) $secret)) {
                return $message;
            }
        }

        // A reply that is not valid UTF-8 is left out (stripping it raises a notice before WordPress 6.9).
        $detail = trim(preg_replace('/\s+/', ' ', (string) wp_check_invalid_utf8((string) $detail)));

        if ('' === $detail) {
            return $message;
        }

        // PHPMailer's own message already carries the detail for some replies (550, 553).
        if (false !== stripos(preg_replace('/\s+/', ' ', $message), $detail)) {
            return $message;
        }

        return trim($message . ' ' . trim($code . ' ' . $detail));
    }

    /**
     * The reason for the last failure, as [code, detail].
     *
     * @return array{0: string, 1: string}
     */
    protected function failureReason()
    {
        $error = $this->getError();

        // The latest refusal, still current: sender, message after DATA.
        if ('Failed to connect to server' !== $error['error'] && (int) $error['smtp_code'] >= 400) {
            return [trim($error['smtp_code'] . ' ' . $error['smtp_code_ex']), $error['detail']];
        }

        // A refusal noted earlier: login and STARTTLS (before QUIT), a 554 greeting, a refused recipient, a closing 421.
        foreach (array_reverse($this->refusals) as $noted) {
            if ((int) $noted['error']['smtp_code'] >= 400) {
                return [trim($noted['error']['smtp_code'] . ' ' . $noted['error']['smtp_code_ex']), $noted['error']['detail']];
            }
            if ('' === $noted['error']['error'] && $refusal = self::refusalIn($noted['reply'])) {
                return $refusal;
            }
        }

        // No server refusal: PHPMailer's detail (a STARTTLS certificate failure) or its own reason.
        $last = end($this->refusals);
        if ($last && ('' !== trim((string) $last['error']['detail']) || '' !== trim((string) $last['error']['error']))) {
            return ['', '' !== trim((string) $last['error']['detail']) ? $last['error']['detail'] : $last['error']['error']];
        }

        // The connection itself failed: refused, DNS, timed out.
        if ('Failed to connect to server' === $error['error'] && '' !== trim((string) $error['smtp_code_ex'])) {
            return ['', $error['smtp_code_ex']];
        }

        // A 421 greeting, which PHPMailer closes on without QUIT or an error.
        if ($refusal = self::refusalIn((string) $this->getLastReply())) {
            return $refusal;
        }

        // PHP's own warning from the last connection attempt: TLS on a plain port, an untrusted certificate on implicit SSL.
        return ['', $this->connectionWarnings ? $this->connectionWarnings[0] : ''];
    }

    /**
     * A server reply split the way PHPMailer splits one, when it is a refusal (4xx or 5xx).
     *
     * @param string $reply
     * @return array{0: string, 1: string}|null [code with enhanced code, text], or null.
     */
    protected static function refusalIn($reply)
    {
        if (!preg_match('/^([45]\d\d)[ -](?:(\d\.\d{1,3}\.\d{1,3}) )?/', $reply, $match)) {
            return null;
        }

        $codeEx = isset($match[2]) ? $match[2] : '';
        $text = preg_replace('/^' . $match[1] . '[ -]' . ('' !== $codeEx ? preg_quote($codeEx, '/') . ' ' : '') . '/m', '', $reply);

        return [trim($match[1] . ' ' . $codeEx), $text];
    }
}
