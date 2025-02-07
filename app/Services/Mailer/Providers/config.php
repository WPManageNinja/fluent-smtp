<?php

return [
    'connections' => [],
    'mappings'    => [],
    'providers'   => [
        'smtp'        => [
            'key'      => 'smtp',
            'title'    => __('SMTP server', 'fluent-smtp'),
            'image'    => fluentMailAssetUrl('images/provider-smtp.svg'),
            'provider' => 'Smtp',
            'need_pro' => 'no',
            'is_smtp'  => true,
            'options'  => [
                'sender_name'      => '',
                'sender_email'     => '',
                'force_from_name'  => 'no',
                'force_from_email' => 'yes',
                'return_path'      => 'yes',
                'host'             => '',
                'port'             => '',
                'auth'             => 'yes',
                'username'         => '',
                'password'         => '',
                'auto_tls'         => 'yes',
                'encryption'       => 'none',
                'key_store'        => 'db'
            ],
            'note'     => '<a href="https://fluentsmtp.com/docs/set-up-fluent-smtp-with-any-host-or-mailer/" target="_blank" rel="noopener">' . __('Read the documentation', 'fluent-smtp') . '</a>' . __(' for how to configure any SMTP with FluentSMTP.', 'fluent-smtp')
        ],
        'ses'         => [
            'key'      => 'ses',
            'title'    => __('Amazon SES', 'fluent-smtp'),
            'image'    => fluentMailAssetUrl('images/provider-aws-ses.svg'),
            'provider' => 'AmazonSes',
            'options'  => [
                'sender_name'      => '',
                'sender_email'     => '',
                'force_from_name'  => 'no',
                'force_from_email' => 'yes',
                'return_path'      => 'yes',
                'access_key'       => '',
                'secret_key'       => '',
                'region'           => 'us-east-1',
                'key_store'        => 'db'
            ],
            'regions'  => [
                'us-east-1'      => __('US East (N. Virginia)', 'fluent-smtp'),
                'us-east-2'      => __('US East (Ohio)', 'fluent-smtp'),
                'us-west-1'      => __('US West (N. California)', 'fluent-smtp'),
                'us-west-2'      => __('US West (Oregon)', 'fluent-smtp'),
                'ca-central-1'   => __('Canada (Central)', 'fluent-smtp'),
                'eu-west-1'      => __('EU (Ireland)', 'fluent-smtp'),
                'eu-west-2'      => __('EU (London)', 'fluent-smtp'),
                'eu-west-3'      => __('Europe (Paris)', 'fluent-smtp'),
                'eu-central-1'   => __('EU (Frankfurt)', 'fluent-smtp'),
                'eu-south-1'     => __('Europe (Milan)', 'fluent-smtp'),
                'eu-north-1'     => __('Europe (Stockholm)', 'fluent-smtp'),
                'ap-south-1'     => __('Asia Pacific (Mumbai)', 'fluent-smtp'),
                'ap-northeast-2' => __('Asia Pacific (Seoul)', 'fluent-smtp'),
                'ap-southeast-1' => __('Asia Pacific (Singapore)', 'fluent-smtp'),
                'ap-southeast-2' => __('Asia Pacific (Sydney)', 'fluent-smtp'),
                'ap-northeast-1' => __('Asia Pacific (Tokyo)', 'fluent-smtp'),
                'sa-east-1'      => __('South America (São Paulo)', 'fluent-smtp'),
                'me-south-1'     => __('Middle East (Bahrain)', 'fluent-smtp'),
                'us-gov-west-1'  => __('AWS GovCloud (US)', 'fluent-smtp'),
                'af-south-1'     => __('Africa (Cape Town)', 'fluent-smtp'),
                'cn-northwest-1' => __('China (Ningxia)', 'fluent-smtp')
            ],
            'note'     => '<a href="https://fluentsmtp.com/docs/set-up-amazon-ses-in-fluent-smtp/" target="_blank" rel="noopener">' . __('Read the documentation', 'fluent-smtp') . '</a>' . __(' for how to configure Amazon SES with FluentSMTP.', 'fluent-smtp')
        ],
        'mailgun'     => [
            'key'      => 'mailgun',
            'title'    => __('Mailgun', 'fluent-smtp'),
            'image'    => fluentMailAssetUrl('images/provider-mailgun.svg'),
            'provider' => 'Mailgun',
            'options'  => [
                'sender_name'     => '',
                'sender_email'    => '',
                'force_from_name' => 'no',
                'return_path'     => 'yes',
                'api_key'         => '',
                'domain_name'     => '',
                'key_store'       => 'db',
                'region'          => 'us'
            ],
            'note'     => '<a href="https://fluentsmtp.com/docs/configure-mailgun-in-fluent-smtp-to-send-emails/" target="_blank" rel="noopener">' . __('Read the documentation', 'fluent-smtp') . '</a>' . __(' for how to configure Mailgun with FluentSMTP.', 'fluent-smtp')
        ],
        'sendgrid'    => [
            'key'      => 'sendgrid',
            'title'    => __('SendGrid', 'fluent-smtp'),
            'image'    => fluentMailAssetUrl('images/provider-sendgrid.svg'),
            'provider' => 'SendGrid',
            'options'  => [
                'sender_name'     => '',
                'sender_email'    => '',
                'force_from_name' => 'no',
                'api_key'         => '',
                'key_store'       => 'db'
            ],
            'note'     => '<a href="https://fluentsmtp.com/docs/set-up-the-sendgrid-driver-in-fluent-smtp/" target="_blank" rel="noopener">' . __('Read the documentation', 'fluent-smtp') . '</a>' . __(' for how to configure SendGrid with FluentSMTP.', 'fluent-smtp')
        ],
        'sendinblue'  => [
            'key'      => 'sendinblue',
            'title'    => __('Sendinblue', 'fluent-smtp'),
            'image'    => fluentMailAssetUrl('images/provider-sendinblue.svg'),
            'provider' => 'SendInBlue',
            'options'  => [
                'sender_name'     => '',
                'sender_email'    => '',
                'force_from_name' => 'no',
                'api_key'         => '',
                'key_store'       => 'db'
            ],
            'note'     => '<a href="https://fluentsmtp.com/docs/setting-up-sendinblue-mailer-in-fluent-smtp/" target="_blank" rel="noopener">' . __('Read the documentation', 'fluent-smtp') . '</a>' . __(' for how to configure Sendinblue with FluentSMTP.', 'fluent-smtp')
        ],
        'sparkpost'   => [
            'key'      => 'sparkpost',
            'title'    => __('SparkPost', 'fluent-smtp'),
            'image'    => fluentMailAssetUrl('images/provider-sparkpost.svg'),
            'provider' => 'SparkPost',
            'options'  => [
                'sender_name'     => '',
                'sender_email'    => '',
                'force_from_name' => 'no',
                'api_key'         => '',
                'key_store'       => 'db'
            ],
            'note'     => '<a href="https://fluentsmtp.com/docs/configure-sparkpost-in-fluent-smtp-to-send-emails/" target="_blank" rel="noopener">' . __('Read the documentation', 'fluent-smtp') . '</a>' . __(' for how to configure SparkPost with FluentSMTP.', 'fluent-smtp')
        ],
        'pepipost'    => [
            'key'      => 'pepipost',
            'title'    => __('Netcore Email API, formerly Pepipost', 'fluent-smtp'),
            'image'    => fluentMailAssetUrl('images/provider-netcore.svg'),
            'provider' => 'PepiPost',
            'options'  => [
                'sender_name'     => '',
                'sender_email'    => '',
                'force_from_name' => 'no',
                'api_key'         => '',
                'key_store'       => 'db'
            ],
            'note'     => '<a href="https://fluentsmtp.com/docs/set-up-the-pepipost-mailer-in-fluent-smtp/" target="_blank" rel="noopener">' . __('Read the documentation', 'fluent-smtp') . '</a>' . __(' for how to configure Netcore (formerly Pepipost) with FluentSMTP.', 'fluent-smtp')
        ],
        'postmark'    => [
            'key'      => 'postmark',
            'title'    => __('Postmark', 'fluent-smtp'),
            'image'    => fluentMailAssetUrl('images/provider-postmark.svg'),
            'provider' => 'Postmark',
            'options'  => [
                'sender_name'     => '',
                'sender_email'    => '',
                'force_from_name' => 'no',
                'track_opens'     => 'no',
                'track_links'     => 'no',
                'api_key'         => '',
                'message_stream'  => 'outbound',
                'key_store'       => 'db'
            ],
            'note'     => '<a href="https://fluentsmtp.com/docs/configure-postmark-in-fluent-smtp-to-send-emails/" target="_blank" rel="noopener">' . __('Read the documentation', 'fluent-smtp') . '</a>' . __(' for how to configure Postmark with FluentSMTP.', 'fluent-smtp')
        ],
        'elasticmail' => [
            'key'      => 'elasticmail',
            'title'    => __('Elastic Email', 'fluent-smtp'),
            'image'    => fluentMailAssetUrl('images/provider-elastic-email.svg'),
            'provider' => 'ElasticMail',
            'options'  => [
                'sender_name'     => '',
                'sender_email'    => '',
                'force_from_name' => 'no',
                'api_key'         => '',
                'mail_type'       => 'transactional',
                'key_store'       => 'db'
            ],
            'note'     => '<a href="https://fluentsmtp.com/docs/configure-elastic-email-in-fluent-smtp/" target="_blank" rel="noopener">' . __('Read the documentation', 'fluent-smtp') . '</a>' . __(' for how to configure Elastic Email with FluentSMTP.', 'fluent-smtp')
        ],
        'smtp2go'    => [
          'key'      => 'smtp2go',
          'title'    => __('SMTP2GO', 'fluent-smtp'),
          'image'    => fluentMailAssetUrl('images/provider-smtp2go.svg'),
          'provider' => 'Smtp2Go',
          'options'  => [
            'sender_name'     => '',
            'sender_email'    => '',
            'force_from_name' => 'no',
            'api_key'         => '',
            'key_store'       => 'db'
          ]
        ],
        'gmail'       => [
            'key'      => 'gmail',
            'title'    => __('Gmail or Google Workspace', 'fluent-smtp'),
            'image'    => fluentMailAssetUrl('images/provider-gmail-google-workspace.svg'),
            'provider' => 'Gmail',
            'options'  => [
                'sender_name'     => '',
                'sender_email'    => '',
                'force_from_name' => 'no',
                'return_path'     => 'yes',
                'key_store'       => 'db',
                'client_id'       => '',
                'client_secret'   => '',
                'auth_token'      => '',
                'access_token'    => '',
                'refresh_token'   => ''
            ],
            'note'     => __('Gmail/Google Workspace is not recommended for sending mass marketing emails.', 'fluent-smtp')
        ],
        'outlook'     => [
            'key'      => 'outlook',
            'title'    => __('Outlook or Office 365', 'fluent-smtp'),
            'image'    => fluentMailAssetUrl('images/provider-microsoft.svg'),
            'provider' => 'Outlook',
            'options'  => [
                'sender_name'     => '',
                'sender_email'    => '',
                'force_from_name' => 'no',
                'return_path'     => 'yes',
                'key_store'       => 'db',
                'client_id'       => '',
                'client_secret'   => '',
                'auth_token'      => '',
                'access_token'    => '',
                'refresh_token'   => ''
            ],
            'note'     => __('Outlook/Office365 is not recommended for sending mass marketing emails.', 'fluent-smtp')
        ],
        'default'     => [
            'key'      => 'default',
            'title'    => __('PHP mail()', 'fluent-smtp'),
            'image'    => fluentMailAssetUrl('images/provider-php.svg'),
            'provider' => 'DefaultMail',
            'options'  => [
                'sender_name'      => '',
                'sender_email'     => '',
                'force_from_name'  => 'no',
                'force_from_email' => 'yes',
                'return_path'      => 'yes',
                'key_store'        => 'db'
            ],
            'note'     => __('The Default option does not use SMTP or any Email Service Providers so it will not improve email delivery on your site.', 'fluent-smtp')
        ],
    ],
    'misc'        => [
        'log_emails'              => 'yes',
        'log_saved_interval_days' => '14',
        'disable_fluentcrm_logs'  => 'no',
        'default_connection'      => '',
        'fallback_connection'     => ''
    ]
];
