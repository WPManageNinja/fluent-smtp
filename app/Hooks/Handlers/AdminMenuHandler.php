<?php

namespace FluentMail\App\Hooks\Handlers;

use FluentMail\App\Models\Logger;
use FluentMail\App\Models\Settings;
use FluentMail\App\Services\Converter;
use FluentMail\App\Services\NotificationHelper;
use FluentMail\App\Services\SecretMasker;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\TransStrings;

class AdminMenuHandler
{
    /**
     * The class that puts the admin app into its dark theme.
     *
     * FluentCart's, deliberately - see printThemeClass().
     */
    const DARK_CLASS = 'fluent_theme_dark';

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
            add_action('admin_head', array($this, 'printThemeClass'));

            if (isset($_REQUEST['sub_action']) && $_REQUEST['sub_action'] == 'slack_success') {
                add_action('admin_init', function () {
                    /*
                     * This writes a notification connection, which is the same
                     * authority as saving one from the settings screen, so it
                     * asks the same question every other management path asks.
                     * The nonce below is bound to whoever started the
                     * registration and so is already hard to present as another
                     * user — but a nonce proves the request was intended, not
                     * that the person making it is allowed to. Returning rather
                     * than redirecting leaves the response to the admin page,
                     * which refuses users who cannot manage anyway.
                     */
                    if (!fluentMailCurrentUserCanManage()) {
                        return;
                    }

                    /*
                     * The return URL is handed to the remote registration
                     * service and comes back to us minutes later, so a site that
                     * updates mid-flow returns carrying the old misspelled key.
                     * Reading both keeps that window working; the misspelled one
                     * can go once no in-flight registration can still hold it.
                     */
                    $nonce = Arr::get($_REQUEST, '_slack_nonce', Arr::get($_REQUEST, '_slacK_nonce'));
                    if (!wp_verify_nonce($nonce, 'fluent_smtp_slack_register_site')) {
                        wp_safe_redirect(admin_url('options-general.php?page=fluent-mail&slack_security_failed=1#/notification-settings'));
                        die();
                    }

                    $settings = (new Settings())->notificationSettings();
                    $token = (string) Arr::get($_REQUEST, 'site_token');
                    $pendingToken = (string) Arr::get($settings, 'slack.token');

                    /*
                     * hash_equals, not ==. The two operands are strings from a
                     * remote service and the database, and PHP still compares
                     * two numeric strings numerically, so '1e3' and '1000' are
                     * loosely equal. Both are cast above because hash_equals
                     * rejects a null from a connection that was never started.
                     */
                    if ($token !== '' && $pendingToken !== '' && hash_equals($pendingToken, $token)) {
                        NotificationHelper::updateChannelSettings('slack', [
                            'status'      => 'yes',
                            'token'       => sanitize_text_field($token),
                            'slack_team'  => sanitize_text_field(Arr::get($_REQUEST, 'slack_team')),
                            'webhook_url' => sanitize_url(Arr::get($_REQUEST, 'slack_webhook'))
                        ]);
                    }

                    wp_safe_redirect(admin_url('options-general.php?page=fluent-mail#/notification-settings'));
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
                <h3 style="margin: 0;"><?php esc_html_e('For SMTP, you already have FluentSMTP Installed', 'fluent-smtp'); ?></h3>
                <p><?php esc_html_e('You seem to be looking for an SMTP plugin, but there\'s no need for another one — FluentSMTP is already installed on your site. FluentSMTP is a comprehensive, free, and open-source plugin with full features available without any upsell', 'fluent-smtp'); ?>
                    (<a href="https://fluentsmtp.com/articles/why-we-built-fluentsmtp-plugin/"><?php esc_html_e('learn why it\'s free', 'fluent-smtp'); ?></a>)<?php esc_html_e('. It\'s compatible with various SMTP services, including Amazon SES, SendGrid, MailGun, ElasticEmail, SendInBlue, Google, Microsoft, and others, providing you with a wide range of options for your email needs.', 'fluent-smtp'); ?>
                </p><a href="<?php echo esc_url(admin_url('options-general.php?page=fluent-mail#/')); ?>"
                       class="wp-core-ui button button-primary"><?php esc_html_e('Go To FluentSMTP Settings', 'fluent-smtp'); ?></a>
                <p style="font-size: 80%; margin: 15px 0 0;"><?php esc_html_e('This notice is from FluentSMTP plugin to prevent plugin conflict.', 'fluent-smtp'); ?></p>
            </div>
            <?php
        }, 1);

        add_action('wp_ajax_fluent_smtp_get_dashboard_html', function () {
            // This widget should be displayed for certain high-level users only.
            if (!fluentMailCurrentUserCanManage() || apply_filters('fluent_mail_disable_dashboard_widget', false)) {
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
            fluentMailManageCapability(),
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

    /**
     * Applies the chosen theme to <html> before the page paints.
     *
     * The app itself could do this once Vue has booted, but by then the screen has
     * already been drawn light and the switch reads as a flash. This runs synchronously
     * in <head>, so the first frame is the right one.
     *
     * The storage key, the class name and the `system:<resolved>` form of the stored
     * value are all FluentCart's rather than this plugin's. The plugins sit in the same
     * admin, and a person who has chosen dark in one has chosen it for both - sharing the
     * key is what makes that true without any of them knowing about the others.
     */
    public function printThemeClass()
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'fluent-mail') {
            return;
        }

        ?>
        <script>
            (function () {
                var key = 'fluent_theme_mode',
                    stored = localStorage.getItem(key) || localStorage.getItem('fcart_admin_theme'),
                    mode = stored === 'dark' ? 'dark' : (stored === 'light' ? 'light' : 'system'),
                    dark = stored === 'dark' || stored === 'system:dark' ||
                        ((!stored || stored === 'system') && window.matchMedia &&
                            window.matchMedia('(prefers-color-scheme: dark)').matches);

                document.documentElement.setAttribute('data-fct-theme', mode);

                if (dark) {
                    document.documentElement.classList.add('<?php echo esc_js(self::DARK_CLASS); ?>');
                }
            })();
        </script>
        <?php
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
            fluentMailAssetVersion('admin/js/boot.js')
        );

        /*
         * Chart.js and vue-chartjs used to be enqueued here from a vendored copy
         * under resources/libs/chartjs/, publishing window.VueChartJs for the
         * dashboard to pick up. The vendored build was Chart.js 2.7.1 while
         * package.json declared ^3.4.1, so the version anyone read was not the
         * version that shipped. They are bundle imports now - see
         * resources/admin/Modules/Dashboard/Charts/_chart.js - which means one
         * declared version, and one place it comes from.
         */
        /*
         * DOMPurify 3.4.13, vendored at resources/libs/purify/ from the npm
         * package of the same version. It sanitizes logged email bodies before
         * they are framed, so keep it current — check the advisories when
         * bumping, and update the version in this comment with the files.
         *
         * The cache buster is the plugin version, not the library version. A
         * hard-coded library version was stale by two majors, which meant an
         * updated file would have kept serving from browser cache under the old
         * URL. The plugin version changes on every release that can carry a new
         * bundled library, so it cannot drift.
         */
        wp_enqueue_script(
            'dompurify',
            fluentMailMix('libs/purify/purify.min.js'),
            [],
            fluentMailAssetVersion('libs/purify/purify.min.js')
        );

        wp_enqueue_style(
            'fluent_mail_admin_app',
            fluentMailMix('admin/css/fluent-mail-admin.css'),
            [],
            fluentMailAssetVersion('admin/css/fluent-mail-admin.css')
        );

        $user = get_user_by('ID', get_current_user_id());

        // wp_is_file_mod_allowed() answers "are mods ALLOWED"; this flag is the
        // inverse — it hides the one-click install button — so it must be negated.
        $disable_installation = !wp_is_file_mod_allowed('install_plugins');

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
            // What a saved-but-not-shown credential looks like in `settings`. The
            // password fields compare against it to render themselves as "saved".
            'masked_key'             => SecretMasker::MASK,
            'brand_logo'             => esc_url(fluentMailMix('images/logo.svg')),
            'nonce'                  => wp_create_nonce(FLUENTMAIL),
            'settings'               => $settings,
            'images_url'             => esc_url(fluentMailMix('images/')),
            'has_fluentcrm'          => defined('FLUENTCRM'),
            'has_fluentform'         => defined('FLUENTFORM'),
            'user_email'             => $user->user_email,
            'user_display_name'      => $displayName,
            // The dashboard greets the admin by name the way FluentCart's does, so it
            // needs the avatar FluentCart reads off its own config.
            'user_avatar'            => esc_url(get_avatar_url($user->ID, ['size' => 96])),
            /*
             * Logs are stored and filtered in the site's timezone, but every date the
             * app computed came from the browser's. An administrator working from
             * another timezone got a "Today" that was the site's yesterday, and the
             * dashboard filed records under the wrong day. This is what the app dates
             * from instead of new Date(). A zone rather than a timestamp, so it stays
             * right across a daylight-saving change on a page nobody has reloaded.
             */
            'site_timezone'          => wp_timezone_string(),
            'require_optin'          => $this->isRequireOptin(),
            'has_ninja_tables'       => defined('NINJA_TABLES_VERSION'),
            'disable_recommendation' => apply_filters('fluentmail_disable_recommendation', false),
            'disable_installation'   => $disable_installation,
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
            fluentMailAssetVersion('admin/js/fluent-mail-admin-app.js'),
            true
        );

        add_filter('admin_footer_text', function ($text) {
            return sprintf(
                __('%1$s is a free plugin & it will be always free %2$s. %3$s', 'fluent-smtp'),
                '<b>FluentSMTP</b>',
                '<a href="https://fluentsmtp.com/articles/why-we-built-fluentsmtp-plugin/" target="_blank" rel="noopener noreferrer">'. esc_html__('(Learn why it\'s free)', 'fluent-smtp') .'</a>',
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

        /*
         * The last thing that happens before this array is printed into the page.
         *
         * Everything above reads the settings the way the mailers do, with the stored
         * credentials decrypted, because that is what fluentMailGetSettings() returns.
         * Handing that to wp_localize_script() put every SMTP password, API key and
         * OAuth refresh token into the HTML of every screen this plugin renders - the
         * dashboard and the logs included, not just the connection form - where
         * view-source reads them without a click, and where any admin-side XSS from
         * any other plugin collects the lot in a single property read.
         *
         * The app does not need them. It needs to know a credential is set, which the
         * mask tells it, and the admin needs to be able to replace one, which typing
         * over the mask does. See SecretMasker::resolve() for the other half.
         */
        return SecretMasker::mask($settings);
    }

    public function maybeAdminNotice()
    {
        if (!fluentMailCurrentUserCanManage()) {
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
        if (!fluentMailCurrentUserCanManage()) {
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
        if (!fluentMailCurrentUserCanManage() || apply_filters('fluent_mail_disable_dashboard_widget', false)) {
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
                    <td><?php echo esc_html($stat['title']); ?></td>
                    <td><?php echo absint($stat['sent']); ?></td>
                    <td class="<?php echo absint($stat['failed']) ? 'fstmp_failed' : ''; ?>"><?php echo absint($stat['failed']); ?></td>
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
