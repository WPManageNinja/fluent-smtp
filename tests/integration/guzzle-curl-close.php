<?php

/**
 * The bundled, prefixed Guzzle/Monolog code must not call curl_close() on
 * PHP 8+. The function has been a no-op since PHP 8.0 and raises
 * E_DEPRECATED on PHP 8.5, which flooded customers' logs on every Gmail
 * request.
 *
 * Handles are created with the real CurlFactory but never executed, so no
 * request leaves the machine.
 */
return function () {
    require_once FLUENTMAIL_PLUGIN_PATH . 'includes/libs/google-api-client/build/vendor/autoload.php';

    /** Run $fn and return every deprecation it raised. */
    $captureDeprecations = function (callable $fn) {
        $deprecations = [];
        $previous = set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$deprecations) {
            if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
                $deprecations[] = $errstr . ' @ ' . basename($errfile) . ':' . $errline;
                return true;
            }
            return false;
        });

        try {
            $fn();
        } finally {
            set_error_handler($previous);
        }

        return $deprecations;
    };

    $makeEasy = function ($factory) {
        $request = new \FluentSmtpLib\GuzzleHttp\Psr7\Request('GET', 'https://fluentsmtp.invalid/');
        return $factory->create($request, []);
    };

    FsmtpTest::case('releasing an over-limit cURL handle raises no deprecation', function () use ($captureDeprecations, $makeEasy) {
        if (!function_exists('curl_init')) {
            FsmtpTest::skip('cURL extension is not loaded');
            return;
        }

        $deprecations = $captureDeprecations(function () use ($makeEasy) {
            // maxHandles 0 forces release() down the close branch.
            $factory = new \FluentSmtpLib\GuzzleHttp\Handler\CurlFactory(0);
            $factory->release($makeEasy($factory));
        });

        FsmtpTest::assertSame([], $deprecations, 'deprecations from CurlFactory::release()');
    });

    FsmtpTest::case('destroying a CurlFactory with pooled handles raises no deprecation', function () use ($captureDeprecations, $makeEasy) {
        if (!function_exists('curl_init')) {
            FsmtpTest::skip('cURL extension is not loaded');
            return;
        }

        $deprecations = $captureDeprecations(function () use ($makeEasy) {
            $factory = new \FluentSmtpLib\GuzzleHttp\Handler\CurlFactory(3);
            $factory->release($makeEasy($factory)); // pooled, closed in __destruct
            unset($factory);
        });

        FsmtpTest::assertSame([], $deprecations, 'deprecations from CurlFactory::__destruct()');
    });

    FsmtpTest::case('bundled vendor code guards every curl_close() call', function () {
        // Covers the paths a runtime test cannot reach cheaply (the multi
        // handler's cancel() and Monolog's Curl\Util).
        $root = FLUENTMAIL_PLUGIN_PATH . 'includes/libs/google-api-client/build/vendor';
        $unguarded = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $lines = file($file->getPathname());
            foreach ($lines as $i => $line) {
                if (preg_match('~^\s*(//|\*|/\*)~', $line)
                    || !preg_match('/\\\\?curl_close\s*\(/', $line)
                    || preg_match('/function\s+curl_close/', $line)) {
                    continue;
                }
                // The guard must sit on the same line or within the 3 lines above.
                $window = implode('', array_slice($lines, max(0, $i - 3), 4));
                if (strpos($window, 'PHP_VERSION_ID < 80000') === false) {
                    $unguarded[] = substr($file->getPathname(), strlen($root) + 1) . ':' . ($i + 1);
                }
            }
        }

        FsmtpTest::assertSame([], $unguarded, 'unguarded curl_close() calls in bundled vendor code');
    });
};
