<?php
/**
 * Static gate: the DOMPurify version recorded in PHP must match the version in
 * the vendored library header.
 *
 * These drifted once already — the enqueue declared 2.4.3 while the shipped
 * file was 3.2.4 — which made the cache buster wrong and led a security audit
 * to reason about the wrong version's advisories. The cache buster is now the
 * plugin version, so the remaining risk is a stale record, and a stale record
 * is what sends the next reviewer looking at the wrong CVE list.
 */

$root = is_dir(__DIR__ . '/../../app') ? dirname(__DIR__, 2) : getcwd();
if (isset($argv[1]) && $argv[1] !== '') {
    $root = rtrim($argv[1], '/');
}

$libraryFile = $root . '/resources/libs/purify/purify.js';
$recordFile  = $root . '/app/Hooks/Handlers/AdminMenuHandler.php';

$fail = function ($message) {
    echo "\nFAIL — {$message}\n";
    exit(1);
};

if (!is_readable($libraryFile)) {
    $fail('vendored DOMPurify is missing at resources/libs/purify/purify.js');
}
if (!is_readable($recordFile)) {
    $fail('AdminMenuHandler.php is missing');
}

// The upstream banner is the authority for what is actually shipped.
$header = (string) fread(fopen($libraryFile, 'r'), 400);
if (!preg_match('/DOMPurify\s+([0-9]+\.[0-9]+\.[0-9]+)/', $header, $shipped)) {
    $fail('could not read a version banner from the vendored DOMPurify build');
}

$record = file_get_contents($recordFile);
if (!preg_match('/DOMPurify\s+([0-9]+\.[0-9]+\.[0-9]+)/', $record, $recorded)) {
    $fail('AdminMenuHandler.php no longer records which DOMPurify version is vendored');
}

echo "vendored-library-version: DOMPurify shipped={$shipped[1]} recorded={$recorded[1]}\n";

if ($shipped[1] !== $recorded[1]) {
    $fail(
        "recorded DOMPurify version {$recorded[1]} does not match the shipped build {$shipped[1]}.\n"
        . '        Update the comment in app/Hooks/Handlers/AdminMenuHandler.php with the files,' . "\n"
        . '        and re-check the advisories for the version actually being shipped.'
    );
}

echo "OK — the recorded DOMPurify version matches the vendored build.\n";
exit(0);
