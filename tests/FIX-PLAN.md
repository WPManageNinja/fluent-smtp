# FIX-PLAN.md — bug and test-gap record

> Findings from test-writing runs. There are currently no open
> `KNOWN-FAILURE` items; resolved entries remain below as regression history.
>
> Everything below shipped in 2.3.0. Entries are kept rather than deleted so
> that a future strict-SQL regression is recognised as a repeat rather than
> investigated from scratch. Add new findings at the top.

## RESOLVED — PepiPost `getBody()` read a property PHPMailer does not have

- Production: `app/Services/Mailer/Providers/PepiPost/Handler.php` (`getBody()`)
- Symptom: every PepiPost send raised "Undefined property:
  PHPMailer\PHPMailer\PHPMailer::$contentType" (a warning on PHP 8, a notice
  on 7.4), and a plain-text email went out as raw text in an `html` part, so
  its line breaks collapsed.
- Mechanism: `getBody()` tested `$this->phpMailer->contentType`; PHPMailer's
  property is `ContentType`. The branch it guarded (from `3f8e15fb`,
  2024-07-02, "Added Plain Text Version") would have sent `AltBody` as an
  `amp` part.
- Resolution: the v5 API accepts only `html` and `amp` content parts (the
  official SDK's `TypeEnum`), so there is no plain-text part to send and
  AltBody is not AMP; the branch is gone. A plain-text email is sent as HTML,
  escaped, with its line breaks kept.
- Owning tests: `PepiPost: ...` cases in `tests/integration/attachments.php`
  (no warning any more) and `tests/integration/connection-behavior.php`
  (the content parts).

## RESOLVED — bracketed provider message IDs were erased by log sanitization

- Production: `app/Services/Mailer/BaseHandler.php` (`recursiveSanitize()`)
- Symptom: a successful Brevo response such as
  `<202609031856.15147286983@smtp-relay.mailin.fr>` was stored with an empty
  `messageId` in the FluentSMTP email log. Mailgun-style `<...@domain>` ids
  were erased the same way.
- Mechanism: `BaseHandler::recursiveSanitize()` sends non-email response values
  through `sanitize_text_field()`, which treats the angle-bracketed ID as HTML
  and strips it completely.
- Owning tests: `tests/integration/brevo-message-id.php` verifies a Brevo send
  logs the ID without brackets, an unbracketed ID is unchanged, any provider's
  `<local@domain>` value keeps its content, and other angle-bracket values
  (`Name <email>`, HTML, two IDs in one string) are sanitized exactly as before.
- Resolution: the sanitizer removes only an enclosing `<...>` pair around a
  single `local@domain` token (no whitespace, no inner brackets) and runs the
  inner value through `sanitize_text_field()`. It skips `sanitize_email()`,
  because a message ID is not a mailbox and valid IDs such as `<x@a.b>` would
  be mangled. This matches the form Brevo's webhooks
  use (`message-id` without brackets).
- Tracking: AuthLab task 22862 —
  https://lounge.authlab.io/projects#/boards/9/tasks/22862-Fluent-SMTP-with-Brevo

## RESOLVED — weekly reporting failed with `ONLY_FULL_GROUP_BY`

- Production: `app/Services/Reporting.php:40,58`
- Symptom: `Reporting::getSendingStats()` returns no weekly rows and `$wpdb`
  reports that SELECT expression 2 is not in the GROUP BY clause.
- Mechanism: the SELECT projects a Monday date derived from `created_at`, but
  groups only by the separate `YEARWEEK(created_at, 1)` alias. MySQL 8 does not
  infer that the projected expression is functionally dependent on that alias.
- Owning tests: `weekly report aggregate is tracked under strict SQL modes` and
  the `GET sending_stats` smoke variations when they select weekly frequency.
- Resolution: `e7a2b2f9` aggregates the deterministic Monday expression without
  changing the bucket date. Both normal and strict-known-failure suites run the
  real Monday-bucket assertion successfully on both installs.

## RESOLVED — monthly reporting failed with `ONLY_FULL_GROUP_BY`

- Production: `app/Services/Reporting.php:45,58`
- Symptom: `Reporting::getSendingStats()` returns no monthly rows and `$wpdb`
  reports that SELECT expression 2 is not in the GROUP BY clause.
- Mechanism: the SELECT projects both a first-of-month date and a year-month
  value derived from `created_at`, but groups only by the year-month alias.
- Owning tests: `monthly report aggregate is tracked under strict SQL modes` and
  the `GET sending_stats` smoke variations when they select monthly frequency.
- Resolution: `6faeb96f` aggregates the deterministic first-of-month
  expression without changing the bucket date. Both normal and
  strict-known-failure suites run the real month-bucket assertion successfully
  on both installs.

## RESOLVED — heatmap ordering failed with `ONLY_FULL_GROUP_BY`

- Production: `app/Http/Controllers/DashboardController.php:60-62,79-81`
- Symptom: both all-time and bounded day/time heatmap queries return no rows and
  `$wpdb` reports that ORDER BY expression 1 is not in the GROUP BY clause.
- Mechanism: the query groups by `DAYNAME(created_at)` and `HOUR(created_at)` but
  orders through `FIELD(DAYNAME(created_at), ...)`; MySQL 8 rejects the FIELD
  expression under `ONLY_FULL_GROUP_BY`.
- Owning tests: strict-mode `GET /day-time-stats` smoke variations, the
  site-local heatmap case, the clamped-lookback case, and `day-time heatmap
  aggregate is tracked under strict SQL modes`.
- Resolution: `9bc02fb4` orders through the aggregate weekday value in both
  query branches. Both normal and strict-known-failure suites run the real
  heatmap assertions successfully on both installs.
