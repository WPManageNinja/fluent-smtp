<?php
/**
 * FluentSMTP local WP-CLI test harness.
 *
 * Adapted from the wp-plugin-test-suite asset. AJAX is dispatched in-process
 * so the same PHP process can see database errors and plugin diagnostics.
 */

if (!defined('WP_CLI') || !WP_CLI) {
    exit("FluentSMTP tests must run via WP-CLI.\n");
}

$fsmtpSuiteConfig = require dirname(__DIR__) . '/suite.config.php';

if (!class_exists($fsmtpSuiteConfig['sentinel_class'])) {
    WP_CLI::error('FluentSMTP is not active on this site.');
}

// The plugin is already loaded by WP-CLI, but the provider seam is evaluated
// at send time. Defining this here therefore closes the transport before any
// suite case can resolve a provider.
if (!defined('FLUENTMAIL_SIMULATE_EMAILS')) {
    define('FLUENTMAIL_SIMULATE_EMAILS', true);
}

// Extending Error keeps a completed wp_send_json() response from being mistaken
// for an application Exception by controller catch blocks.
class FsmtpAjaxExit extends Error
{
}

class FsmtpTest
{
    /** @var array<int,array{name:string,detail:string}> */
    public static $failures = [];

    /** @var int */
    public static $passed = 0;

    /** @var int */
    public static $skipped = 0;

    /** @var array<int,string> */
    private static $diagnostics = [];

    /** @var string|null */
    private static $currentCase = null;

    /** @var float */
    private static $startedAt = 0.0;

    /** @var array<string,int> */
    private static $protectedCounts = [];

    /** @var array<int,array{url:string,args:array}> */
    private static $httpRequests = [];

    /** @var callable|null */
    private static $httpInterceptor = null;

    /** @var bool */
    private static $routesLoaded = false;

    /** @return array<string,mixed> */
    public static function config()
    {
        static $config;
        if ($config === null) {
            $config = require dirname(__DIR__) . '/suite.config.php';
        }
        return $config;
    }

    /**
     * Install fail-closed diagnostics, register admin routes for WP-CLI, and
     * select an administrator for read-only smoke calls.
     */
    public static function boot()
    {
        self::$startedAt = microtime(true);

        $hint = self::config()['plugin_dir_hint'];
        set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($hint) {
            if (!(error_reporting() & $errno)) {
                return false;
            }

            if (strpos(str_replace('\\', '/', $errfile), $hint) === false) {
                return false;
            }

            self::$diagnostics[] = self::errorLabel($errno) . ': ' . $errstr
                . ' (' . self::relPath($errfile) . ':' . $errline . ')';
            return true;
        });

        $admins = get_users([
            'role'    => 'administrator',
            'number'  => 1,
            'orderby' => 'ID',
        ]);
        if (!$admins) {
            WP_CLI::error('No administrator user found on this site.');
        }
        wp_set_current_user($admins[0]->ID);

        self::loadAdminRoutes();
        self::clearCaches();
        self::assertMailSimulationActive();
        self::$protectedCounts = self::protectedTableCounts();
    }

    /**
     * Admin routes are conditionally included behind is_admin() in production;
     * WP-CLI is not an admin request, so the test harness includes the same
     * route file with the real application instance in scope.
     */
    private static function loadAdminRoutes()
    {
        if (self::$routesLoaded) {
            return;
        }

        $bootstrap = self::config()['app_bootstrap'];
        $app = $bootstrap();
        require dirname(__DIR__, 2) . '/' . self::config()['routes_file'];
        self::$routesLoaded = true;
    }

    /**
     * Print a summary and leave WP-CLI non-zero on any failure. Protected log
     * counts are compared here even when a runner throws before its own cleanup.
     */
    public static function finish($suiteName)
    {
        $after = self::protectedTableCounts();
        if (self::$protectedCounts !== $after) {
            self::$currentCase = 'protected production data';
            self::fail(
                "FluentSMTP protected row-count drift\n  before: "
                . var_export(self::$protectedCounts, true)
                . "\n  after:  " . var_export($after, true)
            );
            self::$currentCase = null;
        }

        restore_error_handler();
        self::releaseHttpInterceptor();

        $elapsed = round(microtime(true) - self::$startedAt, 1);
        $total = self::$passed + count(self::$failures);

        WP_CLI::log('');
        WP_CLI::log(str_repeat('=', 72));
        WP_CLI::log(sprintf(
            '%s: %d/%d passed, %d failed, %d skipped  (%ss)',
            $suiteName,
            self::$passed,
            $total,
            count(self::$failures),
            self::$skipped,
            $elapsed
        ));

        if (self::$failures) {
            WP_CLI::log('');
            foreach (self::$failures as $index => $failure) {
                WP_CLI::log(sprintf('%d) %s', $index + 1, $failure['name']));
                foreach (explode("\n", rtrim($failure['detail'])) as $line) {
                    WP_CLI::log('   ' . $line);
                }
                WP_CLI::log('');
            }
            WP_CLI::log(str_repeat('=', 72));
            WP_CLI::halt(1);
        }

        WP_CLI::log(str_repeat('=', 72));
        WP_CLI::halt(0);
    }

    public static function case($name, callable $callback)
    {
        self::$currentCase = $name;
        self::$diagnostics = [];
        $failedBefore = count(self::$failures);

        try {
            // This is deliberately repeated for every case. A send-path test is
            // never allowed to rely on an earlier case's safety assertion.
            self::assertMailSimulationActive();
            $callback();
        } catch (Throwable $e) {
            self::fail('threw ' . get_class($e) . ': ' . $e->getMessage()
                . ' (' . self::relPath($e->getFile()) . ':' . $e->getLine() . ')');
        }

        if (self::$diagnostics) {
            self::fail("PHP diagnostics raised:\n  - " . implode("\n  - ", self::$diagnostics));
        }

        if (count(self::$failures) === $failedBefore) {
            self::$passed++;
        }
        self::$currentCase = null;
    }

    public static function fail($detail)
    {
        self::$failures[] = [
            'name'   => self::$currentCase ?: '(no case)',
            'detail' => $detail,
        ];
    }

    public static function skip($reason)
    {
        self::$skipped++;
        WP_CLI::log('  SKIP ' . (self::$currentCase ?: '') . ' — ' . $reason);
    }

    public static function assert($condition, $detail)
    {
        if (!$condition) {
            self::fail($detail);
        }
    }

    public static function assertSame($expected, $actual, $label)
    {
        if ($expected !== $actual) {
            self::fail($label . "\n  expected: " . var_export($expected, true)
                . "\n  actual:   " . var_export($actual, true));
        }
    }

    /**
     * Dispatch one registered admin-AJAX action in-process.
     *
     * The action name is always obtained by calling Application::getAjaxAction;
     * the harness never reproduces the plugin's string-building rules.
     *
     * @return array{action:string,status:int,data:mixed,raw:string,db_error:string,terminated:bool}
     */
    public static function ajax($method, $route, array $params = [])
    {
        global $wpdb;

        $bootstrap = self::config()['app_bootstrap'];
        $app = $bootstrap();
        $method = strtolower($method);
        $hook = $app->getAjaxAction($route, $method, true);
        $requestAction = substr($hook, strlen('wp_ajax_'));
        $nonce = wp_create_nonce(FLUENTMAIL);

        $payload = array_merge($params, [
            'action' => $requestAction,
            'nonce'  => $nonce,
        ]);
        $get = $method === 'get' ? $payload : [];
        $post = $method === 'post' ? $payload : [];

        $requestClass = self::config()['request_class'];
        $request = new $requestClass($app, $get, $post, []);
        $app->instance($requestClass, $request);

        $oldGet = $_GET;
        $oldPost = $_POST;
        $oldRequest = $_REQUEST;
        $oldServerMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;

        $_GET = $get;
        $_POST = $post;
        $_REQUEST = $payload;
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $wpdb->last_error = '';

        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }

        $dieHandler = function () {
            return function ($message = '', $title = '', $args = []) {
                throw new FsmtpAjaxExit('AJAX response completed.');
            };
        };
        add_filter('wp_die_ajax_handler', $dieHandler, PHP_INT_MAX);

        $raw = '';
        $terminated = false;
        $status = 200;
        ob_start();
        try {
            if (!has_action($hook)) {
                throw new RuntimeException('No callback is registered for derived action ' . $hook);
            }
            do_action($hook);
        } catch (FsmtpAjaxExit $e) {
            $terminated = true;
        } finally {
            $raw = (string) ob_get_clean();
            $httpStatus = http_response_code();
            if (is_int($httpStatus) && $httpStatus >= 100) {
                $status = $httpStatus;
            }

            remove_filter('wp_die_ajax_handler', $dieHandler, PHP_INT_MAX);
            $_GET = $oldGet;
            $_POST = $oldPost;
            $_REQUEST = $oldRequest;
            if ($oldServerMethod === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $oldServerMethod;
            }
        }

        $data = json_decode(trim($raw), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $data = null;
        }

        return [
            'action'     => $hook,
            'status'     => $status,
            'data'       => $data,
            'raw'        => $raw,
            'db_error'   => (string) $wpdb->last_error,
            'terminated' => $terminated,
        ];
    }

    /** Require a usable JSON AJAX response with no database failure. */
    public static function assertAjaxHealthy(array $result, $label)
    {
        if ($result['db_error'] !== '') {
            self::fail($label . "\n  DATABASE ERROR: " . $result['db_error']);
            return;
        }
        if (!$result['terminated']) {
            self::fail($label . "\n  action returned without completing a JSON response");
            return;
        }
        if (!is_array($result['data'])) {
            self::fail($label . "\n  invalid JSON response (body length " . strlen($result['raw']) . ')');
            return;
        }
        if (isset($result['data']['success']) && $result['data']['success'] === false) {
            self::fail($label . "\n  AJAX error response: " . self::responseMessage($result['data']));
        }
    }

    /** Resolve an action only through the production method. */
    public static function ajaxAction($method, $route, $isAdmin = true)
    {
        $bootstrap = self::config()['app_bootstrap'];
        return $bootstrap()->getAjaxAction($route, strtolower($method), $isAdmin);
    }

    /** Extract the human-facing message from a WordPress AJAX envelope. */
    public static function ajaxMessage(array $result)
    {
        return is_array($result['data']) ? self::responseMessage($result['data']) : trim($result['raw']);
    }

    /**
     * Fail closed for every WordPress HTTP request. The optional callback may
     * return a normal WordPress HTTP response fixture for expected endpoints.
     */
    public static function interceptHttp(?callable $responder = null)
    {
        self::$httpRequests = [];
        self::releaseHttpInterceptor();

        self::$httpInterceptor = function ($preempt, $args, $url) use ($responder) {
            self::$httpRequests[] = [
                'url'  => (string) $url,
                'args' => is_array($args) ? $args : [],
            ];

            if ($responder) {
                $response = $responder((string) $url, is_array($args) ? $args : []);
                if ($response !== null) {
                    return $response;
                }
            }

            $safeUrl = strtok((string) $url, '?');
            return new WP_Error(
                'fsmtp_test_http_blocked',
                'Outbound HTTP blocked by the FluentSMTP test harness: ' . $safeUrl
            );
        };

        add_filter('pre_http_request', self::$httpInterceptor, PHP_INT_MAX, 3);
    }

    public static function releaseHttpInterceptor()
    {
        if (self::$httpInterceptor !== null) {
            remove_filter('pre_http_request', self::$httpInterceptor, PHP_INT_MAX);
            self::$httpInterceptor = null;
        }
    }

    /** @return array<int,array{url:string,args:array}> */
    public static function httpRequests()
    {
        return self::$httpRequests;
    }

    /**
     * Prove the plugin's own provider resolver chose Simulator. A constant check
     * alone is not enough: this asserts the runtime seam used by actual sends.
     */
    public static function assertMailSimulationActive()
    {
        if (!defined('FLUENTMAIL_SIMULATE_EMAILS') || !FLUENTMAIL_SIMULATE_EMAILS) {
            throw new RuntimeException('FLUENTMAIL_SIMULATE_EMAILS is not truthy.');
        }

        $handler = fluentMailGetProvider('fsmtp-suite-safety@example.test', true);
        if (!$handler instanceof \FluentMail\App\Services\Mailer\Providers\Simulator\Handler) {
            throw new RuntimeException(
                'fluentMailGetProvider() did not resolve the Simulator handler.'
            );
        }
        return true;
    }

    /** Clear only FluentSMTP-owned transient/object-cache entries. */
    public static function clearCaches()
    {
        global $wpdb;
        $rows = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE '\\_transient\\_fluentmail%'
                OR option_name LIKE '\\_transient\\_timeout\\_fluentmail%'
                OR option_name LIKE '\\_transient\\_fluentsmtp%'
                OR option_name LIKE '\\_transient\\_timeout\\_fluentsmtp%'"
        );

        foreach ($rows as $name) {
            $key = preg_replace('/^_transient_(timeout_)?/', '', $name);
            delete_transient($key);
        }
        return count($rows);
    }

    /** @return array<string,int> */
    public static function protectedTableCounts()
    {
        global $wpdb;
        $counts = [];
        foreach (self::config()['protected_tables'] as $suffix) {
            $table = $wpdb->prefix . $suffix;
            $counts[$suffix] = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
        }
        return $counts;
    }

    /**
     * Run the plugin's real WP-CLI command in a fresh process. The child loads
     * cli-bootstrap.php before command dispatch, so mail simulation, HTTP and
     * option-write fuses are enforced inside the process that owns the command.
     *
     * @return array{code:int,stdout:string,stderr:string,output:string}
     */
    public static function wpCli(array $arguments, array $environment = [])
    {
        $candidates = [
            isset($_SERVER['argv'][0]) ? $_SERVER['argv'][0] : '',
            getenv('_') ?: '',
        ];
        $binary = '';

        foreach ($candidates as $candidate) {
            if ($candidate && is_file($candidate) && is_executable($candidate)) {
                $binary = $candidate;
                break;
            }
        }

        if (!$binary) {
            $located = [];
            $locateCode = 1;
            exec('command -v wp 2>/dev/null', $located, $locateCode);
            if ($locateCode === 0 && !empty($located[0])) {
                $binary = trim($located[0]);
            }
        }

        if (!$binary) {
            throw new RuntimeException('Could not locate the wp executable for CLI integration tests.');
        }

        $command = array_merge([
            $binary,
            '--path=' . untrailingslashit(ABSPATH),
            '--require=' . dirname(__DIR__) . '/bin/cli-bootstrap.php',
            '--no-color',
        ], $arguments);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $baseEnvironment = getenv();
        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            null,
            array_merge(is_array($baseEnvironment) ? $baseEnvironment : [], $environment)
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Could not start the WP-CLI child process.');
        }

        fclose($pipes[0]);
        $stdout = (string)stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($process);

        return [
            'code'   => (int)$code,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'output' => trim($stdout . "\n" . $stderr),
        ];
    }

    public static function uniq($prefix = 'fsmtptest')
    {
        return $prefix . '-' . strtolower(wp_generate_password(8, false, false));
    }

    private static function responseMessage(array $data)
    {
        if (isset($data['data']['message']) && is_string($data['data']['message'])) {
            return $data['data']['message'];
        }
        if (isset($data['message']) && is_string($data['message'])) {
            return $data['message'];
        }
        return wp_json_encode($data);
    }

    private static function relPath($file)
    {
        $normalized = str_replace('\\', '/', $file);
        $position = strpos($normalized, '/fluent-smtp/');
        return $position === false ? basename($normalized) : substr($normalized, $position + 1);
    }

    private static function errorLabel($errno)
    {
        $map = [
            E_WARNING           => 'Warning',
            E_NOTICE            => 'Notice',
            E_USER_WARNING      => 'User Warning',
            E_USER_NOTICE       => 'User Notice',
            E_DEPRECATED        => 'Deprecated',
            E_USER_DEPRECATED   => 'User Deprecated',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
        ];
        return isset($map[$errno]) ? $map[$errno] : ('Error(' . $errno . ')');
    }
}
