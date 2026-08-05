# FIX-PLAN.md — bug and test-gap record

> Findings from test-writing runs. There are currently no open
> `KNOWN-FAILURE` items; resolved entries remain below as regression history.

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
