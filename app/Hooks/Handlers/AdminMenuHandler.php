<?php

namespace FluentMail\App\Hooks\Handlers;

use FluentMail\App\Models\Logger;
use FluentMail\App\Models\Settings;
use FluentMail\App\Services\Converter;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\TransStrings;

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
                    'html' => __('You do not have permission to see this data', 'fluent-smtp')
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
                __('%1$s is a free plugin & it will be always free %2$s. %3$s', 'fluent-smtp'),
                '<b>FluentSMTP</b>',
                '<a href="https://fluentsmtp.com/why-we-built-fluentsmtp-plugin/" target="_blank" rel="noopener noreferrer">'. esc_html__('(Learn why it\'s free)', 'fluent-smtp') .'</a>',
                '<a href="https://wordpress.org/support/plugin/fluent-smtp/reviews/?filter=5" target="_blank" rel="noopener noreferrer">'. esc_html__('Write a review ★★★★★', 'fluent-smtp') .'</a>'
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
            <h3 style="min-height: 170px;"><?php esc_html_e('Loading data...', 'fluent-smtp'); ?></h3>
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
        return TransStrings::getStrings();
    }
}
