# FluentSMTP local test suite

This suite runs through WP-CLI against the local development WordPress install.
It does not use Docker, PHPUnit, `wp-env`, or a CI service.

```bash
bash tests/bin/run-all.sh
bash tests/bin/run-all.sh static
bash tests/bin/run-all.sh smoke
bash tests/bin/run-all.sh permissions
bash tests/bin/run-all.sh integration
```

The harness forces FluentSMTP's Simulator provider, blocks outbound HTTP, and
fails if the real `fsmpt_email_logs` row count changes during a run. Read
`tests/AGENT.md` before adding cases.

The admin application uses admin-AJAX. Its 42-route manifest (11 GET and 31
POST) lives at `tests/smoke/routes.manifest.php`; only the Outlook callback is a
REST route.

Current environment limitation: this install has the default `wp_` WordPress
table prefix, so runtime non-default-prefix coverage must be performed on a
separate suitable install.

## Phase 6 — shared-table isolation

Not applicable. FluentSMTP owns one plugin table,
`{$wpdb->prefix}fsmpt_email_logs`, and its rows have no site, tenant, account,
or other shared-table discriminator. Prefix safety is covered by the static SQL
gate and by resolving the table through `$wpdb->prefix`; the remaining runtime
non-default-prefix check is the environment limitation above.
