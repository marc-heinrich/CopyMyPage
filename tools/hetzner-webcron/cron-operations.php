<?php
declare(strict_types=1);
// Private filesystem/monitor boundary. No Joomla bootstrap, database or task calls.
function opsSettings(string $base): array {
    $defaults = ['max_bytes'=>4194304,'archives'=>12,'retention_days'=>30,'stale_seconds'=>900,'monitor_enabled'=>false];
    $file = $base.'/operations-config.json';
    if (!file_exists($file) && !is_link($file)) return $defaults;
    opsPrivate($file);
    if (filesize($file) > 4096) throw new RuntimeException('operations_config');
    $c = json_decode((string)file_get_contents($file), true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($c) || array_diff(array_keys($c), array_keys($defaults)) || array_diff(array_keys($defaults), array_keys($c))) throw new RuntimeException('operations_config');
    foreach (['max_bytes'=>[65536,16777216],'archives'=>[1,64],'retention_days'=>[1,365],'stale_seconds'=>[600,3600]] as $k=>[$min,$max]) {
        if (!is_int($c[$k]) || $c[$k] < $min || $c[$k] > $max) throw new RuntimeException('operations_config');
    }
    if (!is_bool($c['monitor_enabled'])) throw new RuntimeException('operations_config');
    return $c;
}
function opsPrivate(string $path, bool $optional = false): void {
    clearstatcache(true, $path);
    if (is_link($path)) throw new RuntimeException('operations_permissions');
    if ($optional && !file_exists($path)) return;
    if (!is_file($path) || (PHP_OS_FAMILY !== 'Windows' && (((int)fileperms($path) & 0777) !== 0600
        || (function_exists('posix_geteuid') && fileowner($path) !== posix_geteuid())))) throw new RuntimeException('operations_permissions');
}
function opsLock(string $base, callable $work): mixed {
    $file = $base.'/operations.lock'; opsPrivate($file, true);
    $h = @fopen($file, 'c');
    if (!$h) throw new RuntimeException('operations_lock');
    try {
        if (!chmod($file, 0600)) throw new RuntimeException('operations_permissions');
        $until = hrtime(true) + 2000000000;
        while (!flock($h, LOCK_EX|LOCK_NB)) {
            if (hrtime(true) >= $until) throw new RuntimeException('operations_lock');
            usleep(10000);
        }
        return $work();
    } finally { fclose($h); }
}
function opsArchives(string $base): array {
    $files = [];
    foreach (glob($base.'/webcron-executions.*.log') ?: [] as $file) {
        if (!preg_match('/^webcron-executions\.[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}\.log$/D', basename($file))) continue;
        opsPrivate($file); $files[$file] = filemtime($file);
    }
    asort($files, SORT_NUMERIC); return $files;
}
// All writers and rotation hold operations.lock and open the current file AFTER
// acquiring it. Never copytruncate, keep an old file descriptor or delete a lock.
function opsAppendUnlocked(string $base, array $row, array $c): void {
    $line = json_encode($row, JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
    if (strlen($line) > 32768) throw new RuntimeException('operations_record');
    $log = $base.'/webcron-executions.log'; opsPrivate($log, true);
    $rotate = false;
    if (is_file($log) && filesize($log) > 0) {
        $h = fopen($log, 'rb');
        if (!$h) throw new RuntimeException('operations_log');
        try {
            if (fseek($h,-1,SEEK_END) !== 0) throw new RuntimeException('operations_log');
            // Preserve an interrupted final record in its own segment. Never
            // merge a new JSON line into it or truncate the available evidence.
            $rotate = fread($h,1) !== "\n" || filesize($log) + strlen($line) > $c['max_bytes'];
        } finally { fclose($h); }
    }
    if ($rotate) {
        $archive = $base.'/webcron-executions.'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(6)).'.log';
        if (!rename($log, $archive)) throw new RuntimeException('operations_rotation');
    }
    $h = @fopen($log, 'ab');
    if (!$h) throw new RuntimeException('operations_log');
    try {
        if (!chmod($log, 0600) || fwrite($h, $line) !== strlen($line) || !fflush($h) || !fsync($h)) throw new RuntimeException('operations_log');
    } finally { fclose($h); }
    $files = opsArchives($base); $cutoff = time() - $c['retention_days'] * 86400;
    foreach ($files as $file=>$modified) {
        if ($modified < $cutoff || count($files) > $c['archives']) {
            if (!unlink($file)) throw new RuntimeException('operations_rotation');
            unset($files[$file]);
        }
    }
}
function opsReadState(string $base): array {
    $file = $base.'/cron-health.json'; opsPrivate($file, true);
    if (!is_file($file)) return [];
    if (filesize($file) > 8192) throw new RuntimeException('operations_state');
    $s = json_decode((string)file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($s)) throw new RuntimeException('operations_state');
    return $s;
}
function opsWriteState(string $base, array $s): void {
    $file = $base.'/cron-health.json'; opsPrivate($file, true);
    $tmp = tempnam($base, '.operations-');
    if ($tmp === false) throw new RuntimeException('operations_state');
    try {
        $h = fopen($tmp, 'wb');
        if (!$h) throw new RuntimeException('operations_state');
        try {
            $line = json_encode($s, JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
            if (!chmod($tmp, 0600) || fwrite($h, $line) !== strlen($line) || !fflush($h) || !fsync($h)) throw new RuntimeException('operations_state');
        } finally { fclose($h); }
        if (!rename($tmp, $file)) throw new RuntimeException('operations_state');
    } finally { if (is_file($tmp)) unlink($tmp); }
}
function opsEvent(string $base, array $row): void {
    $c = opsSettings($base);
    opsLock($base, static function() use($base,$row,$c): void {
        opsAppendUnlocked($base, $row, $c);
        $s = opsReadState($base);
        if (isset($row['run']) && ($s['run'] ?? null) === $row['run']) {
            if (($row['event'] ?? '') === 'monitor_delivery') $s['last_monitor'] = $row['result'];
            if (($row['event'] ?? '') === 'task_end' && ($row['result'] ?? '') !== 'success') $s['task_problem'] = true;
            if (($row['event'] ?? '') === 'cycle_end') $s['cycle'] = array_intersect_key($row, array_flip(['result','exit','general_errors','remaining_due']));
            opsWriteState($base, $s);
        }
    });
}
function opsHealthy(array $s, int $exit): bool {
    $cycle = $s['cycle'] ?? [];
    return $exit === 0 && empty($s['task_problem']) && in_array($cycle['result'] ?? '', ['success','site_offline'], true)
        && ($cycle['general_errors'] ?? -1) === 0 && (($cycle['remaining_due'] ?? null) === 0 || ($cycle['result'] ?? '') === 'site_offline');
}
function opsMonitorUrl(string $base): string {
    $file = $base.'/monitoring.url'; opsPrivate($file);
    if (filesize($file) > 1024) throw new RuntimeException('monitor_config');
    $url = trim((string)file_get_contents($file));
    // Dedicated Healthchecks-compatible UUID endpoint, never the Joomla key/URL.
    if (!preg_match('~^https://[A-Za-z0-9.-]+/(?:[A-Za-z0-9_-]+/)*[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$~D', $url)) throw new RuntimeException('monitor_config');
    return $url;
}
function opsPing(string $base, string $signal, ?callable $transport = null): string {
    if (!opsSettings($base)['monitor_enabled']) return 'disabled';
    if (!in_array($signal,['success','fail'],true)) throw new RuntimeException('monitor_config');
    $url = opsMonitorUrl($base).($signal === 'success' ? '' : '/fail');
    // Secret URL via stdin, absent from process arguments and diagnostics. No
    // inherited curlrc/proxy, redirects, response body, logs or customer data.
    $input = 'url = "'.$url.'"'."\n";
    if ($transport !== null) return $transport($input) ? 'sent' : 'failed';
    $p = proc_open(['/usr/bin/curl','--disable','--silent','--fail','--proxy','','--noproxy','*','--proto','=https',
        '--max-redirs','0','--connect-timeout','2','--max-time','5','--output','/dev/null','--write-out','%{http_code}','--config','-'],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['file','/dev/null','a']], $pipes);
    if (!is_resource($p)) return 'failed';
    fwrite($pipes[0], $input); fclose($pipes[0]);
    $http = stream_get_contents($pipes[1]); fclose($pipes[1]); $rc = proc_close($p);
    return $rc === 0 && $http === '200' ? 'sent' : 'failed';
}
function opsStart(string $base, string $run): void {
    $c = opsSettings($base);
    if ($c['monitor_enabled']) opsMonitorUrl($base);
    opsLock($base, static function() use($base,$run,$c): void {
        $s = opsReadState($base);
        $s = array_intersect_key($s, array_flip(['last_end','last_failure','sequence']));
        $s += ['run'=>$run,'started'=>time(),'task_problem'=>false];
        opsAppendUnlocked($base, ['utc'=>gmdate('Y-m-d\TH:i:s\Z'),'run'=>$run,'event'=>'wrapper_start'], $c);
        opsWriteState($base, $s);
    });
    // Only completed success pings extend the external deadline. Repeated
    // starts/crashes must never postpone a missing-completion alarm.
}
function opsFinish(string $base, string $run, string $result, int $exit, bool $acquired, int $duration, ?callable $transport = null): bool {
    $c = opsSettings($base);
    $healthy = opsLock($base, static function() use($base,$run,$result,$exit,$acquired,$duration,$c): bool {
        $s = opsReadState($base);
        $owns = $acquired && ($s['run'] ?? null) === $run;
        $healthy = $owns && opsHealthy($s, $exit) && !file_exists($base.'/pending-request.json') && !is_link($base.'/pending-request.json');
        $record = ['utc'=>gmdate('Y-m-d\TH:i:s\Z'),'event'=>'wrapper_end','result'=>$result,'exit'=>$exit,
            'lock_acquired'=>(int)$acquired,'duration_s'=>$duration,'run'=>$run,'healthy'=>$healthy];
        opsAppendUnlocked($base, $record, $c);
        $s['sequence'] = ($s['sequence'] ?? 0) + 1;
        $end = ['at'=>time(),'sequence'=>$s['sequence'],'run'=>$run,'result'=>$result,'exit'=>$exit,'healthy'=>$healthy];
        if ($owns || !isset($s['started'])) { $s['last_end']=$end; unset($s['started']); }
        if (!$healthy) $s['last_failure']=$end;
        opsWriteState($base, $s); return $healthy;
    });
    // A colliding wrapper must never overwrite another run's start/success.
    $ping = opsPing($base, $healthy ? 'success' : 'fail', $transport);
    opsEvent($base, ['utc'=>gmdate('Y-m-d\TH:i:s\Z'),'run'=>$run,'event'=>'monitor_delivery','result'=>$ping]);
    if ($ping === 'failed') throw new RuntimeException('monitor_transport');
    return $healthy;
}
function opsHealth(string $base, ?int $now = null): array {
    $c = opsSettings($base); $s = opsReadState($base); $now ??= time();
    $end = $s['last_end'] ?? []; $age = $now - ($end['at'] ?? 0);
    $reason = 'healthy';
    if (!$end || $age < 0 || $age > $c['stale_seconds']) $reason='missing_runs';
    elseif (empty($end['healthy'])) $reason='run_failed';
    elseif (isset($s['started']) && $now - $s['started'] > 270) $reason='run_timeout';
    elseif (($s['last_failure']['sequence'] ?? 0) > ($end['sequence'] ?? 0)) $reason='wrapper_failure';
    if ($c['monitor_enabled'] && ($s['last_monitor'] ?? '') === 'failed') $reason='monitor_transport';
    if (file_exists($base.'/pending-request.json') || is_link($base.'/pending-request.json')) $reason='pending_request';
    return ['utc'=>gmdate('c',$now),'healthy'=>$reason==='healthy','reason'=>$reason,'age_seconds'=>$end ? $age : null,
        'running'=>isset($s['started']),'last_end'=>$end,'last_failure'=>$s['last_failure'] ?? null,'monitor_enabled'=>$c['monitor_enabled']];
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(2); }
ini_set('display_errors','0'); umask(0077);
try {
    $base = __DIR__; $command = $argv[1] ?? '';
    if ($command === 'check') {
        $c = opsSettings($base); if ($c['monitor_enabled']) opsMonitorUrl($base);
        echo json_encode(['operations'=>'valid','settings'=>$c], JSON_THROW_ON_ERROR)."\n"; exit(0);
    }
    if ($command === 'health') {
        $health = opsHealth($base); echo json_encode($health, JSON_THROW_ON_ERROR)."\n"; exit($health['healthy'] ? 0 : 1);
    }
    $run = (string)getenv('CMP_CRON_RUN_ID');
    if (!preg_match('/^[A-Za-z0-9-]{1,80}$/D', $run)) throw new RuntimeException('operations_arguments');
    if ($command === 'start') { opsStart($base,$run); exit(0); }
    if ($command !== 'finish' || count($argv) !== 6 || !preg_match('/^[a-z_]{1,48}$/D',$argv[2])
        || !ctype_digit($argv[3]) || !in_array($argv[4],['0','1'],true) || !ctype_digit($argv[5])) throw new RuntimeException('operations_arguments');
    opsFinish($base,$run,$argv[2],(int)$argv[3],$argv[4]==='1',(int)$argv[5]);
} catch (Throwable $e) {
    // Do not print endpoint URLs, config contents or exception text.
    fwrite(STDERR,"{\"event\":\"operations_error\",\"result\":\"operations_failed\"}\n"); exit(23);
}
