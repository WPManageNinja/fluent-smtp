<?php

use FluentMail\App\Services\Mailer\Manager;
use FluentMail\App\Services\Mailer\Providers\AmazonSes\SimpleEmailService;
use FluentMail\App\Services\Mailer\Providers\Factory;

if (!function_exists('fluentMail')) {
    /**
     * Returns an instance of the FluentMail\App\App class.
     *
     * @param string|null $module The module name (optional).
     * @return FluentMail\App\App The instance of the FluentMail\App\App class.
     */
    function fluentMail($module = null)
    {
        return FluentMail\App\App::getInstance($module);
    }
}

if (!function_exists('fluentMailManageCapability')) {
    /**
     * The capability required to view and change FluentSMTP's settings.
     *
     * Every administrative surface in the plugin is guarded by this: the AJAX
     * controllers, the settings page, the dashboard widget and the admin notices.
     * It is a single value on purpose, so a site cannot end up with a settings
     * screen that opens and an API that refuses, or the reverse.
     *
     * The default is unchanged, so nothing differs on an ordinary installation.
     * The filter exists for hosts and multi-tenant products where the people who
     * administer email are deliberately not WordPress administrators — granting
     * them `manage_options` to reach this screen would hand over the whole site.
     *
     * Note for anyone filtering this: `manage_options` does not imply a custom
     * capability, so a site administrator loses access unless the replacement is
     * also granted to them.
     *
     * @since 2.3.0
     *
     * @param string $capability Capability name. Default `manage_options`.
     * @return string
     */
    function fluentMailManageCapability()
    {
        return apply_filters('fluent_mail/manage_capability', 'manage_options');
    }
}

if (!function_exists('fluentMailCurrentUserCanManage')) {
    /**
     * Whether the current user may administer FluentSMTP.
     *
     * @since 2.3.0
     *
     * @return bool
     */
    function fluentMailCurrentUserCanManage()
    {
        return current_user_can(fluentMailManageCapability());
    }
}

if (!function_exists('fluentMailMix')) {
    /**
     * Generate the mixed URL for the given asset path.
     *
     * @param string $path The asset path.
     * @param string $manifestDirectory The directory where the manifest file is located (optional).
     * @return string The mixed URL for the asset.
     */
    function fluentMailMix($path, $manifestDirectory = '')
    {
        return fluentMail('url.assets') . ltrim($path, '/');
    }
}

if (!function_exists('fluentMailAssetUrl')) {
    /**
     * Returns the URL for the assets of the Fluent Mail plugin.
     *
     * @param string|null $path The path to the asset file (optional).
     * @return string The URL for the assets of the Fluent Mail plugin.
     */
    function fluentMailAssetUrl($path = null)
    {
        $assetUrl = fluentMail('url.assets');
        return $path ? ($assetUrl . $path) : $assetUrl;
    }
}

if (!function_exists('fluentMailIsListedSenderEmail')) {
    /**
     * Check if the given email is listed as a sender email in the Fluent SMTP plugin settings.
     *
     * @param string $email The email address to check.
     * @return bool Returns true if the email is listed as a sender email, false otherwise.
     */
    function fluentMailIsListedSenderEmail($email)
    {
        static $settings;

        if (!$settings) {
            $settings = fluentMailGetSettings();
        }

        if (!$settings) {
            return false;
        }
        return !empty($settings['mappings'][$email]);
    }
}

if (!function_exists('fluentMailDefaultConnection')) {
    /**
     * Retrieves the default mail connection settings.
     *
     * @return array The default mail connection settings.
     */
    function fluentMailDefaultConnection()
    {
        static $defaultConnection;

        if ($defaultConnection) {
            return $defaultConnection;
        }

        $settings = fluentMailGetSettings();

        if (!$settings) {
            return [];
        }

        if (
            isset($settings['misc']['default_connection']) &&
            isset($settings['connections'][$settings['misc']['default_connection']])
        ) {
            $default = $settings['misc']['default_connection'];
            $defaultConnection = $settings['connections'][$default]['provider_settings'];
        } else if (count($settings['connections'])) {
            $connection = reset($settings['connections']);
            $defaultConnection = $connection['provider_settings'];
        } else {
            $defaultConnection = [];
        }

        return $defaultConnection;
    }
}

if (!function_exists('fluentMailgetConnection')) {
    /**
     * Get the connection for the given email address.
     *
     * @param string $email The email address.
     * @return Connection The connection object.
     */
    function fluentMailgetConnection($email)
    {
        $factory = fluentMail(Factory::class);
        if (!($connection = $factory->get($email))) {
            $connection = fluentMailDefaultConnection();
        }

        return $connection;
    }
}

if (!function_exists('fluentMailGetProvider')) {
    /**
     * Get the FluentMail provider for the given email address.
     *
     * @param string $fromEmail The email address to get the provider for.
     * @param bool $cacheClear Whether to clear the provider cache.
     * @return \FluentMail\App\Services\Mailer\Providers\Simulator\Handler|false The FluentMail provider for the given email address, or false if no provider is found.
     */
    function fluentMailGetProvider($fromEmail, $cacheClear = false)
    {
        static $providers = [];

        if (isset($providers[$fromEmail]) && !$cacheClear) {
            return $providers[$fromEmail];
        }

        $manager = fluentMail(Manager::class);

        $misc = $manager->getSettings('misc');

        if ((!empty($misc['simulate_emails']) && $misc['simulate_emails'] == 'yes') || (defined('FLUENTMAIL_SIMULATE_EMAILS') && FLUENTMAIL_SIMULATE_EMAILS)) {
            $providers[$fromEmail] = new FluentMail\App\Services\Mailer\Providers\Simulator\Handler();
            return $providers[$fromEmail];
        }

        $mappings = $manager->getSettings('mappings');

        $connection = false;

        if (isset($mappings[$fromEmail])) {
            $connectionId = $mappings[$fromEmail];
            $connections = $manager->getSettings('connections');
            if (isset($connections[$connectionId])) {
                $connection = $connections[$connectionId]['provider_settings'];
            }
        }

        if (!$connection) {
            $connection = fluentMailDefaultConnection();
            if ($connection && \FluentMail\Includes\Support\Arr::get($connection, 'force_from_email') != 'no') {
                $connection['force_from_email_id'] = $connection['sender_email'];
            }
        }

        if ($connection) {
            $factory = fluentMail(Factory::class);
            $driver = $factory->make($connection['provider']);
            $driver->setSettings($connection);
            $providers[$fromEmail] = $driver;
        } else {
            $providers[$fromEmail] = false;
        }

        return $providers[$fromEmail];
    }
}

if (!function_exists('fluentMailSesConnection')) {
    /**
     * Establishes a connection to the Fluent SMTP service using the Amazon Simple Email Service (SES).
     *
     * @param array $connection The connection details including sender email, access key, secret key, and region.
     * @return SimpleEmailService The SES driver instance for the specified sender email.
     */
    function fluentMailSesConnection($connection)
    {
        static $drivers = [];

        if (isset($drivers[$connection['sender_email']])) {
            return $drivers[$connection['sender_email']];
        }

        $region = SimpleEmailService::regionToHost($connection['region']);

        $ses = new SimpleEmailService(
            $connection['access_key'],
            $connection['secret_key'],
            $region,
            false
        );

        $drivers[$connection['sender_email']] = $ses;

        return $drivers[$connection['sender_email']];
    }
}

if (!function_exists('fluentMailSend')) {
    /**
     * Sends an email using the wp_mail() function with additional filters and pre-send checks.
     *
     * Mirrors WordPress core's wp_mail(), which this replaces, so that hooks and
     * arguments behave identically for anything that calls wp_mail().
     *
     * When using the `$embeds` parameter to embed images for use in HTML emails,
     * reference the embedded file in your HTML with a `cid:` URL whose value
     * matches the file's Content-ID. By default, the Content-ID (`cid`) used for
     * each embedded file is the key in the embeds array, unless modified via the
     * {@see 'wp_mail_embed_args'} filter. For example:
     *
     * `<img src="cid:0" alt="Logo">`
     * `<img src="cid:my-image" alt="Image">`
     *
     * Note: embedded images are applied to the PHPMailer instance, so they work on
     * SMTP connections. Providers that send over their own HTTP API build their
     * payload separately and currently ignore embeds.
     *
     * @since 6.9.0 The `$embeds` parameter was added, matching WP core.
     *
     * @param string|array $to Array or comma-separated list of email addresses to send the message to.
     * @param string $subject The subject of the email.
     * @param string $message The message content of the email.
     * @param string|array $headers Additional headers for the email.
     * @param string|array $attachments Paths to files to attach to the email.
     * @param string|array $embeds Optional. Paths to files to embed.
     *
     * @return bool|null Returns true if the email was successfully sent, false if sending was preempted, or null if there was an error.
     *
     *
     * @filter wp_mail
     * @filter pre_wp_mail
     */
    function fluentMailSend($to, $subject, $message, $headers = '', $attachments = array(), $embeds = array())
    {
        // Compact the input, apply the filters, and extract them back out.
        /**
         * Filters the wp_mail() arguments.
         *
         * @param array $args A compacted array of wp_mail() arguments, including the "to" email,
         *                    subject, message, headers, attachments and embeds values.
         * @since 2.2.0
         * @since 6.9.0 The `$embeds` element was added to the `$args` array.
         *
         */
        $atts = apply_filters(
            'wp_mail', compact('to', 'subject', 'message', 'headers', 'attachments', 'embeds')
        );

        /**
         * Filters whether to preempt sending an email.
         *
         * Returning a non-null value will short-circuit {@see wp_mail()}, returning
         * that value instead. A boolean return value should be used to indicate whether
         * the email was successfully sent.
         *
         * @param null|bool $return Short-circuit return value.
         * @param array $atts {
         *     Array of the `wp_mail()` arguments.
         *
         * @type string|string[] $to Array or comma-separated list of email addresses to send message.
         * @type string $subject Email subject.
         * @type string $message Message contents.
         * @type string|string[] $headers Additional headers.
         * @type string|string[] $attachments Paths to files to attach.
         * @type string|string[] $embeds Paths to files to embed.
         * }
         * @since 5.7.0
         *
         */
        $pre_wp_mail = apply_filters('pre_wp_mail', null, $atts);

        if (null !== $pre_wp_mail) {
            return $pre_wp_mail;
        }

        if (isset($atts['to'])) {
            $to = $atts['to'];
        }

        if (!is_array($to)) {
            $to = explode(',', $to);
        }

        if (isset($atts['subject'])) {
            $subject = $atts['subject'];
        }

        if (isset($atts['message'])) {
            $message = $atts['message'];
        }

        if (isset($atts['headers'])) {
            $headers = $atts['headers'];
        }

        if (isset($atts['attachments'])) {
            $attachments = $atts['attachments'];
        }

        if (!is_array($attachments)) {
            $attachments = explode("\n", str_replace("\r\n", "\n", $attachments));
        }

        if (isset($atts['embeds'])) {
            $embeds = $atts['embeds'];
        }

        if (!is_array($embeds)) {
            $embeds = explode("\n", str_replace("\r\n", "\n", $embeds));
        }

        global $phpmailer;

        // (Re)create it, if it's gone missing.
        if (!($phpmailer instanceof PHPMailer\PHPMailer\PHPMailer)) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

            // WP 6.8+ ships WP_PHPMailer, which routes PHPMailer's error strings
            // through WordPress translations. Guarded on the file, not a version
            // number, because this plugin still supports WP 5.5 where it is absent.
            $wpPhpMailerFile = ABSPATH . WPINC . '/class-wp-phpmailer.php';

            if (file_exists($wpPhpMailerFile)) {
                require_once $wpPhpMailerFile;
                $phpmailer = new WP_PHPMailer(true);
            } else {
                $phpmailer = new PHPMailer\PHPMailer\PHPMailer(true);
            }

            $phpmailer::$validator = static function ($email) {
                return (bool)is_email($email);
            };
        }

        // Headers.
        $cc = array();
        $bcc = array();
        $reply_to = array();

        if (empty($headers)) {
            $headers = array();
        } else {
            if (!is_array($headers)) {
                // Explode the headers out, so this function can take
                // both string headers and an array of headers.
                $tempheaders = explode("\n", str_replace("\r\n", "\n", $headers));
            } else {
                $tempheaders = $headers;
            }
            $headers = array();

            // If it's actually got contents.
            if (!empty($tempheaders)) {
                // Iterate through the raw headers.
                foreach ((array)$tempheaders as $header) {
                    if (strpos($header, ':') === false) {
                        if (false !== stripos($header, 'boundary=')) {
                            $parts = preg_split('/boundary=/i', trim($header));
                            $boundary = trim(str_replace(array("'", '"'), '', $parts[1]));
                        }
                        continue;
                    }
                    // Explode them out.
                    list($name, $content) = explode(':', trim($header), 2);

                    // Cleanup crew.
                    $name = trim($name);
                    $content = trim($content);

                    switch (strtolower($name)) {
                        // Mainly for legacy -- process a "From:" header if it's there.
                        case 'from':
                            $bracket_pos = strpos($content, '<');
                            if (false !== $bracket_pos) {
                                // Text before the bracketed email is the "From" name.
                                if ($bracket_pos > 0) {
                                    $from_name = substr($content, 0, $bracket_pos);
                                    $from_name = str_replace('"', '', $from_name);
                                    $from_name = trim($from_name);
                                }

                                $from_email = substr($content, $bracket_pos + 1);
                                $from_email = str_replace('>', '', $from_email);
                                $from_email = trim($from_email);

                                // Avoid setting an empty $from_email.
                            } elseif ('' !== trim($content)) {
                                $from_email = trim($content);
                            }
                            break;
                        case 'content-type':
                            if (strpos($content, ';') !== false) {
                                list($type, $charset_content) = explode(';', $content);
                                $content_type = trim($type);
                                if (false !== stripos($charset_content, 'charset=')) {
                                    $charset = trim(str_replace(array('charset=', '"'), '', $charset_content));
                                } elseif (false !== stripos($charset_content, 'boundary=')) {
                                    $boundary = trim(str_replace(array('BOUNDARY=', 'boundary=', '"'), '', $charset_content));
                                    $charset = '';
                                    // Normalize the subtype and re-attach the boundary, so a
                                    // header like "Content-Type: multipart/MIXED; boundary=x"
                                    // yields a well-formed lowercase type. Matches WP 6.9.
                                    if (preg_match('~^multipart/(\S+)~', $content_type, $matches)) {
                                        $content_type = 'multipart/' . strtolower($matches[1]) . '; boundary="' . $boundary . '"';
                                    }
                                }

                                // Avoid setting an empty $content_type.
                            } elseif ('' !== trim($content)) {
                                $content_type = trim($content);
                            }
                            break;
                        case 'cc':
                            $cc = array_merge((array)$cc, explode(',', $content));
                            break;
                        case 'bcc':
                            $bcc = array_merge((array)$bcc, explode(',', $content));
                            break;
                        case 'reply-to':
                            $reply_to = array_merge((array)$reply_to, explode(',', $content));
                            break;
                        default:
                            // Add it to our grand headers array.
                            $headers[trim($name)] = trim($content);
                            break;
                    }
                }
            }
        }

        // Empty out the values that may be set.
        $phpmailer->clearAllRecipients();
        $phpmailer->clearAttachments();
        $phpmailer->clearCustomHeaders();
        $phpmailer->clearReplyTos();
        $phpmailer->Body = '';
        $phpmailer->AltBody = '';
        // The instance now persists across sends, so the envelope sender must
        // reset like a fresh instance would. This runs before phpmailer_init,
        // so listeners (and the return_path handlers) still set it per send.
        $phpmailer->Sender = '';

        /*
         * Reset encoding to 8-bit, as it may have been automatically downgraded
         * to 7-bit by PHPMailer (based on the body contents) in a previous call
         * to wp_mail().
         *
         * Matches WP core. This matters more here than it does in core: the
         * PHPMailer instance is now deliberately kept alive across sends for
         * SMTP connection reuse, so without this reset one plain-ASCII email
         * would downgrade the encoding for every later email in the request.
         *
         * See https://core.trac.wordpress.org/ticket/33972
         */
        $phpmailer->Encoding = PHPMailer\PHPMailer\PHPMailer::ENCODING_8BIT;

        /*
     * If we don't have an email from the input headers, default to wordpress@$sitename
     * Some hosts will block outgoing mail from this address if it doesn't exist,
     * but there's no easy alternative. Defaulting to admin_email might appear to be
     * another option, but some hosts may refuse to relay mail from an unknown domain.
     * See https://core.trac.wordpress.org/ticket/5007.
     */
        $defaultConnection = false;
        if (!isset($from_email)) {
            $defaultConnection = fluentMailDefaultConnection();

            if (!empty($defaultConnection['sender_email'])) {
                $from_email = $defaultConnection['sender_email'];
            } else {
                // Get the site domain and get rid of www.
                $sitename = wp_parse_url(network_home_url(), PHP_URL_HOST);
                if ('www.' === substr($sitename, 0, 4)) {
                    $sitename = substr($sitename, 4);
                }
                $from_email = 'wordpress@' . $sitename;
            }
        }

        // Set "From" name and email.
        // If we don't have a name from the input headers.
        if (!isset($from_name)) {
            if ($defaultConnection && !empty($defaultConnection['sender_name'])) {
                $from_name = $defaultConnection['sender_name'];
            } else {
                $provider = fluentMailGetProvider($from_email);
                if ($provider && !empty($provider->getSetting('sender_name'))) {
                    $from_name = $provider->getSetting('sender_name');
                } else {
                    $from_name = 'WordPress';
                }
            }
        }

        if (!apply_filters('fluentsmtp_disable_from_name_email', false)) {
            /**
             * Filters the email address to send from.
             *
             * @param string $from_email Email address to send from.
             * @since 2.2.0
             *
             */
            $from_email = apply_filters('wp_mail_from', $from_email);
        }

        /**
         * Filters the name to associate with the "from" email address.
         *
         * @param string $from_name Name associated with the "from" email address.
         * @since 2.3.0
         *
         */
        $from_name = apply_filters('wp_mail_from_name', $from_name);

        try {
            $phpmailer->setFrom($from_email, $from_name, false);
        } catch (PHPMailer\PHPMailer\Exception $e) {
            $mail_error_data = compact('to', 'subject', 'message', 'headers', 'attachments');
            $mail_error_data['phpmailer_exception_code'] = $e->getCode();

            /**
             * This filter is documented in wp-includes/pluggable.php
             *
             * @param WP_Error $error A WP_Error object containing the error message.
             * @return void
             */
            do_action('wp_mail_failed', new WP_Error('wp_mail_failed', $e->getMessage(), $mail_error_data));

            return false;
        }

        // Set mail's subject and body.
        $phpmailer->Subject = $subject;
        $phpmailer->Body = $message;

        // Set destination addresses, using appropriate methods for handling addresses.
        $address_headers = compact('to', 'cc', 'bcc', 'reply_to');

        foreach ($address_headers as $address_header => $addresses) {
            if (empty($addresses)) {
                continue;
            }

            foreach ((array)$addresses as $address) {
                try {
                    // Break $recipient into name and address parts if in the format "Foo <bar@baz.com>".
                    $recipient_name = '';

                    if (preg_match('/(.*)<(.+)>/', $address, $matches)) {
                        if (count($matches) == 3) {
                            $recipient_name = $matches[1];
                            $address = $matches[2];
                        }
                    }

                    switch ($address_header) {
                        case 'to':
                            $phpmailer->addAddress($address, $recipient_name);
                            break;
                        case 'cc':
                            $phpmailer->addCc($address, $recipient_name);
                            break;
                        case 'bcc':
                            $phpmailer->addBcc($address, $recipient_name);
                            break;
                        case 'reply_to':
                            $phpmailer->addReplyTo($address, $recipient_name);
                            break;
                    }
                } catch (PHPMailer\PHPMailer\Exception $e) {
                    continue;
                }
            }
        }

        // Set to use PHP's mail(). This runs before phpmailer_init, so a host
        // or plugin listening there still has the final word on the transport,
        // and nothing an earlier send left on the persistent instance carries
        // into this one.
        $phpmailer->isMail();
        FluentMail\App\Services\Mailer\BaseHandler::forgetSmtpTransportClaim();

        // Set Content-Type and charset.

        // If we don't have a content-type from the input headers.
        if (!isset($content_type)) {
            $content_type = 'text/plain';
        }

        /**
         * Filters the wp_mail() content type.
         *
         * @param string $content_type Default wp_mail() content type.
         * @since 2.3.0
         *
         */
        $content_type = apply_filters('wp_mail_content_type', $content_type);

        $phpmailer->ContentType = $content_type;

        // If we don't have a charset from the input headers.
        if (!isset($charset)) {
            $charset = get_bloginfo('charset');
        }

        /**
         * Filters the default wp_mail() charset.
         *
         * @param string $charset Default email charset.
         * @since 2.3.0
         *
         */
        $phpmailer->CharSet = apply_filters('wp_mail_charset', $charset);

        // Set custom headers.
        if (!empty($headers)) {
            foreach ((array)$headers as $name => $content) {
                // Only add custom headers not added automatically by PHPMailer.
                if (!in_array($name, array('MIME-Version', 'X-Mailer'), true)) {
                    try {
                        $phpmailer->addCustomHeader(sprintf('%1$s: %2$s', $name, $content));
                    } catch (PHPMailer\PHPMailer\Exception $e) {
                        continue;
                    }
                }
            }

            /*
             * The boundary is no longer re-attached here. As of WP 6.9 the boundary is
             * folded into $content_type while parsing the Content-Type header above, and
             * that value is assigned to $phpmailer->ContentType. Emitting it again as a
             * custom header would produce a duplicate Content-Type carrying the boundary
             * twice.
             */
        }


        if (!empty($attachments)) {
            foreach ($attachments as $filename => $attachment) {
                $filename = is_string($filename) ? $filename : '';

                try {
                    $phpmailer->addAttachment($attachment, $filename);
                } catch (PHPMailer\PHPMailer\Exception $e) {
                    continue;
                }
            }
        }

        if (!empty($embeds)) {
            foreach ($embeds as $key => $embed_path) {
                /**
                 * Filters the arguments for PHPMailer's addEmbeddedImage() method.
                 *
                 * @since 6.9.0
                 *
                 * @param array $args {
                 *     An array of arguments for PHPMailer's addEmbeddedImage() method.
                 *
                 * @type string $path The path to the file.
                 * @type string $cid The Content-ID of the image. Default: The key in the embeds array.
                 * @type string $name The filename of the image.
                 * @type string $encoding The encoding of the image. Default: 'base64'.
                 * @type string $type The MIME type of the image. Default: empty string, which lets PHPMailer auto-detect.
                 * @type string $disposition The disposition of the image. Default: 'inline'.
                 * }
                 */
                $embed_args = apply_filters(
                    'wp_mail_embed_args',
                    array(
                        'path'        => $embed_path,
                        'cid'         => (string)$key,
                        'name'        => basename($embed_path),
                        'encoding'    => 'base64',
                        'type'        => '',
                        'disposition' => 'inline',
                    )
                );

                try {
                    $phpmailer->addEmbeddedImage(
                        $embed_args['path'],
                        $embed_args['cid'],
                        $embed_args['name'],
                        $embed_args['encoding'],
                        $embed_args['type'],
                        $embed_args['disposition']
                    );
                } catch (PHPMailer\PHPMailer\Exception $e) {
                    continue;
                }
            }
        }

        /**
         * Fires after PHPMailer is initialized.
         *
         * @param PHPMailer $phpmailer The PHPMailer instance (passed by reference).
         * @since 2.2.0
         *
         */
        do_action_ref_array('phpmailer_init', array(&$phpmailer));

        // Set whether it's plaintext, depending on $content_type.
        if ('text/html' === $phpmailer->ContentType) {
            $phpmailer->isHTML(true);
            if (fluentMailSendMultiPartText() && !$phpmailer->AltBody) {
                $phpmailer->AltBody = (new \FluentMail\App\Services\Html2Text($message))->getText();
                if ($phpmailer->AltBody) {
                    // Set multipart
                    $phpmailer->ContentType = 'multipart/alternative';
                }
            }
        }

        $mail_data = compact('to', 'subject', 'message', 'headers', 'attachments', 'embeds');

        // Send!
        try {
            // Trap the fluentSMTPMail mailer here. Deliberately a LOCAL
            // variable: assigning the wrapper to the global $phpmailer made
            // the next wp_mail() call fail its instanceof PHPMailer check and
            // rebuild a fresh instance for every email — destroying the
            // instance (and closing any kept-alive SMTP connection) between
            // sends. Keeping the raw PHPMailer in the global matches WP core
            // behavior and lets a FluentCRM bulk sending session reuse one
            // SMTP connection across a whole run (BulkSendSessionHandler).
            $fluentMailer = new FluentMail\App\Services\Mailer\FluentPHPMailer($phpmailer);
            $send = $fluentMailer->send();

            /**
             * Fires after a successful email is sent using the wp_mail() function.
             *
             * @param array $mail_data The data of the sent email.
             */
            do_action('wp_mail_succeeded', $mail_data);

            return $send;

        } catch (PHPMailer\PHPMailer\Exception $e) {

            $mail_data['phpmailer_exception_code'] = $e->getCode();

            /**
             * Fires after a PHPMailer\PHPMailer\Exception is caught.
             *
             * @param WP_Error $error A WP_Error object with the PHPMailer\PHPMailer\Exception message, and an array
             *                        containing the mail recipient, subject, message, headers, and attachments.
             * @since 4.4.0
             *
             */
            do_action('wp_mail_failed', new WP_Error('wp_mail_failed', $e->getMessage(), $mail_data));
            return false;
        }
    }
}

if (!function_exists('fluentMailGetSettings')) {
    /**
     * Retrieves the Fluent Mail settings.
     *
     * This function retrieves the Fluent Mail settings from the WordPress options.
     * If the settings are not found, it returns the default settings provided.
     * If the settings are found and the 'use_encrypt' option is enabled, it decrypts
     * the secret fields for each connection provider.
     *
     * @param array $defaults The default settings to return if the Fluent Mail settings are not found.
     * @param bool $cached Whether to use the cached settings or not.
     * @return array The Fluent Mail settings.
     */
    function fluentMailGetSettings($defaults = [], $cached = true)
    {
        static $cachedSettings;
        if ($cached && $cachedSettings) {
            return $cachedSettings;
        }

        $settings = get_option('fluentmail-settings');

        if (!$settings) {
            return $defaults;
        }

        if (!empty($settings['use_encrypt'])) {
            $providerKeyMaps = [
                'smtp'        => 'password',
                'ses'         => 'secret_key',
                'mailgun'     => 'api_key',
                'sendgrid'    => 'api_key',
                'sendinblue'  => 'api_key',
                'sparkpost'   => 'api_key',
                'pepipost'    => 'api_key',
                'postmark'    => 'api_key',
                'elasticmail' => 'api_key',
                'gmail'       => 'client_secret',
                'outlook'     => 'client_secret',
                'tosend'      => 'api_key',
                'cloudflare'  => 'api_key',
            ];
            if (!empty($settings['connections']) && is_array($settings['connections'])) {
                foreach ($settings['connections'] as $key => $connection) {
                    $providerKey = $connection['provider_settings']['provider'];
                    if (empty($providerKeyMaps[$providerKey])) {
                        continue;
                    }

                    if (isset($connection['provider_settings']['disable_encryption']) && $connection['provider_settings']['disable_encryption'] === 'yes') {
                        continue;
                    }

                    $secretFieldKey = $providerKeyMaps[$providerKey];

                    if (empty($connection['provider_settings'][$secretFieldKey])) {
                        continue;
                    }

                    $settings['connections'][$key]['provider_settings'][$secretFieldKey] = fluentMailEncryptDecrypt($connection['provider_settings'][$secretFieldKey], 'd');
                }
            }
        }

        /*
         * Sender names reach us HTML-escaped when they were taken from the site
         * title, which WordPress stores that way. Left alone, a site called
         * "Tom & Jerry" sends every email from "Tom &amp; Jerry". Decoding here
         * rather than only on save fixes installs that already hold an escaped
         * name, without asking anyone to re-save a connection, and covers every
         * reader — this function is the one path settings travel through.
         */
        if (!empty($settings['connections']) && is_array($settings['connections'])) {
            foreach ($settings['connections'] as $key => $connection) {
                if (!empty($connection['provider_settings']['sender_name'])) {
                    $settings['connections'][$key]['provider_settings']['sender_name'] = wp_specialchars_decode(
                        $connection['provider_settings']['sender_name'],
                        ENT_QUOTES
                    );
                }
            }
        }

        $cachedSettings = $settings;

        return $settings;
    }
}

if (!function_exists('fluentMailSetSettings')) {
    /**
     * Sets the Fluent Mail settings and encrypts sensitive fields.
     *
     * @param array $settings The Fluent Mail settings.
     *
     * @return bool Returns Result of the Settings..
     */
    function fluentMailSetSettings($settings)
    {

        /**
         * Get the value of the 'use_encrypt' setting.
         *
         * This function applies the 'fluentsmtp_use_encrypt' filter to the 'yes' default value.
         *
         * @param string $default The default value for the 'use_encrypt' setting.
         * @return string The filtered value of the 'use_encrypt' setting.
         */
        $settings['use_encrypt'] = apply_filters('fluentsmtp_use_encrypt', 'yes');

        $hasSecretField = false;

        if (!empty($settings['use_encrypt'])) {
            $providerKeyMaps = [
                'smtp'        => 'password',
                'ses'         => 'secret_key',
                'mailgun'     => 'api_key',
                'sendgrid'    => 'api_key',
                'sendinblue'  => 'api_key',
                'sparkpost'   => 'api_key',
                'pepipost'    => 'api_key',
                'postmark'    => 'api_key',
                'elasticmail' => 'api_key',
                'gmail'       => 'client_secret',
                'outlook'     => 'client_secret',
                'tosend'      => 'api_key',
                'cloudflare'  => 'api_key',
            ];
            if (!empty($settings['connections']) && is_array($settings['connections'])) {
                foreach ($settings['connections'] as $key => $connection) {
                    $providerKey = $connection['provider_settings']['provider'];
                    if (empty($providerKeyMaps[$providerKey])) {
                        continue;
                    }
                    if (\FluentMail\Includes\Support\Arr::get($connection, 'provider_settings.disable_encryption') === 'yes') {
                        continue;
                    }

                    $secretFieldKey = $providerKeyMaps[$providerKey];

                    if (empty($connection['provider_settings'][$secretFieldKey])) {
                        continue;
                    }

                    $hasSecretField = true;

                    $settings['connections'][$key]['provider_settings'][$secretFieldKey] = fluentMailEncryptDecrypt($connection['provider_settings'][$secretFieldKey], 'e');
                }
            }
        }

        if ($hasSecretField) {
            $settings['test'] = fluentMailEncryptDecrypt('test', 'e');
        } else {
            $settings['test'] = '';
            $settings['use_encrypt'] = '';
        }
        
        $result = update_option('fluentmail-settings', $settings);

        fluentMailGetSettings([], false);

        return $result;
    }
}

if (!function_exists('fluentMailEncryptDecrypt')) {
    /**
     * Encrypts or decrypts a value using AES-256-CTR encryption.
     *
     * @param string $value The value to be encrypted or decrypted.
     * @param string $type The type of operation to perform. Defaults to 'e' for encryption.
     *
     * @return string|false The encrypted or decrypted value, or false on failure.
     */
    function fluentMailEncryptDecrypt($value, $type = 'e')
    {
        if (!$value) {
            return $value;
        }

        if (!extension_loaded('openssl')) {
            return $value;
        }

        if (defined('FLUENTMAIL_ENCRYPT_SALT')) {
            $salt = FLUENTMAIL_ENCRYPT_SALT;
        } else {
            $salt = (defined('LOGGED_IN_SALT') && '' !== LOGGED_IN_SALT) ? LOGGED_IN_SALT : 'this-is-a-fallback-salt-but-not-secure';
        }

        if (defined('FLUENTMAIL_ENCRYPT_KEY')) {
            $key = FLUENTMAIL_ENCRYPT_KEY;
        } else {
            $key = (defined('LOGGED_IN_KEY') && '' !== LOGGED_IN_KEY) ? LOGGED_IN_KEY : 'this-is-a-fallback-key-but-not-secure';
        }

        if ($type == 'e') {
            $method = 'aes-256-ctr';
            $ivlen = openssl_cipher_iv_length($method);
            $iv = openssl_random_pseudo_bytes($ivlen);

            $raw_value = openssl_encrypt($value . $salt, $method, $key, 0, $iv);
            if (!$raw_value) {
                return false;
            }

            return base64_encode($iv . $raw_value); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        }

        $raw_value = base64_decode($value, true); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

        $method = 'aes-256-ctr';
        $ivlen = openssl_cipher_iv_length($method);
        $iv = substr($raw_value, 0, $ivlen);
        if (strlen($iv) < 16) {
            $iv = str_pad($iv, 16, "\0");  // Pad with null bytes if it's shorter
        }

        $raw_value = substr($raw_value, $ivlen);

        $newValue = openssl_decrypt($raw_value, $method, $key, 0, $iv);
        if (!$newValue || substr($newValue, -strlen($salt)) !== $salt) {
            return false;
        }

        return substr($newValue, 0, -strlen($salt));
    }
}

/**
 * Returns the FluentSmtpDb instance.
 *
 * If the function `FluentSmtpDb` exists, it will be called and the result will be returned.
 * Otherwise, the file `wpfluent.php` will be required and the `FluentSmtpDb` function will be called and returned.
 *
 * @return FluentSmtpDb The FluentSmtpDb instance.
 */
function fluentMailDb()
{
    if (function_exists('FluentSmtpDb')) {
        return FluentSmtpDb();
    }

    require_once FLUENTMAIL_PLUGIN_PATH . 'app/Services/DB/wpfluent.php';
    return FluentSmtpDb();
}

if (!function_exists('fluentMailDebugLog')) {
    /**
     * Write a diagnostic line to the PHP error log, but only while debugging.
     *
     * Every one of these sites reports a swallowed failure — a log row that
     * could not be written, an attachment that could not be read. They recur:
     * whatever broke once breaks again on every send, so an ungated
     * error_log() grows the PHP error log for as long as the site keeps
     * sending. That is noise a production admin never asked for and cannot
     * turn off, while anyone actually chasing the failure has WP_DEBUG on.
     *
     * @param string $message Message to record, prefixed with the plugin name.
     * @return void
     */
    function fluentMailDebugLog($message)
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        error_log('FluentSMTP: ' . $message);
    }
}

if (!function_exists('fluentMailSiteTitle')) {
    /**
     * The site title as the admin typed it, safe to drop into plain text.
     *
     * WordPress stores blogname HTML-escaped — sanitize_option() runs
     * esc_html() over it — so a site called "Tom & Jerry" comes back from
     * get_bloginfo('name') as "Tom &amp; Jerry". That is correct for HTML
     * output and wrong everywhere else: an email subject, a Slack message or
     * a push notification is plain text, and the entity shows up literally.
     *
     * Core has the same problem and solves it the same way — see the
     * wp_specialchars_decode() calls around get_option('blogname') in
     * pluggable.php. Use this anywhere the title is not being written into
     * HTML; keep esc_html(fluentMailSiteTitle()) where it is.
     *
     * @return string
     */
    function fluentMailSiteTitle()
    {
        return wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    }
}

function fluentMailFuncCouldNotBeLoadedRecheckPluginsLoad()
{
    add_action('admin_notices', function () {
        if (!fluentMailCurrentUserCanManage()) {
            return;
        }
        $details = new ReflectionFunction('wp_mail');
        $hints = $details->getFileName() . ':' . $details->getStartLine();
        ?>
        <div class="notice notice-warning fluentsmtp_urgent is-dismissible">
            <p>The <strong>FluentSMTP</strong> plugin depends on <a target="_blank"
                                                                    href="https://developer.wordpress.org/reference/functions/wp_mail/">wp_mail</a>
                pluggable function and plugin is not able to extend it. Please check if another plugin is using this and
                disable it for <strong>FluentSMTP</strong> to work!</p>
            <p style="color: red;">
                <?php esc_html_e('Possible Conflict: ', 'fluent-smtp'); ?>
                <?php echo esc_html($hints); ?>
            </p>
        </div>
        <?php
    });

    $activePlugins = get_option('active_plugins');
    $index = array_search('fluent-smtp/fluent-smtp.php', $activePlugins);
    if ($index !== false) {
        if ($index === 0) {
            return;
        }
        unset($activePlugins[$index]);
        array_unshift($activePlugins, 'fluent-smtp/fluent-smtp.php');
        update_option('active_plugins', $activePlugins, true);
    }
}

/**
 * Check if the email should be sent as multi-part text.
 *
 * @return bool Returns true if the email should be sent as multi-part text, false otherwise.
 */
function fluentMailSendMultiPartText()
{
    $settings = fluentMailGetSettings();
    return isset($settings['misc']['send_as_text']) && $settings['misc']['send_as_text'] == 'yes';
}
