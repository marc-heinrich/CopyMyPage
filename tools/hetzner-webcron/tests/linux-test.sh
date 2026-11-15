#!/usr/bin/env bash
# Isolated Linux kernel/permissions/FD/timeout checks. No Joomla, DB or HTTP.
set -euo pipefail
umask 077
[[ "$(uname -s)" == Linux ]] || { echo 'Linux required; Git Bash is not a substitute.' >&2; exit 2; }
source_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
fixture="$(mktemp -d "${TMPDIR:-/tmp}/cmp-cron-linux.XXXXXXXX")"
cleanup() { rm -f -- "$fixture"/* "$fixture"/.operations-*; rmdir -- "$fixture"; }
trap cleanup EXIT
cp -- "$source_dir/hetzner-webcron.sh" "$source_dir/cron-operations.php" "$fixture/"
chmod 700 "$fixture"
chmod 600 "$fixture/hetzner-webcron.sh" "$fixture/cron-operations.php"
export CMP_CRON_PHP="$(command -v php)"
export TEST_FLOCK="$(command -v flock)" TEST_TIMEOUT="$(command -v timeout)"
export TEST_HARD=5 TEST_GRACE=1 TEST_MODE=normal
cat > "$fixture/scheduler-central.php" <<'PHP'
<?php
declare(strict_types=1);
require __DIR__.'/cron-operations.php';
if ($argv[1] === 'config-shell') {
    echo implode("\n",[getenv('TEST_FLOCK'),getenv('TEST_TIMEOUT'),getenv('TEST_HARD'),getenv('TEST_GRACE'),str_repeat('a',64)])."\n"; exit(0);
}
if (getenv('CMP_CRON_CONFIG_SHA256') !== str_repeat('a',64)) exit(99);
$fd=fopen('php://fd/9','a'); $actual=fstat($fd);$expected=stat(__DIR__.'/joomla-webcron.lock');
if ($actual['ino'] !== $expected['ino'] || $actual['dev'] !== $expected['dev'] || !flock($fd,LOCK_EX|LOCK_NB)) exit(99);
if ((fileperms(__DIR__)&0777)!==0700 || (fileperms(__DIR__.'/joomla-webcron.lock')&0777)!==0600) exit(99);
if (file_exists(__DIR__.'/pending-request.json')) exit(22);
$run=getenv('CMP_CRON_RUN_ID'); $mode=getenv('TEST_MODE');
if ($mode==='hold' || $mode==='timeout') {
    file_put_contents(__DIR__.'/pending-request.json','synthetic unresolved intent');
    if ($mode==='timeout') sleep(30); else { sleep(2); unlink(__DIR__.'/pending-request.json'); }
}
opsEvent(__DIR__,['run'=>$run,'event'=>'cycle_end','result'=>'success','exit'=>0,'general_errors'=>0,'remaining_due'=>0]);
PHP
chmod 600 "$fixture/scheduler-central.php"
fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }
bash "$fixture/hetzner-webcron.sh" || fail 'ordinary run / inherited FD / modes'
php "$fixture/cron-operations.php" health || fail 'health after successful Linux run'
printf 'PASS: private modes, inherited kernel lock and successful wrapper\n'
TEST_MODE=hold bash "$fixture/hetzner-webcron.sh" &
holder=$!
for i in {1..100}; do [[ -f "$fixture/pending-request.json" ]] && break; sleep 0.02; done
[[ -f "$fixture/pending-request.json" ]] || fail 'holder did not start'
set +e
bash "$fixture/hetzner-webcron.sh"
collision=$?
set -e
[[ "$collision" == 75 ]] || fail "collision exit $collision"
wait "$holder" || fail 'holder failed after collision'
php "$fixture/cron-operations.php" health || fail 'collision overwrote successful active run'
printf 'PASS: actual competing wrapper is excluded\n'
set +e
TEST_MODE=timeout TEST_HARD=1 bash "$fixture/hetzner-webcron.sh"
timeout_exit=$?
set -e
[[ "$timeout_exit" == 124 && -f "$fixture/pending-request.json" ]] || fail 'timeout did not retain intent'
set +e
bash "$fixture/hetzner-webcron.sh"
pending_exit=$?
php "$fixture/cron-operations.php" health
health_exit=$?
set -e
[[ "$pending_exit" == 22 && "$health_exit" == 1 ]] || fail 'pending request was retried or hidden'
[[ "$(cat "$fixture/pending-request.json")" == 'synthetic unresolved intent' ]] || fail 'pending evidence changed'
printf 'PASS: real timeout preserves intent and subsequent run stays blocked\n'
printf 'Linux fixture passed; HTTP/native tasks are synthetic, monitor disabled.\n'
