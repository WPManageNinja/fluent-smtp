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

        if (!is_string($code) || $code === '') {
            $output = $this->app->view->make('admin.html_code', [
                'title' => __('Microsoft sign-in did not complete', 'fluent-smtp'),
                'body'  => $this->outlookCallbackErrorBody(
                    $this->outlookCallbackErrorCode($request),
                    $request->get_param('error_description')
                ),
            ]);

            wp_die($output, esc_html__('Microsoft sign-in did not complete', 'fluent-smtp'), ['response' => 400]);
        }

        $output = $this->app->view->make('admin.html_code', [
            'title' => __('Your Access Code', 'fluent-smtp'),
            'body'  => sprintf(
                '<p>%s</p><textarea readonly>%s</textarea>',
                esc_html__('Copy the following code and paste in the FluentSMTP settings', 'fluent-smtp'),
                esc_textarea(sanitize_textarea_field($code))
            ),
        ]);

        wp_die($output, esc_html__('Access Code', 'fluent-smtp'));
    }

    /**
     * The OAuth `error` parameter of the callback.
     *
     * `error` is one of WordPress's reserved public query vars, and
     * WP::parse_request() unsets $_GET['error'] before the REST server
     * collects its query params, so the request object never carries it.
     * Read it back from the raw query string instead.
     *
     * @param WP_REST_Request $request
     * @return string Empty when Microsoft sent none
     */
    private function outlookCallbackErrorCode(WP_REST_Request $request)
    {
        $error = $request->get_param('error');

        if (!is_string($error) || $error === '') {
            $query = isset($_SERVER['QUERY_STRING']) ? (string)$_SERVER['QUERY_STRING'] : '';
            wp_parse_str($query, $parsed);
            $error = isset($parsed['error']) && is_string($parsed['error']) ? $parsed['error'] : '';
        }

        return sanitize_text_field(wp_unslash($error));
    }

    /**
     * Body of the callback page when Microsoft sent an error instead of a code.
     *
     * Microsoft explains the refusal in error_description, so that is shown
     * verbatim. The common refusals all come down to the connection's
     * Directory (tenant) ID or the app's redirect URI not matching the Entra
     * app registration, so a hint naming the field to change follows it.
     *
     * @param mixed $error            OAuth error code from the query string
     * @param mixed $errorDescription OAuth error_description from the query string
     * @return string Escaped HTML
     */
    private function outlookCallbackErrorBody($error, $errorDescription)
    {
        $error            = is_string($error) ? $error : '';
        $errorDescription = is_string($errorDescription) ? sanitize_textarea_field($errorDescription) : '';

        $html = '<p>' . esc_html__('Microsoft did not return an authorization code, so there is nothing to paste into FluentSMTP yet.', 'fluent-smtp') . '</p>';

        if ($errorDescription || $error) {
            $html .= '<p style="text-align:left;background:#fef2f2;border:1px solid #fecaca;padding:10px;word-break:break-word;">'
                . '<strong>' . esc_html($error ?: __('Error', 'fluent-smtp')) . '</strong>';

            if ($errorDescription) {
                $html .= '<br>' . esc_html($errorDescription);
            }

            $html .= '</p>';
        }

        $hint = $this->outlookCallbackErrorHint($error, $errorDescription);

        if ($hint) {
            $html .= '<p style="text-align:left;"><strong>' . esc_html__('How to fix', 'fluent-smtp') . ':</strong> ' . esc_html($hint) . '</p>';
        }

        $html .= '<p>' . esc_html__('Close this window, adjust the connection settings in FluentSMTP, and click the authenticate button again.', 'fluent-smtp') . '</p>';

        return $html;
    }

    /**
     * A remedy for the Microsoft refusals this plugin sees most often.
     *
     * Matched on the description text rather than only the AADSTS code
     * because some refusals (the userAudience one among them) arrive with a
     * generic error code and the useful detail only in the description.
     *
     * @param string $error
     * @param string $errorDescription
     * @return string Empty when there is no specific advice
     */
    private function outlookCallbackErrorHint($error, $errorDescription)
    {
        $haystack = strtolower($error . ' ' . $errorDescription);

        if (strpos($haystack, 'useraudience') !== false || strpos($haystack, "'consumer'") !== false) {
            return __('Your Entra app registration only allows personal Microsoft accounts, but the connection is using the common sign-in endpoint. Either enter consumers in the Directory (tenant) ID field of the connection, or change the app registration\'s Supported account types to "Accounts in any organizational directory and personal Microsoft accounts".', 'fluent-smtp');
        }

        if (strpos($haystack, 'aadsts50194') !== false || strpos($haystack, 'multi-tenant') !== false) {
            return __('Your Entra app registration is single-tenant, so it cannot use the common sign-in endpoint. Paste the Directory (tenant) ID from the app registration overview page into the Directory (tenant) ID field of the connection.', 'fluent-smtp');
        }

        if (strpos($haystack, 'aadsts700016') !== false) {
            return __('Microsoft could not find this Application (client) ID in the tenant that was used to sign in. Check the Application (client) ID, and make sure the Directory (tenant) ID field matches the tenant the app is registered in.', 'fluent-smtp');
        }

        if (strpos($haystack, 'aadsts50011') !== false || strpos($haystack, 'redirect uri') !== false || strpos($haystack, 'reply url') !== false) {
            return __('The redirect URI registered in Entra does not match this site. Copy the App Callback URL shown in the FluentSMTP connection form and add it under Authentication > Web > Redirect URIs in the app registration.', 'fluent-smtp');
        }

        if ($error === 'access_denied') {
            return __('The sign-in was cancelled or consent was declined. Start the authentication again and accept the requested permissions.', 'fluent-smtp');
        }

        return '';
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
