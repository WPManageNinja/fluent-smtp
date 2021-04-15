<?php

namespace FluentMail\App\Http\Controllers;

use Exception;
use FluentMail\App\Models\Settings;
use FluentMail\Includes\Request\Request;
use FluentMail\Includes\Support\ValidationException;
use FluentMail\App\Services\Mailer\Providers\Factory;

class SettingsController extends Controller
{
    public function index(Settings $settings)
    {
        $this->verify();

        try {
            $settings = $settings->get();

            return $this->sendSuccess([
                'settings' => $settings
            ]);

        } catch (Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ], $e->getCode());
        }
    }

    public function validate(Request $request, Settings $settings, Factory $factory)
    {
        $this->verify();

        try {
            $data = $request->except(['action', 'nonce']);

            $provider = $factory->make($data['provider']['key']);

            $provider->validateBasicInformation($data);

            $this->sendSuccess();

        } catch (ValidationException $e) {
            $this->sendError($e->errors(), $e->getCode());
        }
    }

    public function store(Request $request, Settings $settings, Factory $factory)
    {
        $this->verify();

        try {
            $data = $request->except(['action', 'nonce']);

            $data = wp_unslash($data);

            $provider = $factory->make($data['connection']['provider']);

            $connection = $data['connection'];
            $this->validateConnection($provider, $connection);
            $provider->checkConnection($connection);

            $data['valid_senders'] =  $provider->getValidSenders($connection);

            $settings->store($data);

            return $this->sendSuccess([
                'message' => 'Settings saved successfully.',
                'connections' => $settings->getConnections(),
                'mappings' => $settings->getMappings(),
                'misc' => $settings->getMisc()
            ]);

        } catch (ValidationException $e) {
            return $this->sendError($e->errors(), $e->getCode());
        } catch (Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ], $e->getCode());
        }
    }

    public function storeMiscSettings(Request $request, Settings $settings)
    {
        $this->verify();

        $misc = $request->get('settings');
        $settings->updateMiscSettings($misc);
        $this->sendSuccess([
            'message' => __('General Settings has been updated', 'fluent-smtp')
        ]);
    }

    public function delete(Request $request, Settings $settings)
    {
        $this->verify();

        $settings = $settings->delete($request->get('key'));
        
        return $this->sendSuccess($settings);
    }

    public function storeGlobals(Request $request, Settings $settings)
    {
        $this->verify();

        $settings->saveGlobalSettings(
            $data = $request->except(['action', 'nonce'])
        );

        return $this->sendSuccess([
            'form' => $data,
            'message' => __('Settings saved successfully.', 'fluent-smtp')
        ]);
    }

    public function sendTestEmil(Request $request, Settings $settings)
    {
        $this->verify();

        try {

            $this->app->addAction('wp_mail_failed', [$this, 'onFail']);

            $data = $request->except(['action', 'nonce']);

            if (!isset($data['email'])) {
                return $this->sendError([
                    'email_error' => __('The email field is required.', 'fluent-smtp')
                ], 422);
            }
            
            if (!defined('FLUENTMAIL_EMAIL_TESTING')) {
                define('FLUENTMAIL_EMAIL_TESTING', true);
            }

            $settings->sendTestEmail($data, $settings->get());

            return $this->sendSuccess([
                'message' => __('Email delivered successfully.', 'fluent-smtp')
            ]);

        } catch (Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ], $e->getCode());
        }
    }

    public function onFail($response)
    {
        return $this->sendError([
            'message' => $response->get_error_message(),
            'errors' => $response->get_error_data()
        ], 423);
    }

    public function validateConnection($provider, $connection)
    {
        $errors = [];

        try {
            $provider->validateBasicInformation($connection);
        } catch (ValidationException $e) {
            $errors = $e->errors();
        }

        try {
            $provider->validateProviderInformation($connection);
        } catch (ValidationException $e) {
            $errors = array_merge($errors, $e->errors());
        }

        if ($errors) {
            throw new ValidationException('Unprocessable Entity', 422, null, $errors);
        }
    }

    public function getConnectionInfo(Request $request, Settings $settings, Factory $factory)
    {
        $this->verify();

        $connectionId = $request->get('connection_id');
        $connections = $settings->getConnections();

        if (!isset($connections[$connectionId]['provider_settings'])) {
            return $this->sendSuccess([
                'info' => __('Sorry no connection found. Please reload the page and try again', 'fluent-smtp')
            ]);
        }

        $connection = $connections[$connectionId]['provider_settings'];

        $provider = $factory->make($connection['provider']);

        return $this->sendSuccess([
            'info' => $provider->getConnectionInfo($connection)
        ]);
    }

    public function installPlugin(Request $request)
    {
        $this->verify();
        $pluginSlug = $request->get('plugin_slug');
        $plugin = [
            'name'      => $pluginSlug,
            'repo-slug' => $pluginSlug,
            'file'      => $pluginSlug.'.php'
        ];

        $UrlMaps = [
            'fluentform' => [
                'admin_url' => admin_url('admin.php?page=fluent_forms'),
                'title' => __('Go to Fluent Forms Dashboard', 'fluent-smtp')
            ],
            'fluent-crm' => [
                'admin_url' => admin_url('admin.php?page=fluentcrm-admin'),
                'title' => __('Go to FluentCRM Dashboard', 'fluent-smtp')
            ],
            'ninja-tables' => [
                'admin_url' => admin_url('admin.php?page=ninja_tables#/'),
                'title' => __('Go to Ninja Tables Dashboard', 'fluent-smtp')
            ]
        ];

        if(!isset($UrlMaps[$pluginSlug])) {
            $this->sendError([
                'message' => __('Sorry, You can not install this plugin', 'fluent-smtp')
            ]);
        }

        try {
            $this->backgroundInstaller($plugin);
            $this->send([
                'message' => __('Plugin has been successfully installed.', 'fluent-smtp'),
                'info' => $UrlMaps[$pluginSlug]
            ]);
        } catch (\Exception $exception) {
            $this->sendError([
                'message' => $exception->getMessage()
            ]);
        }
    }


    private function backgroundInstaller($plugin_to_install)
    {
        if (!empty($plugin_to_install['repo-slug'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';

            WP_Filesystem();

            $skin = new \Automatic_Upgrader_Skin();
            $upgrader = new \WP_Upgrader($skin);
            $installed_plugins = array_reduce(array_keys(\get_plugins()), array($this, 'associate_plugin_file'), array());
            $plugin_slug = $plugin_to_install['repo-slug'];
            $plugin_file = isset($plugin_to_install['file']) ? $plugin_to_install['file'] : $plugin_slug . '.php';
            $installed = false;
            $activate = false;

            // See if the plugin is installed already.
            if (isset($installed_plugins[$plugin_file])) {
                $installed = true;
                $activate = !is_plugin_active($installed_plugins[$plugin_file]);
            }

            // Install this thing!
            if (!$installed) {
                // Suppress feedback.
                ob_start();

                try {
                    $plugin_information = plugins_api(
                        'plugin_information',
                        array(
                            'slug'   => $plugin_slug,
                            'fields' => array(
                                'short_description' => false,
                                'sections'          => false,
                                'requires'          => false,
                                'rating'            => false,
                                'ratings'           => false,
                                'downloaded'        => false,
                                'last_updated'      => false,
                                'added'             => false,
                                'tags'              => false,
                                'homepage'          => false,
                                'donate_link'       => false,
                                'author_profile'    => false,
                                'author'            => false,
                            ),
                        )
                    );

                    if (is_wp_error($plugin_information)) {
                        throw new \Exception($plugin_information->get_error_message());
                    }

                    $package = $plugin_information->download_link;
                    $download = $upgrader->download_package($package);

                    if (is_wp_error($download)) {
                        throw new \Exception($download->get_error_message());
                    }

                    $working_dir = $upgrader->unpack_package($download, true);

                    if (is_wp_error($working_dir)) {
                        throw new \Exception($working_dir->get_error_message());
                    }

                    $result = $upgrader->install_package(
                        array(
                            'source'                      => $working_dir,
                            'destination'                 => WP_PLUGIN_DIR,
                            'clear_destination'           => false,
                            'abort_if_destination_exists' => false,
                            'clear_working'               => true,
                            'hook_extra'                  => array(
                                'type'   => 'plugin',
                                'action' => 'install',
                            ),
                        )
                    );

                    if (is_wp_error($result)) {
                        throw new \Exception($result->get_error_message());
                    }

                    $activate = true;

                } catch (\Exception $e) {
                    throw new \Exception($e->getMessage());
                }

                // Discard feedback.
                ob_end_clean();
            }

            wp_clean_plugins_cache();

            // Activate this thing.
            if ($activate) {
                try {
                    $result = activate_plugin($installed ? $installed_plugins[$plugin_file] : $plugin_slug . '/' . $plugin_file);

                    if (is_wp_error($result)) {
                        throw new \Exception($result->get_error_message());
                    }
                } catch (\Exception $e) {
                    throw new \Exception($e->getMessage());
                }
            }
        }
    }

    public function subscribe()
    {
        $email = sanitize_text_field($_REQUEST['email']);
        if(!is_email($email)) {
            return $this->sendError([
                'message' => 'Sorry! The provider email is not valid'
            ], 423);
        }

        $shareEssentials = 'no';

        if($_REQUEST['share_essentials'] == 'yes') {
            update_option('_fluentsmtp_sub_update', 'shared', 'no');
            $shareEssentials = 'yes';
        } else {
            update_option('_fluentsmtp_sub_update', 'yes', 'no');
        }

        $this->pushData($email, $shareEssentials);

        return $this->sendSuccess([
            'message' => 'You are subscribed to plugin update and monthly tips'
        ]);
    }

    public function subscribeDismiss()
    {
        update_option('_fluentsmtp_dismissed_timestamp', time(), 'no');

        return $this->sendSuccess([
            'message' => 'success'
        ]);
    }

    private function pushData($optinEmail, $shareEssentials)
    {
        $user = get_user_by('ID', get_current_user_id());

        $data = [
            'answers'    => [
                'website' => site_url(),
                'email'   => $optinEmail,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name'    => $user->display_name,
                'essential' => $shareEssentials
            ],
            'questions'  => [
                'website' => 'website',
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'email'   => 'email',
                'name'    => 'name',
                'essential' => 'essential'
            ],
            'user'       => [
                'email' => $optinEmail
            ],
            'fb_capture' => 1,
            'form_id'    => 67
        ];

        $url = add_query_arg($data, 'https://wpmanageninja.com/');

        wp_remote_post($url);
    }

}
