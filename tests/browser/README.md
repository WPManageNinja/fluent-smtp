# FluentSMTP browser smoke

Build production assets before every run:

```bash
pnpm run prod
```

`admin-screen-smoke.mjs` exports the complete eight-route manifest and a runner
for the repository's browser-control surface. Give it an authenticated tab on a
local WordPress install, a base URL, and a fail-closed outbound HTTP seam. The
fixture site needs one non-secret SMTP connection so the connections screen does
not redirect to the first-run wizard.

The runner requires one visible `.fluent-mail-app` root and two route-specific
content markers on every screen. An HTTP 200 with an empty Vue root therefore
fails the phase.

The regular static tier also compares this manifest to
`resources/admin/routes.js`, so adding or removing a Vue route cannot silently
leave the browser smoke incomplete.

## Authenticated E2E flows

`admin-e2e.mjs` exports three DOM-grounded flows for a browser-client Tab:

- dashboard `Last week` range selection and report refresh;
- email-log search with a unique no-match term;
- test-email submission through FluentSMTP's Simulator provider.

Before opening the admin page, copy
`fixtures/fsmtp-e2e-safety.php` to the target site's `wp-content/mu-plugins`
directory. The runner refuses every flow unless the rendered safety marker
proves that Simulator resolution, the log fuse, the outbound-HTTP fuse, and the
settings-write fuse are active. Record the real FluentSMTP log count and a hash
of `fluentmail-settings` before and after the run, then remove the copied
MU-plugin. If the stored connection list is empty, the fixture supplies a
non-secret, request-only virtual connection so the configured dashboard and
email-test branches can run without a database write. The source fixture
remains in this repository; the installed copy must never be left active after
testing.

With an authenticated browser-client `tab`, run:

```js
const { runAdminE2E } = await import('./tests/browser/admin-e2e.mjs');
const results = await runAdminE2E(tab, 'https://local-wordpress.test');
```

All three results must have `passed: true`. The browser suite is deliberately
manual because it reuses the developer's authenticated local admin session;
the regular local runner never stores or synthesizes login credentials.
