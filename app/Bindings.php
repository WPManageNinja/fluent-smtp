<?php

$singletons = [
    'manager' => 'FluentMail\App\Services\Mailer\Manager',
    'smtp' => 'FluentMail\App\Services\Mailer\Providers\Smtp\Handler',
    'ses' => 'FluentMail\App\Services\Mailer\Providers\AmazonSes\Handler',
    'mailgun' => 'FluentMail\App\Services\Mailer\Providers\Mailgun\Handler',
    'sendgrid' => 'FluentMail\App\Services\Mailer\Providers\SendGrid\Handler',
    'pepipost' => 'FluentMail\App\Services\Mailer\Providers\PepiPost\Handler',
    'sparkpost' => 'FluentMail\App\Services\Mailer\Providers\SparkPost\Handler',
    'default' => 'FluentMail\App\Services\Mailer\Providers\DefaultMail\Handler',
    'sendinblue' => 'FluentMail\App\Services\Mailer\Providers\SendInBlue\Handler',
];

foreach ($singletons as $key => $className) {
    $app->alias($className, $key);
    $app->singleton($className, function($app) use ($className) {
        return new $className();
    });
}
