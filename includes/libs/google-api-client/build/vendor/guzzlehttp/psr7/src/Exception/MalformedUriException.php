<?php

declare (strict_types=1);
namespace FluentSmtpLib\GuzzleHttp\Psr7\Exception;

use InvalidArgumentException;
/**
 * Exception thrown if a URI cannot be parsed because it's malformed.
 */
class MalformedUriException extends \InvalidArgumentException
{
}
