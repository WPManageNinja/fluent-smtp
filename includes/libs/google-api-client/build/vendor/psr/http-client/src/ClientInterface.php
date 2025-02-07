<?php

namespace FluentSmtpLib\Psr\Http\Client;

use FluentSmtpLib\Psr\Http\Message\RequestInterface;
use FluentSmtpLib\Psr\Http\Message\ResponseInterface;
interface ClientInterface
{
    /**
     * Sends a PSR-7 request and returns a PSR-7 response.
     *
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     *
     * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens while processing the request.
     */
    public function sendRequest(\FluentSmtpLib\Psr\Http\Message\RequestInterface $request) : \FluentSmtpLib\Psr\Http\Message\ResponseInterface;
}
