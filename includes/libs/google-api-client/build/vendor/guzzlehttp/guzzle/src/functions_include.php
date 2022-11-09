<?php

namespace FluentSmtpLib;

// Don't redefine the functions if included multiple times.
if (!\function_exists('FluentSmtpLib\\GuzzleHttp\\uri_template')) {
    require __DIR__ . '/functions.php';
}
