<?php

namespace FluentMail\App\Hooks\Handlers;

use FluentMail\App\Models\Settings;
use FluentMail\Includes\Core\Application;
use FluentMail\App\Services\Mailer\Manager;

class AdminMenuHandler
{
    protected $app = null;

    public function __construct(Application $application)
    {
        $this->app = $application;
    }

    public function addFluentMailMenu()
    {
        $title = $this->app->applyCustomFilters('admin-menu-title', 'Fluent SMTP');
        
        add_submenu_page(
            'options-general.php',
            __($title, 'fluent-mail'),
            $title,
            'manage_options',
            'fluent-mail',
            [$this, 'renderApp'],
            16
        );
    }

    public function renderApp()
    {
        $this->enqueueAssets();
        $this->app->view->render('admin.menu');
    }

    private function enqueueAssets()
    {
        wp_enqueue_script(
            'fluent_mail_admin_app_boot',
            fluentMailMix('admin/js/boot.js'),
            ['jquery']
        );

        wp_enqueue_style(
            'fluent_mail_admin_app', fluentMailMix('admin/css/fluent-mail-admin.css')
        );

        wp_localize_script('fluent_mail_admin_app_boot', 'FluentMailAdmin', [
            'slug'  => FLUENTMAIL,
            'has_pro' => $this->hasPro(),
            'brand_logo' => esc_url( fluentMailMix('images/fluentsmtp.png')),
            'nonce' => wp_create_nonce(FLUENTMAIL),
            'settings' => $this->getMailerSettings()
        ]);

        do_action('fluent_mail_loading_app');

        wp_enqueue_script(
            'fluent_mail_admin_app',
            fluentMailMix('admin/js/fluent-mail-admin-app.js'),
            ['fluent_mail_admin_app_boot'],
            '1.0',
            true
        );

        wp_enqueue_script(
            'fluent_mail_admin_app_vendor',
            fluentMailMix('admin/js/vendor.js'),
            ['fluent_mail_admin_app_boot'],
            '1.0',
            true
        );
    }

    protected function hasPro()
    {
        return fluentMailHasPro();
    }

    protected function getMailerSettings()
    {
        $settings = $this->app->make(Manager::class)->getMailerConfigAndSettings(true);

        $settings = array_merge(
            $settings,
            ['user_email' => wp_get_current_user()->user_email]
        );
        
        return $settings;
    }
}
