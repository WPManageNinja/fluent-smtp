<?php

namespace FluentMail\App\Services;

class Documentation
{
    const CACHE_KEY = 'fluent_smtp_docs_v1';
    const BACKUP_KEY = 'fluent_smtp_docs_v1_last_success';

    public function getDocs()
    {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached) && $cached) {
            return $cached;
        }

        $response = wp_remote_get('https://fluentsmtp.com/docs/api/v1/docs.json', [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/json'],
            'limit_response_size' => 2 * 1024 * 1024
        ]);
        $payload = null;
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $payload = json_decode(wp_remote_retrieve_body($response), true);
        }

        $docs = $this->validate($payload);
        if ($docs !== false) {
            set_transient(self::CACHE_KEY, $docs, 6 * HOUR_IN_SECONDS);
            // Persist separately so an expired transient cannot erase the fallback.
            update_option(self::BACKUP_KEY, $docs, false);
            return $docs;
        }

        $backup = get_option(self::BACKUP_KEY, []);
        if (is_array($backup) && $backup) {
            // Avoid repeatedly contacting an unavailable service on each page load.
            set_transient(self::CACHE_KEY, $backup, 5 * MINUTE_IN_SECONDS);
            return $backup;
        }

        return new \WP_Error('docs_unavailable', __('Unable to load documentation. Please try again shortly.', 'fluent-smtp'));
    }

    /**
     * The article to read for each provider, keyed the way connections key their
     * provider. Full URLs rather than slugs: the docs repository's
     * check-plugin-links script scans this file for links and fails the docs
     * build when a page one of these points at is renamed, which a bare slug
     * would slip past.
     */
    public static function providerLinks()
    {
        return [
            'smtp'        => 'https://fluentsmtp.com/docs/set-up-fluent-smtp-with-any-host-or-mailer/',
            'tosend'      => 'https://fluentsmtp.com/docs/set-up-tosend-in-fluent-smtp/',
            'ses'         => 'https://fluentsmtp.com/docs/set-up-amazon-ses-in-fluent-smtp/',
            'mailgun'     => 'https://fluentsmtp.com/docs/configure-mailgun-in-fluent-smtp-to-send-emails/',
            'sendgrid'    => 'https://fluentsmtp.com/docs/set-up-the-sendgrid-driver-in-fluent-smtp/',
            'sendinblue'  => 'https://fluentsmtp.com/docs/setting-up-sendinblue-mailer-in-fluent-smtp/',
            'sparkpost'   => 'https://fluentsmtp.com/docs/configure-sparkpost-in-fluent-smtp-to-send-emails/',
            'pepipost'    => 'https://fluentsmtp.com/docs/set-up-the-pepipost-mailer-in-fluent-smtp/',
            'postmark'    => 'https://fluentsmtp.com/docs/configure-postmark-in-fluent-smtp-to-send-emails/',
            'elasticmail' => 'https://fluentsmtp.com/docs/configure-elastic-email-in-fluent-smtp/',
            'smtp2go'     => 'https://fluentsmtp.com/docs/configure-smtp2go-in-fluentsmtp-to-send-emails/',
            'gmail'       => 'https://fluentsmtp.com/docs/connect-gmail-or-google-workspace-emails-with-fluentsmtp/',
            'outlook'     => 'https://fluentsmtp.com/docs/configure-fluentsmtp-with-microsoft-outlook-office/',
            'cloudflare'  => 'https://fluentsmtp.com/docs/configure-cloudflare-email-in-fluent-smtp/'
        ];
    }

    /**
     * The articles for the providers this site sends through, as [doc id => provider
     * key], in the order the connections were made. One entry per article: two
     * Outlook connections still want the Outlook guide once.
     */
    public function suggestedFor(array $docs, array $providers)
    {
        $links = self::providerLinks();
        $byLink = [];
        foreach ($docs as $doc) {
            $byLink[$doc['link']] = $doc['id'];
        }

        $suggested = [];
        foreach ($providers as $provider) {
            if (!is_string($provider) || !isset($links[$provider], $byLink[$links[$provider]])) {
                continue;
            }
            $id = $byLink[$links[$provider]];
            if (!isset($suggested[$id])) {
                $suggested[$id] = $provider;
            }
        }
        return $suggested;
    }

    private function validate($payload)
    {
        if (!is_array($payload) || !isset($payload['version'], $payload['docs']) ||
            $payload['version'] !== 1 || !is_array($payload['docs']) || !$payload['docs']) {
            return false;
        }

        $docs = [];
        $ids = [];
        foreach ($payload['docs'] as $doc) {
            if (!is_array($doc)) {
                return false;
            }
            foreach (['id', 'title', 'description', 'content', 'link'] as $field) {
                if (!isset($doc[$field]) || !is_string($doc[$field]) || $doc[$field] === '') {
                    return false;
                }
            }
            if (!isset($doc['category']['value'], $doc['category']['label']) ||
                !is_string($doc['category']['value']) || !is_string($doc['category']['label']) ||
                !$doc['category']['value'] || !$doc['category']['label'] ||
                !preg_match('#^https://fluentsmtp\\.com/docs/[a-z0-9-]+/$#D', $doc['link']) ||
                isset($ids[$doc['id']])) {
                return false;
            }
            $ids[$doc['id']] = true;
            $docs[] = [
                'id' => $doc['id'],
                'title' => $doc['title'],
                'description' => $doc['description'],
                'content' => $doc['content'],
                'link' => $doc['link'],
                'category' => ['value' => $doc['category']['value'], 'label' => $doc['category']['label']]
            ];
        }
        return $docs;
    }
}
