<?php

(new \FluentMail\App\Hooks\Handlers\AdminMenuHandler($app))->addFluentMailMenu();

$app->addCustomAction('handle_exception', 'ExceptionHandler@handle');


$app->addAction('admin_notices', 'AdminMenuHandler@maybeAdminNotice');

$app->addAction('fluentmail_do_daily_scheduled_tasks', function () {
    $manager = fluentMail(\FluentMail\App\Services\Mailer\Manager::class);
    $logSaveDays = $manager->getSettings('misc.log_saved_interval_days');
    $logSaveDays = intval($logSaveDays);
    if($logSaveDays) {
        (new \FluentMail\App\Models\Logger())->deleteLogsOlderThan($logSaveDays);
    }

    $day = date('d');
    if($day == '01') {
        // this is a monthly cron
    }
});