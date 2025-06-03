<?php

namespace FluentMail\App\Hooks\Handlers;

use FluentMail\Includes\Core\Application;
use FluentMail\App\Hooks\Handlers\AdminMenuHandler;
use FluentMail\App\Hooks\Handlers\SchedulerHandler;
use FluentMail\App\Hooks\Handlers\InitializeSiteHandler;
use WP_REST_Request;

class ActionsRegistrar
{
    /**
     * Application instance.
     *
     * @var Application
     */
    protected $app;

    /**
     * Constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Alternative static constructor.
     *
     * @param Application $app
     * @return static
     */
    public static function init(Application $app)
    {
        $instance = new static($app);
        $instance->registerHooks();
        return $instance;
    }

    /**
     * Register all core hooks and REST routes.
     *
     * @return void
     */
    public function registerHooks()
    {
        $this->registerAdminMenu();
        $this->registerScheduler();
        $this->registerSiteInitialization();
        $this->registerCustomActions();
        $this->registerRestRoutes();
    }

    /**
     * Register admin menu and notices.
     *
     * @return void
     */
    protected function registerAdminMenu()
    {
        $adminMenuHandler = new AdminMenuHandler($this->app);
        $adminMenuHandler->addFluentMailMenu();

        $this->app->addAction('admin_notices', 'AdminMenuHandler@maybeAdminNotice');
    }

    /**
     * Register background scheduler hooks.
     *
     * @return void
     */
    protected function registerScheduler()
    {
        (new SchedulerHandler)->register();
    }

    /**
     * Register site-level initialization logic.
     *
     * @return void
     */
    protected function registerSiteInitialization()
    {
        (new InitializeSiteHandler)->addHandler();
    }

    /**
     * Register custom application actions.
     *
     * @return void
     */
    protected function registerCustomActions()
    {
        $this->app->addCustomAction(
            'handle_exception', 'ExceptionHandler@handle'
        );
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    protected function registerRestRoutes()
    {
        $this->app->addAction('rest_api_init', function () {
            register_rest_route('fluent-smtp', '/outlook_callback/', [
                'methods'             => 'GET',
                'callback'            => [$this, 'handleOutlookCallback'],
                'permission_callback' => [$this, 'verifyOutlookCallbackState'],
            ]);
        });
    }

    /**
     * Handle the Outlook OAuth callback.
     *
     * @param WP_REST_Request $request
     * @return void
     */
    public function handleOutlookCallback(WP_REST_Request $request)
    {
        $code = $request->get_param('code');

        $output = $this->app->view->make('admin.html_code', [
            'title' => 'Your Access Code',
            'body'  => sprintf(
                '<p>Copy the following code and paste in the fluentSMTP settings</p><textarea readonly>%s</textarea>',
                sanitize_textarea_field($code)
            ),
        ]);

        wp_die($output, 'Access Code');
    }

    /**
     * Verify the 'state' parameter in the OAuth callback.
     *
     * @return bool
     */
    public function verifyOutlookCallbackState()
    {
        $state = $_REQUEST['state'] ?? null;

        return $state === get_option('_fluentmail_last_generated_state');
    }
}
