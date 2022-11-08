<?php

namespace FluentSmtpLib;
return;
if (\class_exists('FluentSmtpLib\\Google_Client', \false)) {
    // Prevent error with preloading in PHP 7.4
    // @see https://github.com/googleapis/google-api-php-client/issues/1976
    return;
}
$classMap = ['FluentSmtpLib\\Google\\Client' => 'Google_Client', 'FluentSmtpLib\\Google\\Service' => 'Google_Service', 'FluentSmtpLib\\Google\\AccessToken\\Revoke' => 'Google_AccessToken_Revoke', 'FluentSmtpLib\\Google\\AccessToken\\Verify' => 'Google_AccessToken_Verify', 'FluentSmtpLib\\Google\\Model' => 'Google_Model', 'FluentSmtpLib\\Google\\Utils\\UriTemplate' => 'Google_Utils_UriTemplate', 'FluentSmtpLib\\Google\\AuthHandler\\Guzzle6AuthHandler' => 'Google_AuthHandler_Guzzle6AuthHandler', 'FluentSmtpLib\\Google\\AuthHandler\\Guzzle7AuthHandler' => 'Google_AuthHandler_Guzzle7AuthHandler', 'FluentSmtpLib\\Google\\AuthHandler\\Guzzle5AuthHandler' => 'Google_AuthHandler_Guzzle5AuthHandler', 'FluentSmtpLib\\Google\\AuthHandler\\AuthHandlerFactory' => 'Google_AuthHandler_AuthHandlerFactory', 'FluentSmtpLib\\Google\\Http\\Batch' => 'Google_Http_Batch', 'FluentSmtpLib\\Google\\Http\\MediaFileUpload' => 'Google_Http_MediaFileUpload', 'FluentSmtpLib\\Google\\Http\\REST' => 'Google_Http_REST', 'FluentSmtpLib\\Google\\Task\\Retryable' => 'Google_Task_Retryable', 'FluentSmtpLib\\Google\\Task\\Exception' => 'Google_Task_Exception', 'FluentSmtpLib\\Google\\Task\\Runner' => 'Google_Task_Runner', 'FluentSmtpLib\\Google\\Collection' => 'Google_Collection', 'FluentSmtpLib\\Google\\Service\\Exception' => 'Google_Service_Exception', 'FluentSmtpLib\\Google\\Service\\Resource' => 'Google_Service_Resource', 'FluentSmtpLib\\Google\\Exception' => 'Google_Exception'];
foreach ($classMap as $class => $alias) {
    \class_alias($class, $alias);
}
/**
 * This class needs to be defined explicitly as scripts must be recognized by
 * the autoloader.
 */
class Google_Task_Composer extends \FluentSmtpLib\Google\Task\Composer
{
}
/** @phpstan-ignore-next-line */
if (\false) {
    class Google_AccessToken_Revoke extends \FluentSmtpLib\Google\AccessToken\Revoke
    {
    }
    class Google_AccessToken_Verify extends \FluentSmtpLib\Google\AccessToken\Verify
    {
    }
    class Google_AuthHandler_AuthHandlerFactory extends \FluentSmtpLib\Google\AuthHandler\AuthHandlerFactory
    {
    }
    class Google_AuthHandler_Guzzle5AuthHandler extends \FluentSmtpLib\Google\AuthHandler\Guzzle5AuthHandler
    {
    }
    class Google_AuthHandler_Guzzle6AuthHandler extends \FluentSmtpLib\Google\AuthHandler\Guzzle6AuthHandler
    {
    }
    class Google_AuthHandler_Guzzle7AuthHandler extends \FluentSmtpLib\Google\AuthHandler\Guzzle7AuthHandler
    {
    }
    class Google_Client extends \FluentSmtpLib\Google\Client
    {
    }
    class Google_Collection extends \FluentSmtpLib\Google\Collection
    {
    }
    class Google_Exception extends \FluentSmtpLib\Google\Exception
    {
    }
    class Google_Http_Batch extends \FluentSmtpLib\Google\Http\Batch
    {
    }
    class Google_Http_MediaFileUpload extends \FluentSmtpLib\Google\Http\MediaFileUpload
    {
    }
    class Google_Http_REST extends \FluentSmtpLib\Google\Http\REST
    {
    }
    class Google_Model extends \FluentSmtpLib\Google\Model
    {
    }
    class Google_Service extends \FluentSmtpLib\Google\Service
    {
    }
    class Google_Service_Exception extends \FluentSmtpLib\Google\Service\Exception
    {
    }
    class Google_Service_Resource extends \FluentSmtpLib\Google\Service\Resource
    {
    }
    class Google_Task_Exception extends \FluentSmtpLib\Google\Task\Exception
    {
    }
    interface Google_Task_Retryable extends \FluentSmtpLib\Google\Task\Retryable
    {
    }
    class Google_Task_Runner extends \FluentSmtpLib\Google\Task\Runner
    {
    }
    class Google_Utils_UriTemplate extends \FluentSmtpLib\Google\Utils\UriTemplate
    {
    }
}
