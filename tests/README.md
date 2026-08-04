# FluentSMTP local test suite

This suite runs through WP-CLI against the local development WordPress install.
It does not use Docker, PHPUnit, `wp-env`, or a CI service.

```bash
bash tests/bin/run-all.sh
bash tests/bin/run-all.sh static
bash tests/bin/run-all.sh smoke
bash tests/bin/run-all.sh permissions
bash tests/bin/run-all.sh integration
bash tests/bin/run-coverage.sh
```

The harness forces FluentSMTP's Simulator provider, blocks outbound HTTP, and
fails if the real `fsmpt_email_logs` row count changes during a run. Read
`tests/AGENT.md` before adding cases.

The integration tier also launches fresh child processes for the real
`wp fluent-smtp test`, `health`, `stats`, and `prune-logs` commands. The child
bootstrap independently proves Simulator resolution and fuses logging, HTTP,
cron scheduling, and health-report option writes.

The admin application uses admin-AJAX. Its 42-route manifest (11 GET and 31
POST) lives at `tests/smoke/routes.manifest.php`; only the Outlook callback is a
REST route.

Runtime prefix portability was verified on 2026-08-04 by running the full suite
against an isolated WordPress install with the `wptest_` table prefix. Set
`FSMTP_WP_ROOT` to exercise the suite against a different local install.

The optional coverage gate uses PCOV to merge smoke, permission, and integration
line maps. It fails until every production PHP file over 100 lines with zero
hits has a current decision in `tests/coverage/zero-coverage-triage.php`.

## Phase 6 — shared-table isolation

Not applicable. FluentSMTP owns one plugin table,
`{$wpdb->prefix}fsmpt_email_logs`, and its rows have no site, tenant, account,
or other shared-table discriminator. Prefix safety is covered by the static SQL
gate, by resolving the table through `$wpdb->prefix`, and by the isolated
`wptest_` runtime suite described above.
