<?php

namespace FluentMail\App\Services;

use FluentMail\Includes\Support\Arr;

class Converter
{
    public function getSuggestedConnection()
    {
        $wpMailSmtp = $this->maybeWPMailSmtp();
        if($wpMailSmtp) {
            return $wpMailSmtp;
        }

        $easySMTP = $this->maybeEasySmtp();
        if($easySMTP) {
            return $easySMTP;
        }

        return false;
    }

    private function maybeWPMailSmtp()
    {
        $wpMailSettings = get_option('wp_mail_smtp');
        if (!$wpMailSettings) {
            return false;
        }

        $mailSettings = Arr::get($wpMailSettings, 'mail', []);

        $commonSettings = [
            'sender_name'      => $this->maybeFromWPMailDefined('mail', 'from_name', Arr::get($mailSettings, 'from_name')),
            'sender_email'     => $this->maybeFromWPMailDefined('mail', 'from_email', Arr::get($mailSettings, 'from_email')),
            'force_from_name'  => Arr::get($mailSettings, 'from_name_force') == 1 ? 'yes' : 'no',
            'force_from_email' => Arr::get($mailSettings, 'from_email_force') == 1 ? 'yes' : 'no',
            'return_path'      => Arr::get($mailSettings, 'return_path') == 1 ? 'yes' : 'no'
        ];

        // Let's try the SMTP First
        $mailer = Arr::get($mailSettings, 'mailer');

        if ($mailer == 'smtp') {
            $smtp = Arr::get($wpMailSettings, 'smtp', []);
            $auth = $this->maybeFromWPMailDefined('smtp', 'auth', Arr::get($smtp, 'auth')) == 1 ? 'yes' : 'no';

            $userName = $this->maybeFromWPMailDefined('smtp', 'user', Arr::get($smtp, 'user'));
            $password = $this->maybeFromWPMailDefined('smtp', 'pass', '');

            if ($auth == 'yes') {
                if (!$password) {
                    $password = $this->wpMailPassDecode(Arr::get($smtp, 'pass'));
                }
            }

            $localSettings = [
                'host'       => $this->maybeFromWPMailDefined('smtp', 'host', Arr::get($smtp, 'host')),
                'port'       => $this->maybeFromWPMailDefined('smtp', 'port', Arr::get($smtp, 'port')),
                'auth'       => $auth,
                'username'   => $userName,
                'password'   => $password,
                'auto_tls'   => $this->maybeFromWPMailDefined('smtp', 'auto_tls', Arr::get($smtp, 'auto_tls')) == 1 ? 'yes' : 'no',
                'encryption' => $this->maybeFromWPMailDefined('smtp', 'encryption', Arr::get($smtp, 'encryption', 'none')),
                'key_store'  => 'db',
                'provider'   => 'smtp'
            ];

            $commonSettings = wp_parse_args($commonSettings, $localSettings);
        } else if ($mailer == 'mailgun') {
            $mailgun = Arr::get($wpMailSettings, 'mailgun', []);
            $localSettings = [
                'api_key'     => $this->maybeFromWPMailDefined('mailgun', 'api_key', Arr::get($mailgun, 'api_key')),
                'domain_name' => $this->maybeFromWPMailDefined('mailgun', 'domain', Arr::get($mailgun, 'domain')),
                'key_store'   => 'db',
                'region'      => strtolower($this->maybeFromWPMailDefined('mailgun', 'region', Arr::get($mailgun, 'region'))),
                'provider'    => 'mailgun'
            ];
            $commonSettings = wp_parse_args($commonSettings, $localSettings);
            unset($commonSettings['force_from_email']);
        } else if ($mailer == 'sendinblue' || $mailer == 'sendgrid' || $mailer == 'pepipostapi') {
            $local = Arr::get($wpMailSettings, $mailer, []);
            $localSettings = [
                'api_key'   => $this->maybeFromWPMailDefined($mailer, 'api_key', Arr::get($local, 'api_key')),
                'key_store' => 'db',
                'provider'  => ($mailer == 'pepipostapi') ? 'pepipost' : $mailer
            ];
            $commonSettings = wp_parse_args($commonSettings, $localSettings);
            unset($commonSettings['force_from_email']);
        } else if ($mailer == 'amazonses') {
            $local = Arr::get($wpMailSettings, $mailer, []);
            $localSettings = [
                'access_key' => $this->maybeFromWPMailDefined($mailer, 'client_id', Arr::get($local, 'client_id')),
                'secret_key' => $this->maybeFromWPMailDefined($mailer, 'client_secret', Arr::get($local, 'client_secret')),
                'region'     => $this->maybeFromWPMailDefined($mailer, 'region', Arr::get($local, 'region')),
                'key_store'  => 'db',
                'provider'   => 'ses'
            ];

            $commonSettings = wp_parse_args($commonSettings, $localSettings);
        } else if ($mailer == 'mail') {
            $commonSettings['provider'] = 'default';
        } else {
            return false;
        }

        return [
            'title'       => __('Import data from your current plugin (WP Mail SMTP)', 'fluent-smtp'),
            'subtitle'    => __('We have detected other SMTP plugin\'s settings available on your site. Click bellow to pre-populate the values', 'fluent-smtp'),
            'settings'    => $commonSettings,
            'button_text' => __('Import From WP Mail SMTP', 'fluent-smtp')
        ];
    }

    private function wpMailPassDecode($encrypted)
    {
        if (apply_filters('wp_mail_smtp_helpers_crypto_stop', false)) {
            return $encrypted;
        }

        if (!function_exists('\mb_strlen') || !function_exists('\mb_substr') || !function_exists('\sodium_crypto_secretbox_open')) {
            return $encrypted;
        }

        // Unpack base64 message.
        $decoded = base64_decode($encrypted); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

        if (false === $decoded) {
            return $encrypted;
        }

        if (mb_strlen($decoded, '8bit') < (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES)) { // phpcs:ignore
            return $encrypted;
        }

        // Pull nonce and ciphertext out of unpacked message.
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit'); // phpcs:ignore
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit'); // phpcs:ignore

        $secret_key = $this->getWPMailSecretKey();

        if (empty($secret_key)) {
            return $encrypted;
        }

        // Decrypt it.
        $message = sodium_crypto_secretbox_open( // phpcs:ignore
            $ciphertext,
            $nonce,
            $secret_key
        );

        // Check for decryption failures.
        if (false === $message) {
            return $encrypted;
        }

        return $message;
    }

    private function getWPMailSecretKey()
    {
        if (defined('WPMS_CRYPTO_KEY')) {
            return WPMS_CRYPTO_KEY;
        }

        $secret_key = get_option('wp_mail_smtp_mail_key');
        $secret_key = apply_filters('wp_mail_smtp_helpers_crypto_get_secret_key', $secret_key);

        // If we already have the secret, send it back.
        if (false !== $secret_key) {
            $secret_key = base64_decode($secret_key); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        }

        return $secret_key;
    }

    private function maybeFromWPMailDefined($group, $key, $value)
    {

        if (!defined('WPMS_ON') || !WPMS_ON) {
            return $value;
        }

        // Just to feel safe.
        $group = sanitize_key($group);
        $key = sanitize_key($key);
        $return = false;

        switch ($group) {
            case 'mail':
                switch ($key) {
                    case 'from_name':
                        if (defined('WPMS_MAIL_FROM_NAME') && WPMS_MAIL_FROM_NAME) {
                            $value = WPMS_MAIL_FROM_NAME;
                        }
                        break;
                    case 'from_email':
                        if (defined('WPMS_MAIL_FROM') && WPMS_MAIL_FROM) {
                            $value = WPMS_MAIL_FROM;
                        }
                        break;
                    case 'mailer':
                        if (defined('WPMS_MAILER') && WPMS_MAILER) {
                            $value = WPMS_MAILER;
                        }
                        break;
                    case 'return_path':
                        if (defined('WPMS_SET_RETURN_PATH') && WPMS_SET_RETURN_PATH) {
                            $value = WPMS_SET_RETURN_PATH;
                        }
                        break;
                    case 'from_name_force':
                        if (defined('WPMS_MAIL_FROM_NAME_FORCE') && WPMS_MAIL_FROM_NAME_FORCE) {
                            $value = WPMS_MAIL_FROM_NAME_FORCE;
                        }
                        break;
                    case 'from_email_force':
                        if (defined('WPMS_MAIL_FROM_FORCE') && WPMS_MAIL_FROM_FORCE) {
                            $value = WPMS_MAIL_FROM_FORCE;
                        }
                        break;
                }

                break;

            case 'smtp':
                switch ($key) {
                    case 'host':
                        if (defined('WPMS_SMTP_HOST') && WPMS_SMTP_HOST) {
                            $value = WPMS_SMTP_HOST;
                        }
                        break;
                    case 'port':
                        if (defined('WPMS_SMTP_PORT') && WPMS_SMTP_PORT) {
                            $value = WPMS_SMTP_PORT;
                        }
                        break;
                    case 'encryption':
                        if (defined('WPMS_SSL') && WPMS_SSL) {
                            $value = WPMS_SSL;
                        }
                        break;
                    case 'auth':
                        if (defined('WPMS_SMTP_AUTH') && WPMS_SMTP_AUTH) {
                            $value = WPMS_SMTP_AUTH;
                        }
                        break;
                    case 'autotls':
                        if (defined('WPMS_SMTP_AUTOTLS') && WPMS_SMTP_AUTOTLS) {
                            $value = WPMS_SMTP_AUTOTLS;
                        }
                        break;
                    case 'user':
                        if (defined('WPMS_SMTP_USER') && WPMS_SMTP_USER) {
                            $value = WPMS_SMTP_USER;
                        }
                        break;
                    case 'pass':
                        if (defined('WPMS_SMTP_PASS') && WPMS_SMTP_PASS) {
                            $value = WPMS_SMTP_PASS;
                        }
                        break;
                }

                break;

            case 'amazonses':
                switch ($key) {
                    case 'client_id':
                        if (defined('WPMS_AMAZONSES_CLIENT_ID') && WPMS_AMAZONSES_CLIENT_ID) {
                            $value = WPMS_AMAZONSES_CLIENT_ID;
                        }
                        break;
                    case 'client_secret':
                        if (defined('WPMS_AMAZONSES_CLIENT_SECRET') && WPMS_AMAZONSES_CLIENT_SECRET) {
                            $value = WPMS_AMAZONSES_CLIENT_SECRET;
                        }
                        break;
                    case 'region':
                        if (defined('WPMS_AMAZONSES_REGION') && WPMS_AMAZONSES_REGION) {
                            $value = WPMS_AMAZONSES_REGION;
                        }
                        break;
                }

                break;

            case 'mailgun':
                switch ($key) {
                    case 'api_key':
                        if (defined('WPMS_MAILGUN_API_KEY') && WPMS_MAILGUN_API_KEY) {
                            $value = WPMS_MAILGUN_API_KEY;
                        }
                        break;
                    case 'domain':
                        if (defined('WPMS_MAILGUN_DOMAIN') && WPMS_MAILGUN_DOMAIN) {
                            $value = WPMS_MAILGUN_DOMAIN;
                        }
                        break;
                    case 'region':
                        if (defined('WPMS_MAILGUN_REGION') && WPMS_MAILGUN_REGION) {
                            $value = WPMS_MAILGUN_REGION;
                        }
                        break;
                }

                break;

            case 'sendgrid':
                switch ($key) {
                    case 'api_key':
                        if (defined('WPMS_SENDGRID_API_KEY') && WPMS_SENDGRID_API_KEY) {
                            $value = WPMS_SENDGRID_API_KEY;
                        }
                        break;
                    case 'domain':
                        if (defined('WPMS_SENDGRID_DOMAIN') && WPMS_SENDGRID_DOMAIN) {
                            $value = WPMS_SENDGRID_DOMAIN;
                        }
                        break;
                }

                break;

            case 'sendinblue':
                switch ($key) {
                    case 'api_key':
                        if (defined('WPMS_SENDINBLUE_API_KEY') && WPMS_SENDINBLUE_API_KEY) {
                            $value = WPMS_SENDINBLUE_API_KEY;
                        }
                        break;
                    case 'domain':
                        if (defined('WPMS_SENDINBLUE_DOMAIN') && WPMS_SENDINBLUE_DOMAIN) {
                            $value = WPMS_SENDINBLUE_DOMAIN;
                        }
                        break;
                }
                break;

            case 'pepipostapi':
                switch ($key) {
                    case 'api_key':
                        if (defined('WPMS_PEPIPOST_API_KEY') && WPMS_PEPIPOST_API_KEY) {
                            $value = WPMS_PEPIPOST_API_KEY;
                        }
                        break;
                }
                break;

            case 'elasticmail':
                switch ($key) {
                    case 'api_key':
                        if (defined('FLUENTMAIL_ELASTICMAIL_API_KEY') && FLUENTMAIL_ELASTICMAIL_API_KEY) {
                            $value = FLUENTMAIL_ELASTICMAIL_API_KEY;
                        }
                        break;
                }

                break;
        }

        return $value;
    }

    /*
     * For EasySMTP
     */
    private function maybeEasySmtp()
    {
        $settings = get_option('swpsmtp_options');

        if (!$settings || !is_array($settings)) {
            return false;
        }

        $auth = 'no';
        if (Arr::get($settings, 'smtp_settings.autentication')) {
            $auth = 'yes';
        }

        $commonSettings = [
            'sender_name'      => Arr::get($settings, 'from_name_field'),
            'sender_email'     => Arr::get($settings, 'from_email_field'),
            'force_from_name'  => Arr::get($settings, 'force_from_name_replace') == 1 ? 'yes' : 'no',
            'force_from_email' => 'yes',
            'return_path'      => 'yes',
            'host'             => Arr::get($settings, 'smtp_settings.host'),
            'port'             => Arr::get($settings, 'smtp_settings.port'),
            'auth'             => $auth,
            'username'         => Arr::get($settings, 'smtp_settings.username'),
            'password'         => $this->decryptEasySMTPPass(Arr::get($settings, 'smtp_settings.password')),
            'auto_tls'         => Arr::get($settings, 'smtp_settings.password') == 1 ? 'yes' : 'no',
            'encryption'       => Arr::get($settings, 'smtp_settings.type_encryption'),
            'key_store'        => 'db',
            'provider'         => 'smtp'
        ];

        return [
            'title'       => __('Import data from your current plugin (Easy WP SMTP)', 'fluent-smtp'),
            'subtitle'    => __('We have detected other SMTP plugin\'s settings available on your site. Click bellow to pre-populate the values', 'fluent-smtp'),
            'driver'      => 'smtp',
            'settings'    => $commonSettings,
            'button_text' => __('Import From Easy WP SMTP', 'fluent-smtp')
        ];

    }

    private function decryptEasySMTPPass($temp_password)
    {
        if (!$temp_password) {
            return $temp_password;
        }

        try {
            if (get_option('swpsmtp_pass_encrypted')) {
                $key = get_option('swpsmtp_enc_key', false);
                if (empty($key)) {
                    $key = wp_salt();
                }
                return $this->decryptEasypassword($temp_password, $key);
            }
        } catch (\Exception $e) {
            return $temp_password;
        }

        $password = '';
        $decoded_pass = base64_decode($temp_password); //phpcs:ignore
        /* no additional checks for servers that aren't configured with mbstring enabled */
        if (!function_exists('mb_detect_encoding')) {
            return $decoded_pass;
        }
        /* end of mbstring check */
        if (base64_encode($decoded_pass) === $temp_password) { //phpcs:ignore
            //it might be encoded
            if (false === mb_detect_encoding($decoded_pass)) {  //could not find character encoding.
                $password = $temp_password;
            } else {
                $password = base64_decode($temp_password); //phpcs:ignore
            }
        } else { //not encoded
            $password = $temp_password;
        }
        return stripslashes($password);

    }

    private function decryptEasyPassword($in, $key, $fmt = 1)
    {

        if (!function_exists('\openssl_cipher_iv_length') || !function_exists('\openssl_decrypt') || !function_exists('\openssl_digest')) {
            return $in;
        }

        $raw = base64_decode($in);

        $iv_num_bytes = openssl_cipher_iv_length('aes-256-ctr');

        // and do an integrity check on the size.
        if (strlen($raw) < $iv_num_bytes) {
            return $in;
        }

        // Extract the initialisation vector and encrypted data
        $iv = substr($raw, 0, $iv_num_bytes);
        $raw = substr($raw, $iv_num_bytes);

        $hasAlgo = 'sha256';
        // Hash the key
        $keyhash = openssl_digest($key, $hasAlgo, true);

        // and decrypt.
        $opts = 1;
        $res = openssl_decrypt($raw, 'aes-256-ctr', $keyhash, $opts, $iv);

        if ($res === false) {
            return $in;
        }

        return $res;

    }

}
