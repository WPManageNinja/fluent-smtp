<?php

namespace FluentSmtpLib\GuzzleHttp\Handler;

use FluentSmtpLib\Psr\Http\Message\RequestInterface;
interface CurlFactoryInterface
{
    /**
     * Creates a cURL handle resource.
     *
     * @param RequestInterface $request Request
     * @param array            $options Transfer options
     *
     * @throws \RuntimeException when an option cannot be applied
     */
    public function create(\FluentSmtpLib\Psr\Http\Message\RequestInterface $request, array $options) : \FluentSmtpLib\GuzzleHttp\Handler\EasyHandle;
    /**
     * Release an easy handle, allowing it to be reused or closed.
     *
     * This function must call unset on the easy handle's "handle" property.
     */
    public function release(\FluentSmtpLib\GuzzleHttp\Handler\EasyHandle $easy) : void;
}
