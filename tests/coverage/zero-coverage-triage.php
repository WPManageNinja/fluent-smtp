<?php
/** Phase 13 decisions for production PHP files over 100 lines with zero hits. */

$triage = [];

$providerHandlers = [
    'app/Services/Mailer/Providers/AmazonSes/Handler.php' => 'Amazon SES',
    'app/Services/Mailer/Providers/Cloudflare/Handler.php' => 'Cloudflare',
    'app/Services/Mailer/Providers/ElasticMail/Handler.php' => 'Elastic Email',
    'app/Services/Mailer/Providers/Mailgun/Handler.php' => 'Mailgun',
    'app/Services/Mailer/Providers/PepiPost/Handler.php' => 'PepiPost',
    'app/Services/Mailer/Providers/Postmark/Handler.php' => 'Postmark',
    'app/Services/Mailer/Providers/SendGrid/Handler.php' => 'SendGrid',
    'app/Services/Mailer/Providers/SendInBlue/Handler.php' => 'Brevo/SendInBlue',
    'app/Services/Mailer/Providers/Smtp2Go/Handler.php' => 'SMTP2GO',
    'app/Services/Mailer/Providers/SparkPost/Handler.php' => 'SparkPost',
    'app/Services/Mailer/Providers/TransMail/Handler.php' => 'TransMail',
];

foreach ($providerHandlers as $path => $provider) {
    $triage[$path] = [
        'category' => 'outbound provider adapter',
        'reason'   => "Every send-path test forces Simulator, so the live {$provider} transport is intentionally never resolved or called.",
        'next'     => "Add a {$provider} contract fixture with fail-closed HTTP/SDK seams; assert request mapping and error normalization without network.",
    ];
}

$triage['app/Services/Mailer/Providers/AmazonSes/SimpleEmailService.php'] = [
    'category' => 'Amazon SES protocol client',
    'reason'   => 'The low-level SES HTTP and signing client is bypassed while Simulator is mandatory.',
    'next'     => 'Add deterministic AWS signature/request fixtures with a fixed clock and a fail-closed HTTP responder.',
];
$triage['app/Services/Mailer/Providers/AmazonSes/SimpleEmailServiceMessage.php'] = [
    'category' => 'Amazon SES message serializer',
    'reason'   => 'No current safe-send case constructs the provider-specific SES MIME/message object.',
    'next'     => 'Add pure recipient, header, attachment, and MIME serialization fixtures without dispatching a request.',
];
$triage['app/Services/Mailer/Providers/AmazonSes/SimpleEmailServiceRequest.php'] = [
    'category' => 'Amazon SES request serializer',
    'reason'   => 'Canonical SES request construction is below the Simulator seam and receives no runtime hit.',
    'next'     => 'Add fixed-input canonical query and signature fixtures before enabling the SES handler contract test.',
];

$triage['app/Services/TransStrings.php'] = [
    'category' => 'browser-only localization catalog',
    'reason'   => 'The Vue browser smoke loads localized admin assets in a web process, outside the merged WP-CLI PCOV runs.',
    'next'     => 'Capture web-process PHP coverage or add a catalog contract when translated-key drift becomes a target.',
];
$triage['app/views/admin/digest_email.php'] = [
    'category' => 'notification rendering',
    'reason'   => 'Notification settings and scheduling are covered, but the digest template itself is not rendered.',
    'next'     => 'Add a deterministic digest render fixture that asserts escaped visible content without sending email.',
];
$triage['includes/Request/File.php'] = [
    'category' => 'file-upload infrastructure',
    'reason'   => 'No current happy-path route fixture submits an uploaded attachment, so the file wrapper remains dormant.',
    'next'     => 'Cover attachment normalization in the Simulator-backed end-to-end send flow, using an exact temporary file fixture.',
];
$triage['includes/Support/Collection.php'] = [
    'category' => 'bundled framework utility',
    'reason'   => 'The plugin paths exercised in Round 2 do not call this generic collection implementation.',
    'next'     => 'Add callsite-driven tests only when production code adopts Collection; avoid blanket testing unused framework surface.',
];
$triage['includes/Support/Str.php'] = [
    'category' => 'bundled framework utility',
    'reason'   => 'The plugin paths exercised in Round 2 do not call this large generic string helper.',
    'next'     => 'Add callsite-driven tests for the specific helpers when a production feature depends on them.',
];
$triage['includes/View/View.php'] = [
    'category' => 'web-process view renderer',
    'reason'   => 'Admin rendering occurs in the Phase 11 browser process, which is outside the WP-CLI PCOV merger.',
    'next'     => 'Capture web-process PHP coverage or add an isolated template-render contract alongside digest rendering.',
];

return $triage;
