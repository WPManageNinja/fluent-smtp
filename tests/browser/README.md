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
