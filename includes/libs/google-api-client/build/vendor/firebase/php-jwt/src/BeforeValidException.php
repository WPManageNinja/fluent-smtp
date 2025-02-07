<?php

namespace FluentSmtpLib\Firebase\JWT;

class BeforeValidException extends \UnexpectedValueException implements \FluentSmtpLib\Firebase\JWT\JWTExceptionWithPayloadInterface
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
