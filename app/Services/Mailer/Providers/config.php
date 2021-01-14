<?php

return [
    'connections' => [],
    'mappings' => [],
    'providers' => [
        'default' => [
            'key' => 'default',
            'title' => __('Default Mail', 'fluentmail'),
            'image' => fluentMailAssetUrl('images/default.svg'),
            'provider' => 'DefaultMail',
            'options' => [
                'sender_name' => '',
                'sender_email' => '',
                'force_from_name' => 'no',
                'force_from_email' => 'yes',
                'return_path' => 'yes'
            ],
            'note' => 'The Default option does not use SMTP or any Email Service Providers so it will not improve email delivery on your site.'
        ],
        'mailgun' => [
            'key' => 'mailgun',
            'title' => __('Mailgun', 'fluentmail'),
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
                'region' => 'us'
            ]
        ],
        'pepipost' => [
            'key' => 'pepipost',
            'title' => __('PepiPost', 'fluentmail'),
            'image' => fluentMailAssetUrl('images/pepipost-logo.png'),
            'provider' => 'PepiPost',
            'options' => [
                'sender_name' => '',
                'sender_email' => '',
                'force_from_name' => 'no',
                'force_from_email' => 'yes',
                'api_key' => ''
            ]
        ],
        'sendgrid' => [
            'key' => 'sendgrid',
            'title' => __('SendGrid', 'fluentmail'),
            'image' => fluentMailAssetUrl('images/sendgrid.svg'),
            'provider' => 'SendGrid',
            'options' => [
                'sender_name' => '',
                'sender_email' => '',
                'force_from_name' => 'no',
                'force_from_email' => 'yes',
                'api_key' => ''
            ]
        ],
        'sendinblue' => [
            'key' => 'sendinblue',
            'title' => __('SendInBlue', 'fluentmail'),
            'image' => fluentMailAssetUrl('images/sendinblue.svg'),
            'provider' => 'SendInBlue',
            'options' => [
                'sender_name' => '',
                'sender_email' => '',
                'force_from_name' => 'no',
                'force_from_email' => 'yes',
                'api_key' => ''
            ]
        ],
        'ses' => [
            'key' => 'ses',
            'title' => __('Amazon Ses', 'fluentmail'),
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
                'region' => 'us-east-1'
            ],
            'regions' => [
                'us-east-1'      => __('US East (N. Virginia)', 'fluentmail'),
                'us-east-2'      => __('US East (Ohio)', 'fluentmail'),
                'us-west-2'      => __('US West (Oregon)', 'fluentmail'),
                'ca-central-1'   => __('Canada (Central)', 'fluentmail'),
                'eu-west-1'      => __('EU (Ireland)', 'fluentmail'),
                'eu-west-2'      => __('EU (London)', 'fluentmail'),
                'eu-central-1'   => __('EU (Frankfurt)', 'fluentmail'),
                'ap-south-1'     => __('Asia Pacific (Mumbai)', 'fluentmail'),
                'ap-northeast-2' => __('Asia Pacific (Seoul)', 'fluentmail'),
                'ap-southeast-1' => __('Asia Pacific (Singapore)', 'fluentmail'),
                'ap-southeast-2' => __('Asia Pacific (Sydney)', 'fluentmail'),
                'ap-northeast-1' => __('Asia Pacific (Tokyo)', 'fluentmail'),
                'sa-east-1'      => __('South America (São Paulo)', 'fluentmail')
            ]
        ],
        'smtp' => [
            'key' => 'smtp',
            'title' => __('SMTP', 'fluentmail'),
            'image' => fluentMailAssetUrl('images/smtp.jpg'),
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
                'auth' => 'no',
                'username' => '',
                'password' => '',
                'auto_tls' => 'yes',
                'encryption' => 'none'
            ]
        ]
    ],
    'misc' => [
        'log_emails' => 'yes',
        'log_saved_interval_days' => '7',
        'disable_fluentcrm_logs' => 'yes',
        'default_connection' => ''
    ]
];