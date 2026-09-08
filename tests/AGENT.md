# AGENT.md — rules for writing FluentSMTP tests

Read this completely before writing a test. This suite runs locally through
WP-CLI against the real development WordPress install. Do not introduce
PHPUnit, Docker, `wp-env`, a CI service, real loopback HTTP, real cron, or
`sleep()`.

## Ground truth

Run commands from the FluentSMTP plugin root:

```bash
bash tests/bin/run-all.sh
bash tests/bin/run-all.sh static
bash tests/bin/run-all.sh smoke
bash tests/bin/run-all.sh permissions
bash tests/bin/run-all.sh integration
FSMTP_STRICT_KNOWN_FAILURES=1 bash tests/bin/run-all.sh permissions

wp eval-file tests/bin/run-smoke.php
wp eval-file tests/bin/run-smoke.php -- filter=logs
```

Paths are discovered from the runner location and WordPress itself. Never
hardcode a WordPress root, site URL, or WordPress table prefix. The plugin table
is `{$wpdb->prefix}fsmpt_email_logs`.

Every runner must exit non-zero on failure. Every WP-CLI runner must finish via
`FsmtpTest::finish()` so protected log counts are compared before exit.

## Proof-of-catch

Never keep a test you have not watched fail.

For every test:

1. Break the covered behavior in a production file or its test fixture.
2. Run the narrow owning tier and observe red.
3. Confirm the message describes the intended defect, not an incidental error.
4. Restore the production file.
5. Run the tier again and observe green.
6. Record the red output in the phase commit message.

If the test cannot be made to fail, delete it.

## Absolute prohibitions

- Never mock `$wpdb`, the query builder, or models. Use the real local database.
- Mock only outbound third parties: SMTP/provider APIs and OAuth token endpoints.
- Never send real email. `FLUENTMAIL_SIMULATE_EMAILS` must be truthy and every
  case must assert that `fluentMailGetProvider()` resolved the Simulator handler.
- Never make real HTTP or loopback calls. Install a fail-closed interceptor.
- Never run cron and never sleep.
- Never assert on pre-existing site data. Read-only smoke may use an existing log
  ID and skip if none exists; behavioral assertions create isolated fixtures.
- Never alter or delete a real `fsmpt_email_logs` row. Fixture IDs are recorded
  and deleted in `finally`; the runner compares the protected row count before
  and after every run.
- Never fix production code in a test phase. Park a confirmed bug as
  `KNOWN-FAILURE` and record it in `tests/FIX-PLAN.md` with file:line, symptom,
  mechanism, and owning test.
- Never weaken a test to make it pass.

## AJAX rules

All 41 admin routes are declared in `app/Http/routes.php`. The SPA calls them
through WordPress admin-AJAX, not REST.

- Keep every route in `tests/smoke/routes.manifest.php`.
- Preserve every GET query variation the Vue app sends. Variations matter more
  than the bare route count.
- Derive action names only by calling
  `Application::getAjaxAction($route, $method, $isAdmin)`. Never reconstruct an
  action string in a test.
- GET smoke is read-only (11 routes). Mutating happy paths for the 30 POST
  routes belong in integration fixtures.
- Permission smoke must prove every POST is unavailable anonymously and rejects
  a logged-in low-privilege user. Its option-write and HTTP fuses must stay on.

The sole REST route, `fluent-smtp/outlook_callback`, is outside the 41-route
admin-AJAX manifest and should be tested separately if its behavior changes.

## Test shape

- Name one behavior per case.
- Arrange, act, assert in that order.
- Use `FsmtpTest::uniq()` for collision-free fixture values.
- Assert behavior and returned values, not exact SQL or log text.
- Clean up in `finally`. Cleanup must be exact and idempotent.
- Promote FluentSMTP notices, warnings, and deprecations to failures.
- Never print raw settings or provider request data; failure output must redact
  credentials and URL query strings.
- Clear only FluentSMTP-owned caches before each suite.

The shared harness exposes:

```php
FsmtpTest::boot();
FsmtpTest::case('behavior name', function () {});
FsmtpTest::ajax('GET', '/logs', ['page' => 1]);
FsmtpTest::assertAjaxHealthy($result, 'label');
FsmtpTest::assert($condition, 'detail');
FsmtpTest::assertSame($expected, $actual, 'label');
FsmtpTest::assertMailSimulationActive();
FsmtpTest::interceptHttp($responder);
FsmtpTest::uniq('fixture');
FsmtpTest::finish('SUITE');
```

Add a reusable primitive to `tests/lib/harness.php` with a comment explaining
the safety or fidelity reason; do not hide one-off mock behavior inside tests.

## Findings and reporting

Production findings go in `tests/FIX-PLAN.md`. Proof-of-catch output, skips,
runtime, and case counts go in the phase commit message:

```text
Cases added: <count>   Suite runtime: <seconds>

Proof-of-catch:
  <case> — broke <behavior>, observed: <red failure>

Deliberately skipped:
  <case> — <reason>

Needs a human decision:
  <surprise or environment gap>
```

Never commit a red tier. If a case is flaky, skip it with a documented reason;
do not report it green. This install currently uses the default `wp_` prefix, so
prefix portability is an explicit environment gap until the suite is also run
on a non-default-prefix install.
