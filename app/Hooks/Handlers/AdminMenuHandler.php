<?php

namespace FluentMail\App\Hooks\Handlers;

use FluentMail\App\Models\Logger;
use FluentMail\App\Models\Settings;
use FluentMail\App\Services\Converter;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\Includes\Support\Arr;

class AdminMenuHandler
{
    protected $app = null;

    public function __construct(Application $application)
    {
        $this->app = $application;
    }

    public function addFluentMailMenu()
    {
        add_action('admin_menu', array($this, 'addMenu'));

        if (isset($_GET['page']) && $_GET['page'] == 'fluent-mail' && is_admin()) {
            add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));

            if (isset($_REQUEST['sub_action']) && $_REQUEST['sub_action'] == 'slack_success') {
                add_action('admin_init', function () {
                    $nonce = Arr::get($_REQUEST, '_slacK_nonce');
                    if (!wp_verify_nonce($nonce, 'fluent_smtp_slack_register_site')) {
                        wp_redirect(admin_url('options-general.php?page=fluent-mail&slack_security_failed=1#/notification-settings'));
                        die();
                    }

                    $settings = (new Settings())->notificationSettings();
                    $token = Arr::get($_REQUEST, 'site_token');

                    if ($token && $token == Arr::get($settings, 'slack.token')) {
                        $settings['slack'] = [
                            'status'      => 'yes',
                            'token'       => sanitize_text_field($token),
                            'slack_team'  => sanitize_text_field(Arr::get($_REQUEST, 'slack_team')),
                            'webhook_url' => sanitize_url(Arr::get($_REQUEST, 'slack_webhook'))
                        ];
                        $settings['active_channel'] = 'slack';
                        update_option('_fluent_smtp_notify_settings', $settings);
                    }

                    wp_redirect(admin_url('options-general.php?page=fluent-mail#/notification-settings'));
                    die();
                });
            }

        }

        add_action('admin_bar_menu', array($this, 'addSimulationBar'), 999);

        add_action('admin_init', array($this, 'initAdminWidget'));

        add_action('install_plugins_table_header', function () {
            if (!isset($_REQUEST['s']) || empty($_REQUEST['s']) || empty($_REQUEST['tab']) || $_REQUEST['tab'] != 'search') {
                return;
            }

            $search = str_replace(['%20', '_', '-'], ' ', $_REQUEST['s']);
            $search = trim(strtolower(sanitize_text_field($search)));

            $searchTerms = ['wp-mail-smtp', 'wp mail', 'wp mail smtp', 'post mailer', 'wp smtp', 'smtp mail', 'smtp', 'post smtp', 'easy smtp', 'easy wp smtp', 'smtp mailer', 'gmail smtp', 'offload ses'];

            if (!strpos($search, 'smtp')) {
                if (!in_array($search, $searchTerms)) {
                    return;
                }
            }
            ?>
            <div
                style="background-color: #fff;border: 1px solid #dcdcde;box-sizing: border-box;padding: 20px;margin: 15px 0;"
                class="fluent_smtp_box">
                <h3 style="margin: 0;"><?php __('For SMTP, you already have FluentSMTP Installed', 'fluent-smtp'); ?></h3>
                <p><?php __('You seem to be looking for an SMTP plugin, but there\'s no need for another one — FluentSMTP is already installed on your site. FluentSMTP is a comprehensive, free, and open-source plugin with full features available without any upsell', 'fluent-smtp'); ?>
                    (<a href="https://fluentsmtp.com/why-we-built-fluentsmtp-plugin/"><?php __('learn why it\'s free', 'fluent-smtp'); ?></a>)<?php __('. It\'s compatible with various SMTP services, including Amazon SES, SendGrid, MailGun, ElasticEmail, SendInBlue, Google, Microsoft, and others, providing you with a wide range of options for your email needs.', 'fluent-smtp'); ?>
                </p><a href="<?php echo esc_url(admin_url('options-general.php?page=fluent-mail#/')); ?>"
                       class="wp-core-ui button button-primary"><?php __('Go To FluentSMTP Settings', 'fluent-smtp'); ?></a>
                <p style="font-size: 80%; margin: 15px 0 0;"><?php __('This notice is from FluentSMTP plugin to prevent plugin
                    conflict.', 'fluent-smtp') ?></p>
            </div>
            <?php
        }, 1);

        add_action('wp_ajax_fluent_smtp_get_dashboard_html', function () {
            // This widget should be displayed for certain high-level users only.
            if (!current_user_can('manage_options') || apply_filters('fluent_mail_disable_dashboard_widget', false)) {
                wp_send_json([
                    'html' => 'You do not have permission to see this data'
                ]);
            }

            wp_send_json([
                'html' => $this->getDashboardWidgetHtml()
            ]);
        });

    }

    public function addMenu()
    {
        $title = $this->app->applyCustomFilters('admin-menu-title', __('FluentSMTP', 'fluent-smtp'));

        add_submenu_page(
            'options-general.php',
            $title,
            $title,
            'manage_options',
            'fluent-mail',
            [$this, 'renderApp'],
            16
        );

    }

    public function renderApp()
    {
        $dailyTaskHookName = 'fluentmail_do_daily_scheduled_tasks';

        if (!wp_next_scheduled($dailyTaskHookName)) {
            wp_schedule_event(time(), 'daily', $dailyTaskHookName);
        }

        $this->app->view->render('admin.menu');
    }

    public function enqueueAssets()
    {
        add_action('wp_print_scripts', function () {
            $isSkip = apply_filters('fluentsmtp_skip_no_conflict', false);

            if ($isSkip) {
                return;
            }

            global $wp_scripts;
            if (!$wp_scripts) {
                return;
            }

            $themeUrl = content_url('themes');
            $pluginUrl = plugins_url();
            foreach ($wp_scripts->queue as $script) {
                if (empty($wp_scripts->registered[$script]) || empty($wp_scripts->registered[$script]->src)) {
                    continue;
                }

                $src = $wp_scripts->registered[$script]->src;
                $isMatched = strpos($src, $pluginUrl) !== false && !strpos($src, 'fluent-smtp') !== false;
                if (!$isMatched) {
                    continue;
                }

                $isMatched = strpos($src, $themeUrl) !== false;

                if ($isMatched) {
                    wp_dequeue_script($wp_scripts->registered[$script]->handle);
                }
            }

        }, 1);

        wp_enqueue_script(
            'fluent_mail_admin_app_boot',
            fluentMailMix('admin/js/boot.js'),
            ['jquery'],
            FLUENTMAIL_PLUGIN_VERSION
        );

        wp_enqueue_script('fluentmail-chartjs', fluentMailMix('libs/chartjs/Chart.min.js'), [], FLUENTMAIL_PLUGIN_VERSION);
        wp_enqueue_script('fluentmail-vue-chartjs', fluentMailMix('libs/chartjs/vue-chartjs.min.js'), [], FLUENTMAIL_PLUGIN_VERSION);
        wp_enqueue_script('dompurify', fluentMailMix('libs/purify/purify.min.js'), [], '2.4.3');

        wp_enqueue_style(
            'fluent_mail_admin_app', fluentMailMix('admin/css/fluent-mail-admin.css'), [], FLUENTMAIL_PLUGIN_VERSION
        );

        $user = get_user_by('ID', get_current_user_id());

        $disable_recommendation = defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS;

        $settings = $this->getMailerSettings();

        $recommendedSettings = false;
        if (empty($settings['connections'])) {
            $recommendedSettings = (new Converter())->getSuggestedConnection();
        }

        $displayName = trim($user->first_name . ' ' . $user->last_name);
        if (!$displayName) {
            $displayName = $user->display_name;
        }

        wp_localize_script('fluent_mail_admin_app_boot', 'FluentMailAdmin', [
            'slug'                   => FLUENTMAIL,
            'brand_logo'             => esc_url(fluentMailMix('images/logo.svg')),
            'nonce'                  => wp_create_nonce(FLUENTMAIL),
            'settings'               => $settings,
            'images_url'             => esc_url(fluentMailMix('images/')),
            'has_fluentcrm'          => defined('FLUENTCRM'),
            'has_fluentform'         => defined('FLUENTFORM'),
            'user_email'             => $user->user_email,
            'user_display_name'      => $displayName,
            'require_optin'          => $this->isRequireOptin(),
            'has_ninja_tables'       => defined('NINJA_TABLES_VERSION'),
            'disable_recommendation' => apply_filters('fluentmail_disable_recommendation', false),
            'disable_installation'   => $disable_recommendation,
            'plugin_url'             => 'https://fluentsmtp.com/?utm_source=wp&utm_medium=install&utm_campaign=dashboard',
            'trans'                  => $this->getTrans(),
            'recommended'            => $recommendedSettings,
            'is_disabled_defined'    => defined('FLUENTMAIL_SIMULATE_EMAILS') && FLUENTMAIL_SIMULATE_EMAILS
        ]);

        do_action('fluent_mail_loading_app');

        wp_enqueue_script(
            'fluent_mail_admin_app',
            fluentMailMix('admin/js/fluent-mail-admin-app.js'),
            ['fluent_mail_admin_app_boot'],
            FLUENTMAIL_PLUGIN_VERSION,
            true
        );

        add_filter('admin_footer_text', function ($text) {
            return sprintf(
                __('<b>FluentSMTP</b> is a free plugin & it will be always free %1$s. %2$s', 'fluent-smtp'),
                '<a href="https://fluentsmtp.com/why-we-built-fluentsmtp-plugin/" target="_blank" rel="noopener noreferrer">(Learn why it\'s free)</a>',
                '<a href="https://wordpress.org/support/plugin/fluent-smtp/reviews/?filter=5" target="_blank" rel="noopener noreferrer">Write a review ★★★★★</a>'
            );
        });
    }

    protected function getMailerSettings()
    {
        $settings = $this->app->make(Manager::class)->getMailerConfigAndSettings(true);

        if ($settings['mappings'] && $settings['connections']) {
            $validMappings = array_keys(Arr::get($settings, 'connections', []));

            $settings['mappings'] = array_filter($settings['mappings'], function ($key) use ($validMappings) {
                return in_array($key, $validMappings);
            });
        }

        $settings['providers']['outlook']['callback_url'] = rest_url('fluent-smtp/outlook_callback');

        $settings = array_merge(
            $settings,
            [
                'user_email' => wp_get_current_user()->user_email
            ]
        );

        return $settings;
    }

    public function maybeAdminNotice()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $connections = $this->app->make(Manager::class)->getConfig('connections');

        global $wp_version;

        $requireUpdate = version_compare($wp_version, '5.5', '<');

        if ($requireUpdate) { ?>
            <div class="notice notice-warning">
                <p>
                    <?php echo esc_html(sprintf(__('WordPress version 5.5 or greater is required for FluentSMTP. You are using version %s currently. Please update your WordPress Core to use FluentSMTP Plugin.', 'fluent-smtp'), $wp_version)); ?>
                </p>
            </div>
        <?php } else if (empty($connections)) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php esc_html_e('FluentSMTP needs to be configured for it to work.', 'fluent-smtp'); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('options-general.php?page=fluent-mail#/')); ?>"
                       class="button button-primary">
                        <?php esc_html_e('Configure FluentSMTP', 'fluent-smtp'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }

    public function addSimulationBar($adminBar)
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $misc = $this->app->make(Manager::class)->getConfig('misc');

        if ((!empty($misc['simulate_emails']) && $misc['simulate_emails'] == 'yes') || (defined('FLUENTMAIL_SIMULATE_EMAILS') && FLUENTMAIL_SIMULATE_EMAILS)) {
            $args = [
                'parent' => 'top-secondary',
                'id'     => 'fluentsmtp_simulated',
                'title'  => __('Email Disabled', 'fluent-smtp'),
                'href'   => admin_url('options-general.php?page=fluent-mail#/connections'),
                'meta'   => false
            ];

            echo '<style>li#wp-admin-bar-fluentsmtp_simulated a {background: red; color: white;}</style>';

            $adminBar->add_node($args);
        }
    }

    public function isRequireOptin()
    {
        $opted = get_option('_fluentsmtp_sub_update');
        if ($opted) {
            return 'no';
        }
        // check if dismissed
        $dismissedStamp = get_option('_fluentsmtp_dismissed_timestamp');
        if ($dismissedStamp && (time() - $dismissedStamp) < 30 * 24 * 60 * 60) {
            return 'no';
        }

        return 'yes';
    }

    public function initAdminWidget()
    {
        // This widget should be displayed for certain high-level users only.
        if (!current_user_can('manage_options') || apply_filters('fluent_mail_disable_dashboard_widget', false)) {
            return;
        }

        add_action('wp_dashboard_setup', function () {
            $widget_key = 'fluentsmtp_reports_widget';

            wp_add_dashboard_widget(
                $widget_key,
                esc_html__('Fluent SMTP', 'fluent-smtp'),
                [$this, 'dashWidgetContent']
            );

        });


    }

    public function dashWidgetContent()
    {
        ?>
        <style type="text/css">
            td.fstmp_failed {
                color: red;
                font-weight: bold;
            }
        </style>
        <div id="fsmtp_dashboard_widget_html" class="fsmtp_dash_wrapper">
            <h3 style="min-height: 170px;">Loading data....</h3>
        </div>
        <?php
        add_action('admin_footer', function () {
            ?>
            <script type="application/javascript">
                document.addEventListener('DOMContentLoaded', function () {
                    // send an ajax request to ajax url with raw javascript
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo esc_url(admin_url('admin-ajax.php?action=fluent_smtp_get_dashboard_html')); ?>', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function () {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            var response = JSON.parse(xhr.responseText);
                            if (response && response.html) {
                                document.getElementById('fsmtp_dashboard_widget_html').innerHTML = response.html;
                            } else {
                                document.getElementById('fsmtp_dashboard_widget_html').innerHTML = '<h3>Failed to load FluentSMTP Reports</h3>';
                            }
                        }
                    };

                    xhr.send();
                });
            </script>
            <?php
        });
    }

    protected function getDashboardWidgetHtml()
    {
        $stats = [];
        $logModel = new Logger();
        $currentTimeStamp = current_time('timestamp');
        $startToday = gmdate('Y-m-d 00:00:01', $currentTimeStamp);

        $allTime = $logModel->getStats();

        $stats['today'] = [
            'title'  => __('Today', 'fluent-smtp'),
            'sent'   => ($allTime['sent']) ? $logModel->getTotalCountStat('sent', $startToday) : 0,
            'failed' => ($allTime['failed']) ? $logModel->getTotalCountStat('failed', $startToday) : 0
        ];

        $lastWeek = gmdate('Y-m-d 00:00:01', strtotime('-7 days'));
        $stats['week'] = [
            'title'  => __('Last 7 days', 'fluent-smtp'),
            'sent'   => ($allTime['sent']) ? $logModel->getTotalCountStat('sent', $lastWeek) : 0,
            'failed' => ($allTime['failed']) ? $logModel->getTotalCountStat('failed', $lastWeek) : 0,
        ];

        $stats['all_time'] = [
            'title'  => __('All', 'fluent-smtp'),
            'sent'   => $allTime['sent'],
            'failed' => $allTime['failed'],
        ];
        ob_start();
        ?>
        <table class="fsmtp_dash_table wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th><?php esc_html_e('Date', 'fluent-smtp'); ?></th>
                <th><?php esc_html_e('Sent', 'fluent-smtp'); ?></th>
                <th><?php esc_html_e('Failed', 'fluent-smtp'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($stats as $stat): ?>
                <tr>
                    <td><?php echo $stat['title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                    <td><?php echo $stat['sent']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                    <td class="<?php echo ($stat['failed']) ? 'fstmp_failed' : ''; ?>"><?php echo $stat['failed']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <a style="text-decoration: none; padding-top: 10px; display: block"
           href="<?php echo esc_url(admin_url('options-general.php?page=fluent-mail#/')); ?>"
           class=""><?php esc_html_e('View All', 'fluent-smtp'); ?></a>
        <?php

        return ob_get_clean();
    }

    public function getTrans()
    {
        return [
            ' connection.'                                                                 => __(' connection.', 'fluent-smtp'),
            ' in the '                                                                     => __(' in the ', 'fluent-smtp'),
            ' option in the Google Cloud Project.'                                         => __(' option in the Google Cloud Project.', 'fluent-smtp'),
            '(Default: US East(N.Virginia) / us - east - 1)'                               => __('(Default: US East(N.Virginia) / us - east - 1)', 'fluent-smtp'),
            '(Re Authentication Required)'                                                 => __('(Re Authentication Required)', 'fluent-smtp'),
            '*** It is very important to put '                                             => __('*** It is very important to put ', 'fluent-smtp'),
            'A name for the connection.'                                                   => __('A name for the connection.', 'fluent-smtp'),
            'API Key'                                                                      => __('API Key', 'fluent-smtp'),
            'About'                                                                        => __('About', 'fluent-smtp'),
            'Access Key'                                                                   => __('Access Key', 'fluent-smtp'),
            'Access Keys in Config File'                                                   => __('Access Keys in Config File', 'fluent-smtp'),
            'Access Token'                                                                 => __('Access Token', 'fluent-smtp'),
            'Actions'                                                                      => __('Actions', 'fluent-smtp'),
            'Activation Pin'                                                               => __('Activation Pin', 'fluent-smtp'),
            'Active Connections:'                                                          => __('Active Connections:', 'fluent-smtp'),
            'Active Email Connections'                                                     => __('Active Email Connections', 'fluent-smtp'),
            'Active Senders:'                                                              => __('Active Senders:', 'fluent-smtp'),
            'Add'                                                                          => __('Add', 'fluent-smtp'),
            'Add Additional Senders'                                                       => __('Add Additional Senders', 'fluent-smtp'),
            'Add Another Connection'                                                       => __('Add Another Connection', 'fluent-smtp'),
            'After 1 Year'                                                                 => __('After 1 Year', 'fluent-smtp'),
            'After 14 Days'                                                                => __('After 14 Days', 'fluent-smtp'),
            'After 2 Years'                                                                => __('After 2 Years', 'fluent-smtp'),
            'After 30 Days'                                                                => __('After 30 Days', 'fluent-smtp'),
            'After 6 Months'                                                               => __('After 6 Months', 'fluent-smtp'),
            'After 60 Days'                                                                => __('After 60 Days', 'fluent-smtp'),
            'After 7 Days'                                                                 => __('After 7 Days', 'fluent-smtp'),
            'After 90 Days'                                                                => __('After 90 Days', 'fluent-smtp'),
            'Alerts'                                                                       => __('Alerts', 'fluent-smtp'),
            'All Statuses'                                                                 => __('All Statuses', 'fluent-smtp'),
            'All Time'                                                                     => __('All Time', 'fluent-smtp'),
            'App Callback URL(Use this URL to your APP)'                                   => __('App Callback URL(Use this URL to your APP)', 'fluent-smtp'),
            'Application Client ID'                                                        => __('Application Client ID', 'fluent-smtp'),
            'Application Client Secret'                                                    => __('Application Client Secret', 'fluent-smtp'),
            'Application Keys in Config File'                                              => __('Application Keys in Config File', 'fluent-smtp'),
            'Apply'                                                                        => __('Apply', 'fluent-smtp'),
            'Are you sure you want to disconnect Discord notifications?'                   => __('Are you sure you want to disconnect Discord notifications?', 'fluent-smtp'),
            'Are you sure you want to disconnect Slack notifications?'                     => __('Are you sure you want to disconnect Slack notifications?', 'fluent-smtp'),
            'Are you sure you want to disconnect Telegram notifications?'                  => __('Are you sure you want to disconnect Telegram notifications?', 'fluent-smtp'),
            'Are you sure you want to remove this email address?'                          => __('Are you sure you want to remove this email address?', 'fluent-smtp'),
            'Are you sure, you want to delete all the logs?'                               => __('Are you sure, you want to delete all the logs?', 'fluent-smtp'),
            'Attachments'                                                                  => __('Attachments', 'fluent-smtp'),
            'Authenticate with Google & Get Access Token'                                  => __('Authenticate with Google & Get Access Token', 'fluent-smtp'),
            'Authenticate with Office365 & Get Access Token'                               => __('Authenticate with Office365 & Get Access Token', 'fluent-smtp'),
            'Authentication'                                                               => __('Authentication', 'fluent-smtp'),
            'Authorized Redirect URI'                                                      => __('Authorized Redirect URI', 'fluent-smtp'),
            'Authorized Redirect URIs'                                                     => __('Authorized Redirect URIs', 'fluent-smtp'),
            'Awesome! Please check your email inbox and confirm your subscription.'        => __('Awesome! Please check your email inbox and confirm your subscription.', 'fluent-smtp'),
            'Best WP DataTables Plugin for WordPress'                                      => __('Best WP DataTables Plugin for WordPress', 'fluent-smtp'),
            'Bulk Action'                                                                  => __('Bulk Action', 'fluent-smtp'),
            'By Date'                                                                      => __('By Date', 'fluent-smtp'),
            'Cancel'                                                                       => __('Cancel', 'fluent-smtp'),
            'Close'                                                                        => __('Close', 'fluent-smtp'),
            'Configure Discord Notification'                                               => __('Configure Discord Notification', 'fluent-smtp'),
            'Connection Details'                                                           => __('Connection Details', 'fluent-smtp'),
            'Connection Name '                                                             => __('Connection Name ', 'fluent-smtp'),
            'Connection Provider'                                                          => __('Connection Provider', 'fluent-smtp'),
            'Connection deleted Successfully.'                                             => __('Connection deleted Successfully.', 'fluent-smtp'),
            'Continue'                                                                     => __('Continue', 'fluent-smtp'),
            'Continue to Slack'                                                            => __('Continue to Slack', 'fluent-smtp'),
            'Contributors'                                                                 => __('Contributors', 'fluent-smtp'),
            'Create API Key.'                                                              => __('Create API Key.', 'fluent-smtp'),
            'Current verified senders:'                                                    => __('Current verified senders:', 'fluent-smtp'),
            'Date-Time'                                                                    => __('Date-Time', 'fluent-smtp'),
            'Days'                                                                         => __('Days', 'fluent-smtp'),
            'Default Connection'                                                           => __('Default Connection', 'fluent-smtp'),
            'Delete Logs'                                                                  => __('Delete Logs', 'fluent-smtp'),
            'Delete Logs:'                                                                 => __('Delete Logs:', 'fluent-smtp'),
            'Delete Selected'                                                              => __('Delete Selected', 'fluent-smtp'),
            'Disable Logging for FluentCRM Emails'                                         => __('Disable Logging for FluentCRM Emails', 'fluent-smtp'),
            'Disconnect'                                                                   => __('Disconnect', 'fluent-smtp'),
            'Disconnect & Reconnect'                                                       => __('Disconnect & Reconnect', 'fluent-smtp'),
            'Discord Channel Details: '                                                    => __('Discord Channel Details: ', 'fluent-smtp'),
            'Discord Notifications Enabled'                                                => __('Discord Notifications Enabled', 'fluent-smtp'),
            'Discord Webhook URL'                                                          => __('Discord Webhook URL', 'fluent-smtp'),
            'Documentation'                                                                => __('Documentation', 'fluent-smtp'),
            'Domain Name'                                                                  => __('Domain Name', 'fluent-smtp'),
            'Edit Connection'                                                              => __('Edit Connection', 'fluent-smtp'),
            'ElasticEmail API Settings'                                                    => __('ElasticEmail API Settings', 'fluent-smtp'),
            'Email Address'                                                                => __('Email Address', 'fluent-smtp'),
            'Email Body'                                                                   => __('Email Body', 'fluent-smtp'),
            'Email Failed:'                                                                => __('Email Failed:', 'fluent-smtp'),
            'Email Headers'                                                                => __('Email Headers', 'fluent-smtp'),
            'Email Log'                                                                    => __('Email Log', 'fluent-smtp'),
            'Email Logs'                                                                   => __('Email Logs', 'fluent-smtp'),
            'Email Marketing Automation and CRM Plugin for WordPress'                      => __('Email Marketing Automation and CRM Plugin for WordPress', 'fluent-smtp'),
            'Email Sending Error Notifications'                                            => __('Email Sending Error Notifications', 'fluent-smtp'),
            'Email Simulation'                                                             => __('Email Simulation', 'fluent-smtp'),
            'Email Test'                                                                   => __('Email Test', 'fluent-smtp'),
            'Email Type'                                                                   => __('Email Type', 'fluent-smtp'),
            'Enable Email Summary'                                                         => __('Enable Email Summary', 'fluent-smtp'),
            'Enable email opens tracking on postmark(For HTML Emails only).'               => __('Enable email opens tracking on postmark(For HTML Emails only).', 'fluent-smtp'),
            'Enable link tracking on postmark (For HTML Emails only).'                     => __('Enable link tracking on postmark (For HTML Emails only).', 'fluent-smtp'),
            'Encryption'                                                                   => __('Encryption', 'fluent-smtp'),
            'End date'                                                                     => __('End date', 'fluent-smtp'),
            'Enter Full Screen'                                                            => __('Enter Full Screen', 'fluent-smtp'),
            'Enter new email address ex: new_sender@'                                      => __('Enter new email address ex: new_sender@', 'fluent-smtp'),
            'Enter the sender email address(optional).'                                    => __('Enter the sender email address(optional).', 'fluent-smtp'),
            'Failed'                                                                       => __('Failed', 'fluent-smtp'),
            'Fallback Connection'                                                          => __('Fallback Connection', 'fluent-smtp'),
            'Fastest Contact Form Builder Plugin for WordPress'                            => __('Fastest Contact Form Builder Plugin for WordPress', 'fluent-smtp'),
            'Filter'                                                                       => __('Filter', 'fluent-smtp'),
            'FluentCRM Email Logging'                                                      => __('FluentCRM Email Logging', 'fluent-smtp'),
            'FluentSMTP does not store your email notifications data.'                     => __('FluentSMTP does not store your email notifications data.', 'fluent-smtp'),
            'FluentSMTP does not store your email notifications data. '                    => __('FluentSMTP does not store your email notifications data. ', 'fluent-smtp'),
            'FluentSMTP is built using the following open-source libraries and software'   => __('FluentSMTP is built using the following open-source libraries and software', 'fluent-smtp'),
            'Follow this link to get a Domain Name from Mailgun:'                          => __('Follow this link to get a Domain Name from Mailgun:', 'fluent-smtp'),
            'Follow this link to get an API Key from ElasticEmail: '                       => __('Follow this link to get an API Key from ElasticEmail: ', 'fluent-smtp'),
            'Follow this link to get an API Key from Mailgun:'                             => __('Follow this link to get an API Key from Mailgun:', 'fluent-smtp'),
            'Follow this link to get an API Key from SendGrid:'                            => __('Follow this link to get an API Key from SendGrid:', 'fluent-smtp'),
            'Follow this link to get an API Key:'                                          => __('Follow this link to get an API Key:', 'fluent-smtp'),
            'Force From Email (Recommended Settings: Enable)'                              => __('Force From Email (Recommended Settings: Enable)', 'fluent-smtp'),
            'Force Sender Name'                                                            => __('Force Sender Name', 'fluent-smtp'),
            'From'                                                                         => __('From', 'fluent-smtp'),
            'From Email'                                                                   => __('From Email', 'fluent-smtp'),
            'From Name'                                                                    => __('From Name', 'fluent-smtp'),
            'General Settings'                                                             => __('General Settings', 'fluent-smtp'),
            'Get API Key.'                                                                 => __('Get API Key.', 'fluent-smtp'),
            'Get a Domain Name.'                                                           => __('Get a Domain Name.', 'fluent-smtp'),
            'Get a Private API Key.'                                                       => __('Get a Private API Key.', 'fluent-smtp'),
            'Get v3 API Key.'                                                              => __('Get v3 API Key.', 'fluent-smtp'),
            'Gmail / Google Workspace API Settings'                                        => __('Gmail / Google Workspace API Settings', 'fluent-smtp'),
            'How can we help you?'                                                         => __('How can we help you?', 'fluent-smtp'),
            'I have sent the code'                                                         => __('I have sent the code', 'fluent-smtp'),
            'If you find an issue or have a suggestion please '                            => __('If you find an issue or have a suggestion please ', 'fluent-smtp'),
            'If you have a minute, consider '                                              => __('If you have a minute, consider ', 'fluent-smtp'),
            'Install Fluent Forms (Free)'                                                  => __('Install Fluent Forms (Free)', 'fluent-smtp'),
            'Install FluentCRM (Free)'                                                     => __('Install FluentCRM (Free)', 'fluent-smtp'),
            'Install Ninja Tables (Free)'                                                  => __('Install Ninja Tables (Free)', 'fluent-smtp'),
            'Join FluentCRM Facebook Community'                                            => __('Join FluentCRM Facebook Community', 'fluent-smtp'),
            'Join FluentForms Facebook Community'                                          => __('Join FluentForms Facebook Community', 'fluent-smtp'),
            'Last 3 months'                                                                => __('Last 3 months', 'fluent-smtp'),
            'Last 30 Days'                                                                 => __('Last 30 Days', 'fluent-smtp'),
            'Last 7 Days'                                                                  => __('Last 7 Days', 'fluent-smtp'),
            'Last month'                                                                   => __('Last month', 'fluent-smtp'),
            'Last step!'                                                                   => __('Last step!', 'fluent-smtp'),
            'Last week'                                                                    => __('Last week', 'fluent-smtp'),
            'Less'                                                                         => __('Less', 'fluent-smtp'),
            'Log All Emails for Reporting'                                                 => __('Log All Emails for Reporting', 'fluent-smtp'),
            'Log Emails'                                                                   => __('Log Emails', 'fluent-smtp'),
            'Mailgun API Settings'                                                         => __('Mailgun API Settings', 'fluent-smtp'),
            'Manage Additional Senders'                                                    => __('Manage Additional Senders', 'fluent-smtp'),
            'Marketing'                                                                    => __('Marketing', 'fluent-smtp'),
            'Meet '                                                                        => __('Meet ', 'fluent-smtp'),
            'Message Stream'                                                               => __('Message Stream', 'fluent-smtp'),
            'More'                                                                         => __('More', 'fluent-smtp'),
            'Next'                                                                         => __('Next', 'fluent-smtp'),
            'None'                                                                         => __('None', 'fluent-smtp'),
            'Notification Days'                                                            => __('Notification Days', 'fluent-smtp'),
            'Notification Email Addresses'                                                 => __('Notification Email Addresses', 'fluent-smtp'),
            'Off'                                                                          => __('Off', 'fluent-smtp'),
            'On'                                                                           => __('On', 'fluent-smtp'),
            'Outlook / Office365 API Settings'                                             => __('Outlook / Office365 API Settings', 'fluent-smtp'),
            'Pepipost API Settings'                                                        => __('Pepipost API Settings', 'fluent-smtp'),
            'Pin copied to clipboard'                                                      => __('Pin copied to clipboard', 'fluent-smtp'),
            'Please '                                                                      => __('Please ', 'fluent-smtp'),
            'Please Provide an email'                                                      => __('Please Provide an email', 'fluent-smtp'),
            'Please add another connection to use fallback feature'                        => __('Please add another connection to use fallback feature', 'fluent-smtp'),
            'Please authenticate with Google to get '                                      => __('Please authenticate with Google to get ', 'fluent-smtp'),
            'Please authenticate with Office365 to get '                                   => __('Please authenticate with Office365 to get ', 'fluent-smtp'),
            'Please enter a valid email address'                                           => __('Please enter a valid email address', 'fluent-smtp'),
            'Please send test email to confirm if the connection is working or not.'       => __('Please send test email to confirm if the connection is working or not.', 'fluent-smtp'),
            'Postmark API Settings'                                                        => __('Postmark API Settings', 'fluent-smtp'),
            'Prev'                                                                         => __('Prev', 'fluent-smtp'),
            'Private API Key'                                                              => __('Private API Key', 'fluent-smtp'),
            'Provider'                                                                     => __('Provider', 'fluent-smtp'),
            'Quick Overview'                                                               => __('Quick Overview', 'fluent-smtp'),
            'Read the documentation'                                                       => __('Read the documentation', 'fluent-smtp'),
            'Receiver\'s Telegram Username: '                                              => __('Receiver\'s Telegram Username: ', 'fluent-smtp'),
            'Region '                                                                      => __('Region ', 'fluent-smtp'),
            'Remove'                                                                       => __('Remove', 'fluent-smtp'),
            'Resend'                                                                       => __('Resend', 'fluent-smtp'),
            'Resend Selected Emails'                                                       => __('Resend Selected Emails', 'fluent-smtp'),
            'Resent Count'                                                                 => __('Resent Count', 'fluent-smtp'),
            'Retry'                                                                        => __('Retry', 'fluent-smtp'),
            'Run Another Test Email'                                                       => __('Run Another Test Email', 'fluent-smtp'),
            'SMTP Host'                                                                    => __('SMTP Host', 'fluent-smtp'),
            'SMTP Password'                                                                => __('SMTP Password', 'fluent-smtp'),
            'SMTP Port'                                                                    => __('SMTP Port', 'fluent-smtp'),
            'SMTP Username'                                                                => __('SMTP Username', 'fluent-smtp'),
            'SSL'                                                                          => __('SSL', 'fluent-smtp'),
            'Save Connection Settings'                                                     => __('Save Connection Settings', 'fluent-smtp'),
            'Save Email Logs:'                                                             => __('Save Email Logs:', 'fluent-smtp'),
            'Save Settings'                                                                => __('Save Settings', 'fluent-smtp'),
            'Search Results for'                                                           => __('Search Results for', 'fluent-smtp'),
            'Search Type and Enter...'                                                     => __('Search Type and Enter...', 'fluent-smtp'),
            'Secret Key'                                                                   => __('Secret Key', 'fluent-smtp'),
            'Select'                                                                       => __('Select', 'fluent-smtp'),
            'Select Email or Type'                                                         => __('Select Email or Type', 'fluent-smtp'),
            'Select Provider'                                                              => __('Select Provider', 'fluent-smtp'),
            'Select Region'                                                                => __('Select Region', 'fluent-smtp'),
            'Select date and time'                                                         => __('Select date and time', 'fluent-smtp'),
            'Send Test Email'                                                              => __('Send Test Email', 'fluent-smtp'),
            'Send Test Message'                                                            => __('Send Test Message', 'fluent-smtp'),
            'Send this email in HTML or in plain text format.'                             => __('Send this email in HTML or in plain text format.', 'fluent-smtp'),
            'SendGrid API Settings'                                                        => __('SendGrid API Settings', 'fluent-smtp'),
            'Sender Email '                                                                => __('Sender Email ', 'fluent-smtp'),
            'Sender Email Address'                                                         => __('Sender Email Address', 'fluent-smtp'),
            'Sender Name'                                                                  => __('Sender Name', 'fluent-smtp'),
            'Sender Settings'                                                              => __('Sender Settings', 'fluent-smtp'),
            'Sendinblue API Settings'                                                      => __('Sendinblue API Settings', 'fluent-smtp'),
            'Sending Stats'                                                                => __('Sending Stats', 'fluent-smtp'),
            'Sending by time of day'                                                       => __('Sending by time of day', 'fluent-smtp'),
            'Server Response'                                                              => __('Server Response', 'fluent-smtp'),
            'Set the return-path to match the From Email'                                  => __('Set the return-path to match the From Email', 'fluent-smtp'),
            'Settings'                                                                     => __('Settings', 'fluent-smtp'),
            'Slack Channel Details: '                                                      => __('Slack Channel Details: ', 'fluent-smtp'),
            'Slack Notifications Enabled'                                                  => __('Slack Notifications Enabled', 'fluent-smtp'),
            'Sorry! No docs found'                                                         => __('Sorry! No docs found', 'fluent-smtp'),
            'SparkPost API Settings'                                                       => __('SparkPost API Settings', 'fluent-smtp'),
            'Start date'                                                                   => __('Start date', 'fluent-smtp'),
            'Status'                                                                       => __('Status', 'fluent-smtp'),
            'Status:'                                                                      => __('Status:', 'fluent-smtp'),
            'Store API Keys in Config File'                                                => __('Store API Keys in Config File', 'fluent-smtp'),
            'Store API Keys in DB'                                                         => __('Store API Keys in DB', 'fluent-smtp'),
            'Store Access Keys in DB'                                                      => __('Store Access Keys in DB', 'fluent-smtp'),
            'Store Application Keys in DB'                                                 => __('Store Application Keys in DB', 'fluent-smtp'),
            'Subject'                                                                      => __('Subject', 'fluent-smtp'),
            'Subscribe To Updates'                                                         => __('Subscribe To Updates', 'fluent-smtp'),
            'Successful'                                                                   => __('Successful', 'fluent-smtp'),
            'Summary Email'                                                                => __('Summary Email', 'fluent-smtp'),
            'TLS'                                                                          => __('TLS', 'fluent-smtp'),
            'Telegram Connection Status: '                                                 => __('Telegram Connection Status: ', 'fluent-smtp'),
            'Telegram Notifications Enable'                                                => __('Telegram Notifications Enable', 'fluent-smtp'),
            'Test Email Has been successfully sent'                                        => __('Test Email Has been successfully sent', 'fluent-smtp'),
            'The email address already exists in the list'                                 => __('The email address already exists in the list', 'fluent-smtp'),
            'The email address must match the domain: '                                    => __('The email address must match the domain: ', 'fluent-smtp'),
            'The email address which emails are sent from.'                                => __('The email address which emails are sent from.', 'fluent-smtp'),
            'The name which emails are sent from.'                                         => __('The name which emails are sent from.', 'fluent-smtp'),
            'To'                                                                           => __('To', 'fluent-smtp'),
            'To send emails you will need only a Mail Send access level for this API key.' => __('To send emails you will need only a Mail Send access level for this API key.', 'fluent-smtp'),
            'Today'                                                                        => __('Today', 'fluent-smtp'),
            'Total Email Sent (Logged):'                                                   => __('Total Email Sent (Logged):', 'fluent-smtp'),
            'Track Opens'                                                                  => __('Track Opens', 'fluent-smtp'),
            'Transactional'                                                                => __('Transactional', 'fluent-smtp'),
            'Try Again'                                                                    => __('Try Again', 'fluent-smtp'),
            'Turn On'                                                                      => __('Turn On', 'fluent-smtp'),
            'Type & press enter...'                                                        => __('Type & press enter...', 'fluent-smtp'),
            'Use Auto TLS'                                                                 => __('Use Auto TLS', 'fluent-smtp'),
            'Validating Data. Please wait...'                                              => __('Validating Data. Please wait...', 'fluent-smtp'),
            'Write a review (really appreciate 😊)'                                         => __('Write a review (really appreciate 😊)', 'fluent-smtp'),
            'Yes, Disconnect'                                                              => __('Yes, Disconnect', 'fluent-smtp'),
            'You may add additional sending emails in this'                                => __('You may add additional sending emails in this', 'fluent-smtp'),
            'Your Discord Channel Name (For Internal Use)'                                 => __('Your Discord Channel Name (For Internal Use)', 'fluent-smtp'),
            'Your Discord Channel Webhook URL'                                             => __('Your Discord Channel Webhook URL', 'fluent-smtp'),
            'Your Email'                                                                   => __('Your Email', 'fluent-smtp'),
            'Your Email Address'                                                           => __('Your Email Address', 'fluent-smtp'),
            'Your Name'                                                                    => __('Your Name', 'fluent-smtp'),
            'Your SMTP Username'                                                           => __('Your SMTP Username', 'fluent-smtp'),
            '__from_email_tooltip'                                                         => __('If checked, the From Email setting above will be used for all emails (It will check if the from email is listed to available connections).', 'fluent-smtp'),
            '__wizard_title'                                                               => __('Welcome to FluentSMTP', 'fluent-smtp'),
            '__wizard_sub'                                                                 => __('Thank you for installing FluentSMTP - The ultimate SMTP & Email Service Connection Plugin for WordPress', 'fluent-smtp'),
            '__wizard_instruction'                                                         => __('Please configure your first email service provider connection', 'fluent-smtp'),
            '__routing_info'                                                               => __('Your emails will be routed automatically based on From email address. No additional configuration is required.', 'fluent-smtp'),
            '__default_connection_popover'                                                 => __('Select which connection will be used for sending transactional emails from your WordPress. If you use multiple connection then email will be routed based on source from email address', 'fluent-smtp'),
            '__fallback_connection_popover'                                                => __('Fallback Connection will be used if an email is failed to send in one connection. Please select a different connection than the default connection', 'fluent-smtp'),
            '__Email_Simulation_Label'                                                     => __('Disable sending all emails. If you enable this, no email will be sent.', 'fluent-smtp'),
            '__Email_Simulation_Yes'                                                       => __('No Emails will be sent from your WordPress.', 'fluent-smtp'),
            '__RETURN_PATH_ALERT'                                                          => __('Return Path indicates where non-delivery receipts - or bounce messages - are to be sent. If unchecked, bounce messages may be lost. With this enabled, you\'ll be emailed using "From Email" if any messages bounce as a result of issues with the recipient’s email.', 'fluent-smtp'),
            '__RETURN_PATH_TOOLTIP'                                                        => sprintf(__('Return Path indicates where non - delivery receipts - or bounce messages - %1$s are to be sent. If unchecked, bounce messages may be lost. With this enabled, %2$s you\'ll be emailed using "From Email" if any messages bounce as a result of issues with the recipient\'s email.', 'fluent-smtp'), '<br />', '<br />'),
            '__TEST_EMAIL_INST'                                                            => __('Enter email address where test email will be sent (By default, logged in user email will be used if email address is not provided).', 'fluent-smtp'),
            '__ABOUT_INTRO'                                                                => __('is a free and opensource WordPress Plugin. Our mission is to provide the ultimate email delivery solution with your favorite Email sending service.FluentSMTP is built for performance and speed.', 'fluent-smtp'),
            '__ABOUT_BY'                                                                   => __('FluentSMTP is free and will be always free.This is our pledge to WordPress community from WPManageNinja LLC.', 'fluent-smtp'),
            '__ABOUT_POWERED'                                                              => __('FluentSMTP is powered by it\'s users like you. Feel free to contribute on Github. Thanks to all of our contributors.', 'fluent-smtp'),
            '__ABOUT_COMMUNITY'                                                            => __('FluentSMTP is powered by community.We listen to our community users and build products that add values to businesses and save time.', 'fluent-smtp'),
            '__ABOUT_JOIN'                                                                 => __('Join our communities and participate in great conversations.', 'fluent-smtp'),
            '__GMAIL_SUCCESS'                                                              => __('Your Gmail / Google Workspace Authentication has been enabled. No further action is needed. If you want to re-authenticate, ', 'fluent-smtp'),
            '__GMAIL_CODE_INSTRUCTION'                                                     => __('Simply copy the following snippet and replace the stars with the corresponding credential.Then simply paste to wp-config.php file of your WordPress installation', 'fluent-smtp'),
            '__SLACK_NOTIFICATION_ENABLED'                                                 => __('Your FluentSMTP plugin is currently integrated with your Slack Channel. Receive timely notifications on Slack for any email sending issues from your website. This ongoing connection ensures you\'re always informed about your email delivery status.', 'fluent-smtp'),
            '__DISCORD_NOTIFICATION_ENABLED'                                               => __('Your FluentSMTP plugin is currently integrated with your Discord Channel.Receive timely notifications on Discord for any email sending issues from your website.This ongoing connection ensures you\'re always informed about your email delivery status.', 'fluent-smtp'),
            '__WP_CONFIG_INSTRUCTION'                                                      => __('Simply copy the following snippet and replace the stars with the corresponding credential. Then simply paste to wp-config.php file of your WordPress installation', 'fluent-smtp'),
            '__SLACK_INTRO'                                                                => __('Get real-time notification on your Slack Channel on any email sending failure. Configure notification with Slack Bot to start getting real time notifications.', 'fluent-smtp'),
            '__DISCORD_INTRO'                                                              => __('Get real - time notification on your Discord Channel on any email sending failure.Configure notification with Discord to start getting real time notifications.', 'fluent-smtp'),
            '__FC_DESC'                                                                    => __(' is the best and complete feature-rich Email Marketing & CRM solution. It is also the simplest and fastest CRM and Marketing Plugin on WordPress. Manage your customer relationships, build your email lists, send email campaigns, build funnels, and make more profit and increase your conversion rates. (Yes, It’s Free!)', 'fluent-smtp'),
            '__FF_DESC'                                                                    => __('is the ultimate user-friendly, fast, customizable drag-and-drop WordPress Contact Form Plugin that offers you all the premium features, plus many more completely unique additional features.', 'fluent-smtp'),
            '__NT_DESC'                                                                    => __('Looking for a WordPress table plugin for your website? Then you’re in the right place.', 'fluent-smtp'),
            '__NT_DESC_EXT'                                                                => __('the best WP table plugin that comes with all the solutions to the problems you face while creating tables on your posts/pages.', 'fluent-smtp'),
            '__TELEGRAM_NOTIFICATION_ENABLED'                                              => sprintf(__('Your FluentSMTP plugin is currently integrated with Telegram.Receive timely notifications from %s on Telegram for any email sending issues from your website. This ongoing connection ensures you\'re always informed about your email delivery status.', 'fluent-smtp'), '<a target="_blank" rel="noopener" href="https://t.me/fluentsmtp_bot">@fluentsmtp_bot</a>'),
            '__TLS_HELP'                                                                   => __('(By default, the TLS encryption would be used if the server supports it. On some servers, it could be a problem and may need to be disabled.)', 'fluent-smtp'),
            '__SMTP_CRED_HELP'                                                             => __('(If you need to provide your SMTP server\'s credentials (username and password) enable the authentication, in most cases this is required.)', 'fluent-smtp'),
            '__ANOTHER_CONNECTION_NOTICE'                                                  => __('Another connection with same email address exist. This connection will replace that connection', 'fluent-smtp'),
            '__EMAIL_LOGGING_OFF'                                                          => __('Email Logging is currently turned off. Only Failed and resent emails will be shown here', 'fluent-smtp'),
            '__EMAIL_SUMMARY_INTRO'                                                        => __('Email summary is useful for getting weekly or daily emails about the all the email sending stats for this site.', 'fluent-smtp'),
            '__SUBSCRIBE_INTRO'                                                            => __('Subscribe with your email to know about this plugin updates, releases and useful tips.', 'fluent-smtp'),
            '__DEFAULT_MAIl_WARNING'                                                       => __('The Default(none) option does not use SMTP and will not improve email delivery on your site.', 'fluent-smtp'),
            '__TELE_RESPONSE_ERROR'                                                        => __('We could not fetch the Telegram notification status.Here is the server response: ', 'fluent-smtp'),
            '__FORCE_SENDER_NAME_TIP'                                                      => __('When checked, the From Name setting above will be used for all emails, ignoring values set by other plugins.', 'fluent-smtp'),
            '__SUPPORT_INTRO'                                                              => sprintf(__('Please view the %1$s first. If you still can\'t find the answer %2$s  and we will be happy to answer your questions and assist you with any problems.', 'fluent-smtp'), '<a href="https://fluentsmtp.com/docs" target="_blank" rel="noopener">' . __('documentation', 'fluent-smtp') . '</a>', '<a href="https://github.com/WPManageNinja/fluent-smtp" target="_blank" rel="noopener">' . __('open a github issue', 'fluent-smtp') . '</a>'),
            '__DEFAULT_CONNECTION_CONFLICT'                                                => __('Default and Fallback connection can not be same. Please select different connections.', 'fluent-smtp'),
            '__MAILGUN_URL_TIP'                                                            => __('Define which endpoint you want to use for sending messages.', 'fluent-smtp'),
            '__PEPIPOST_HELP'                                                              => __('Follow this link to get an API Key from Pepipost(Click Show button on Settings Page):', 'fluent-smtp'),
            '__POSTMARK_HELP'                                                              => __('Follow this link to get an API Key from Postmark(Your API key is in the API Tokens tab of your):', 'fluent-smtp'),
            '__TELE_INTRO'                                                                 => sprintf(__('Get real - time notification on your %1$s on any email sending failure. Configure notification with FluentSMTP\'s official %2$s to start getting real time notifications. ', 'fluent-smtp'), '<a target="_blank" rel="noopener" href="https://telegram.org/">Telegram Messenger</a>', '<a target="_blank" rel="noopener" href="https://t.me/fluentsmtp_bot">telegram bot</a>'),
            '__SLACK_TERMS'                                                                => sprintf(__('I agree to the %1s of this slack integration.', 'fluent-smtp'), '<a target="_blank" rel="noopener" href="https://fluentsmtp.com/terms-and-conditions/">' . __('terms and conditions', 'fluent-smtp') . '</a>'),
            '__GIT_CONTRIBUTE'                                                             => sprintf(__('If you are a developer and would like to contribute to the project, Please %s', 'fluent-smtp'), '<a target="_blank" rel="nofollow" href="https://github.com/WPManageNinja/fluent-smtp/">' . __('contribute on GitHub', 'fluent-smtp') . '</a>'),
            'If you are operating under EU laws, you may be required to use EU region.' => __('If you are operating under EU laws, you may be required to use EU region.', 'fluent-smtp'),
            '__POSTMARK_CLICK'                                                             => __('If you enable this then link tracking header will be added to the email for postmark.', 'fluent-smtp'),
            '__POSTMARK_OPEN'                                                              => __('If you enable this then open tracking header will be added to the email for postmark.', 'fluent-smtp'),
            '__TELE_LAST_STEP'                                                             => sprintf(__('Please find %s on telegram and send following text to activate this connection.', 'fluent-smtp'), '<a target="_blank" rel="noopener" href="https://t.me/fluentsmtp_bot"><span class="tele_bot">@fluentsmtp_bot</span></a>'),
            '__TELE_TERMS'                                                                 => sprintf(__('I agree to the  %s of this telegram integration.', 'fluent-smtp'), '<a target="_blank" rel="noopener" href="https://fluentsmtp.com/terms-and-conditions/">' . __('terms and conditions', 'fluent-smtp') . '</a>'),
            '__GCP_API_INST'                                                               => sprintf(__('Please %s to create API keys on the Google Cloud Platform.', 'fluent-smtp'), '<a target="_blank" rel="nofollow" href="https://fluentsmtp.com/docs/connect-gmail-or-google-workspace-emails-with-fluentsmtp/">' . __('check the documentation', 'fluent-smtp') . '</a>'),
            '__GCP_INTRO'                                                                  => sprintf(__('Google API version has been upgraded. Please %s.', 'fluent-smtp'), '<a target="_blank" rel="noopener" href="https://fluentsmtp.com/docs/connect-gmail-or-google-workspace-emails-with-fluentsmtp/">' . __('read the doc and upgrade your API connection', 'fluent-smtp') . '</a>'),
            '__MAILGUN_REGION'                                                             => sprintf(__('If you are operating under EU laws, you may be required to use EU region. %s.', 'fluent-smtp'), '<a target="_blank" href="https://www.mailgun.com/regions">' . __('More information on Mailgun.com', 'fluent-smtp') . '</a>'),
            '__Email_TEXT_PART_Label'                                                      => __('Enable Multi-Part Plain Text version of your HTML Emails. This feature is in beta', 'fluent-smtp'),
            '__PASSWORD_ENCRYPT_HELP'                                                      => __('This input will be securely encrypted using WP SALTS as encryption keys before save.', 'fluent-smtp'),
            '__PASSWORD_ENCRYPT_TIP'                                                       => __('If you change your WordPress SALT Keys, this credential will become invalid. Please update this credential whenever the WP SALTS are modified.', 'fluent-smtp'),
            'activate '                                                                    => __('activate ', 'fluent-smtp'),
            'cancel'                                                                       => __('cancel', 'fluent-smtp'),
            'check the documentation first to create API keys at Microsoft'                => __('check the documentation first to create API keys at Microsoft', 'fluent-smtp'),
            'click here'                                                                   => __('click here', 'fluent-smtp'),
            'confirm'                                                                      => __('confirm', 'fluent-smtp'),
            'copy'                                                                         => __('copy', 'fluent-smtp'),
            'delete_logs_info'                                                             => __('Select how many days, the logs will be saved. If you select 7 days, then logs older than 7 days will be deleted automatically.', 'fluent-smtp'),
            'force_sender_tooltip'                                                         => __('When checked, the From Name setting above will be used for all emails, ignoring values set by other plugins.', 'fluent-smtp'),
            'open an issue on GitHub'                                                      => __('open an issue on GitHub', 'fluent-smtp'),
            'provider for the connection.'                                                 => __('provider for the connection.', 'fluent-smtp'),
            'read the documentation here'                                                  => __('read the documentation here', 'fluent-smtp'),
            'save_connection_error_1'                                                      => __('Please select your email service provider', 'fluent-smtp'),
            'save_connection_error_2'                                                      => __('Credential Verification Failed. Please check your inputs', 'fluent-smtp'),
            'write a review for FluentSMTP'                                                => __('write a review for FluentSMTP', 'fluent-smtp'),
        ];
    }

    public function reservedTransShorts()
    {

        // for internal use only
//        return [
//            '__from_email_tooltip'            => __('If checked, the From Email setting above will be used for all emails (It will check if the from email is listed to available connections).', 'fluent-smtp'),
//            '__wizard_title'                  => __('Welcome to FluentSMTP', 'fluent-smtp'),
//            '__wizard_sub'                    => __('Thank you for installing FluentSMTP - The ultimate SMTP & Email Service Connection Plugin for WordPress', 'fluent-smtp'),
//            '__wizard_instruction'            => __('Please configure your first email service provider connection', 'fluent-smtp'),
//            '__routing_info'                  => __('Your emails will be routed automatically based on From email address. No additional configuration is required.', 'fluent-smtp'),
//            '__default_connection_popover'    => __('Select which connection will be used for sending transactional emails from your WordPress. If you use multiple connection then email will be routed based on source from email address', 'fluent-smtp'),
//            '__fallback_connection_popover'   => __('Fallback Connection will be used if an email is failed to send in one connection. Please select a different connection than the default connection', 'fluent-smtp'),
//            '__Email_Simulation_Label'        => __('Disable sending all emails. If you enable this, no email will be sent.', 'fluent-smtp'),
//            '__Email_Simulation_Yes'          => __('No Emails will be sent from your WordPress.', 'fluent-smtp'),
//            '__RETURN_PATH_ALERT'             => __('Return Path indicates where non-delivery receipts - or bounce messages - are to be sent. If unchecked, bounce messages may be lost. With this enabled, you\'ll be emailed using "From Email" if any messages bounce as a result of issues with the recipient’s email.', 'fluent-smtp'),
//            '__RETURN_PATH_TOOLTIP'           => __(sprintf('Return Path indicates where non - delivery receipts - or bounce messages - %1s are to be sent. If unchecked, bounce messages may be lost. With this enabled, %2s you\'ll be emailed using "From Email" if any messages bounce as a result of issues with the recipient\'s email.', '<br />', '<br />'), 'fluent-smtp'),
//            '__TEST_EMAIL_INST'               => __('Enter email address where test email will be sent (By default, logged in user email will be used if email address is not provided).', 'fluent-smtp'),
//            '__ABOUT_INTRO'                   => __('is a free and opensource WordPress Plugin. Our mission is to provide the ultimate email delivery solution with your favorite Email sending service.FluentSMTP is built for performance and speed.', 'fluent-smtp'),
//            '__ABOUT_BY'                      => __('FluentSMTP is free and will be always free.This is our pledge to WordPress community from WPManageNinja LLC.', 'fluent-smtp'),
//            '__ABOUT_POWERED'                 => __('FluentSMTP is powered by it\'s users like you. Feel free to contribute on Github. Thanks to all of our contributors.', 'fluent-smtp'),
//            '__ABOUT_COMMUNITY'               => __('FluentSMTP is powered by community.We listen to our community users and build products that add values to businesses and save time.', 'fluent-smtp'),
//            '__ABOUT_JOIN'                    => __('Join our communities and participate in great conversations.', 'fluent-smtp'),
//            '__GMAIL_SUCCESS'                 => __('Your Gmail / Google Workspace Authentication has been enabled. No further action is needed. If you want to re-authenticate, ', 'fluent-smtp'),
//            '__GMAIL_CODE_INSTRUCTION'        => __('Simply copy the following snippet and replace the stars with the corresponding credential.Then simply paste to wp-config.php file of your WordPress installation', 'fluent-smtp'),
//            '__SLACK_NOTIFICATION_ENABLED'    => __('Your FluentSMTP plugin is currently integrated with your Slack Channel. Receive timely notifications on Slack for any email sending issues from your website. This ongoing connection ensures you\'re always informed about your email delivery status.', 'fluent-smtp'),
//            '__DISCORD_NOTIFICATION_ENABLED'  => __('Your FluentSMTP plugin is currently integrated with your Discord Channel.Receive timely notifications on Discord for any email sending issues from your website.This ongoing connection ensures you\'re always informed about your email delivery status.', 'fluent-smtp'),
//            '__WP_CONFIG_INSTRUCTION'         => __('Simply copy the following snippet and replace the stars with the corresponding credential. Then simply paste to wp-config.php file of your WordPress installation', 'fluent-smtp'),
//            '__SLACK_INTRO'                   => __('Get real-time notification on your Slack Channel on any email sending failure. Configure notification with Slack Bot to start getting real time notifications.', 'fluent-smtp'),
//            '__DISCORD_INTRO'                 => __('Get real - time notification on your Discord Channel on any email sending failure.Configure notification with Discord to start getting real time notifications.', 'fluent-smtp'),
//            '__FC_DESC'                       => __(' is the best and complete feature-rich Email Marketing & CRM solution. It is also the simplest and fastest CRM and Marketing Plugin on WordPress. Manage your customer relationships, build your email lists, send email campaigns, build funnels, and make more profit and increase your conversion rates. (Yes, It’s Free!)', 'fluent-smtp'),
//            '__FF_DESC'                       => __('is the ultimate user-friendly, fast, customizable drag-and-drop WordPress Contact Form Plugin that offers you all the premium features, plus many more completely unique additional features.', 'fluent-smtp'),
//            '__NT_DESC'                       => __('Looking for a WordPress table plugin for your website? Then you’re in the right place.', 'fluent-smtp'),
//            '__NT_DESC_EXT'                   => __('the best WP table plugin that comes with all the solutions to the problems you face while creating tables on your posts/pages.', 'fluent-smtp'),
//            '__TELEGRAM_NOTIFICATION_ENABLED' => __(sprintf('Your FluentSMTP plugin is currently integrated with Telegram.Receive timely notifications from %s on Telegram for any email sending issues from your website. This ongoing connection ensures you\'re always informed about your email delivery status.', '<a target="_blank" rel="noopener" href="https://t.me/fluentsmtp_bot">@fluentsmtp_bot</a>'), 'fluent-smtp'),
//            '__TLS_HELP'                      => __('(By default, the TLS encryption would be used if the server supports it. On some servers, it could be a problem and may need to be disabled.)', 'fluent-smtp'),
//            '__SMTP_CRED_HELP'                => __('(If you need to provide your SMTP server\'s credentials (username and password) enable the authentication, in most cases this is required.)', 'fluent-smtp'),
//            '__ANOTHER_CONNECTION_NOTICE'     => __('Another connection with same email address exist. This connection will replace that connection', 'fluent-smtp'),
//            '__EMAIL_LOGGING_OFF'             => __('Email Logging is currently turned off. Only Failed and resent emails will be shown here', 'fluent-smtp'),
//            '__EMAIL_SUMMARY_INTRO'           => __('Email summary is useful for getting weekly or daily emails about the all the email sending stats for this site.', 'fluent-smtp'),
//            '__SUBSCRIBE_INTRO'               => __('Subscribe with your email to know about this plugin updates, releases and useful tips.', 'fluent-smtp'),
//            '__DEFAULT_MAIl_WARNING'          => __('The Default(none) option does not use SMTP and will not improve email delivery on your site.', 'fluent-smtp'),
//            '__TELE_RESPONSE_ERROR'           => __('We could not fetch the Telegram notification status.Here is the server response: ', 'fluent-smtp'),
//            '__FORCE_SENDER_NAME_TIP'         => __('When checked, the From Name setting above will be used for all emails, ignoring values set by other plugins.', 'fluent-smtp'),
//            '__SUPPORT_INTRO'                 => __(sprintf('Please view the %1s first. If you still can\'t find the answer %2s  and we will be happy to answer your questions and assist you with any problems.', '<a href="https://fluentsmtp.com/docs" target="_blank" rel="noopener">' . __('documentation', 'fluent-smtp') . '</a>', '<a href="https://wpmanageninja.com/support-tickets/" target="_blank" rel="noopener">' . __('open a support ticket', 'fluent-smtp') . '</a>'), 'fluent-smtp'),
//            '__DEFAULT_CONNECTION_CONFLICT'   => __('Default and Fallback connection can not be same. Please select different connections.', 'fluent-smtp'),
//            '__MAILGUN_URL_TIP'               => __('Define which endpoint you want to use for sending messages.', 'fluent-smtp'),
//            '__PEPIPOST_HELP'                 => __('Follow this link to get an API Key from Pepipost(Click Show button on Settings Page):', 'fluent-smtp'),
//            '__POSTMARK_HELP'                 => __('Follow this link to get an API Key from Postmark(Your API key is in the API Tokens tab of your):', 'fluent-smtp'),
//            '__TELE_INTRO'                    => __(sprintf('Get real - time notification on your %1s on any email sending failure. Configure notification with FluentSMTP\'s official %2s to start getting real time notifications. ', '<a target="_blank" rel="noopener" href="https://telegram.org/">Telegram Messenger</a>', '<a target="_blank" rel="noopener" href="https://t.me/fluentsmtp_bot">telegram bot</a>'), 'fluent-smtp'),
//            '__SLACK_TERMS'                   => __(sprintf('I agree to the %1s of this slack integration.', '<a target="_blank" rel="noopener" href="https://fluentsmtp.com/terms-and-conditions/">' . __('terms and conditions', 'fluent-smtp') . '</a>'), 'fluent-smtp'),
//            '__GIT_CONTRIBUTE'                => __(sprintf('If you are a developer and would like to contribute to the project, Please %s', '<a target="_blank" rel="nofollow" href="https://github.com/WPManageNinja/fluent-smtp/">' . __('contribute on GitHub', 'fluent-smtp') . '</a>'), 'fluent-smtp'), 'If you are operating under EU laws, you may be required to use EU region.' => __('If you are operating under EU laws, you may be required to use EU region.', 'fluent-smtp'),
//            '__POSTMARK_CLICK'                => __('If you enable this then link tracking header will be added to the email for postmark.', 'fluent-smtp'),
//            '__POSTMARK_OPEN'                 => __('If you enable this then open tracking header will be added to the email for postmark.', 'fluent-smtp'),
//            '__TELE_LAST_STEP'                => __(sprintf('Please find %s on telegram and send following text to activate this connection.', '<a target="_blank" rel="noopener" href="https://t.me/fluentsmtp_bot"><span class="tele_bot">@fluentsmtp_bot</span></a>'), 'fluent-smtp'),
//            '__TELE_TERMS'                    => __(sprintf('I agree to the  %s of this telegram integration.', '<a target="_blank" rel="noopener" href="https://fluentsmtp.com/terms-and-conditions/">' . __('terms and conditions', 'fluent-smtp') . '</a>'), 'fluent-smtp'),
//            '__GCP_API_INST'                  => __(sprintf('Please %s to create API keys on the Google Cloud Platform.', '<a target="_blank" rel="nofollow" href="https://fluentsmtp.com/docs/connect-gmail-or-google-workspace-emails-with-fluentsmtp/">' . __('check the documentation', 'fluent-smtp') . '</a>'), 'fluent-smtp'),
//            '__GCP_INTRO'                     => __(sprintf('Google API version has been upgraded. Please %s.', '<a target="_blank" rel="noopener" href="https://fluentsmtp.com/docs/connect-gmail-or-google-workspace-emails-with-fluentsmtp/">' . __('read the doc and upgrade your API connection', 'fluent-smtp') . '</a>'), 'fluent-smtp'),
//            '__MAILGUN_REGION'                => __(sprintf('If you are operating under EU laws, you may be required to use EU region. %s.', '<a target="_blank" href="https://www.mailgun.com/regions">' . __('More information on Mailgun.com', 'fluent-smtp') . '</a>'), 'fluent-smtp'),
//        'save_connection_error_1'                                                      => __('Please select your email service provider', 'fluent-smtp'),
//            'save_connection_error_2'                                                      => __('Credential Verification Failed. Please check your inputs', 'fluent-smtp'),
//        'delete_logs_info'                                      => __('Select how many days, the logs will be saved. If you select 7 days, then logs older than 7 days will be deleted automatically.', 'fluent-smtp'),
//        'force_sender_tooltip'                                  => __('When checked, the From Name setting above will be used for all emails, ignoring values set by other plugins.', 'fluent-smtp'),
//        '__Email_TEXT_PART_Label'                                                      => __('Enable Multi-Part Plain Text version of your HTML Emails. This feature is in beta', 'fluent-smtp'),
//            '__PASSWORD_ENCRYPT_HELP'                                                      => __('This input will be securely encrypted using WP SALTS as encryption keys before save.', 'fluent-smtp'),
//            '__PASSWORD_ENCRYPT_TIP'                                                       => __('If you change your WordPress SALT Keys, this credential will become invalid. Please update this credential whenever the WP SALTS are modified.', 'fluent-smtp'),

//        ];
    }
}
