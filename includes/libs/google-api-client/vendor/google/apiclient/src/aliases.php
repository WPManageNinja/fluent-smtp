<?php

if (class_exists('Google_Client', false)) {
    // Prevent error with preloading in PHP 7.4
    // @see https://github.com/googleapis/google-api-php-client/issues/1976
    return;
}

$classMap = [
    'FluentMail\\Google\\Client' => 'Google_Client',
    'FluentMail\\Google\\Service' => 'Google_Service',
    'FluentMail\\Google\\AccessToken\\Revoke' => 'Google_AccessToken_Revoke',
    'FluentMail\\Google\\AccessToken\\Verify' => 'Google_AccessToken_Verify',
    'FluentMail\\Google\\Model' => 'Google_Model',
    'FluentMail\\Google\\Utils\\UriTemplate' => 'Google_Utils_UriTemplate',
    'FluentMail\\Google\\AuthHandler\\Guzzle6AuthHandler' => 'Google_AuthHandler_Guzzle6AuthHandler',
    'FluentMail\\Google\\AuthHandler\\Guzzle7AuthHandler' => 'Google_AuthHandler_Guzzle7AuthHandler',
    'FluentMail\\Google\\AuthHandler\\Guzzle5AuthHandler' => 'Google_AuthHandler_Guzzle5AuthHandler',
    'FluentMail\\Google\\AuthHandler\\AuthHandlerFactory' => 'Google_AuthHandler_AuthHandlerFactory',
    'FluentMail\\Google\\Http\\Batch' => 'Google_Http_Batch',
    'FluentMail\\Google\\Http\\MediaFileUpload' => 'Google_Http_MediaFileUpload',
    'FluentMail\\Google\\Http\\REST' => 'Google_Http_REST',
    'FluentMail\\Google\\Task\\Retryable' => 'Google_Task_Retryable',
    'FluentMail\\Google\\Task\\Exception' => 'Google_Task_Exception',
    'FluentMail\\Google\\Task\\Runner' => 'Google_Task_Runner',
    'FluentMail\\Google\\Collection' => 'Google_Collection',
    'FluentMail\\Google\\Service\\Exception' => 'Google_Service_Exception',
    'FluentMail\\Google\\Service\\Resource' => 'Google_Service_Resource',
    'FluentMail\\Google\\Exception' => 'Google_Exception',
];

foreach ($classMap as $class => $alias) {
    class_alias($class, $alias);
}

/**
 * This class needs to be defined explicitly as scripts must be recognized by
 * the autoloader.
 */
class Google_Task_Composer extends \FluentMail\Google\Task\Composer
{
}

/** @phpstan-ignore-next-line */
if (\false) {
    class Google_AccessToken_Revoke extends \FluentMail\Google\AccessToken\Revoke
    {
    }
    class Google_AccessToken_Verify extends \FluentMail\Google\AccessToken\Verify
    {
    }
    class Google_AuthHandler_AuthHandlerFactory extends \FluentMail\Google\AuthHandler\AuthHandlerFactory
    {
    }
    class Google_AuthHandler_Guzzle5AuthHandler extends \FluentMail\Google\AuthHandler\Guzzle5AuthHandler
    {
    }
    class Google_AuthHandler_Guzzle6AuthHandler extends \FluentMail\Google\AuthHandler\Guzzle6AuthHandler
    {
    }
    class Google_AuthHandler_Guzzle7AuthHandler extends \FluentMail\Google\AuthHandler\Guzzle7AuthHandler
    {
    }
    class Google_Client extends \FluentMail\Google\Client
    {
    }
    class Google_Collection extends \FluentMail\Google\Collection
    {
    }
    class Google_Exception extends \FluentMail\Google\Exception
    {
    }
    class Google_Http_Batch extends \FluentMail\Google\Http\Batch
    {
    }
    class Google_Http_MediaFileUpload extends \FluentMail\Google\Http\MediaFileUpload
    {
    }
    class Google_Http_REST extends \FluentMail\Google\Http\REST
    {
    }
    class Google_Model extends \FluentMail\Google\Model
    {
    }
    class Google_Service extends \FluentMail\Google\Service
    {
    }
    class Google_Service_Exception extends \FluentMail\Google\Service\Exception
    {
    }
    class Google_Service_Resource extends \FluentMail\Google\Service\Resource
    {
    }
    class Google_Task_Exception extends \FluentMail\Google\Task\Exception
    {
    }
    interface Google_Task_Retryable extends \FluentMail\Google\Task\Retryable
    {
    }
    class Google_Task_Runner extends \FluentMail\Google\Task\Runner
    {
    }
    class Google_Utils_UriTemplate extends \FluentMail\Google\Utils\UriTemplate
    {
    }
}
