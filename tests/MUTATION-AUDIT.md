# Phase 19 mutation audit — HEAD rerun

Date: 2026-08-04

Baseline: `fix/strict-sql-reports` after the three survivor follow-ups
(`17e36e90`)

Command for every mutant: `bash tests/bin/run-all.sh`

Full-suite shape: smoke 33, permissions 62, integration 58, zero skips.

> The counts throughout this document describe the suite as it stood on the
> audit date and are left unchanged, because a mutation result only means
> anything alongside the suite that produced it. As of the 2.3.0 release the
> suite runs 33/62/60. Widen the mutant set and re-run rather than editing
> these figures.
Every mutation was applied alone, exercised against the complete suite, and
restored before the next mutation. The worktree was clean after every batch.
The CLI pruning path remained protected by its subprocess-only production-log
SQL fuse; pruning mutants executed only against isolated fixture tables.

## Result

- Mutants: 20
- Clause-deletion mutants: 18
- Comparison-reversal mutants: 2
- Killed: 16 at the time of the run, 18 after the follow-ups below
- Survived: 4 at the time of the run, 0 outstanding
- Kill rate: 80.0% at the time of the run, 100% of the mutants in this set

All four survivors have since been resolved: S1 and S4 by the guards described
below, S2 and S3 by deleting redundant production code rather than asserting
it. A 100% figure for a 20-mutant set is not a claim about the suite overall -
it means this particular set is exhausted, and the next audit should widen the
set rather than re-run it.

The percentage is context, not a target. Every survivor below completed the
full 33/62/58 suite with zero failures and zero skips; the survivor list is the
deliverable.

## Survivor list

### S1 — bounded heatmap `GROUP BY` deletion

- Production: `app/Http/Controllers/DashboardController.php:57-59`
- Mutation: removed the bounded query's `GROUP BY DAYNAME(created_at),
  HOUR(created_at)` clause.
- Full-suite result: survived; 33/33 smoke, 62/62 permissions, 58/58
  integration, zero skips, protected log count unchanged.
- Gap: bounded heatmap coverage exercises route health and lookback clamping,
  but does not run two or more in-range weekday/hour groups from an isolated
  table. The strict aggregate fixture uses only the all-time branch.
- Highest-value follow-up: add an isolated strict-SQL bounded heatmap fixture
  with rows in distinct weekday/hour groups and assert each returned cell.
- **Killed.** `tests/integration/aggregate-grouping.php` requests
  `last_day = 30` against an isolated table holding two rows in one
  weekday/hour group, one in another, and one outside the lookback. Re-applying
  the mutation fails two assertions: `bounded heatmap grouped bucket` expected
  2, actual 0; `bounded heatmap second bucket` expected 1, actual 3 - every row
  collapsing into a single cell.

### S2 — bounded heatmap `ORDER BY` deletion

- Production: `app/Http/Controllers/DashboardController.php:60-62`
- Mutation: removed the bounded query's weekday/hour ordering.
- Full-suite result: survived; 33/33 smoke, 62/62 permissions, 58/58
  integration, zero skips, protected log count unchanged.
- Gap: the controller copies rows into a prebuilt weekday/hour matrix, so row
  order is not observable in the response and no current behavior requires the
  SQL ordering.
- Highest-value follow-up: decide whether database row order is an intended
  internal contract. If not, the clause is redundant production code rather
  than a missing behavioral assertion.
- **Resolved.** Row order is not a contract. The clause was removed rather than
  asserted, because there was no behaviour to assert - see the note below.

### S3 — all-time heatmap `ORDER BY` deletion

- Production: `app/Http/Controllers/DashboardController.php:79-81`
- Mutation: removed the all-time query's weekday/hour ordering.
- Full-suite result: survived; 33/33 smoke, 62/62 permissions, 58/58
  integration, zero skips, protected log count unchanged.
- Gap: as in S2, the response matrix has deterministic key order regardless of
  the database row order.
- Highest-value follow-up: resolve the same production-contract question as S2
  before adding any SQL-shape assertion.
- **Resolved.** Same decision as S2.

### Resolution of S2 and S3

Both survivors were redundant production code, not missing assertions.

`getDayTimeStats()` seeds a fixed 7x24 matrix (`Mon`..`Sun`, `0:00`..`23:00`)
with zeros and then assigns each returned row by key. Assigning to an existing
PHP array key does not move it, so the response key order comes entirely from
the seeded matrix and the database row order cannot reach the caller.

Verified before removal against an isolated table holding five populated
weekday/hour cells: the ordered and unordered queries produced byte-identical
payloads, including key order.

Both `ORDER BY` clauses were therefore deleted. This also removes the
`MIN(WEEKDAY(created_at))` aggregate that had been added purely to keep the
ordering `ONLY_FULL_GROUP_BY`-safe; the remaining `SELECT` projects exactly the
grouped expressions, so the query is still strict-SQL compliant.

Adding a test here would have asserted an internal SQL shape that no behaviour
depends on, which is the kind of incidental-detail assertion this audit exists
to avoid.

### S4 — subject-count status predicate deletion

- Production: `app/Models/Logger.php:535`
- Mutation: replaced the status discriminator with a constant-true prepared
  predicate while retaining all parameters.
- Full-suite result: survived; 33/33 smoke, 62/62 permissions, 58/58
  integration, zero skips, protected log count unchanged.
- Gap: the strict aggregate fixture asks for sent subjects during January, but
  its only failed subject is in March, so the date range masks deletion of the
  status predicate.
- Highest-value follow-up: place distinct sent and failed subjects inside the
  same requested range and assert status-specific distinct-subject counts.
- **Killed.** `tests/integration/aggregate-grouping.php` puts a distinct sent
  subject and a distinct failed subject inside the same January range, plus a
  repeat subject (proving DISTINCT) and an out-of-range one. Re-applying the
  mutation fails both assertions: sent and failed distinct-subject counts each
  expected 1, actual 2. This aggregate feeds the daily digest email, where a
  dropped predicate would report identical sent and failed subject counts.

## Killed mutants

| ID | Production mutation | Full-suite killer evidence |
|---|---|---|
| M01 | Removed the report date-range predicate (`app/Services/Reporting.php:57`). | The new boundary case exposed both outside buckets and a total count of 3 instead of 1. |
| M02 | Reversed Outlook's expiry comparison (`app/Services/Mailer/Providers/Outlook/Handler.php:215`). | The future-token case made one request instead of zero and returned a refreshed token; the expired case returned the stale cached token. |
| M03 | Removed replacement-index confirmation before the legacy drop (`database/migrations/EmailLogs.php:78`). | The failed-ADD fixture observed one forbidden drop and the legacy `status` index disappeared. |
| M04 | Removed the pruning retention predicate (`app/Models/Logger.php:480`). | Six assertions failed across bounded pruning, no-match pruning, and the site-local cutoff; newer rows were deleted. |
| M05 | Removed the status discriminator from both total-count branches (`app/Models/Logger.php:509,520`). | Dashboard Today counters and the strict open-ended failed count included the wrong status. |
| M06 | Reversed Gmail's expiry comparison (`app/Services/Mailer/Providers/Gmail/Handler.php:274`). | The rejected-grant handler case returned a client instead of the expected provider error. |
| M07 | Removed the replacement-index existence guard before `ADD INDEX` (`database/migrations/EmailLogs.php:70`). | The idempotence case observed two `ALTER TABLE` statements instead of zero. |
| M08 | Removed the report `GROUP BY` clause (`app/Services/Reporting.php:58`). | Twelve integration assertions failed across daily, weekly, monthly, strict-SQL, timezone, and whitelist behavior. |
| M09 | Removed the report `ORDER BY` clause (`app/Services/Reporting.php:59`). | The whitelist-fallback case rejected the missing safe daily ordering. |
| M10 | Removed the daily report date projection (`app/Services/Reporting.php:48`). | All three daily smoke variations reported an unknown `date` grouping column and seven integration assertions failed. |
| M11 | Removed the daily report count projection (`app/Services/Reporting.php:48`). | Three smoke variations and four integration behaviors raised missing-count diagnostics; five value assertions also failed. |
| M12 | Removed the bounded heatmap range predicate (`app/Http/Controllers/DashboardController.php:56`). | The lookback validation case no longer observed the clamped `INTERVAL 365 DAY` predicate. |
| M15 | Removed the all-time heatmap `GROUP BY` clause (`app/Http/Controllers/DashboardController.php:76-78`). | Four timezone and strict-SQL assertions failed, including an `ONLY_FULL_GROUP_BY` database error. |
| M17 | Removed the heatmap's upper 365-day clamp (`app/Http/Controllers/DashboardController.php:41`). | Both lookback-clamp assertions failed. |
| M18 | Removed the pruning `LIMIT` clause (`app/Models/Logger.php:480`). | Five batch-count and fixed-batch SQL assertions failed. |
| M19 | Removed pruning loop continuation (`app/Models/Logger.php:493`). | Seven assertions showed only the first batch was deleted and rows remained. |

## Closed survivors from the previous audit

The previous 48-case audit's three survivors are M01-M03 above. Each is now
killed by its stated follow-up, and each follow-up has an isolated
proof-of-catch commit on this branch.
