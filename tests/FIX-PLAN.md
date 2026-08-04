# FIX-PLAN.md — open bug and test-gap backlog

> Findings from test-writing runs. Production fixes are deliberately separate
> from this branch; parked `KNOWN-FAILURE` cases stay visible while tiers remain
> green.

| # | Item | Kind | Risk |
|---|---|---|---|
| 1 | `app/Http/Controllers/TelegramController.php:116,141`, `SlackController.php:58,85`, `DiscordController.php:50,78`, and `PushoverController.php:41,69` — each channel's `sendTestMessage()` and `disconnect()` omits `$this->verify()`. A logged-in subscriber with a valid FluentSMTP nonce reaches the handler; disconnect changes notification configuration and send-test can call a third party. Parked by the eight dynamic `KNOWN-FAILURE` permission cases. | Authorization | Critical |

## Environment gaps

- The current development install uses the default `wp_` database prefix. The
  suite resolves `$wpdb->prefix` correctly and the static gate covers raw SQL,
  but a second run on a non-default-prefix install is still required to prove
  runtime prefix portability.
