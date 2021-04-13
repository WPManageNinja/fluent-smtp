<?php

return [
    'connections' => [],
    'mappings' => [],
    'providers' => [
        'smtp' => [
            'key' => 'smtp',
            'title' => __('SMTP', 'fluent-smtp'),
            'image' => fluentMailAssetUrl('images/smtp.svg'),
            'provider' => 'Smtp',
            'need_pro' => 'no',
            'is_smtp' => true,
            'options' => [
                'sender_name' => '',
                'sender_email' => '',
                'force_from_name' => 'no',
                'force_from_email' => 'yes',
                'return_path' => 'yes',
                'host' => '',
                'port' => '',
                'auth' => 'yes',
                'username' => '',
                'password' => '',
                'auto_tls' => 'yes',
                'encryption' => 'none',
                'key_store' => 'db'
            ],
            'note' => '<a href="https://fluentsmtp.com/docs/set-up-fluent-smtp-with-any-host-or-mailer/">Read the documentation</a> for how to configure any SMTP with FluentSMTP.'
        ],
        'ses' => [
            'key' => 'ses',
            'title' => __('Amazon Ses', 'fluent-smtp'),
            'image' => fluentMailAssetUrl('images/amazon.png'),
            'provider' => 'AmazonSes',
            'options' => [
                'sender_name' => '',
                'sender_email' => '',
                'force_from_name' => 'no',
                'force_from_email' => 'yes',
                'return_path' => 'yes',
                'access_key' => '',
                'secret_key' => '',
                'region' => 'us-east-1',
                'key_store' => 'db'
            ],
            'regions' => [
                'us-east-1'      => __('US East (N. Virginia)', 'fluent-smtp'),
                'us-east-2'      => __('US East (Ohio)', 'fluent-smtp'),
                'us-west-1'      => __('US West (N. California)', 'fluent-smtp'),
                'us-west-2'      => __('US West (Oregon)', 'fluent-smtp'),
                'ca-central-1'   => __('Canada (Central)', 'fluent-smtp'),
                'eu-west-1'      => __('EU (Ireland)', 'fluent-smtp'),
                'eu-west-2'      => __('EU (London)', 'fluent-smtp'),
                'eu-west-3'      => __('Europe (Paris)', 'fluent-smtp'),
                'eu-central-1'   => __('EU (Frankfurt)', 'fluent-smtp'),
                'eu-north-1'   => __('Europe (Stockholm)', 'fluent-smtp'),
                'ap-south-1'     => __('Asia Pacific (Mumbai)', 'fluent-smtp'),
                'ap-northeast-2' => __('Asia Pacific (Seoul)', 'fluent-smtp'),
                'ap-southeast-1' => __('Asia Pacific (Singapore)', 'fluent-smtp'),
                'ap-southeast-2' => __('Asia Pacific (Sydney)', 'fluent-smtp'),
                'ap-northeast-1' => __('Asia Pacific (Tokyo)', 'fluent-smtp'),
                'sa-east-1'      => __('South America (São Paulo)', 'fluent-smtp'),
                'me-south-1'      => __('Middle East (Bahrain)', 'fluent-smtp'),
                'us-gov-west-1'      => __('AWS GovCloud (US)', 'fluent-smtp'),
            ],
            'note' => '<a href="https://fluentsmtp.com/docs/set-up-amazon-ses-in-fluent-smtp/">Read the documentation</a> for how to configure Amazon SES with FluentSMTP.'
        ],
        'mailgun' => [
            'key' => 'mailgun',
            'title' => __('Mailgun', 'fluent-smtp'),
            'image' => fluentMailAssetUrl('images/mailgun.svg'),
            'provider' => 'Mailgun',
            'options' => [
                'sender_name' => '',
                'sender_email' => '',
                'force_from_name' => 'no',
                'force_from_email' => 'yes',
                'return_path' => 'yes',
                'api_key' => '',
                'domain_name' => '',
                'key_store' => 'db',
                'region' => 'us'
            ],
            'note' => '<a href="https://fluentsmtp.com/docs/configure-mailgun-in-fluent-smtp-to-send-emails/">Read the documentation</a> for how to configure MailGun with FluentSMTP.'
        ],
        'sendgrid' => [
            'key' => 'sendgrid',
            'title' => __('SendGrid', 'fluent-smtp'),
            'image' => fluentMailAssetUrl('images/sendgrid.svg'),
            'provider' => 'SendGrid',
            'options' => [
                'sender_name' => '',
                'sender_email' => '',
                'force_from_name' => 'no',
                'force_from_email' => 'yes',
                'api_key' => '',
                'key_store' => 'db'
            ],
            'note' => '<a href="https://fluentsmtp.com/docs/set-up-the-sendgrid-driver-in-fluent-smtp/">Read the documentation</a> for how to configure sendgrid with FluentSMTP.'
        ],
        'sendinblue' => [
            'key' => 'sendinblue',
            'title' => __('SendInBlue', 'fluent-smtp'),
            'image' => fluentMailAssetUrl('images/sendinblue.svg'),
            'provider' => 'SendInBlue',
            'options' => [
                'sender_name' => '',
                'sender_email' => '',
                'force_from_name' => 'no',
                'force_from_email' => 'yes',
                'api_key' => '',
                'key_store' => 'db'
            ],
            'note' => '<a href="https://fluentsmtp.com/docs/setting-up-sendinblue-mailer-in-fluent-smtp/">Read the documentation</a> for how to configure SendInBlue with FluentSMTP.'
        ],
        'sparkpost' => [
            'key' => 'sparkpost',
            'title' => __('SparkPost', 'fluent-smtp'),
            'image' => fluentMailAssetUrl('images/sparkpost.png'),
            'provider' => 'SparkPost',
            'options' => [
                'sender_name' => '',
                'sender_email' => '',
                'force_from_name' => 'no',
                'force_from_email' => 'yes',
                'api_key' => '',
                'key_store' => 'db'
            ],
            'note' => '<a href="https://fluentsmtp.com/docs/configure-sparkpost-in-fluent-smtp-to-send-emails/">Read the documentation</a> for how to configure SparkPost with FluentSMTP.'
        ],
        'pepipost' => [
            'key' => 'pepipost',
            'title' => __('PepiPost', 'fluent-smtp'),
            'image' => fluentMailAssetUrl('images/pepipost-logo.png'),
            'provider' => 'PepiPost',
            'options' => [
                'sender_name' => '',
                'sender_email' => '',
                'force_from_name' => 'no',
                'force_from_email' => 'yes',
                'api_key' => '',
                'key_store' => 'db'
            ],
            'note' => '<a href="https://fluentsmtp.com/docs/set-up-the-pepipost-mailer-in-fluent-smtp/">Read the documentation</a> for how to configure PepiPost with FluentSMTP.'
        ],
        'default' => [
            'key' => 'default',
            'title' => __('PHP Mail', 'fluent-smtp'),
            'image' => fluentMailAssetUrl('images/default.svg'),
            'provider' => 'DefaultMail',
            'options' => [
                'sender_name' => '',
                'sender_email' => '',
                'force_from_name' => 'no',
                'force_from_email' => 'yes',
                'return_path' => 'yes',
                'key_store' => 'db'
            ],
            'note' => __('The Default option does not use SMTP or any Email Service Providers so it will not improve email delivery on your site.', 'fluent-smtp')
        ],
    ],
    'misc' => [
        'log_emails' => 'yes',
        'log_saved_interval_days' => '14',
        'disable_fluentcrm_logs' => 'no',
        'default_connection' => ''
    ]
];
