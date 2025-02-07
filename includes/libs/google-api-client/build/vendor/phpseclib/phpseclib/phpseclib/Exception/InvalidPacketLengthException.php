<?php

namespace FluentSmtpLib\phpseclib3\Exception;

/**
 * Indicates an absent or malformed packet length header
 */
class InvalidPacketLengthException extends \FluentSmtpLib\phpseclib3\Exception\ConnectionClosedException
{
}
