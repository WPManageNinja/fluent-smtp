<?php

declare (strict_types=1);
namespace FluentSmtpLib\GuzzleHttp\Promise;

final class Is
{
    /**
     * Returns true if a promise is pending.
     */
    public static function pending(\FluentSmtpLib\GuzzleHttp\Promise\PromiseInterface $promise) : bool
    {
        return $promise->getState() === \FluentSmtpLib\GuzzleHttp\Promise\PromiseInterface::PENDING;
    }
    /**
     * Returns true if a promise is fulfilled or rejected.
     */
    public static function settled(\FluentSmtpLib\GuzzleHttp\Promise\PromiseInterface $promise) : bool
    {
        return $promise->getState() !== \FluentSmtpLib\GuzzleHttp\Promise\PromiseInterface::PENDING;
    }
    /**
     * Returns true if a promise is fulfilled.
     */
    public static function fulfilled(\FluentSmtpLib\GuzzleHttp\Promise\PromiseInterface $promise) : bool
    {
        return $promise->getState() === \FluentSmtpLib\GuzzleHttp\Promise\PromiseInterface::FULFILLED;
    }
    /**
     * Returns true if a promise is rejected.
     */
    public static function rejected(\FluentSmtpLib\GuzzleHttp\Promise\PromiseInterface $promise) : bool
    {
        return $promise->getState() === \FluentSmtpLib\GuzzleHttp\Promise\PromiseInterface::REJECTED;
    }
}
