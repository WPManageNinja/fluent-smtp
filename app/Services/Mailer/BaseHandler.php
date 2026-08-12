<?php

namespace FluentMail\App\Services\Mailer;

use Exception;
use InvalidArgumentException;
use FluentMail\App\Models\Logger;
use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\App\Services\Mailer\ValidatorTrait;

class BaseHandler
{
    use ValidatorTrait;

    protected $app = null;
    
    protected $params = [];

    protected $manager = null;
    
    protected $phpMailer = null;

    protected $settings = [];

    protected $attributes = [];

    protected $response = null;

    protected $existing_row_id = null;

    protected $sendStartedAt = null;

    protected $sendDurationMs = null;

    /**
     * Set once this plugin's SMTP provider has pointed the shared PHPMailer
     * instance at a relay, and cleared again at the start of the next send.
     *
     * The instance persists across sends now, so a PHP mail() send that follows
     * an SMTP one — a fallback, most of all — would otherwise inherit
     * Mailer='smtp' along with that relay's host and credentials. Only that
     * carry-over may be undone: a phpmailer_init listener selecting a transport
     * for the send in progress has to be honored, so the reset is not a blanket
     * one. @see Providers\DefaultMail\Handler::postSend()
     *
     * @var bool
     */
    protected static $smtpTransportClaimed = false;

    /**
     * Record that the SMTP provider has taken over the shared PHPMailer.
     *
     * @return void
     */
    public static function markSmtpTransportClaimed()
    {
        self::$smtpTransportClaimed = true;
    }

    /**
     * Forget any earlier claim, because the transport has just been reset.
     *
     * Called from the wp_mail() replacement alongside the isMail() reset, which
     * runs before phpmailer_init, so listeners get the final word again.
     *
     * @return void
     */
    public static function forgetSmtpTransportClaim()
    {
        self::$smtpTransportClaimed = false;
    }

    public function __construct(?Application $app = null, ?Manager $manager = null)
    {
        $this->app = $app ?: fluentMail();
        $this->manager = $manager ?: fluentMail(Manager::class);
    }

    public function setPhpMailer($phpMailer)
    {
        $this->phpMailer = $phpMailer;

        if(!$this->phpMailer->CharSet) {
            $this->phpMailer->CharSet = 'UTF-8';
        }

        return $this;
    }

    public function setSettings($settings)
    {

        $this->settings = $settings;

        return $this;
    }

    protected function preSend()
    {
        /*
         * Every provider calls this first, so it is the one point that marks
         * the start of a send for all of them. What lands in the log is the
         * span from here to the provider's answer: assembling the MIME message
         * and then the round trip itself - connection, the API call or SMTP
         * conversation, and the response. It is not the time until the mail
         * reaches an inbox; that part is out of our hands.
         */
        $this->sendStartedAt = microtime(true);

        $this->attributes = [];
        
        if ($this->isForced('from_name')) {
            $this->phpMailer->FromName = $this->getSetting('sender_name');
        }

        /*
         * A display name is plain text — an entity in it has no meaning and
         * simply shows up as "Tom &amp; Jerry" in the recipient's inbox. Names
         * arrive escaped whenever they came from the site title, which
         * WordPress stores that way, whether that was our own sender_name
         * setting or a theme filtering wp_mail_from_name with the raw option.
         * Normalize once here, where the handler takes ownership of the
         * message and before any provider or the log reads the name.
         */
        if ($this->phpMailer->FromName) {
            $this->phpMailer->FromName = wp_specialchars_decode($this->phpMailer->FromName, ENT_QUOTES);
        }

        if ($this->getSetting('return_path') == 'yes') {
            $this->phpMailer->Sender = $this->phpMailer->From;
        }

        $this->attributes = $this->setAttributes();

        return true;
    }

    protected function isForced($key)
    {
        return $this->getSetting("force_{$key}") == 'yes';
    }

    public function isForcedEmail()
    {
        return $this->getSetting("force_from_email") != 'no';
    }

    public function isActive()
    {
        return $this->getSetting('is_active') == 'yes';
    }

    protected function getDefaultParams()
    {
        $timeout = (int)ini_get('max_execution_time');

        return [
            'timeout'     => $timeout ?: 30,
            'httpversion' => '1.1',
            'blocking'    => true,
        ];
    }

    protected function setAttributes()
    {
        $from = $this->setFrom();
        
        $replyTos = $this->setRecipientsArray(array_values(
            $this->phpMailer->getReplyToAddresses()
        ));
        
        /*
         * Expose the bare MIME type only. PHPMailer's ContentType can carry
         * parameters — a charset, or the multipart boundary that WP 6.9+ folds
         * into the Content-Type value instead of emitting a separate header.
         * Every provider and the logger compare this against bare types such as
         * 'text/html' and 'multipart/alternative', so a parameterized value would
         * silently fall through to the plain-text branch.
         */
        $contentType = trim(strtok((string)$this->phpMailer->ContentType, ';'));
        
        $customHeaders = $this->setFormattedCustomHeaders();

        $recipients = [
            'to' => $this->setRecipientsArray($this->phpMailer->getToAddresses()),
            'cc' => $this->setRecipientsArray($this->phpMailer->getCcAddresses()),
            'bcc' => $this->setRecipientsArray($this->phpMailer->getBccAddresses())
        ];

        return array_merge($this->attributes, [
            'from' => $from,
            'to' => $recipients['to'],
            'subject' => $this->phpMailer->Subject,
            'message' => $this->phpMailer->Body,
            'alt_body' => $this->phpMailer->AltBody,
            'attachments' => $this->phpMailer->getAttachments(),
            'custom_headers' => $customHeaders,
            'headers' => [
                'reply-to' => $replyTos,
                'cc' => $recipients['cc'],
                'bcc' => $recipients['bcc'],
                'content-type' => $contentType
            ]
        ]);
    }

    protected function setFrom()
    {
        $name = $this->getSetting('sender_name');
        $email = $this->getSetting('sender_email');
        $overrideName = $this->getSetting('force_from_name');

        if ($name && ($overrideName == 'yes' || $this->phpMailer->FromName == 'WordPress')) {
            $this->attributes['sender_name'] = $name;
            $this->attributes['sender_email'] = $email;
            $from = $name . ' <' . $email . '>';
        } elseif ($this->phpMailer->FromName) {
            $this->attributes['sender_email'] = $email;
            $this->attributes['sender_name'] = $this->phpMailer->FromName;
            $from = $this->phpMailer->FromName . ' <' . $email . '>';
        } else {
            $from = $this->attributes['sender_email'] = $email;
        }

        return $from;
    }

    protected function setRecipientsArray($array)
    {
        $recipients = [];

        foreach ($array as $key => $recipient) {
            $recipient = array_filter($recipient);

            if (!$recipient) continue;
            
            $recipients[$key] = [
                'email' => array_shift($recipient)
            ];

            if ($recipient) {
                $recipients[$key]['name'] = array_shift($recipient);
            }
        }

        return $recipients;
    }

    protected function setFormattedCustomHeaders()
    {
        $headers = [];

        $customHeaders = $this->phpMailer->getCustomHeaders();

        foreach ($customHeaders as $key => $header) {
            if ($header[0] == 'Return-Path') {
                if ($this->getSetting('options.return_path') == 'no') {
                    if (!empty($header[1])) {
                        $this->phpMailer->Sender = $header[1];
                    }
                }
                unset($customHeaders[$key]);
            } else {
                $headers[] = [
                    'key' => $header[0],
                    'value' => $header[1]
                ];
            }
        }

        $this->phpMailer->clearCustomHeaders();

        foreach ($customHeaders as $customHeader) {
            $this->phpMailer->addCustomHeader($customHeader[0], $customHeader[1]);
        }

        return $headers;
    }

    /**
     * Repair List-Unsubscribe / List-Unsubscribe-Post headers in a raw MIME message.
     *
     * PHPMailer RFC-2047-encodes a custom header whose value is longer than it
     * can fit on one line. Our wp_mail() replacement leaves PHPMailer in mail()
     * mode, so that limit is MAIL_MAX_LINE_LENGTH (63) minus the encoded-word
     * overhead — only 47 characters. Every realistic unsubscribe URL is longer
     * than that and therefore ships encoded.
     *
     * Encoded-words are not valid inside structured headers such as
     * List-Unsubscribe, so Gmail ignores the header entirely when it arrives
     * encoded, silently breaking RFC 8058 one-click unsubscribe that Gmail and
     * Yahoo require from bulk senders.
     *
     * Providers that hand the fully built message to the API can repair the
     * headers here: unfold each target header, decode any encoded-words, and
     * re-emit it as a plain single line (re-folded at a URI boundary only if it
     * would otherwise exceed the RFC 5322 line limit).
     *
     * Any header we cannot rewrite safely is left exactly as PHPMailer built
     * it — a missing unsubscribe button is a far better outcome than a message
     * the provider rejects.
     *
     * @param string $rawMessage The message from PHPMailer::getSentMIMEMessage()
     * @return string
     */
    protected function normalizeListHeaders($rawMessage)
    {
        if (stripos($rawMessage, 'List-Unsubscribe') === false) {
            return $rawMessage;
        }

        // Work on the top-level header block only.
        $breakPos = strpos($rawMessage, "\r\n\r\n");

        if ($breakPos === false) {
            $breakPos = strpos($rawMessage, "\n\n");
        }

        if ($breakPos === false) {
            return $rawMessage;
        }

        $headerBlock = substr($rawMessage, 0, $breakPos);
        $rest = substr($rawMessage, $breakPos);

        $lineEnding = (strpos($headerBlock, "\r\n") !== false) ? "\r\n" : "\n";

        $fixedHeaderBlock = preg_replace_callback(
            '/^(List-Unsubscribe(?:-Post)?)[ \t]*:[ \t]*([^\r\n]*(?:\r?\n[ \t][^\r\n]*)*)/mi',
            function ($matches) use ($lineEnding) {
                $name = $matches[1];

                // Unfold continuation lines.
                $value = preg_replace('/\r?\n[ \t]+/', ' ', $matches[2]);

                // Decode RFC 2047 encoded-words if present.
                if (strpos($value, '=?') !== false) {
                    $decoded = false;

                    if (function_exists('iconv_mime_decode')) {
                        $decoded = @iconv_mime_decode(
                            $value,
                            ICONV_MIME_DECODE_CONTINUE_ON_ERROR,
                            'UTF-8'
                        );
                    }

                    if (($decoded === false || $decoded === null) && function_exists('mb_decode_mimeheader')) {
                        $decoded = mb_decode_mimeheader($value);
                    }

                    if ($decoded !== false && $decoded !== null && $decoded !== '') {
                        $value = $decoded;
                    }
                }

                $value = preg_replace('/[ \t]+/', ' ', trim($value));

                // Decoding is what makes an encoded-word unnecessary in the
                // first place, so it must produce a header that is legal
                // unencoded: printable US-ASCII, no control characters and no
                // line breaks. A percent-escaped URL always is. Anything else
                // (an IDN host, a unicode query value, a decode that went
                // wrong) has to stay encoded or we would emit raw 8-bit bytes
                // into the header block and risk the provider rejecting the
                // whole message.
                if (preg_match('/[^\x20-\x7E]/', $value)) {
                    return $matches[0];
                }

                $headerLine = $name . ': ' . $value;

                // RFC 5322 caps a line at 998 characters. Fold at the comma
                // between URIs, which is a legal fold point.
                if (strlen($headerLine) > 990) {
                    $headerLine = $name . ': ' . preg_replace(
                        '/>,\s*</',
                        '>,' . $lineEnding . ' <',
                        $value
                    );
                }

                // A single URI longer than the limit has no fold point, so the
                // rewrite would produce an over-long line. Keep the original.
                foreach (explode($lineEnding, $headerLine) as $line) {
                    if (strlen($line) > 998) {
                        return $matches[0];
                    }
                }

                return $headerLine;
            },
            $headerBlock
        );

        if ($fixedHeaderBlock === null) {
            return $rawMessage; // regex failure — send untouched
        }

        return $fixedHeaderBlock . $rest;
    }

    public function getSetting($key = null, $default = null)
    {
        try {
            return $key ? Arr::get($this->settings, $key, $default) : $this->settings;
        } catch (Exception $e) {
            return $default;
        }
    }

    protected function getParam($key = null, $default = null)
    {
        try {
            return $key ? Arr::get($this->attributes, $key, $default) : $this->attributes;
        } catch (Exception $e) {
            return $default;
        }
    }

    protected function getHeader($key, $default = null)
    {
        try {
            return Arr::get(
                $this->attributes['headers'], $key, $default
            );
        } catch (Exception $e) {
            return $default;
        }
    }

    public function getSubject()
    {
        $subject = '';

        if (isset($this->attributes['subject'])) {
            $subject = $this->attributes['subject'];
        }

        return $subject;
    }

    protected function getExtraParams()
    {
        $this->attributes['extra']['provider'] = $this->getSetting('provider');

        if ($this->sendDurationMs !== null) {
            $this->attributes['extra']['send_time_ms'] = $this->sendDurationMs;
        }

        return $this->attributes['extra'];
    }

    public function handleResponse($response)
    {
        // Stopped here rather than in writeLog() so the figure is the provider
        // round trip, not the round trip plus however long the database took.
        // Failed sends are timed too — a send that takes ten seconds to fail is
        // worth seeing.
        if ($this->sendStartedAt) {
            $this->sendDurationMs = round((microtime(true) - $this->sendStartedAt) * 1000, 1);
        }

        if ( is_wp_error($response) ) {
            $code = $response->get_error_code();

            if (!is_numeric($code)) {
                $code = 400;
            }

            $message = $response->get_error_message();

            $errorResponse = [
                'code'    => $code,
                'message' => $message,
                'errors'  => $response->get_error_data()
            ];

            $status = $this->processResponse($errorResponse, false);

            if ( !$status ) {
                throw new \PHPMailer\PHPMailer\Exception($message, $code); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            } else {
                return $status;
            }

        } else {
            return $this->processResponse($response, true);
        }
    }

    public function processResponse($response, $status)
    {
        if ($this->shouldBeLogged($status)) {
            try {
                return $this->writeLog($response, $status);
            } catch (\Throwable $e) {
                /*
                 * Logging runs after the provider has already accepted (or
                 * rejected) the message, so a failure here must never change the
                 * outcome of a send that already happened. Throwable rather than
                 * Exception: the database layer can raise engine-level \Errors —
                 * a missing PHP extension, for instance — which would otherwise
                 * escape and kill the request after the email had gone out,
                 * leaving the caller with no response at all.
                 */
                fluentMailDebugLog('Failed to write email log - ' . $e->getMessage());
            }
        }

        return $status;
    }

    /**
     * Persist the email log row for a completed send.
     *
     * @param mixed $response Provider response or error payload.
     * @param bool $status Whether the send succeeded.
     * @return bool
     */
    protected function writeLog($response, $status)
    {
        $data = [
            'to' => $this->serialize($this->attributes['to']),
            'from' => $this->attributes['from'],
            'subject' => sanitize_text_field($this->attributes['subject']),
            'body' => $this->attributes['message'],
            'attachments' => $this->serialize($this->attributes['attachments']),
            'status'   => $status ? 'sent' : 'failed',
            'response' => $this->serialize($response),
            'headers'  => $this->serialize($this->getParam('headers')),
            'extra'    => $this->serialize($this->getExtraParams())
        ];

        if($this->existing_row_id) {
            $row = (new Logger())->find($this->existing_row_id);
            if($row) {
                $row['response'] = (array) $row['response'];
                if($status) {
                    $row['response']['fallback'] = __('Sent using fallback connection ', 'fluent-smtp') . $this->attributes['from'];
                    $row['response']['fallback_response'] = $response;
                } else {
                    $row['response']['fallback'] = __('Tried to send using fallback but failed. ', 'fluent-smtp') . $this->attributes['from'];
                    $row['response']['fallback_response'] = $response;
                }

                $data['response'] = $this->serialize( $row['response']);
                $data['retries'] = $row['retries'] + 1;
                (new Logger())->updateLog($data, ['id' => $row['id']]);

                if(!$status) {
                    do_action('fluentmail_email_sending_failed_no_fallback', $row['id'], $this, $data);
                }
            }
        } else {
            $logId = (new Logger)->add($data);
            if(!$status) {
                // We have to fire an action for this failed job
                $status = apply_filters('fluentmail_email_sending_failed', $status, $logId, $this, $data);
            }
        }

        return $status;
    }

    protected function shouldBeLogged($status)
    {
        if($this->existing_row_id) {
            return true;
        }
        if (defined('FLUENTMAIL_LOG_OFF') && FLUENTMAIL_LOG_OFF) {
            return false;
        }

        if (!$status) {
            return true;
        }

        $miscSettings = $this->manager->getConfig('misc');
        $isLogOn = $miscSettings['log_emails'] == 'yes';

        return apply_filters('fluentmail_will_log_email', $isLogOn, $miscSettings, $this);
    }

    protected function serialize(array $data)
    {
        return serialize($this->recursiveSanitize($data));
    }

    protected function recursiveSanitize(array $data)
    {
        foreach ($data as $key => $item) {

            if (is_array($item)) {
                // Reassign the sanitized array back; without this, nested values
                // (e.g. recipient display names in `to`) bypass sanitization.
                $data[$key] = $this->recursiveSanitize($item);
                continue;
            }

            if (is_object($item) || is_resource($item)) {
                throw new InvalidArgumentException(
                    "Invalid Data: Array cannot contain an object or resource."
                );
            }

            if (is_string($item)) {
                if (is_serialized($item)) {
                    throw new InvalidArgumentException(
                        "Invalid Data: Array cannot contain serialized data."
                    );
                }

                if (filter_var($item, FILTER_VALIDATE_EMAIL)) {
                    $data[$key] = sanitize_email($item);
                } elseif (filter_var($item, FILTER_VALIDATE_URL)) {
                    $data[$key] = esc_url_raw($item);
                } else {
                    $data[$key] = sanitize_text_field($item);
                }
            }
        }

        return $data;
    }

    public function getValidSenders($connection)
    {
        return [$connection['sender_email']];
    }

    public function checkConnection($connection)
    {
        return true;
    }

    public function getConnectionInfo($connection)
    {
        return [
            'info' => (string) fluentMail('view')->make('admin.general_connection_info', [
                'connection' => $connection
            ])
        ];
    }

    public function getPhpMailer()
    {
        return $this->phpMailer;
    }

    public function setRowId($id)
    {
        $this->existing_row_id = $id;
    }

    public function addNewSenderEmail($connection, $email)
    {
        return new \WP_Error('not_implemented', __('Not implemented', 'fluent-smtp'));
    }

    public function removeSenderEmail($connection, $email)
    {
        return new \WP_Error('not_implemented', __('Not implemented', 'fluent-smtp'));
    }

    /**
     * Record an attachment that could not be read, then let the send continue.
     *
     * @param string     $provider Provider label used in the log line.
     * @param \Exception $e        The failure from secureFileRead().
     * @return void
     */
    protected function logAttachmentFailure($provider, $e)
    {
        fluentMailDebugLog(sprintf(
            '%s: Failed to read attachment - %s',
            $provider,
            $e->getMessage()
        ));
    }

    /**
     * Securely read file contents with path traversal protection
     *
     * @param string $filePath The file path to read
     * @return string File contents on success
     * @throws Exception If path validation fails or file cannot be read
     */
    protected function secureFileRead($filePath)
    {
        // Resolve the real path to prevent path traversal attacks
        $realPath = realpath($filePath);

        // Validate that realpath returned a valid path
        if ($realPath === false) {
            throw new Exception(
                sprintf(__('Invalid file path: %s', 'fluent-smtp'), esc_html($filePath))
            );
        }

        // Verify it's actually a file (not a directory)
        if (!is_file($realPath)) {
            throw new Exception(
                sprintf(__('Path is not a file: %s', 'fluent-smtp'), esc_html($realPath))
            );
        }

        // Verify the file is readable
        if (!is_readable($realPath)) {
            throw new Exception(
                sprintf(__('File is not readable: %s', 'fluent-smtp'), esc_html($realPath))
            );
        }

        // Security check: Block access to sensitive system directories
        // This prevents reading sensitive files while allowing plugin/theme attachments
        $blockedPaths = [
            '/etc/',
            '/proc/',
            '/sys/',
            '/dev/',
            '/root/',
            ABSPATH . 'wp-config.php',
            dirname(ABSPATH) . '/wp-config.php',  // WordPress often stores wp-config.php one level up
            ABSPATH . '.htaccess',
        ];

        // Allow developers to customize blocked paths via filter
        $blockedPaths = apply_filters('fluentsmtp_attachment_blocked_paths', $blockedPaths);

        // Validate filter return to prevent fatal errors
        if (!is_array($blockedPaths)) {
            $blockedPaths = [];
        } else {
            // Ensure all entries are strings
            $blockedPaths = array_values(array_filter($blockedPaths, 'is_string'));
        }

        foreach ($blockedPaths as $blockedPath) {
            $blockedRealPath = realpath($blockedPath);
            if ($blockedRealPath && strpos($realPath, $blockedRealPath) === 0) {
                throw new Exception(
                    __('Access to this file location is restricted for security reasons', 'fluent-smtp')
                );
            }
        }

        // Read and return file contents
        $contents = file_get_contents($realPath);

        if ($contents === false) {
            throw new Exception(
                sprintf(__('Failed to read file: %s', 'fluent-smtp'), esc_html($realPath))
            );
        }

        return $contents;
    }

}
