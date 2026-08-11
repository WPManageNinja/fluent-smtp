<?php
/** Fail when a production PHP file over 100 lines has zero hits and no triage. */

$root = dirname(__DIR__, 2);
$coverageDir = isset($argv[1]) ? $argv[1] : '';
$triageFile = $root . '/tests/coverage/zero-coverage-triage.php';

if (!$coverageDir || !is_dir($coverageDir)) {
    fwrite(STDERR, "coverage-triage: pass the PCOV data directory\n");
    exit(2);
}

$coverage = [];
$files = glob(rtrim($coverageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.json');
foreach ($files ?: [] as $file) {
    $decoded = json_decode((string) file_get_contents($file), true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "coverage-triage: invalid coverage file " . basename($file) . "\n");
        exit(2);
    }

    foreach ($decoded as $path => $lines) {
        foreach ((array) $lines as $line => $status) {
            $coverage[$path][(int) $line] = max(
                isset($coverage[$path][(int) $line]) ? $coverage[$path][(int) $line] : -1,
                (int) $status
            );
        }
    }
}

if (count($files ?: []) !== 3) {
    fwrite(STDERR, "coverage-triage: expected smoke, permissions, and integration PCOV files\n");
    exit(2);
}

$eligible = [];
foreach (['app', 'includes', 'database'] as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($fileInfo->getPathname(), strlen($root) + 1));
        if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
            continue;
        }
        if (strpos('/' . $path . '/', '/vendor/') !== false || strpos('/' . $path . '/', '/libs/') !== false) {
            continue;
        }

        $lineCount = count(file($fileInfo->getPathname()));
        if ($lineCount > 100) {
            $eligible[$path] = $lineCount;
        }
    }
}
ksort($eligible);

$zero = [];
foreach ($eligible as $path => $lineCount) {
    $hits = array_filter(isset($coverage[$path]) ? $coverage[$path] : [], function ($status) {
        return $status > 0;
    });
    if (!$hits) {
        $zero[$path] = $lineCount;
    }
}

$triage = is_file($triageFile) ? require $triageFile : [];
if (!is_array($triage)) {
    fwrite(STDERR, "coverage-triage: triage file must return an array\n");
    exit(2);
}

$errors = [];
foreach ($zero as $path => $lineCount) {
    if (!isset($triage[$path])) {
        $errors[] = "untriaged zero-coverage file: {$path} ({$lineCount} lines)";
        continue;
    }
    foreach (['category', 'reason', 'next'] as $field) {
        if (empty($triage[$path][$field]) || !is_string($triage[$path][$field])) {
            $errors[] = "{$path} triage is missing {$field}";
        }
    }
}
foreach ($triage as $path => $details) {
    if (!isset($zero[$path])) {
        $errors[] = "stale zero-coverage triage: {$path}";
    }
}

echo sprintf(
    "coverage-triage: %d production PHP files over 100 lines; %d hit, %d at zero, %d triaged\n",
    count($eligible),
    count($eligible) - count($zero),
    count($zero),
    count(array_intersect_key($triage, $zero))
);

foreach ($zero as $path => $lineCount) {
    $category = isset($triage[$path]['category']) ? $triage[$path]['category'] : 'UNTRIAGED';
    echo "  ZERO {$path} ({$lineCount} lines) — {$category}\n";
}

if ($errors) {
    echo "\nFAIL — coverage triage is incomplete:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}

echo "OK — every >100-line production PHP file at zero coverage is triaged.\n";
exit(0);
