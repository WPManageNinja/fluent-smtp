<?php

namespace FluentMailLib;

// Don't redefine the functions if included multiple times.
if (!\function_exists('FluentMailLib\\GuzzleHttp\\uri_template')) {
    require __DIR__ . '/functions.php';
}
