<?php
/**
 * Child process for smtp-credential-endpoint.php. Run through FsmtpTest::wpCli()
 * with `eval-file`, because constants cannot be undefined once the parent defines
 * them. FSMTP_CHILD_MODE=bound also defines the optional host and port constants.
 * Prints one JSON line of observations; never prints a credential.
 */

use FluentMail\App\Services\Mailer\Providers\Smtp\Handler;
use FluentMail\Includes\Support\ValidationException;

define('FLUENTMAIL_SMTP_USERNAME', 'suite-const-user');
define('FLUENTMAIL_SMTP_PASSWORD', 'suite-const-secret');

if (getenv('FSMTP_CHILD_MODE') === 'bound') {
    define('FLUENTMAIL_SMTP_HOST', 'smtp.bound.example.test');
    define('FLUENTMAIL_SMTP_PORT', '587');
}

$connection = function ($host, $port) {
    return [
        'provider'     => 'smtp',
        'sender_email' => 'child@example.test',
        'host'         => $host,
        'port'         => $port,
        'auth'         => 'yes',
        'encryption'   => 'tls',
        'key_store'    => 'wp_config',
    ];
};

$usesConstants = function ($settings) {
    $handler = (new Handler())->setSettings($settings);
    $identity = new ReflectionMethod($handler, 'getConnectionIdentity');
    $identity->setAccessible(true);
    $values = $identity->invoke($handler);

    return $values['username'] === FLUENTMAIL_SMTP_USERNAME
        && $values['auth_key'] === md5(FLUENTMAIL_SMTP_PASSWORD);
};

$saveErrors = function ($settings) {
    try {
        (new Handler())->validateProviderInformation($settings);
        return [];
    } catch (ValidationException $e) {
        return array_keys((array) $e->errors());
    }
};

echo wp_json_encode([
    'same_host_uses_constants'   => $usesConstants($connection('smtp.bound.example.test', '587')),
    'same_host_case_uses'        => $usesConstants($connection(' SMTP.Bound.Example.Test ', '587')),
    'other_host_uses_constants'  => $usesConstants($connection('smtp.other.example.test', '587')),
    'other_port_uses_constants'  => $usesConstants($connection('smtp.bound.example.test', '2525')),
    'same_host_save_errors'      => $saveErrors($connection('smtp.bound.example.test', '587')),
    'other_host_save_errors'     => $saveErrors($connection('smtp.other.example.test', '587')),
    'other_port_save_errors'     => $saveErrors($connection('smtp.bound.example.test', '2525')),
]) . "\n";
