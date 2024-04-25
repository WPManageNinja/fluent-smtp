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
                    $settings = (new Settings())->notificationSettings();
                    $token = Arr::get($_REQUEST, 'site_token');

                    if ($token == Arr::get($settings, 'slack.token')) {
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
                <h3 style="margin: 0;">For SMTP, you already have FluentSMTP Installed</h3>
                <p>You seem to be looking for an SMTP plugin, but there's no need for another one — FluentSMTP is
                    already installed on your site. FluentSMTP is a comprehensive, free, and open-source plugin with
                    full features available without any upsell (<a
                        href="https://fluentsmtp.com/why-we-built-fluentsmtp-plugin/">learn why it's free</a>). It's
                    compatible with various SMTP services, including Amazon SES, SendGrid, MailGun, ElasticEmail,
                    SendInBlue, Google, Microsoft, and others, providing you with a wide range of options for your email
                    needs.</p>
                <a href="<?php echo admin_url('options-general.php?page=fluent-mail#/'); ?>"
                   class="wp-core-ui button button-primary">Go To FluentSMTP Settings</a>
                <p style="font-size: 80%; margin: 15px 0 0;">This notice is from FluentSMTP plugin to prevent plugin
                    conflict.</p>
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
                __('<b>FluentSMTP</b> is a free plugin & it will be always free %s. %s', 'fluent-smtp'),
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
                    <?php echo sprintf(__('WordPress version 5.5 or greater is required for FluentSMTP. You are using version %s currently. Please update your WordPress Core to use FluentSMTP Plugin.', 'fluent-smtp'), $wp_version); ?>
                </p>
            </div>
        <?php } else if (empty($connections)) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php _e('FluentSMTP needs to be configured for it to work.', 'fluent-smtp'); ?>
                </p>
                <p>
                    <a href="<?php echo admin_url('options-general.php?page=fluent-mail#/'); ?>"
                       class="button button-primary">
                        <?php _e('Configure FluentSMTP', 'fluent-smtp'); ?>
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
                    xhr.open('POST', '<?php echo admin_url('admin-ajax.php?action=fluent_smtp_get_dashboard_html'); ?>', true);
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
        $startToday = date('Y-m-d 00:00:01', $currentTimeStamp);

        $allTime = $logModel->getStats();

        $stats['today'] = [
            'title'  => __('Today', 'fluent-smtp'),
            'sent'   => ($allTime['sent']) ? $logModel->getTotalCountStat('sent', $startToday) : 0,
            'failed' => ($allTime['failed']) ? $logModel->getTotalCountStat('failed', $startToday) : 0
        ];

        $lastWeek = date('Y-m-d 00:00:01', strtotime('-7 days'));
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
                <th><?php _e('Date', 'fluent-smtp'); ?></th>
                <th><?php _e('Sent', 'fluent-smtp'); ?></th>
                <th><?php _e('Failed', 'fluent-smtp'); ?></th>
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
           href="<?php echo admin_url('options-general.php?page=fluent-mail#/'); ?>"
           class=""><?php _e('View All', 'fluent-smtp'); ?></a>
        <?php

        return ob_get_clean();
    }

    public function getTrans()
    {
        return [
            'Settings'                                              => __('Settings', 'fluent-smtp'),
            'Email Test'                                            => __('Email Test', 'fluent-smtp'),
            'Email Logs'                                            => __('Email Logs', 'fluent-smtp'),
            'Support'                                               => __('Support', 'fluent-smtp'),
            'Docs'                                                  => __('Docs', 'fluent-smtp'),
            'cancel'                                                => __('cancel', 'fluent-smtp'),
            'confirm'                                               => __('confirm', 'fluent-smtp'),
            'confirm_msg'                                           => __('Are you sure to delete this?', 'fluent-smtp'),
            'wizard_title'                                          => __('Welcome to FluentSMTP', 'fluent-smtp'),
            'wizard_sub'                                            => __('Thank you for installing FluentSMTP - The ultimate SMTP & Email Service Connection Plugin for WordPress', 'fluent-smtp'),
            'wizard_instruction'                                    => __('Please configure your first email service provider connection', 'fluent-smtp'),
            'Sending Stats'                                         => __('Sending Stats', 'fluent-smtp'),
            'Quick Overview'                                        => __('Quick Overview', 'fluent-smtp'),
            'Total Email Sent (Logged):'                            => __('Total Email Sent (Logged):', 'fluent-smtp'),
            'Email Failed:'                                         => __('Email Failed:', 'fluent-smtp'),
            'Active Connections:'                                   => __('Active Connections:', 'fluent-smtp'),
            'Active Senders:'                                       => __('Active Senders:', 'fluent-smtp'),
            'Save Email Logs:'                                      => __('Save Email Logs:', 'fluent-smtp'),
            'Delete Logs:'                                          => __('Delete Logs:', 'fluent-smtp'),
            'Days'                                                  => __('Days', 'fluent-smtp'),
            'Subscribe To Updates'                                  => __('Subscribe To Updates', 'fluent-smtp'),
            'Last week'                                             => __('Last week', 'fluent-smtp'),
            'Last month'                                            => __('Last month', 'fluent-smtp'),
            'Last 3 months'                                         => __('Last 3 months', 'fluent-smtp'),
            'By Date'                                               => __('By Date', 'fluent-smtp'),
            'Apply'                                                 => __('Apply', 'fluent-smtp'),
            'Resend Selected Emails'                                => __('Resend Selected Emails', 'fluent-smtp'),
            'Bulk Action'                                           => __('Bulk Action', 'fluent-smtp'),
            'Delete All'                                            => __('Delete All', 'fluent-smtp'),
            'Enter Full Screen'                                     => __('Enter Full Screen', 'fluent-smtp'),
            'Filter By'                                             => __('Filter By', 'fluent-smtp'),
            'Status'                                                => __('Status', 'fluent-smtp'),
            'Date'                                                  => __('Date', 'fluent-smtp'),
            'Date Range'                                            => __('Date Range', 'fluent-smtp'),
            'Select'                                                => __('Select', 'fluent-smtp'),
            'Successful'                                            => __('Successful', 'fluent-smtp'),
            'Failed'                                                => __('Failed', 'fluent-smtp'),
            'Select date'                                           => __('Select date', 'fluent-smtp'),
            'Select date and time'                                  => __('Select date and time', 'fluent-smtp'),
            'Start date'                                            => __('Start date', 'fluent-smtp'),
            'End date'                                              => __('End date', 'fluent-smtp'),
            'Filter'                                                => __('Filter', 'fluent-smtp'),
            'Type & press enter...'                                 => __('Type & press enter...', 'fluent-smtp'),
            'Subject'                                               => __('Subject', 'fluent-smtp'),
            'To'                                                    => __('To', 'fluent-smtp'),
            'Date-Time'                                             => __('Date-Time', 'fluent-smtp'),
            'Actions'                                               => __('Actions', 'fluent-smtp'),
            'Retry'                                                 => __('Retry', 'fluent-smtp'),
            'Resend'                                                => __('Resend', 'fluent-smtp'),
            'Turn On'                                               => __('Turn On', 'fluent-smtp'),
            'Resent Count'                                          => __('Resent Count', 'fluent-smtp'),
            'Email Body'                                            => __('Email Body', 'fluent-smtp'),
            'Attachments'                                           => __('Attachments', 'fluent-smtp'),
            'Next'                                                  => __('Next', 'fluent-smtp'),
            'Prev'                                                  => __('Prev', 'fluent-smtp'),
            'Search Results for'                                    => __('Search Results for', 'fluent-smtp'),
            'Sender Settings'                                       => __('Sender Settings', 'fluent-smtp'),
            'From Email'                                            => __('From Email', 'fluent-smtp'),
            'Force From Email (Recommended Settings: Enable)'       => __('Force From Email (Recommended Settings: Enable)', 'fluent-smtp'),
            'from_email_tooltip'                                    => __('If checked, the From Email setting above will be used for all emails (It will check if the from email is listed to available connections).', 'fluent-smtp'),
            'Set the return-path to match the From Email'           => __('Set the return-path to match the From Email', 'fluent-smtp'),
            'From Name'                                             => __('From Name', 'fluent-smtp'),
            'Force Sender Name'                                     => __('Force Sender Name', 'fluent-smtp'),
            'Save Connection Settings'                              => __('Save Connection Settings', 'fluent-smtp'),
            'save_connection_error_1'                               => __('Please select your email service provider', 'fluent-smtp'),
            'save_connection_error_2'                               => __('Credential Verification Failed. Please check your inputs', 'fluent-smtp'),
            'force_sender_tooltip'                                  => __('When checked, the From Name setting above will be used for all emails, ignoring values set by other plugins.', 'fluent-smtp'),
            'Validating Data. Please wait'                          => __('Validating Data. Please wait', 'fluent-smtp'),
            'Active Email Connections'                              => __('Active Email Connections', 'fluent-smtp'),
            'Add Another Connection'                                => __('Add Another Connection', 'fluent-smtp'),
            'Provider'                                              => __('Provider', 'fluent-smtp'),
            'Connection Details'                                    => __('Connection Details', 'fluent-smtp'),
            'Close'                                                 => __('Close', 'fluent-smtp'),
            'General Settings'                                      => __('General Settings', 'fluent-smtp'),
            'Alerts'                                                => __('Alerts', 'fluent-smtp'),
            'Add Connection'                                        => __('Add Connection', 'fluent-smtp'),
            'Edit Connection'                                       => __('Edit Connection', 'fluent-smtp'),
            'routing_info'                                          => __('Your emails will be routed automatically based on From email address. No additional configuration is required.', 'fluent-smtp'),
            'Enable Email Summary'                                  => __('Enable Email Summary', 'fluent-smtp'),
            'Enable Email Summary Notification'                     => __('Enable Email Summary Notification', 'fluent-smtp'),
            'Notification Email Addresses'                          => __('Notification Email Addresses', 'fluent-smtp'),
            'Email Address'                                         => __('Email Address', 'fluent-smtp'),
            'Notification Days'                                     => __('Notification Days', 'fluent-smtp'),
            'Save Settings'                                         => __('Save Settings', 'fluent-smtp'),
            'Log All Emails for Reporting'                          => __('Log All Emails for Reporting', 'fluent-smtp'),
            'Disable Logging for FluentCRM Emails'                  => __('Disable Logging for FluentCRM Emails', 'fluent-smtp'),
            'FluentCRM Email Logging'                               => __('FluentCRM Email Logging', 'fluent-smtp'),
            'Delete Logs'                                           => __('Delete Logs', 'fluent-smtp'),
            'delete_logs_info'                                      => __('Select how many days, the logs will be saved. If you select 7 days, then logs older than 7 days will be deleted automatically.', 'fluent-smtp'),
            'Default Connection'                                    => __('Default Connection', 'fluent-smtp'),
            'Fallback Connection'                                   => __('Fallback Connection', 'fluent-smtp'),
            'default_connection_popover'                            => __('Select which connection will be used for sending transactional emails from your WordPress. If you use multiple connection then email will be routed based on source from email address', 'fluent-smtp'),
            'fallback_connection_popover'                           => __('Fallback Connection will be used if an email is failed to send in one connection. Please select a different connection than the default connection', 'fluent-smtp'),
            'Please add another connection to use fallback feature' => __('Please add another connection to use fallback feature', 'fluent-smtp'),
            'Email Simulation'                                      => __('Email Simulation', 'fluent-smtp'),
            'Email_Simulation_Label'                                => __('Disable sending all emails. If you enable this, no email will be sent.', 'fluent-smtp'),
            'Email_Simulation_Yes'                                  => __('No Emails will be sent from your WordPress.', 'fluent-smtp'),
            'Sending by time of day'                                => __('Sending by time of day', 'fluent-smtp'),
            'More'                                                  => __('More', 'fluent-smtp'),
            'Less'                                                  => __('Less', 'fluent-smtp'),
            'Last 7 Days'                                           => __('Last 7 Days', 'fluent-smtp'),
            'Last 30 Days'                                          => __('Last 30 Days', 'fluent-smtp'),
            'All Time'                                              => __('All Time', 'fluent-smtp')
        ];
    }
}
