<?php

namespace FluentSmtpLib;

// Don't redefine the functions if included multiple times.
if (!\function_exists('FluentSmtpLib\\GuzzleHttp\\Psr7\\str')) {
    require __DIR__ . '/functions.php';
}
