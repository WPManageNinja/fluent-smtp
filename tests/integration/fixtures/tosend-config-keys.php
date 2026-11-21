<?php

use FluentMail\App\Services\Mailer\Providers\ToSend\Handler;
use FluentMail\Includes\Support\ValidationException;

// Constants cannot be undefined between tests. The parent runs this fixture in
// its own process. Both outbound transports are replaced with local responses.
$testHandler = new class extends Handler {
    // Keep both the initially absent and later defined constants independent of wp-config.php.
    const CONFIG_KEY = 'FSMTP_TEST_TOSEND_CONFIG_KEY';
    public $expectedKey;
    public $accountKeyMatched = false;
    public $sendKeyMatched = false;

    public function getAccountInfo($key)
    {
        $this->accountKeyMatched = $key === $this->expectedKey;
        return ['verified_domains' => ['example.test']];
    }

    protected function sendViaCurl($url, $body)
    {
        $payload = json_decode($body, true);
        $this->sendKeyMatched = $payload['api_key'] === $this->expectedKey;
        return ['code' => 200, 'body' => '{"message_id":"fixture"}'];
    }

    public function handleResponse($response)
    {
        return $response;
    }
};
$checks = [];
$connection = [
    'provider' => 'tosend', 'sender_email' => 'first@example.test',
    'sender_name' => 'Fixture', 'key_store' => 'wp_config', 'force_from_name' => 'no',
    'force_from_email' => 'yes', 'additional_senders' => ['alias@example.test'],
    'api_key' => 'fixture-db',
];
$prefix = $testHandler::CONFIG_KEY;
$scope = $prefix . '_' . (is_multisite() ? get_current_blog_id() . '_' : '');
define($scope . md5('first@example.test'), 'fixture-first');
define($scope . md5('second@example.test'), 'fixture-second');
define($scope . md5('alias@example.test'), 'fixture-alias');
define($scope . md5('empty@example.test'), '');
define($scope . md5('invalid@example.test'), false);
define($scope . md5('billing%2bdesk@example.test'), 'fixture-percent');

$testHandler->setSettings($connection);
$checks['scoped key before installing a shared fixture'] = $testHandler->getSetting('api_key') === 'fixture-first';
$missing = array_merge($connection, ['sender_email' => 'missing@example.test']);
$checks['missing config key ignores database value'] = $testHandler->setSettings($missing)->getSetting('api_key') === '';
define($prefix, 'fixture-global');

$percentConnection = \FluentMail\App\Services\ConnectionSaver::sanitize(array_merge($connection, [
    'sender_email' => 'billing%2Bdesk@example.test',
]));
$checks['valid percent mailbox survives the save sanitizer'] = $percentConnection['sender_email'] === 'billing%2Bdesk@example.test'
    && $testHandler->setSettings($percentConnection)->getSetting('api_key') === 'fixture-percent';
$invalidConnection = \FluentMail\App\Services\ConnectionSaver::sanitize(array_merge($connection, [
    'sender_email' => 'first[invalid]@example.test',
]));
$invalidRejected = false;
try {
    $testHandler->validateBasicInformation($invalidConnection);
} catch (ValidationException $e) {
    $invalidRejected = isset($e->errors()['sender_email']);
}
$checks['malformed address cannot become a different mailbox'] = $invalidRejected;

$quoted = \FluentMail\App\Services\ConnectionSaver::sanitize(array_merge($connection, [
    'sender_email' => '"sales"@example.test',
]));
$testHandler->expectedKey = 'fixture-global';
\FluentMail\App\Services\ConnectionSaver::validate($testHandler, $quoted);
$checks['legacy quoted mailbox keeps the shared credential'] = $quoted['sender_email'] === '"sales"@example.test'
    && $testHandler->setSettings($quoted)->getSetting('api_key') === 'fixture-global';
$displayConnection = array_merge($quoted, ['sender_email' => '"sales<2"@example.test']);
$testHandler->validateBasicInformation($displayConnection);
$quotedDetails = $testHandler->getConnectionInfo($displayConnection);
$checks['details show the quoted mailbox as text'] = strpos($quotedDetails['info'], esc_html($displayConnection['sender_email'])) !== false;

require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
$mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
$mailer->setFrom('alias@example.test', 'Fixture');
$mailer->addAddress('recipient@example.test');
$mailer->Subject = 'Fixture';
$mailer->Body = 'Fixture';

foreach (['First@Example.Test' => 'fixture-first', 'second@example.test' => 'fixture-second'] as $sender => $expected) {
    $connection['sender_email'] = $sender;
    $testHandler->expectedKey = $expected;
    $testHandler->sendKeyMatched = false;
    $testHandler->setSettings($connection)->setPhpMailer($mailer)->send();
    $checks[$sender . ' sends with its own key even for an alias'] = $testHandler->sendKeyMatched;
    $testHandler->accountKeyMatched = false;
    $validated = true;
    try {
        $testHandler->validateProviderInformation($connection);
    } catch (ValidationException $e) {
        $validated = false;
    }
    $checks[$sender . ' validates with its own key'] = $validated && $testHandler->accountKeyMatched;
    $testHandler->accountKeyMatched = false;
    $info = $testHandler->getConnectionInfo($connection);
    $checks[$sender . ' loads account details with its own key'] = $testHandler->accountKeyMatched;
    $checks[$sender . ' account details omit the key'] = strpos(wp_json_encode($info), $expected) === false;
}

$connection['sender_email'] = 'legacy@example.test';
$testHandler->setSettings($connection);
$checks['unconfigured primary inherits the shared key'] = $testHandler->getSetting('api_key') === 'fixture-global';
foreach (['empty', 'invalid'] as $kind) {
    $connection['sender_email'] = $kind . '@example.test';
    $testHandler->setSettings($connection);
    $checks['defined ' . $kind . ' key prevents global fallback'] = $testHandler->getSetting('api_key') === '';
    $rejected = false;
    try {
        $testHandler->validateProviderInformation($connection);
    } catch (ValidationException $e) {
        $rejected = isset($e->errors()['api_key']);
    }
    $checks[$kind . ' key has a validation error'] = $rejected;
}
$connection['sender_email'] = 'first@example.test';
$connection['key_store'] = 'db';
$testHandler->setSettings($connection);
$checks['database storage ignores config constants'] = $testHandler->getSetting('api_key') === 'fixture-db';

if (is_multisite()) {
    $otherSites = get_sites(['fields' => 'ids', 'site__not_in' => [get_current_blog_id()], 'number' => 1]);
    if ($otherSites) {
        $connection['key_store'] = 'wp_config';
        $testHandler->setSettings($connection);
        switch_to_blog($otherSites[0]);
        try {
            define($prefix . '_' . get_current_blog_id() . '_' . md5('first@example.test'), 'fixture-other-site');
            $checks['same primary on second site uses its own key'] = $testHandler->getSetting('api_key') === 'fixture-other-site';
        } finally {
            restore_current_blog();
        }
        $checks['restored site uses its original key'] = $testHandler->getSetting('api_key') === 'fixture-first';
    }
}

foreach ($checks as $label => $passed) {
    if (!$passed) {
        WP_CLI::error('toSend fixture failed: ' . $label);
    }
}
WP_CLI::log('toSend scoped and legacy credential checks passed.');
