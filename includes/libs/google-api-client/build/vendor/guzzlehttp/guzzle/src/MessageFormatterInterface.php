<?php

namespace FluentSmtpLib\GuzzleHttp;

use FluentSmtpLib\Psr\Http\Message\RequestInterface;
use FluentSmtpLib\Psr\Http\Message\ResponseInterface;
interface MessageFormatterInterface
{
    /**
     * Returns a formatted message string.
     *
     * @param RequestInterface       $request  Request that was sent
     * @param ResponseInterface|null $response Response that was received
     * @param \Throwable|null        $error    Exception that was received
     */
    public function format(\FluentSmtpLib\Psr\Http\Message\RequestInterface $request, ?\FluentSmtpLib\Psr\Http\Message\ResponseInterface $response = null, ?\Throwable $error = null) : string;
}
