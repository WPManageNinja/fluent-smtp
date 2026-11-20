<?php

namespace FluentMail\App\Services;

class DomainAuthentication
{
    public function check($envelope, $signing, $selector)
    {
        $envelope = $this->domain($envelope);
        $signing = $signing === '' ? '' : $this->domain($signing);
        if (!is_string($selector) || ($selector !== '' && !$this->validLabels($selector))) {
            throw new \InvalidArgumentException(__('Enter the DKIM selector supplied by your email provider.', 'fluent-smtp'));
        }
        $keyName = strtolower($selector) . '._domainkey.' . $signing;
        if ($selector !== '' && ($signing === '' || strlen($keyName) > 253)) {
            throw new \InvalidArgumentException(__('Enter a valid signing domain and selector for this DKIM lookup.', 'fluent-smtp'));
        }

        return [
            'spf'  => $this->lookup('spf', $envelope),
            'dkim' => $selector === '' ? ['name' => '', 'status' => 'skipped'] : $this->lookup('dkim', $keyName),
        ];
    }

    private function domain($domain)
    {
        $domain = is_string($domain) ? strtolower(trim($domain)) : '';
        if (substr($domain, -1) === '.') {
            $domain = substr($domain, 0, -1);
        }
        if (!preg_match('/\.[a-z][a-z0-9-]*$/D', $domain) || !$this->validLabels($domain)) {
            throw new \InvalidArgumentException(__('Enter a full domain name, such as example.com. Use its ASCII spelling for international domains.', 'fluent-smtp'));
        }
        return $domain;
    }

    private function validLabels($name)
    {
        return strlen($name) <= 253 && preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/iD', $name);
    }

    private function lookup($type, $name)
    {
        $cache = 'fluentmail_dns_' . md5($type . ':' . $name);
        $status = get_transient($cache);
        if ($status === false) {
            $records = $this->textRecords($name);
            $status = $records === false ? 'unavailable' : $this->classify($type, $records);
            if ($status !== 'unavailable') {
                set_transient($cache, $status, MINUTE_IN_SECONDS);
            }
        }
        return ['name' => $name, 'status' => $status];
    }

    private function textRecords($name)
    {
        // Providers commonly delegate DKIM selectors with a CNAME.
        $seen = [];
        while (count($seen) < 5 && !isset($seen[$name])) {
            $seen[$name] = true;
            $answer = $this->query($name);
            if ($answer === false) {
                return false;
            }
            $texts = [];
            $target = '';
            foreach ($answer as $row) {
                if (isset($row['host']) && strtolower(rtrim($row['host'], '.')) !== $name) {
                    continue;
                }
                if ($row['type'] === 'TXT') {
                    $texts[] = isset($row['entries']) ? implode('', $row['entries']) : $row['txt'];
                } elseif ($row['type'] === 'CNAME') {
                    $target = strtolower(rtrim($row['target'], '.'));
                }
            }
            if ($target === '') {
                return $texts;
            }
            if (strlen($target) > 253 || !preg_match('/^[a-z0-9_-]+(?:\.[a-z0-9_-]+)+$/D', $target)) {
                return false;
            }
            $name = $target;
        }
        return false;
    }

    protected function query($name)
    {
        // An absolute name avoids the host resolver's search suffix. Resolver warnings
        // are returned as an unavailable result and must not corrupt the AJAX response.
        return function_exists('dns_get_record') ? @dns_get_record($name . '.', DNS_TXT | DNS_CNAME) : false;
    }

    private function classify($type, array $records)
    {
        $matches = [];
        foreach ($records as $record) {
            if ($type === 'spf') {
                if (preg_match('/^v=spf1(?: |$)/iD', $record)) {
                    $matches[] = 'published';
                }
                continue;
            }
            if (preg_match('/(?:^|;)\s*v\s*=\s*([^;]+)/', $record, $version) && trim($version[1]) !== 'DKIM1') {
                continue;
            }
            if (preg_match('/(?:^|;)\s*p\s*=\s*([^;]*)/', $record, $key)) {
                $encoded = preg_replace('/[ \t\r\n]/', '', $key[1]);
                if ($encoded === '') {
                    $matches[] = 'revoked';
                } else {
                    $matches[] = base64_decode($encoded, true) === false ? 'incomplete' : 'published';
                }
            } elseif (preg_match('/^v=DKIM1(?:\s*;|$)/', $record)) {
                $matches[] = 'incomplete';
            }
        }
        return count($matches) > 1 ? 'multiple' : ($matches[0] ?? 'missing');
    }
}
