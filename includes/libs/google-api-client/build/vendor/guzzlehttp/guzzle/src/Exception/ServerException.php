<?php

namespace FluentSmtpLib\GuzzleHttp\Exception;

/**
 * Exception when a server error is encountered (5xx codes)
 */
class ServerException extends \FluentSmtpLib\GuzzleHttp\Exception\BadResponseException
{
}
