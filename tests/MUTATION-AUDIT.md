# Phase 19 mutation audit

Date: 2026-08-04

Baseline: `tests/suite-round-3` after phase 20 (`a25b8530`)

Command for every mutant: `bash tests/bin/run-all.sh`
Full-suite shape: smoke 33, permissions 62, integration 48, zero skips

Each mutation was applied to one production behavior at a time, exercised
against the complete suite, and then reversed before the next mutation. The CLI
pruning path was protected by a subprocess-only SQL fuse before removing the
retention predicate; isolated fixture tables still received the actual mutant
query. No production log row was changed.

## Result

- Mutants: 7
- Killed: 4
- Survived: 3
- Kill rate: 57.1%

The percentage is context, not a target. The survivor list below is the phase
deliverable; no equivalent mutants were added to inflate the result.

## Survivor list

### S1 — report date-range predicate deletion

- Production: `app/Services/Reporting.php:57`
- Mutation: replaced `created_at BETWEEN <from> AND <to>` with a constant true
  predicate while retaining both prepared parameters.
- Full-suite result: survived; 33/33 smoke, 62/62 permissions, 48/48
  integration, zero skips, protected log count unchanged.
- Gap: report tests assert values for requested in-range buckets, but no fixture
  outside the requested range is asserted absent from the returned chart.
- Highest-value follow-up: add an isolated report fixture immediately before
  and after the requested range and assert that neither contributes a bucket or
  count.

### S2 — Outlook expiry comparison reversal

- Production: `app/Services/Mailer/Providers/Outlook/Handler.php:215`
- Mutation: reversed the refresh threshold comparison from `< time()` to
  `> time()`.
- Full-suite result: survived; 33/33 smoke, 62/62 permissions, 48/48
  integration, zero skips, protected log count unchanged.
- Gap: current Outlook renewal coverage reaches the token request through paths
  that force a refresh; it does not distinguish a still-valid cached token from
  an expired token when `force` is false.
- Highest-value follow-up: invoke `getAccessToken()` through reflection with
  future and expired stamps, assert zero versus one intercepted token request,
  and keep settings and scheduling fused.

### S3 — replacement-index confirmation deletion

- Production: `database/migrations/EmailLogs.php:78`
- Mutation: removed the `created_at_status` existence check before dropping the
  legacy `status` index.
- Full-suite result: survived; 33/33 smoke, 62/62 permissions, 48/48
  integration, zero skips, protected log count unchanged.
- Gap: migration tests cover successful convergence and idempotence, but do not
  force the replacement `ADD INDEX` to fail and prove the legacy status index
  remains in place.
- Highest-value follow-up: use a real incompatible pre-existing index state or
  a fail-closed DDL query fixture, then assert that the legacy index is not
  dropped after replacement creation fails.

## Killed mutants

| ID | Production mutation | Full-suite killer evidence |
|---|---|---|
| K1 | Removed the `created_at` predicate and its argument from `Logger::deleteLogsOlderThan()` (`app/Models/Logger.php:480`) | Integration failed on deleted count 5→8, retained rows 3→0, batch queries 3→5, the no-match row being deleted, and the site-local fixture deleting 2 instead of 1. |
| K2 | Removed the `status` discriminator from both `Logger::getTotalCountStat()` branches (`app/Models/Logger.php:509,520`) | Dashboard Today counters changed from `[1,1]` to `[2,2]`; the open-ended failed count changed from 1 to 2. |
| K3 | Reversed Gmail's expiry comparison (`app/Services/Mailer/Providers/Gmail/Handler.php:274`) | The rejected-grant case received a client instead of `WP_Error` and failed before the expected provider detail could be read. |
| K4 | Removed the `created_at_status` existence guard before `ADD INDEX` (`database/migrations/EmailLogs.php:70`) | The idempotence case observed 2 `ALTER TABLE` statements instead of 0. |

## Safety-fuse proof-of-catch

The new CLI pruning case runs `prune-logs --days=1 --yes`, asserts the child
process reports that the production-log `DELETE` was fused, expects zero
deletions, and compares protected row counts. Changing only the child fixture's
fuse marker made the owning integration case fail with:

> child process did not prove the production-log write fuse

The marker was restored and the filtered CLI tier returned 4/4 green before
the mutation audit began.
