<?php
/*
Plugin Name:  FluentSMTP
Plugin URI:   https://fluentsmtp.com
Description:  The Ultimate SMTP Connection Plugin for WordPress.
Version:      2.2.0
Author:       FluentSMTP & WPManageNinja Team
Author URI:   https://fluentsmtp.com
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  fluent-smtp
Domain Path:  /language
*/

define('FLUENTMAIL_PLUGIN_FILE', __FILE__);

require_once(plugin_dir_path(__FILE__) . 'boot.php');

register_activation_hook(
    __FILE__, array('\FluentMail\Includes\Activator', 'handle')
);

register_deactivation_hook(
    __FILE__, array('\FluentMail\Includes\Deactivator', 'handle')
);

function fluentSmtpInit()
{
    $application = new FluentMail\Includes\Core\Application;
    add_action('plugins_loaded', function () use ($application) {
        do_action('fluentMail_loaded', $application);
    });
}

fluentSmtpInit();

if (!function_exists('wp_mail')) :
    function wp_mail($to, $subject, $message, $headers = '', $attachments = array())
    {
        return fluentMailSend($to, $subject, $message, $headers, $attachments);
    }
else:
    if (!(defined('DOING_AJAX') && DOING_AJAX)):
        add_action('admin_notices', function () {
            if (!current_user_can('manage_options')) {
                return;
            }
            $details = new ReflectionFunction('wp_mail');
            $hints = $details->getFileName() . ':' . $details->getStartLine();
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php
                    echo sprintf(
                        __('The <strong>FluentSMTP</strong> plugin depends on
                                <a target="_blank" href="%1s">wp_mail</a> pluggable function and
                                plugin is not able to extend it. Please check if another plugin is using this and disable it for <strong>FluentSMTP</strong> to work!',
                            'fluent-smtp'), 'https://developer.wordpress.org/reference/functions/wp_mail/'
                    );
                    ?>
                </p>
                <p style="color: red;"><?php _e('Possible Conflict: ', 'fluent-smtp'); ?><?php echo $hints; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
            </div>
            <?php
        });
    endif;
endif;

/*
 * Thanks for checking the source code
 * Please check the full source here: https://github.com/WPManageNinja/fluent-smtp
 * Would love to welcome your pull request
*/
