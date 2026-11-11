#!/usr/bin/env bash
# Actual wrapper, simulated PHP/timeout/flock. No native Linux locking evidence.
set -euo pipefail
umask 077
source_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
temp_root="$(cd -- "${TMPDIR:-/tmp}" && pwd -P)"
fixture="$(mktemp -d "$temp_root/cmp-webcron-wrapper.XXXXXXXX")"
cleanup() {
    # Only this flat, newly created fixture is removed; no recursive deletion.
    [[ "$fixture" == "$temp_root"/cmp-webcron-wrapper.* && -d "$fixture" ]] || return
    rm -f -- "$fixture"/*
    rmdir -- "$fixture"
}
trap cleanup EXIT
export TEST_DIR="$fixture"
export TEST_CONFIG_SHA='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
export CMP_CRON_PHP="$fixture/fake-php"
# Source checkouts may have CRLF on Windows. Run the identical shell statements.
sed 's/\r$//' "$source_dir/hetzner-webcron.sh" > "$fixture/hetzner-webcron.sh"
: > "$fixture/scheduler-central.php"

cat > "$fixture/fake-php" <<'PHP_STUB'
#!/usr/bin/env bash
set -euo pipefail
[[ $# == 2 && "$1" == "$TEST_DIR/scheduler-central.php" ]] || exit 99
case "$2" in
    config-shell)
        if [[ "${TEST_CONFIG_FAIL:-0}" == 1 ]]; then
            printf '{"event":"fatal","result":"config_schema"}\n'
            exit 22
        fi
        printf '%s\n' "$TEST_DIR/fake-flock" "$TEST_DIR/fake-timeout" 240 5 "$TEST_CONFIG_SHA"
        ;;
    run)
        [[ "${CMP_CRON_CONFIG_SHA256:-}" == "$TEST_CONFIG_SHA" ]] || exit 99
        [[ -f "$TEST_DIR/flock.calls" && -f "$TEST_DIR/timeout.calls" ]] || exit 99
        : >&9
        printf '%s\n' "$2" > "$TEST_DIR/run.calls"
        printf '{"event":"fake_runner","exit":%s}\n' "$TEST_RUN_RC"
        exit "$TEST_RUN_RC"
        ;;
    *) exit 99 ;;
esac
PHP_STUB
cat > "$fixture/fake-flock" <<'FLOCK_STUB'
#!/usr/bin/env bash
set -euo pipefail
[[ $# == 2 && "$1" == -n && "$2" == 9 ]] || exit 99
: >&9
printf '%s\n' "$@" > "$TEST_DIR/flock.calls"
exit "${TEST_LOCK_RC:-0}"
FLOCK_STUB
cat > "$fixture/fake-timeout" <<'TIMEOUT_STUB'
#!/usr/bin/env bash
set -euo pipefail
[[ $# == 6 && "$1" == --signal=TERM && "$2" == --kill-after=5s && "$3" == 240s ]] || exit 99
[[ "$4" == "$TEST_DIR/fake-php" && "$5" == "$TEST_DIR/scheduler-central.php" && "$6" == run ]] || exit 99
printf '%s\n' "$@" > "$TEST_DIR/timeout.calls"
shift 3
exec "$@"
TIMEOUT_STUB
chmod 700 "$fixture/fake-php" "$fixture/fake-flock" "$fixture/fake-timeout"

checks=0
fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }
run_case() {
    local runner_rc="$1" expected_rc="$2" expected_result="$3" lock_rc="${4:-0}"
    local actual_rc log acquired=1
    rm -f -- "$fixture/webcron-executions.log" "$fixture/flock.calls" "$fixture/timeout.calls" "$fixture/run.calls" "$fixture/joomla-webcron.lock"
    set +e
    TEST_RUN_RC="$runner_rc" TEST_LOCK_RC="$lock_rc" "$BASH" "$fixture/hetzner-webcron.sh" > "$fixture/stdout" 2> "$fixture/stderr"
    actual_rc=$?
    set -e
    [[ "$actual_rc" == "$expected_rc" ]] || fail "runner=$runner_rc lock=$lock_rc: exit $actual_rc, expected $expected_rc"
    [[ -f "$fixture/webcron-executions.log" ]] || fail 'missing wrapper log'
    log="$(cat "$fixture/webcron-executions.log")"
    [[ "$lock_rc" == 0 ]] || acquired=0
    [[ "$log" == *'"event":"wrapper_end","result":"'"$expected_result"'","exit":'"$expected_rc"',"lock_acquired":'"$acquired"','* ]] || fail "unexpected wrapper log: $log"
    [[ "$log" =~ ^\{\"utc\":\"[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z\" ]] || fail 'missing UTC timestamp'
    [[ -f "$fixture/flock.calls" && "$(cat "$fixture/flock.calls")" == $'-n\n9' ]] || fail 'lock argument protocol changed'
    if [[ "$lock_rc" == 0 ]]; then
        [[ -f "$fixture/run.calls" && -f "$fixture/timeout.calls" ]] || fail 'runner or timeout was skipped'
        [[ "$(cat "$fixture/stdout")" == '{"event":"fake_runner","exit":'"$runner_rc"'}' ]] || fail 'runner stdout changed'
    else
        [[ ! -e "$fixture/run.calls" && ! -e "$fixture/timeout.calls" ]] || fail 'runner started without lock'
    fi
    [[ ! -e "$fixture/pending-request.json" ]] || fail 'wrapper created pending state'
    checks=$((checks+1))
    printf 'PASS: runner=%s lock=%s => %s (%s)\n' "$runner_rc" "$lock_rc" "$expected_result" "$actual_rc"
}

run_case 0 0 success
run_case 10 0 site_offline
run_case 20 20 payment_failure
run_case 21 21 general_failure
run_case 22 22 uncertain_or_guard_failure
run_case 30 30 budget_reached
run_case 124 124 hard_budget_uncertain
run_case 137 137 hard_budget_uncertain
run_case 10 75 lock_busy 1

rm -f -- "$fixture/webcron-executions.log" "$fixture/flock.calls" "$fixture/timeout.calls" "$fixture/run.calls" "$fixture/joomla-webcron.lock"
set +e
TEST_CONFIG_FAIL=1 "$BASH" "$fixture/hetzner-webcron.sh" > "$fixture/stdout" 2> "$fixture/stderr"
config_rc=$?
set -e
[[ "$config_rc" == 2 ]] || fail "config failure exit $config_rc, expected 2"
[[ ! -e "$fixture/joomla-webcron.lock" && ! -e "$fixture/flock.calls" && ! -e "$fixture/run.calls" && ! -e "$fixture/timeout.calls" && ! -e "$fixture/webcron-executions.log" ]] || fail 'config failure created state or started execution'
[[ "$(cat "$fixture/stderr")" == '{"event":"fatal","result":"config_schema"}' ]] || fail 'configuration diagnostic lost'
checks=$((checks+1))
printf 'PASS: configuration failure stops before lock/runner\n'
printf '%s wrapper cases passed; PHP, timeout and flock are simulated.\n' "$checks"
