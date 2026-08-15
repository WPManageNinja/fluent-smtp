<?php
/**
 * Static gate: every admin iframe must be sandboxed, and no iframe may combine
 * `allow-scripts` with `allow-same-origin`.
 *
 * The log viewer frames arbitrary HTML supplied by whoever called wp_mail(),
 * which on most sites includes a public contact form. DOMPurify runs first, but
 * the sandbox is the containment that survives a sanitizer bypass. Granting
 * both allow-scripts and allow-same-origin is worse than no sandbox at all: the
 * framed document can then reach its own frame element and clear the attribute,
 * putting script execution back in the wp-admin origin.
 */

$root = is_dir(__DIR__ . '/../../resources') ? dirname(__DIR__, 2) : getcwd();
$scanDirs = ['resources/admin'];
if (isset($argv[1]) && $argv[1] !== '') {
    $scanDirs = [rtrim($argv[1], '/')];
}

$violations = [];
$scanned = 0;
$iframes = 0;

$iterate = function ($directory) {
    $paths = [];
    if (!is_dir($directory)) {
        return $paths;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['vue', 'js', 'html'], true)) {
            continue;
        }
        $path = str_replace('\\', '/', $file->getPathname());
        if (strpos($path, '/node_modules/') !== false) {
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
        $contents = file_get_contents($path);
        if ($contents === false || stripos($contents, '<iframe') === false) {
            continue;
        }

        // The opening tag can span several lines, so match the whole tag.
        if (!preg_match_all('/<iframe\b[^>]*>/is', $contents, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($matches[0] as $match) {
            $iframes++;
            $tag = $match[0];
            $line = substr_count(substr($contents, 0, $match[1]), "\n") + 1;
            $relative = str_replace($root . '/', '', $path);

            if (!preg_match('/\bsandbox\s*=\s*([\'"])(.*?)\1/is', $tag, $sandbox)) {
                $violations[] = [
                    'file' => $relative,
                    'line' => $line,
                    'kind' => 'iframe has no sandbox attribute',
                ];
                continue;
            }

            $tokens = preg_split('/\s+/', strtolower(trim($sandbox[2])), -1, PREG_SPLIT_NO_EMPTY);
            if (in_array('allow-scripts', $tokens, true) && in_array('allow-same-origin', $tokens, true)) {
                $violations[] = [
                    'file' => $relative,
                    'line' => $line,
                    'kind' => 'sandbox grants both allow-scripts and allow-same-origin',
                ];
            }
        }
    }
}

echo "iframe-sandbox: scanned {$scanned} files, found {$iframes} iframe(s)\n";
if (!$violations) {
    echo "OK — every iframe is sandboxed without a self-clearing token pair.\n";
    exit(0);
}

echo "\nFAIL — " . count($violations) . " violation(s):\n\n";
foreach ($violations as $violation) {
    echo "  {$violation['file']}:{$violation['line']}\n";
    echo "    {$violation['kind']}\n\n";
}
echo 'Framed email HTML is attacker-influenced. Keep the sandbox, and never pair' . "\n";
echo 'allow-scripts with allow-same-origin.' . "\n";
exit(1);
