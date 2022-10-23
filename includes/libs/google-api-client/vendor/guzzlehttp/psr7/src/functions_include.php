<?php

namespace FluentMailLib;

// Don't redefine the functions if included multiple times.
if (!\function_exists('FluentMailLib\\GuzzleHttp\\Psr7\\str')) {
    require __DIR__ . '/functions.php';
}
