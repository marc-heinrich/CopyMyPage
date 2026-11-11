#!/usr/bin/env bash
# CopyMyPage: one shared lock and a bounded, fair Joomla Web-Cron queue.
set -euo pipefail
umask 077
BASE_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
LOCK_FILE="${BASE_DIR}/joomla-webcron.lock"
# Bootstrap interpreter only; installation/runtime values come from JSON via PHP.
PHP_BIN="${CMP_CRON_PHP:-/usr/bin/php}"
started_seconds=$SECONDS
result='config_error'
acquired=0
finish() {
    local rc=$?
    printf '{"utc":"%s","event":"wrapper_end","result":"%s","exit":%s,"lock_acquired":%s,"duration_s":%s}\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$result" "$rc" "$acquired" "$((SECONDS-started_seconds))" >> "$BASE_DIR/webcron-executions.log"
}
[[ -d "$BASE_DIR" && -w "$BASE_DIR" ]] || exit 2
[[ -x "$PHP_BIN" && -r "$BASE_DIR/scheduler-central.php" ]] || exit 2
# Fixed line protocol: no JSON is interpreted as shell source. Check parser status
# before mapfile (process substitution alone would hide a failed PHP validator).
if ! config_lines="$("$PHP_BIN" "$BASE_DIR/scheduler-central.php" config-shell)"; then
    printf '%s\n' "$config_lines" >&2
    exit 2
fi
mapfile -t config_values <<< "$config_lines"
[[ "${#config_values[@]}" == 5 ]] || exit 2
FLOCK_BIN="${config_values[0]}"
TIMEOUT_BIN="${config_values[1]}"
hard_seconds="${config_values[2]}"
grace_seconds="${config_values[3]}"
export CMP_CRON_CONFIG_SHA256="${config_values[4]}"
[[ "$FLOCK_BIN" == /* && -x "$FLOCK_BIN" && "$TIMEOUT_BIN" == /* && -x "$TIMEOUT_BIN" ]] || exit 2
[[ "$hard_seconds" =~ ^[0-9]{1,3}$ && "$grace_seconds" =~ ^[0-9]{1,3}$ && "$CMP_CRON_CONFIG_SHA256" =~ ^[a-f0-9]{64}$ ]] || exit 2
trap finish EXIT
exec 9>>"$LOCK_FILE"
if ! "$FLOCK_BIN" -n 9; then result='lock_busy';exit 75;fi
acquired=1
result='runner_error'
# Validated hard stop plus grace is at most 270s, below the five-minute slot.
set +e
"$TIMEOUT_BIN" --signal=TERM "--kill-after=${grace_seconds}s" "${hard_seconds}s" "$PHP_BIN" "$BASE_DIR/scheduler-central.php" run
rc=$?
set -e
case "$rc" in
    0) result='success';;
    # Internal runner signal; an offline site is a regular suspension for cron.
    10) result='site_offline'; rc=0;;
    20) result='payment_failure';;
    21) result='general_failure';;
    22) result='uncertain_or_guard_failure';;
    30) result='budget_reached';;
    124|137) result='hard_budget_uncertain';;
esac
exit "$rc"
