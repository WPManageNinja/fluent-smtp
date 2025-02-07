<?php

namespace FluentSmtpLib\Firebase\JWT;

class ExpiredException extends \UnexpectedValueException implements \FluentSmtpLib\Firebase\JWT\JWTExceptionWithPayloadInterface
{
    private object $payload;
    public function setPayload(object $payload) : void
    {
        $this->payload = $payload;
    }
    public function getPayload() : object
    {
        return $this->payload;
    }
}
