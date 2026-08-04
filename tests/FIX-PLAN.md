# FIX-PLAN.md — open bug and test-gap backlog

> Findings from test-writing runs. Production fixes are deliberately separate
> from this branch; parked `KNOWN-FAILURE` cases stay visible while tiers remain
> green.

## KNOWN-FAILURE — weekly reporting fails with `ONLY_FULL_GROUP_BY`

- Production: `app/Services/Reporting.php:40,58`
- Symptom: `Reporting::getSendingStats()` returns no weekly rows and `$wpdb`
  reports that SELECT expression 2 is not in the GROUP BY clause.
- Mechanism: the SELECT projects a Monday date derived from `created_at`, but
  groups only by the separate `YEARWEEK(created_at, 1)` alias. MySQL 8 does not
  infer that the projected expression is functionally dependent on that alias.
- Owning tests: `weekly report aggregate is tracked under strict SQL modes` and
  the `GET sending_stats` smoke variations when they select weekly frequency.

## KNOWN-FAILURE — monthly reporting fails with `ONLY_FULL_GROUP_BY`

- Production: `app/Services/Reporting.php:45,58`
- Symptom: `Reporting::getSendingStats()` returns no monthly rows and `$wpdb`
  reports that SELECT expression 2 is not in the GROUP BY clause.
- Mechanism: the SELECT projects both a first-of-month date and a year-month
  value derived from `created_at`, but groups only by the year-month alias.
- Owning tests: `monthly report aggregate is tracked under strict SQL modes` and
  the `GET sending_stats` smoke variations when they select monthly frequency.

## KNOWN-FAILURE — heatmap ordering fails with `ONLY_FULL_GROUP_BY`

- Production: `app/Http/Controllers/DashboardController.php:60-62,79-81`
- Symptom: both all-time and bounded day/time heatmap queries return no rows and
  `$wpdb` reports that ORDER BY expression 1 is not in the GROUP BY clause.
- Mechanism: the query groups by `DAYNAME(created_at)` and `HOUR(created_at)` but
  orders through `FIELD(DAYNAME(created_at), ...)`; MySQL 8 rejects the FIELD
  expression under `ONLY_FULL_GROUP_BY`.
- Owning tests: strict-mode `GET /day-time-stats` smoke variations, the
  site-local heatmap case, the clamped-lookback case, and `day-time heatmap
  aggregate is tracked under strict SQL modes`.
