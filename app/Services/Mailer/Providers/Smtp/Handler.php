<?php

namespace FluentMail\App\Services\Mailer\Providers\Smtp;

use FluentMail\Includes\Support\Arr;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Hooks\Handlers\BulkSendSessionHandler;
use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\App\Services\Mailer\Providers\Smtp\ValidatorTrait;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    public function send()
    {
        if ($this->preSend()) {
            // The global PHPMailer persists across sends, so every
            // connection-identity property must be assigned unconditionally —
            // a previous connection's value would otherwise carry over.
            $this->phpMailer->SMTPAutoTLS = $this->getSetting('auto_tls') != 'no';
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong!', 'fluent-smtp'), []));
    }

    protected function postSend()
    {
        try {
            // FluentSMTP routes per From address, so a bulk session can switch
            // between different relays/credentials mid-run. The session handler
            // closes the kept-alive socket when this send's connection identity
            // differs from the one the socket was opened for — PHPMailer alone
            // would silently reuse the old relay's authenticated connection.
            BulkSendSessionHandler::ensureConnectionFor($this->getConnectionIdentity());

            // Keep-alive is enabled only here — for identity-declared SMTP
            // sends during a bulk session — never via a global phpmailer_init
            // hook, so no other integration's mail can ride an unguarded
            // kept-alive socket. Runs after ensureConnectionFor() so the first
            // send on a switched connection gets keep-alive again immediately.
            $this->phpMailer->SMTPKeepAlive = BulkSendSessionHandler::isActive();

            $this->phpMailer->isSMTP();

            // The shared instance keeps this transport, and this relay's host
            // and credentials, until something resets it. Flag it so a fallback
            // to PHP mail() knows to switch back.
            self::markSmtpTransportClaimed();

            $this->phpMailer->Host = $this->getSetting('host');
            $this->phpMailer->Port = $this->getSetting('port');

            if ($this->getSetting('auth') == 'yes') {
                $this->phpMailer->SMTPAuth = true;
                $this->phpMailer->Username = $this->getSetting('username');
                $this->phpMailer->Password = $this->getSetting('password');
            } else {
                // Reset on the persistent instance: without this, PHPMailer
                // would AUTH against this relay with the previous
                // connection's credentials.
                $this->phpMailer->SMTPAuth = false;
                $this->phpMailer->Username = '';
                $this->phpMailer->Password = '';
            }

            $encryption = $this->getSetting('encryption');
            $this->phpMailer->SMTPSecure = $encryption != 'none' ? $encryption : '';

            $fromEmail = $this->phpMailer->From;

            if ($this->isForcedEmail() && !fluentMailIsListedSenderEmail($fromEmail)) {
                $fromEmail = $this->getSetting('sender_email');
            }

            if (isset($this->phpMailer->FromName)) {
                $fromName = $this->phpMailer->FromName;

                if (
                    $this->getSetting('force_from_name') == 'yes' &&
                    $customFrom = $this->getSetting('sender_name')
                ) {
                    $fromName = $customFrom;
                }

                $this->phpMailer->setFrom($fromEmail, $fromName);
            } else {
                $this->phpMailer->setFrom($fromEmail);
            }

            foreach ($this->getParam('to') as $to) {
                if (isset($to['name'])) {
                    $this->phpMailer->addAddress($to['email'], $to['name']);
                } else {
                    $this->phpMailer->addAddress($to['email']);
                }
            }

            foreach ($this->getParam('headers.reply-to') as $replyTo) {
                if (isset($replyTo['name'])) {
                    $this->phpMailer->addReplyTo($replyTo['email'], $replyTo['name']);
                } else {
                    $this->phpMailer->addReplyTo($replyTo['email']);
                }
            }

            foreach ($this->getParam('headers.cc') as $cc) {
                if (isset($cc['name'])) {
                    $this->phpMailer->addCC($cc['email'], $cc['name']);
                } else {
                    $this->phpMailer->addCC($cc['email']);
                }
            }

            foreach ($this->getParam('headers.bcc') as $bcc) {
                if (isset($bcc['name'])) {
                    $this->phpMailer->addBCC($bcc['email'], $bcc['name']);
                } else {
                    $this->phpMailer->addBCC($bcc['email']);
                }
            }

            if ($attachments = $this->getParam('attachments')) {
                foreach ($attachments as $attachment) {
                    $this->phpMailer->addAttachment($attachment[0], $attachment[7]);
                }
            }

            if ($this->getParam('headers.content-type') == 'text/html' || $this->getParam('headers.content-type') == 'multipart/alternative') {
                $this->phpMailer->isHTML(true);
            }

            $this->phpMailer->Subject = $this->getSubject();

            $this->phpMailer->Body = $this->getParam('message');

            if ($this->getParam('headers.content-type') == 'multipart/alternative') {
                $this->phpMailer->AltBody = $this->getParam('alt_body');
                $this->phpMailer->ContentType = 'multipart/alternative';
            }

            $this->phpMailer->send();

            $returnResponse = [
                'response' => 'OK'
            ];

        } catch (\Exception $e) {
            $returnResponse = new \WP_Error(422, $e->getMessage(), []);

            // During a bulk session the relay may drop the kept-alive socket
            // under us (idle timeout, per-connection message cap, LB reset);
            // close it so the next attempt reconnects fresh instead of
            // failing on the same dead connection.
            if (BulkSendSessionHandler::isActive()) {
                $socketWasReused = BulkSendSessionHandler::wasSocketReused();

                BulkSendSessionHandler::closeConnection();

                // Retry THIS email once when a reused socket died under us,
                // but only when isRetrySafe() can PROVE the relay never
                // accepted the message. Fresh-connect failures (relay down,
                // bad credentials) are never retried either.
                if ($socketWasReused && $this->isRetrySafe($e)) {
                    try {
                        $this->phpMailer->send();

                        // Healthy fresh connection — re-declare its identity
                        // so following emails keep full keep-alive reuse.
                        BulkSendSessionHandler::ensureConnectionFor($this->getConnectionIdentity());

                        $returnResponse = [
                            'response' => 'OK'
                        ];
                    } catch (\Exception $retryException) {
                        BulkSendSessionHandler::closeConnection();
                        $returnResponse = new \WP_Error(422, $retryException->getMessage(), []);
                    }
                }
            }
        }

        $this->response = $returnResponse;

        return $this->handleResponse($this->response);
    }

    /**
     * Whether a failed send can be retried with zero duplicate-delivery risk.
     *
     * Only single-recipient messages (one RCPT TO across to/cc/bcc) qualify:
     * with one recipient PHPMailer never reaches the DATA stage after a
     * recipient failure, so the only failure that can follow acceptance is
     * "data not accepted" (DATA terminator sent, 250 confirmation lost) —
     * excluded here via PHPMailer's own, possibly translated, error string.
     * Multi-recipient mail is never retried: on a partial RCPT failure
     * PHPMailer still delivers to the accepted recipients BEFORE throwing
     * "recipients failed", so a retry would deliver to those twice.
     *
     * @param \Exception $e The failure thrown by PHPMailer::send().
     * @return bool
     */
    private function isRetrySafe(\Exception $e)
    {
        $recipientCount = count($this->phpMailer->getToAddresses())
            + count($this->phpMailer->getCcAddresses())
            + count($this->phpMailer->getBccAddresses());

        if ($recipientCount !== 1) {
            return false;
        }

        // PHPMailer throws its TRANSLATED strings, so match the live
        // translation table instead of hardcoding the English text.
        $dataNotAccepted = 'data not accepted';
        if (method_exists($this->phpMailer, 'getTranslations')) {
            $translations = $this->phpMailer->getTranslations();
            if (!empty($translations['data_not_accepted'])) {
                $dataNotAccepted = $translations['data_not_accepted'];
            }
        }

        return stripos($e->getMessage(), $dataNotAccepted) === false;
    }

    /**
     * Connection-identifying settings for the keep-alive session guard.
     *
     * @return array
     */
    private function getConnectionIdentity()
    {
        return [
            'host'       => $this->getSetting('host'),
            'port'       => $this->getSetting('port'),
            'username'   => $this->getSetting('username'),
            'auth'       => $this->getSetting('auth'),
            'encryption' => $this->getSetting('encryption'),
            'auto_tls'   => $this->getSetting('auto_tls'),
            // Hashed so the raw credential never sits in the fingerprint.
            // A rotated password must count as a different connection, or
            // the old authenticated session would keep being reused.
            'auth_key'   => md5((string)$this->getSetting('password')),
        ];
    }

    public function setSettings($settings)
    {
        if (Arr::get($settings, 'key_store') == 'wp_config') {
            $settings['username'] = defined('FLUENTMAIL_SMTP_USERNAME') ? FLUENTMAIL_SMTP_USERNAME : '';
            $settings['password'] = defined('FLUENTMAIL_SMTP_PASSWORD') ? FLUENTMAIL_SMTP_PASSWORD : '';
        }

        $this->settings = $settings;

        return $this;
    }
}
