#!/usr/bin/env bash
# Phase 20: rerun the complete suite with non-default clocks and strict MySQL.

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

for offset in 6 -8; do
  printf '\nFluentSMTP environment axis: gmt_offset=%s\n' "$offset"
  FSMTP_TEST_GMT_OFFSET="$offset" bash "$PLUGIN_DIR/tests/bin/run-all.sh"
done

printf '\nFluentSMTP environment axis: ONLY_FULL_GROUP_BY + STRICT_TRANS_TABLES\n'
FSMTP_TEST_STRICT_SQL=1 bash "$PLUGIN_DIR/tests/bin/run-all.sh"
