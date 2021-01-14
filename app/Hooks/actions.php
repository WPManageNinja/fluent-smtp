<?php

use FluentMail\App\Services\Mailer\Manager;

//$app->addAction('shutdown', 'ShutdownHandler@handle');
//$app->addAction('wp_ajax_send_emails', 'ShutdownHandler@sendEmails');
//$app->addAction('wp_ajax_nopriv_send_emails', 'ShutdownHandler@sendEmails');

$app->addCustomAction('handle_exception', 'ExceptionHandler@handle');

$app->addAction('admin_menu', 'AdminMenuHandler@addFluentMailMenu');

$app->addCustomAction('validate-by-provider', 'ProviderValidator@handle', 10, 2);

$app->addAction('init', function() use ($app) {
    $manager = $app->make(Manager::class);
    $app->instance(Manager::class, $manager);
    $app->alias(Manager::class, 'manager');
});
