#!/usr/bin/env bash
# FluentSMTP local test runner. No Docker, CI service, wp-env, or PHPUnit.

set -uo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
. "$PLUGIN_DIR/tests/bin/lib/resolve-wp-root.sh"
WP_ROOT="$(fsmtp_resolve_wp_root "$PLUGIN_DIR")" || exit 2
SUITE="${1:-all}"

RED=$'\033[31m'; GREEN=$'\033[32m'; YELLOW=$'\033[33m'; BOLD=$'\033[1m'; OFF=$'\033[0m'
FAILED=0
declare -a RESULTS=()

hr() { printf '%s\n' "------------------------------------------------------------------------"; }

record() {
  if [ "$2" -eq 0 ]; then
    RESULTS+=("${GREEN}PASS${OFF}  $1")
  else
    RESULTS+=("${RED}FAIL${OFF}  $1")
    FAILED=1
  fi
}

read_log_count() {
  ( cd "$WP_ROOT" && wp eval 'global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fsmpt_email_logs");' 2>/dev/null )
}

RUN_LOG_COUNT_BEFORE="$(read_log_count)"
if ! [[ "$RUN_LOG_COUNT_BEFORE" =~ ^[0-9]+$ ]]; then
  echo "${RED}Could not record the protected FluentSMTP log count before the run.${OFF}"
  exit 2
fi

run_static() {
  echo "${BOLD}S0 — static gates${OFF}"; hr

  local syntax_errors
  syntax_errors=$(find "$PLUGIN_DIR/app" "$PLUGIN_DIR/includes" "$PLUGIN_DIR/database" -name '*.php' \
    -not -path '*/vendor/*' -not -path '*/libs/*' \
    -exec php -l {} \; 2>&1 | grep -E '^(Parse|Fatal) error' || true)
  if [ -n "$syntax_errors" ]; then
    echo "$syntax_errors"; record "php -l" 1
  else
    echo "php -l: clean"; record "php -l" 0
  fi

  php "$PLUGIN_DIR/tests/lint/raw-sql-prefix.php"
  record "lint: raw-sql-prefix" $?

  if [ -f "$PLUGIN_DIR/tests/lint/route-coverage.php" ]; then
    php "$PLUGIN_DIR/tests/lint/route-coverage.php"
    record "lint: route-coverage" $?
  fi

  if [ -f "$PLUGIN_DIR/tests/lint/browser-route-coverage.php" ]; then
    php "$PLUGIN_DIR/tests/lint/browser-route-coverage.php"
    record "lint: browser-route-coverage" $?
  fi

  php "$PLUGIN_DIR/tests/lint/raw-sql-prefix.php" "$PLUGIN_DIR/tests/lint/fixtures" >/dev/null 2>&1
  if [ $? -eq 1 ]; then
    echo "lint self-test: raw-sql-prefix still fires on fixtures"
    record "lint self-test" 0
  else
    echo "${RED}lint self-test FAILED — raw-sql-prefix no longer catches its fixture${OFF}"
    record "lint self-test" 1
  fi
  echo
}

run_wp_suite() {
  echo "${BOLD}$1${OFF}"; hr
  ( cd "$WP_ROOT" && wp eval-file "$PLUGIN_DIR/$2" 2>&1 ) \
    | grep -vE '^(Notice|Deprecated|Warning): ' \
    | grep -vE '^<div id="error">'
  local code=${PIPESTATUS[0]}
  record "$1" "$code"
  echo
}

run_js() {
  echo "${BOLD}S4 — admin request layer${OFF}"; hr
  if ! command -v pnpm >/dev/null 2>&1; then
    echo "${RED}pnpm not found on PATH.${OFF}"
    record "S4 — admin request layer" 1
    echo
    return
  fi

  ( cd "$PLUGIN_DIR" && pnpm test:js )
  record "S4 — admin request layer" $?
  echo
}

if ! command -v wp >/dev/null 2>&1; then
  echo "${RED}wp-cli not found on PATH.${OFF}"; exit 2
fi
if [ ! -d "$WP_ROOT" ]; then
  echo "${RED}WordPress root not found: $WP_ROOT${OFF}"; exit 2
fi

echo
echo "${BOLD}FluentSMTP test run${OFF}  (suite: $SUITE)"
echo "plugin: $PLUGIN_DIR"
echo "wp:     $WP_ROOT"
echo "logs:   $RUN_LOG_COUNT_BEFORE before run"
echo

case "$SUITE" in
  static) run_static ;;
  smoke) run_wp_suite "S1 — admin-AJAX smoke" "tests/bin/run-smoke.php" ;;
  permissions)
    run_wp_suite "S1 — permission smoke" "tests/bin/run-permissions.php"
    ;;
  integration)
    run_wp_suite "S2/S3 — integration" "tests/bin/run-integration.php"
    ;;
  js) run_js ;;
  all)
    run_static
    run_js
    run_wp_suite "S1 — admin-AJAX smoke" "tests/bin/run-smoke.php"
    if [ -f "$PLUGIN_DIR/tests/smoke/mutating.manifest.php" ]; then
      run_wp_suite "S1 — permission smoke" "tests/bin/run-permissions.php"
    else
      echo "${YELLOW}S1 — permission smoke: phase not built yet, skipping${OFF}"; echo
    fi
    if find "$PLUGIN_DIR/tests/integration" -maxdepth 1 -name '*.php' -print -quit | grep -q .; then
      run_wp_suite "S2/S3 — integration" "tests/bin/run-integration.php"
    else
      echo "${YELLOW}S2/S3 — integration: phase not built yet, skipping${OFF}"; echo
    fi
    ;;
  *)
    echo "Unknown suite: $SUITE (use: static|smoke|permissions|integration|js|all)"
    exit 2
    ;;
esac

RUN_LOG_COUNT_AFTER="$(read_log_count)"
if [ "$RUN_LOG_COUNT_AFTER" != "$RUN_LOG_COUNT_BEFORE" ]; then
  echo "${RED}FAIL — protected fsmpt_email_logs count drifted from $RUN_LOG_COUNT_BEFORE to $RUN_LOG_COUNT_AFTER.${OFF}"
  record "protected log count" 1
else
  echo "protected log count: unchanged at $RUN_LOG_COUNT_AFTER"
  record "protected log count" 0
fi

hr
echo "${BOLD}SUMMARY${OFF}"
for result in "${RESULTS[@]}"; do echo "  $result"; done
hr

exit "$FAILED"
