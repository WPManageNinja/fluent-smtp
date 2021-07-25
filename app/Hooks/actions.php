<?php

(new \FluentMail\App\Hooks\Handlers\AdminMenuHandler($app))->addFluentMailMenu();

$app->addCustomAction('handle_exception', 'ExceptionHandler@handle');

$app->addAction('admin_notices', 'AdminMenuHandler@maybeAdminNotice');

$app->addAction('fluentmail_do_daily_scheduled_tasks', function () {
    $manager = fluentMail(\FluentMail\App\Services\Mailer\Manager::class);
    $logSaveDays = $manager->getSettings('misc.log_saved_interval_days');
    $logSaveDays = intval($logSaveDays);
    if ($logSaveDays) {
        (new \FluentMail\App\Models\Logger())->deleteLogsOlderThan($logSaveDays);
    }

    $day = date('d');
    if ($day == '01') {
        // this is a monthly cron
    }
});

add_action('rest_api_init', function () use ($app) {
    register_rest_route('fluent-smtp', '/outlook_callback/', array(
        'methods'             => 'GET',
        'callback'            => function (\WP_REST_Request $request) use ($app) {
            $code = $request->get_param('code');
            header("Content-Type: text/html");
            $app->view->render('admin.html_code', [
                'title' => 'Your Access Code',
                'body'  => '<p>Copy the following code and paste in the fluentSMTP settings</p><textarea readonly>'.sanitize_textarea_field($code).'</textarea>'
            ]);

            die();

        },
        'permission_callback' => function () {
            return true;
        }
    ));
});