<?php

namespace FluentSmtpLib;

if (\class_exists('FluentSmtpLib\\Google_Client', \false)) {
    // Prevent error with preloading in PHP 7.4
    // @see https://github.com/googleapis/google-api-php-client/issues/1976
    return;
}
$classMap = ['FluentSmtpLib\\Google\\Client' => 'FluentSmtpLib\Google_Client', 'FluentSmtpLib\\Google\\Service' => 'FluentSmtpLib\Google_Service', 'FluentSmtpLib\\Google\\AccessToken\\Revoke' => 'FluentSmtpLib\Google_AccessToken_Revoke', 'FluentSmtpLib\\Google\\AccessToken\\Verify' => 'FluentSmtpLib\Google_AccessToken_Verify', 'FluentSmtpLib\\Google\\Model' => 'FluentSmtpLib\Google_Model', 'FluentSmtpLib\\Google\\Utils\\UriTemplate' => 'FluentSmtpLib\Google_Utils_UriTemplate', 'FluentSmtpLib\\Google\\AuthHandler\\Guzzle6AuthHandler' => 'FluentSmtpLib\Google_AuthHandler_Guzzle6AuthHandler', 'FluentSmtpLib\\Google\\AuthHandler\\Guzzle7AuthHandler' => 'FluentSmtpLib\Google_AuthHandler_Guzzle7AuthHandler', 'FluentSmtpLib\\Google\\AuthHandler\\AuthHandlerFactory' => 'FluentSmtpLib\Google_AuthHandler_AuthHandlerFactory', 'FluentSmtpLib\\Google\\Http\\Batch' => 'FluentSmtpLib\Google_Http_Batch', 'FluentSmtpLib\\Google\\Http\\MediaFileUpload' => 'FluentSmtpLib\Google_Http_MediaFileUpload', 'FluentSmtpLib\\Google\\Http\\REST' => 'FluentSmtpLib\Google_Http_REST', 'FluentSmtpLib\\Google\\Task\\Retryable' => 'FluentSmtpLib\Google_Task_Retryable', 'FluentSmtpLib\\Google\\Task\\Exception' => 'FluentSmtpLib\Google_Task_Exception', 'FluentSmtpLib\\Google\\Task\\Runner' => 'FluentSmtpLib\Google_Task_Runner', 'FluentSmtpLib\\Google\\Collection' => 'FluentSmtpLib\Google_Collection', 'FluentSmtpLib\\Google\\Service\\Exception' => 'FluentSmtpLib\Google_Service_Exception', 'FluentSmtpLib\\Google\\Service\\Resource' => 'FluentSmtpLib\Google_Service_Resource', 'FluentSmtpLib\\Google\\Exception' => 'FluentSmtpLib\Google_Exception'];
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
