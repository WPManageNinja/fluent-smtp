<?php
/**
 * Static gate: production code must not disable TLS peer or hostname checks.
 *
 * Provider and notification requests carry API keys, webhook secrets, and whole
 * message bodies. Turning verification off makes an active network attacker's
 * certificate indistinguishable from the real one, and the failure is silent —
 * nothing in the response tells the site it happened. cURL transports are
 * covered here too, since they bypass WP_Http and so cannot be observed by the
 * suite's HTTP interceptor.
 */

$root = is_dir(__DIR__ . '/../../app') ? dirname(__DIR__, 2) : getcwd();
$scanDirs = ['app', 'includes'];
if (isset($argv[1]) && $argv[1] !== '') {
    $scanDirs = [rtrim($argv[1], '/')];
}

/*
 * Literal disabling only. Runtime-controlled forms such as Amazon SES's
 * `CURLOPT_SSL_VERIFYHOST, ($this->ses->verifyHost() ? 2 : 0)` are deliberately
 * not matched: those default to enabled and are settable by the caller.
 */
$patterns = [
    'sslverify disabled'         => '/[\'"]sslverify[\'"]\s*=>\s*(?:false|0)\b\s*(?:[,\)\]]|$)/i',
    'peer verification disabled' => '/CURLOPT_SSL_VERIFYPEER\s*,\s*(?:false|0)\b\s*(?:[,\)]|$)/i',
    'host verification disabled' => '/CURLOPT_SSL_VERIFYHOST\s*,\s*(?:false|0)\b\s*(?:[,\)]|$)/i',
];

$violations = [];
$scanned = 0;

$iterate = function ($directory) {
    $paths = [];
    if (!is_dir($directory)) {
        return $paths;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $path = str_replace('\\', '/', $file->getPathname());
        if (strpos($path, '/vendor/') !== false || strpos($path, '/libs/') !== false) {
            continue;
        }
        $paths[] = $file->getPathname();
    }
    return $paths;
};

foreach ($scanDirs as $directory) {
    $target = ($directory !== '' && $directory[0] === '/') ? $directory : $root . '/' . $directory;
    foreach ($iterate($target) as $path) {
        $scanned++;
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $index => $line) {
            foreach ($patterns as $label => $pattern) {
                if (!preg_match($pattern, $line)) {
                    continue;
                }

                $violations[] = [
                    'file'  => str_replace($root . '/', '', $path),
                    'line'  => $index + 1,
                    'kind'  => $label,
                    'code'  => trim($line),
                ];
            }
        }
    }
}

echo "tls-verification: scanned {$scanned} PHP files\n";
if (!$violations) {
    echo "OK — no disabled TLS peer or hostname verification.\n";
    exit(0);
}

echo "\nFAIL — " . count($violations) . " violation(s):\n\n";
foreach ($violations as $violation) {
    echo "  {$violation['file']}:{$violation['line']}\n";
    echo "    {$violation['kind']}\n";
    echo "    {$violation['code']}\n\n";
}
echo 'Outbound requests carry credentials and message bodies. Keep verification on;' . "\n";
echo 'WordPress\'s https_ssl_verify filter is the supported local-development escape hatch.' . "\n";
exit(1);
