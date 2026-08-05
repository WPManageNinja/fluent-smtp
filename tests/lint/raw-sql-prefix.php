<?php
/**
 * Static gate: raw SQL must not use a bare table-qualified `fsmpt_*` name.
 *
 * Query-builder grammar can apply `$wpdb->prefix`; raw SQL cannot. A default
 * `wp_` development prefix would otherwise hide this entire defect class.
 */

$root = is_dir(__DIR__ . '/../../app') ? dirname(__DIR__, 2) : getcwd();
$scanDirs = ['app', 'includes', 'database'];
if (isset($argv[1]) && $argv[1] !== '') {
    $scanDirs = [rtrim($argv[1], '/')];
}

$rawCallPattern = '/\b(?:selectRaw|whereRaw|havingRaw|orderByRaw|groupByRaw|orWhereRaw|raw)\s*\(/i';
$qualifiedPattern = '/\bfsmpt_[a-z0-9_]+\s*\./i';
$prefixEvidence = '/\$wpdb->prefix|getTablePrefix\s*\(|\{\$[a-zA-Z_][a-zA-Z0-9_]*\}|\$[a-zA-Z_][a-zA-Z0-9_]*\s*\./';

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
            if (!preg_match($rawCallPattern, $line)) {
                continue;
            }
            if (!preg_match_all(
                '/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'/',
                $line,
                $matches,
                PREG_SET_ORDER | PREG_OFFSET_CAPTURE
            )) {
                continue;
            }

            foreach ($matches as $match) {
                $doubleQuoted = isset($match[1]) ? $match[1][0] : '';
                $singleQuoted = isset($match[2]) ? $match[2][0] : '';
                $literal = $singleQuoted !== '' ? $singleQuoted : $doubleQuoted;
                if (!preg_match($qualifiedPattern, $literal, $hit)) {
                    continue;
                }
                if (preg_match($prefixEvidence, $literal)) {
                    continue;
                }

                $before = substr($line, 0, $match[0][1]);
                if (preg_match(
                    '/(?:\$wpdb->prefix|getTablePrefix\s*\(\s*\)|\$[a-zA-Z_][a-zA-Z0-9_]*)\s*\.\s*$/',
                    $before
                )) {
                    continue;
                }

                $violations[] = [
                    'file'  => str_replace($root . '/', '', $path),
                    'line'  => $index + 1,
                    'ident' => trim($hit[0]),
                    'code'  => trim($line),
                ];
            }
        }
    }
}

echo "raw-sql-prefix: scanned {$scanned} PHP files\n";
if (!$violations) {
    echo "OK — no unprefixed FluentSMTP table identifiers inside raw SQL.\n";
    exit(0);
}

echo "\nFAIL — " . count($violations) . " violation(s):\n\n";
foreach ($violations as $violation) {
    echo "  {$violation['file']}:{$violation['line']}\n";
    echo "    unprefixed identifier: {$violation['ident']}\n";
    echo "    {$violation['code']}\n\n";
}
echo 'Raw SQL bypasses prefix-aware grammar. Resolve $wpdb->prefix explicitly.' . "\n";
exit(1);
