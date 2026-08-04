# FIX-PLAN.md — open bug and test-gap backlog

> Findings from test-writing runs. Production fixes are deliberately separate
> from this branch; parked `KNOWN-FAILURE` cases stay visible while tiers remain
> green.

| # | Item | Kind | Risk |
|---|---|---|---|

## Environment gaps

- The current development install uses the default `wp_` database prefix. The
  suite resolves `$wpdb->prefix` correctly and the static gate covers raw SQL,
  but a second run on a non-default-prefix install is still required to prove
  runtime prefix portability.
