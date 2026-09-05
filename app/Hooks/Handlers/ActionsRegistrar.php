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
        $instance = new self($app);
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

        // SMTP connection reuse across FluentCRM bulk sending sessions.
        (new BulkSendSessionHandler())->register();

        $this->registerCliCommands();

        $this->purgeLegacyOutlookSecret();
    }

    /**
     * Remove the retired `_fluentsmtp_intended_outlook_info` option, which held
     * an Outlook client id, secret, and tenant in plain text while the browser
     * was away at Microsoft. Nothing ever read it back, so the write is gone —
     * this clears what existing installs are still holding.
     *
     * Done on app load rather than on activation or a scheduled pass: a plugin
     * update does not reliably run the activation hook, and a stale secret
     * should not sit in the database for up to a day waiting on cron. The
     * lookup is served from the options cache, so the common case where the
     * option is already gone costs no query.
     *
     * @return void
     */
    protected function purgeLegacyOutlookSecret()
    {
        if (get_option('_fluentsmtp_intended_outlook_info') !== false) {
            delete_option('_fluentsmtp_intended_outlook_info');
        }
    }

    /**
     * Register the `wp fluent-smtp` commands.
     *
     * @return void
     */
    protected function registerCliCommands()
    {
        if (!defined('WP_CLI') || !WP_CLI || !class_exists('\WP_CLI')) {
            return;
        }

        \WP_CLI::add_command('fluent-smtp', '\FluentMail\App\Services\CliHandler');
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
     * The state is the one this site generated when it built the authorize URL
     * (OAuth2Provider::getRandomState()). Read from the REST request rather than
     * the superglobal, compared in constant time, and refused outright when either
     * side is missing - an absent stored state must not match an absent parameter.
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function verifyOutlookCallbackState(WP_REST_Request $request)
    {
        $state = $request->get_param('state');
        $expected = get_option('_fluentmail_last_generated_state');

        if (!is_string($state) || $state === '' || !is_string($expected) || $expected === '') {
            return false;
        }

        return hash_equals($expected, $state);
    }
}
