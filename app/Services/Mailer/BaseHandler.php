<?php

namespace FluentMail\App\Services\Mailer;

use Exception;
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

    public function __construct(Application $app = null, Manager $manager = null)
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
        $this->attributes = [];
        
        if ($this->isForced('from_name')) {
            $this->phpMailer->FromName = $this->getSetting('sender_name');
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
        
        $contentType = $this->phpMailer->ContentType;
        
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

        return $this->attributes['extra'];
    }

    public function handleResponse($response)
    {
        if ( is_wp_error($response) ) {
            $code = $response->get_error_code();

            if (!is_numeric($code)) {
                $code = 400;
            }

            $message = $response->get_error_message();

            $errorResponse = [
                'code'    => $code,
                'message' => $message,
                'errors'  => $response->get_error_messages()
            ];

            $this->processResponse($errorResponse, false);

            throw new \PHPMailer\PHPMailer\Exception($message, $code); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        } else {
            return $this->processResponse($response, true);
        }
    }

    public function processResponse($response, $status)
    {
        if ($this->shouldBeLogged($status)) {
            $data = [
                'to' => maybe_serialize($this->attributes['to']),
                'from' => $this->attributes['from'],
                'subject' => sanitize_text_field($this->attributes['subject']),
                'body' => $this->attributes['message'],
                'attachments' => maybe_serialize($this->attributes['attachments']),
                'status'   => $status ? 'sent' : 'failed',
                'response' => maybe_serialize($response),
                'headers'  => maybe_serialize($this->getParam('headers')),
                'extra'    => maybe_serialize($this->getExtraParams())
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

                    $data['response'] = maybe_serialize( $row['response']);
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
                    do_action('fluentmail_email_sending_failed', $logId, $this, $data);
                }
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

    protected function fireWPMailFailedAction($data)
    {
        $code = is_numeric($data['code']) ? $data['code'] : 400;
        $code = strlen($code) < 3 ? 400 : $code;

        $mail_error_data['phpmailer_exception_code'] = $code;
        $mail_error_data['errors'] = $data['errors'];

        $error = new \WP_Error(
            $code, $data['message'], $mail_error_data
        );

        $this->app->doAction('wp_mail_failed', $error);
    }

    protected function updatedLog($id, $data)
    {
        try {
            $data['updated_at'] = current_time('mysql');
            (new Logger)->updateLog($data, ['id' => $id]);
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
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

}
