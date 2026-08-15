<?php
/**
 * Fixture for the tls-verification lint self-test. Never loaded at runtime.
 *
 * Each disabled form below must keep firing the gate. The Amazon SES-shaped
 * runtime-controlled calls at the end must NOT fire, so the gate stays useful
 * instead of being switched off for noise.
 */

function fsmtp_fixture_bad_wp_http()
{
    return wp_remote_post('https://example.test/hook', [
        'body'      => '{}',
        'sslverify' => false,
        'timeout'   => 30,
    ]);
}

function fsmtp_fixture_bad_wp_http_spaced()
{
    return wp_remote_get('https://example.test/info', [
        'sslverify'   => false
    ]);
}

function fsmtp_fixture_bad_curl($handle)
{
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
}

function fsmtp_fixture_allowed_runtime_curl($handle, $ses)
{
    curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, ($ses->verifyHost() ? 2 : 0));
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, ($ses->verifyPeer() ? 1 : 0));
}

function fsmtp_fixture_allowed_enabled($handle)
{
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);

    return wp_remote_post('https://example.test/hook', ['sslverify' => true]);
}
