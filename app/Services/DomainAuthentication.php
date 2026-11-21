<?php

namespace FluentMail\App\Services;

class DomainAuthentication
{
    public function check($envelope, $signing, $selector)
    {
        $envelope = $this->domain($envelope);
        if (!is_string($selector) || ($selector !== '' && !$this->validLabels($selector))) {
            throw new \InvalidArgumentException(__('Enter the DKIM selector supplied by your email provider.', 'fluent-smtp'));
        }
        $keyName = $selector === '' ? '' : strtolower($selector) . '._domainkey.' . $this->domain($signing);
        if (strlen($keyName) > 253) {
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
        $owners = [];
        while (count($seen) < 5 && !isset($seen[$name])) {
            $seen[$name] = true;
            if (!isset($owners[$name])) {
                $answer = $this->query($name);
                if ($answer === false) {
                    return false;
                }
                // A recursive reply may include the alias and its target in either order.
                $owners = [];
                foreach ($answer as $row) {
                    $owner = strtolower(rtrim($row['host'] ?? $name, '.'));
                    $owners[$owner][] = $row;
                }
            }
            $texts = [];
            $target = '';
            foreach ($owners[$name] ?? [] as $row) {
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
            $status = $this->dkimStatus($record);
            if ($status !== null) {
                $matches[] = $status;
            }
        }
        return count($matches) > 1 ? 'multiple' : ($matches[0] ?? 'missing');
    }

    private function dkimStatus($record)
    {
        // Unfold header-style whitespace before checking the DKIM tag grammar.
        $record = trim(preg_replace('/\r\n(?=[ \t])/', '', $record), " \t");
        if (preg_match('/^v[ \t]*=[ \t]*([^;]*)/', $record, $version)) {
            $value = rtrim($version[1], " \t");
            if ($value !== '' && $value !== 'DKIM1') {
                return null;
            }
        }
        // Ignore unrelated TXT records. The version tag is optional in a DKIM key.
        if (!preg_match('/(?:^|;)\s*(?:p\s*=|v\s*=\s*DKIM1\s*(?:;|$))/', $record)) {
            return null;
        }
        if (preg_match('/[^\x20-\x7e\t]/', $record)) {
            return 'incomplete';
        }
        $parts = explode(';', $record);
        if (end($parts) === '') {
            array_pop($parts);
        }
        $tags = [];
        foreach ($parts as $index => $part) {
            if (!preg_match('/^([A-Za-z][A-Za-z0-9_]*)\s*=\s*(.*)$/sD', trim($part), $pair)) {
                return 'incomplete';
            }
            $tag = $pair[1];
            if (isset($tags[$tag]) || ($tag === 'v' && $index !== 0)) {
                return 'incomplete';
            }
            $tags[$tag] = trim($pair[2]);
        }
        if (($tags['v'] ?? 'DKIM1') !== 'DKIM1' || !isset($tags['p'])) {
            return 'incomplete';
        }
        $key = preg_replace('/[ \t\r\n]/', '', $tags['p']);
        if ($key === '') {
            return 'revoked';
        }
        return base64_decode($key, true) === false ? 'incomplete' : 'published';
    }
}
