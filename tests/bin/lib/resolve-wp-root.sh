#!/usr/bin/env bash
# Resolve WordPress without hardcoding this development machine's layout.

fsmtp_resolve_wp_root() {
  local plugin_dir="$1"
  local dir abspath

  if [ -n "${FSMTP_WP_ROOT:-}" ]; then
    if [ ! -f "$FSMTP_WP_ROOT/wp-load.php" ]; then
      echo "FSMTP_WP_ROOT is set to '$FSMTP_WP_ROOT' but no wp-load.php is there." >&2
      return 2
    fi
    printf '%s\n' "${FSMTP_WP_ROOT%/}"
    return 0
  fi

  dir="$plugin_dir"
  while [ "$dir" != "/" ] && [ -n "$dir" ]; do
    if [ -f "$dir/wp-load.php" ]; then
      printf '%s\n' "$dir"
      return 0
    fi
    dir="$(dirname "$dir")"
  done

  if command -v wp >/dev/null 2>&1; then
    abspath="$(cd "$plugin_dir" && wp eval 'echo untrailingslashit(ABSPATH);' \
      --skip-plugins --skip-themes 2>/dev/null | tail -n 1)"
    if [ -n "$abspath" ] && [ -f "$abspath/wp-load.php" ]; then
      printf '%s\n' "$abspath"
      return 0
    fi
  fi

  echo "Could not locate the WordPress root from '$plugin_dir'." >&2
  echo "Set it explicitly: export FSMTP_WP_ROOT=/path/to/wordpress" >&2
  return 2
}
