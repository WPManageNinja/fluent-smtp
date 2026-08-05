#!/usr/bin/env bash
# Collect PCOV data from all runtime tiers and enforce the Phase 13 zero-file triage.

set -uo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
. "$PLUGIN_DIR/tests/bin/lib/resolve-wp-root.sh"
WP_ROOT="$(fsmtp_resolve_wp_root "$PLUGIN_DIR")" || exit 2
PHP_BIN="${FSMTP_PHP_BIN:-$(command -v php)}"
WP_BIN="$(command -v wp)"
COVERAGE_DIR="$(mktemp -d "${TMPDIR:-/tmp}/fsmtp-coverage.XXXXXX")"
FAILED=0

cleanup() {
  if [[ "$COVERAGE_DIR" == "${TMPDIR:-/tmp}/fsmtp-coverage."* && -d "$COVERAGE_DIR" ]]; then
    rm -rf -- "$COVERAGE_DIR"
  fi
}
trap cleanup EXIT

if [ -z "$PHP_BIN" ] || [ -z "$WP_BIN" ]; then
  echo "coverage: php and wp must both be available on PATH" >&2
  exit 2
fi

run_tier() {
  local name="$1"
  local runner="$2"
  echo "coverage: running $name"
  if ! FSMTP_COVERAGE_FILE="$COVERAGE_DIR/$name.json" \
      "$PHP_BIN" -d pcov.enabled=1 -d pcov.directory="$PLUGIN_DIR" \
      -d auto_prepend_file="$PLUGIN_DIR/tests/bin/coverage-bootstrap.php" \
      "$WP_BIN" \
      --path="$WP_ROOT" eval-file "$PLUGIN_DIR/$runner"; then
    FAILED=1
  fi
}

run_tier smoke tests/bin/run-smoke.php
run_tier permissions tests/bin/run-permissions.php
run_tier integration tests/bin/run-integration.php

if [ "$FAILED" -ne 0 ]; then
  echo "coverage: a runtime tier failed; triage was not evaluated" >&2
  exit 1
fi

"$PHP_BIN" "$PLUGIN_DIR/tests/bin/coverage-triage.php" "$COVERAGE_DIR"
