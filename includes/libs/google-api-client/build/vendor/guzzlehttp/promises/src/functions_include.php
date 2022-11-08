<?php

namespace FluentSmtpLib;

// Don't redefine the functions if included multiple times.
if (!\function_exists('FluentSmtpLib\\GuzzleHttp\\Promise\\promise_for')) {
    require __DIR__ . '/functions.php';
}
