<?php

namespace FluentSmtpLib\Google\AuthHandler;

use FluentSmtpLib\Google\Auth\CredentialsLoader;
use FluentSmtpLib\Google\Auth\FetchAuthTokenCache;
use FluentSmtpLib\Google\Auth\HttpHandler\HttpHandlerFactory;
use FluentSmtpLib\Google\Auth\Middleware\AuthTokenMiddleware;
use FluentSmtpLib\Google\Auth\Middleware\ScopedAccessTokenMiddleware;
use FluentSmtpLib\Google\Auth\Middleware\SimpleMiddleware;
use FluentSmtpLib\GuzzleHttp\Client;
use FluentSmtpLib\GuzzleHttp\ClientInterface;
use FluentSmtpLib\Psr\Cache\CacheItemPoolInterface;
/**
 * This supports Guzzle 6
 */
class Guzzle6AuthHandler
{
    protected $cache;
    protected $cacheConfig;
    public function __construct(?\FluentSmtpLib\Psr\Cache\CacheItemPoolInterface $cache = null, array $cacheConfig = [])
    {
        $this->cache = $cache;
        $this->cacheConfig = $cacheConfig;
    }
    public function attachCredentials(\FluentSmtpLib\GuzzleHttp\ClientInterface $http, \FluentSmtpLib\Google\Auth\CredentialsLoader $credentials, ?callable $tokenCallback = null)
    {
        // use the provided cache
        if ($this->cache) {
            $credentials = new \FluentSmtpLib\Google\Auth\FetchAuthTokenCache($credentials, $this->cacheConfig, $this->cache);
        }
        return $this->attachCredentialsCache($http, $credentials, $tokenCallback);
    }
    public function attachCredentialsCache(\FluentSmtpLib\GuzzleHttp\ClientInterface $http, \FluentSmtpLib\Google\Auth\FetchAuthTokenCache $credentials, ?callable $tokenCallback = null)
    {
        // if we end up needing to make an HTTP request to retrieve credentials, we
        // can use our existing one, but we need to throw exceptions so the error
        // bubbles up.
        $authHttp = $this->createAuthHttp($http);
        $authHttpHandler = \FluentSmtpLib\Google\Auth\HttpHandler\HttpHandlerFactory::build($authHttp);
        $middleware = new \FluentSmtpLib\Google\Auth\Middleware\AuthTokenMiddleware($credentials, $authHttpHandler, $tokenCallback);
        $config = $http->getConfig();
        $config['handler']->remove('google_auth');
        $config['handler']->push($middleware, 'google_auth');
        $config['auth'] = 'google_auth';
        $http = new \FluentSmtpLib\GuzzleHttp\Client($config);
        return $http;
    }
    public function attachToken(\FluentSmtpLib\GuzzleHttp\ClientInterface $http, array $token, array $scopes)
    {
        $tokenFunc = function ($scopes) use($token) {
            return $token['access_token'];
        };
        $middleware = new \FluentSmtpLib\Google\Auth\Middleware\ScopedAccessTokenMiddleware($tokenFunc, $scopes, $this->cacheConfig, $this->cache);
        $config = $http->getConfig();
        $config['handler']->remove('google_auth');
        $config['handler']->push($middleware, 'google_auth');
        $config['auth'] = 'scoped';
        $http = new \FluentSmtpLib\GuzzleHttp\Client($config);
        return $http;
    }
    public function attachKey(\FluentSmtpLib\GuzzleHttp\ClientInterface $http, $key)
    {
        $middleware = new \FluentSmtpLib\Google\Auth\Middleware\SimpleMiddleware(['key' => $key]);
        $config = $http->getConfig();
        $config['handler']->remove('google_auth');
        $config['handler']->push($middleware, 'google_auth');
        $config['auth'] = 'simple';
        $http = new \FluentSmtpLib\GuzzleHttp\Client($config);
        return $http;
    }
    private function createAuthHttp(\FluentSmtpLib\GuzzleHttp\ClientInterface $http)
    {
        return new \FluentSmtpLib\GuzzleHttp\Client(['http_errors' => \true] + $http->getConfig());
    }
}
