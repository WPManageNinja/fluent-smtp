<?php
/** Return the POST subset of the single authoritative 42-route manifest. */

$manifest = require __DIR__ . '/routes.manifest.php';

return array_values(array_filter($manifest, function ($entry) {
    return $entry['method'] === 'POST';
}));
