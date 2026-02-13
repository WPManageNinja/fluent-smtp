<?php

namespace FluentMail\App\Services\Mailer\Providers\AmazonSes;

use FluentMail\App\Models\Settings;
use FluentMail\App\Services\Mailer\BaseHandler;
use FluentMail\Includes\Support\Arr;

class Handler extends BaseHandler
{
    use ValidatorTrait;

    protected $client = null;

    const RAW_REQUEST = true;

    const TRIGGER_ERROR = false;

    public function send()
    {
        if ($this->preSend() && $this->phpMailer->preSend()) {
            $this->client = new SimpleEmailServiceMessage;
            return $this->postSend();
        }

        return $this->handleResponse(new \WP_Error(422, __('Something went wrong!', 'fluent-smtp'), []));
    }

    public function postSend()
    {
        // Base64 encode the raw MIME message for SES V2 API
        $rawMessage = base64_encode($this->phpMailer->getSentMIMEMessage());

        $connectionSettings = $this->filterConnectionVars($this->getSetting());

        $ses = fluentMailSesConnection($connectionSettings);

        // Only use tenant and configuration set if tenant feature is enabled
        $useTenant = !empty($connectionSettings['use_tenant']) && $connectionSettings['use_tenant'] === 'yes';
        $tenantName = null;
        $configurationSetName = null;
        
        if ($useTenant) {
            $tenantName = !empty($connectionSettings['tenant_name']) ? $connectionSettings['tenant_name'] : null;
            $configurationSetName = !empty($connectionSettings['configuration_set_name']) ? $connectionSettings['configuration_set_name'] : null;
        }

        try {
            $this->response = $ses->sendEmail($rawMessage, $tenantName, $configurationSetName);
            
            // Transform V2 response to match expected format
            if (isset($this->response['MessageId'])) {
                $this->response = [
                    'MessageId' => $this->response['MessageId']
                ];
            } else {
                // MessageId is missing - treat as error
                $errorMsg = isset($this->response['error']) ? $this->response['error'] : 'Email accepted but no MessageId returned';
                $this->response = new \WP_Error(422, $errorMsg, []);
            }
        } catch (\Exception $e) {
            $this->response = new \WP_Error(422, $e->getMessage(), []);
        }

        return $this->handleResponse($this->response);
    }

    protected function getFrom()
    {
        return $this->getParam('from');
    }

    public function getVerifiedEmails()
    {
        return (new Settings)->getVerifiedEmails();
    }

    protected function getReplyTo()
    {
        $replyTo = $this->getRecipients(
            $this->getParam('headers.reply-to')
        );

        if (is_array($replyTo)) {
            $replyTo = reset($replyTo);
        }

        return $replyTo;
    }

    protected function getTo()
    {
        return $this->getRecipients($this->getParam('to'));
    }

    protected function getCarbonCopy()
    {
        return $this->getRecipients($this->getParam('headers.cc'));
    }

    protected function getBlindCarbonCopy()
    {
        return $this->getRecipients($this->getParam('headers.bcc'));
    }

    protected function getRecipients($recipients)
    {
        $array = array_map(function ($recipient) {
            return isset($recipient['name'])
                ? $recipient['name'] . ' <' . $recipient['email'] . '>'
                : $recipient['email'];
        }, $recipients);

        return implode(', ', $array);
    }

    protected function getBody()
    {
        return [
            $this->phpMailer->AltBody,
            $this->phpMailer->Body
        ];
    }

    protected function getAttachments()
    {
        $attachments = [];

        foreach ($this->getParam('attachments') as $attachment) {
            $file = false;
            $fileName = null;
            $filetype = null;

            try {
                // Use secure file reading with path traversal protection
                $file = $this->secureFileRead($attachment[0]);
                $fileName = basename($attachment[0]);

                // Get MIME type from the validated real path
                $realPath = realpath($attachment[0]);
                $mimeType = mime_content_type($realPath);
                $filetype = str_replace(';', '', trim($mimeType));
            } catch (\Exception $e) {
                // Log error and skip this attachment
                error_log('FluentSMTP AmazonSes: Failed to read attachment - ' . $e->getMessage());
                $file = false;
            }

            if ($file === false) {
                continue;
            }

            $attachments[] = [
                'type'    => $filetype,
                'name'    => $fileName,
                'content' => $file
            ];
        }

        return $attachments;
    }

    protected function getCustomEmailHeaders()
    {
        $customHeaders = [
            'X-Mailer' => 'Amazon-SES'
        ];

        $headers = [];
        foreach ($customHeaders as $key => $header) {
            $headers[] = $key . ':' . $header;
        }

        return $headers;
    }

    protected function getRegion()
    {
        return 'email.' . $this->getSetting('region') . '.amazonaws.com';
    }

    public function getValidSenders($config)
    {
        $config = $this->filterConnectionVars($config);

        $senders = $this->getSendersFromMappingsAndApi($config);

        return $senders['all_senders'];
    }

    public function getValidSendingIdentities($config)
    {
        $config = $this->filterConnectionVars($config);

        $ses = new SimpleEmailServiceV2(
            $config['access_key'],
            $config['secret_key'],
            $config['region']
        );

        try {
            $identities = $ses->listEmailIdentities();
        } catch (\Exception $e) {
            return [
                'emails'          => [$config['sender_email']],
                'verified_domain' => ''
            ];
        }

        $addresses = [];
        $domains = [];

        if (isset($identities['EmailIdentities'])) {
            foreach ($identities['EmailIdentities'] as $identity) {
                $identityName = $identity['IdentityName'];
                $sendingEnabled = isset($identity['SendingEnabled']) ? $identity['SendingEnabled'] : false;
                
                // Check if it's a domain (no @ sign) or email address
                if (strpos($identityName, '@') === false) {
                    // It's a domain
                    if ($sendingEnabled) {
                        $domains[] = $identityName;
                    }
                } else {
                    // It's an email address
                    if ($sendingEnabled) {
                        $addresses[] = $identityName;
                    }
                }
            }
        }

        $primaryEmail = $config['sender_email'];
        $domainArray = explode('@', $primaryEmail);
        $domainName = $domainArray[1];

        if (apply_filters('fluent_mail_ses_primary_domain_only', true)) {
            $addresses = array_filter($addresses, function ($email) use ($domainName) {
                return !!strpos($email, $domainName);
            });
            $addresses = array_values($addresses);
        }

        return [
            'emails'          => apply_filters('fluentsmtp_ses_valid_senders', $addresses, $config),
            'verified_domain' => in_array($domainName, $domains) ? $domainName : ''
        ];
    }

    public function getConnectionInfo($connection)
    {
        $connection = $this->filterConnectionVars($connection);

        $stats = $this->getStats($connection);
        $error = '';
        if (is_wp_error($stats)) {
            $error = $stats->get_error_message();
            $stats = [];
        }

        $validSenders = $this->getSendersFromMappingsAndApi($connection);

        $info = (string)fluentMail('view')->make('admin.ses_connection_info', [
            'connection'    => $connection,
            'valid_senders' => $validSenders['all_senders'],
            'stats'         => $stats,
            'error'         => $error
        ]);

        return [
            'info'                 => $info,
            'verificationSettings' => [
                'connection_name'  => 'Amazon SES',
                'all_senders'      => $validSenders['all_senders'],
                'verified_senders' => $validSenders['verified_senders'],
                'verified_domain'  => $validSenders['verified_domain']
            ]
        ];
    }

    public function addNewSenderEmail($connection, $email)
    {
        $connection = $this->filterConnectionVars($connection);
        $validSenders = $this->getValidSendingIdentities($connection);

        $emailDomain = explode('@', $email);
        $emailDomain = $emailDomain[1];

        if ($emailDomain != $validSenders['verified_domain']) {
            return new \WP_Error(422, __('Invalid email address! Please use a verified domain.', 'fluent-smtp'));
        }

        $settings = fluentMailGetSettings();
        $mappings = Arr::get($settings, 'mappings', []);

        if (isset($mappings[$email])) {
            return new \WP_Error(422, __('Email address already exists with another connection. Please choose a different email.', 'fluent-smtp'));
        }

        $settings = get_option('fluentmail-settings');

        $settings['mappings'][$email] = md5($connection['sender_email']);

        update_option('fluentmail-settings', $settings);

        return true;
    }

    public function removeSenderEmail($connection, $email)
    {
        $connection = $this->filterConnectionVars($connection);
        $validSenders = $this->getValidSendingIdentities($connection);

        $emailDomain = explode('@', $email);
        $emailDomain = $emailDomain[1];

        if ($emailDomain != $validSenders['verified_domain']) {
            return new \WP_Error(422, __('Invalid email address! Please use a verified domain.', 'fluent-smtp'));
        }

        if (in_array($email, $validSenders['emails'])) {
            return new \WP_Error(422, __('Sorry! you can not remove this email from this connection', 'fluent-smtp'));
        }

        $settings = fluentMailGetSettings();
        $mappings = Arr::get($settings, 'mappings', []);

        if (!isset($mappings[$email])) {
            return new \WP_Error(422, __('Email does not exists. Please try again.', 'fluent-smtp'));
        }

        // check if the it's the same email or not
        if ($mappings[$email] != md5($connection['sender_email'])) {
            return new \WP_Error(422, __('Email does not exists. Please try again.', 'fluent-smtp'));
        }

        $settings = get_option('fluentmail-settings');

        unset($settings['mappings'][$email]);

        update_option('fluentmail-settings', $settings);

        return true;
    }


    private function getStats($config)
    {
        $ses = new SimpleEmailServiceV2(
            $config['access_key'],
            $config['secret_key'],
            $config['region']
        );

        try {
            $account = $ses->getAccount();
            
            if (isset($account['error'])) {
                return new \WP_Error(422, $account['error']);
            }

            // Transform V2 account info to match expected format
            $stats = [];
            if (isset($account['SendQuota'])) {
                $stats['Max24HourSend'] = isset($account['SendQuota']['Max24HourSend']) ? $account['SendQuota']['Max24HourSend'] : 0;
                $stats['SentLast24Hours'] = isset($account['SendQuota']['SentLast24Hours']) ? $account['SendQuota']['SentLast24Hours'] : 0;
                $stats['MaxSendRate'] = isset($account['SendQuota']['MaxSendRate']) ? $account['SendQuota']['MaxSendRate'] : 0;
            }
            
            return $stats;
        } catch (\Exception $e) {
            return new \WP_Error(422, $e->getMessage());
        }
    }

    private function filterConnectionVars($connection)
    {
        if ($connection['key_store'] == 'wp_config') {
            $connection['access_key'] = defined('FLUENTMAIL_AWS_ACCESS_KEY_ID') ? FLUENTMAIL_AWS_ACCESS_KEY_ID : '';
            $connection['secret_key'] = defined('FLUENTMAIL_AWS_SECRET_ACCESS_KEY') ? FLUENTMAIL_AWS_SECRET_ACCESS_KEY : '';
        }

        return $connection;
    }

    private function getSendersFromMappingsAndApi($connection)
    {
        $validSenders = $this->getValidSendingIdentities($connection);
        $verifiedDomain = Arr::get($validSenders, 'verified_domain', '');
        if ($verifiedDomain) {
            $settings = fluentMailGetSettings();
            $mappings = Arr::get($settings, 'mappings', []);

            $mapKey = md5($connection['sender_email']);
            $mapSenders = array_filter($mappings, function ($key) use ($mapKey) {
                return $key == $mapKey;
            });

            $mapSenders[$connection['sender_email']] = true;

            foreach ($validSenders['emails'] as $email) {
                $mapSenders[$email] = $email;
            }

            $mapSenders = array_keys($mapSenders);

        } else {
            $mapSenders = $validSenders['emails'];
        }

        return [
            'all_senders'      => $mapSenders,
            'verified_senders' => $validSenders['emails'],
            'verified_domain'  => $verifiedDomain
        ];
    }
}
