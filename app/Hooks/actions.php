<?php
/*
 * @var $app FluentMail\Includes\Core\Application
 */

(new \FluentMail\App\Hooks\Handlers\AdminMenuHandler($app))->addFluentMailMenu();

(new \FluentMail\App\Hooks\Handlers\SchedulerHandler())->register();

(new \FluentMail\App\Hooks\Handlers\InitializeSiteHandler())->addHandler();

$app->addCustomAction('handle_exception', 'ExceptionHandler@handle');

$app->addAction('admin_notices', 'AdminMenuHandler@maybeAdminNotice');

add_action('rest_api_init', function () use ($app) {
    register_rest_route('fluent-smtp', '/outlook_callback/', array(
        'methods'             => 'GET',
        'callback'            => function (\WP_REST_Request $request) use ($app) {
            $code = $request->get_param('code');
            header("Content-Type: text/html");
            $app->view->render('admin.html_code', [
                'title' => 'Your Access Code',
                'body'  => '<p>Copy the following code and paste in the fluentSMTP settings</p><textarea readonly>' . sanitize_textarea_field($code) . '</textarea>'
            ]);
            die();
        },
        'permission_callback' => function () {
            $state = $_REQUEST['state'];
            if($state != get_option('_fluentmail_last_generated_state')) {
                return false;
            }
            return true;
        }
    ));
});
