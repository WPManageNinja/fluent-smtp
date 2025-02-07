<?php

declare (strict_types=1);
namespace FluentSmtpLib\GuzzleHttp\Psr7;

use FluentSmtpLib\Psr\Http\Message\RequestFactoryInterface;
use FluentSmtpLib\Psr\Http\Message\RequestInterface;
use FluentSmtpLib\Psr\Http\Message\ResponseFactoryInterface;
use FluentSmtpLib\Psr\Http\Message\ResponseInterface;
use FluentSmtpLib\Psr\Http\Message\ServerRequestFactoryInterface;
use FluentSmtpLib\Psr\Http\Message\ServerRequestInterface;
use FluentSmtpLib\Psr\Http\Message\StreamFactoryInterface;
use FluentSmtpLib\Psr\Http\Message\StreamInterface;
use FluentSmtpLib\Psr\Http\Message\UploadedFileFactoryInterface;
use FluentSmtpLib\Psr\Http\Message\UploadedFileInterface;
use FluentSmtpLib\Psr\Http\Message\UriFactoryInterface;
use FluentSmtpLib\Psr\Http\Message\UriInterface;
/**
 * Implements all of the PSR-17 interfaces.
 *
 * Note: in consuming code it is recommended to require the implemented interfaces
 * and inject the instance of this class multiple times.
 */
final class HttpFactory implements \FluentSmtpLib\Psr\Http\Message\RequestFactoryInterface, \FluentSmtpLib\Psr\Http\Message\ResponseFactoryInterface, \FluentSmtpLib\Psr\Http\Message\ServerRequestFactoryInterface, \FluentSmtpLib\Psr\Http\Message\StreamFactoryInterface, \FluentSmtpLib\Psr\Http\Message\UploadedFileFactoryInterface, \FluentSmtpLib\Psr\Http\Message\UriFactoryInterface
{
    public function createUploadedFile(\FluentSmtpLib\Psr\Http\Message\StreamInterface $stream, ?int $size = null, int $error = \UPLOAD_ERR_OK, ?string $clientFilename = null, ?string $clientMediaType = null) : \FluentSmtpLib\Psr\Http\Message\UploadedFileInterface
    {
        if ($size === null) {
            $size = $stream->getSize();
        }
        return new \FluentSmtpLib\GuzzleHttp\Psr7\UploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
    }
    public function createStream(string $content = '') : \FluentSmtpLib\Psr\Http\Message\StreamInterface
    {
        return \FluentSmtpLib\GuzzleHttp\Psr7\Utils::streamFor($content);
    }
    public function createStreamFromFile(string $file, string $mode = 'r') : \FluentSmtpLib\Psr\Http\Message\StreamInterface
    {
        try {
            $resource = \FluentSmtpLib\GuzzleHttp\Psr7\Utils::tryFopen($file, $mode);
        } catch (\RuntimeException $e) {
            if ('' === $mode || \false === \in_array($mode[0], ['r', 'w', 'a', 'x', 'c'], \true)) {
                throw new \InvalidArgumentException(\sprintf('Invalid file opening mode "%s"', $mode), 0, $e);
            }
            throw $e;
        }
        return \FluentSmtpLib\GuzzleHttp\Psr7\Utils::streamFor($resource);
    }
    public function createStreamFromResource($resource) : \FluentSmtpLib\Psr\Http\Message\StreamInterface
    {
        return \FluentSmtpLib\GuzzleHttp\Psr7\Utils::streamFor($resource);
    }
    public function createServerRequest(string $method, $uri, array $serverParams = []) : \FluentSmtpLib\Psr\Http\Message\ServerRequestInterface
    {
        if (empty($method)) {
            if (!empty($serverParams['REQUEST_METHOD'])) {
                $method = $serverParams['REQUEST_METHOD'];
            } else {
                throw new \InvalidArgumentException('Cannot determine HTTP method');
            }
        }
        return new \FluentSmtpLib\GuzzleHttp\Psr7\ServerRequest($method, $uri, [], null, '1.1', $serverParams);
    }
    public function createResponse(int $code = 200, string $reasonPhrase = '') : \FluentSmtpLib\Psr\Http\Message\ResponseInterface
    {
        return new \FluentSmtpLib\GuzzleHttp\Psr7\Response($code, [], null, '1.1', $reasonPhrase);
    }
    public function createRequest(string $method, $uri) : \FluentSmtpLib\Psr\Http\Message\RequestInterface
    {
        return new \FluentSmtpLib\GuzzleHttp\Psr7\Request($method, $uri);
    }
    public function createUri(string $uri = '') : \FluentSmtpLib\Psr\Http\Message\UriInterface
    {
        return new \FluentSmtpLib\GuzzleHttp\Psr7\Uri($uri);
    }
}
