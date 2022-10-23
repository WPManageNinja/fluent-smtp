<?php

namespace FluentMailLib;

// Don't redefine the functions if included multiple times.
if (!\function_exists('FluentMailLib\\GuzzleHttp\\Promise\\promise_for')) {
    require __DIR__ . '/functions.php';
}
