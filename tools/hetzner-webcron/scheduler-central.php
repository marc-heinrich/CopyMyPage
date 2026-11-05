<?php
declare(strict_types=1);
// Private CLI evidence/dispatcher. Database access is READ ONLY; all tasks use Joomla HTTPS.
const CMP_BASE = __DIR__;
const CMP_PAYMENT = 'copymypage.reconcile_payments';
const CMP_NATIVE = 'plg_task_dpcalendar_booking_deletions';
const CMP_CARTS = 'copymypage.release_reservations';
// Reviewed installed source, 2026-11-04; the other existing guards remain intact.
const CMP_FIX = '7d249846466d27439f7a5ea53fa6af6ca3e52313cd92bcd8500b72fedca2b97a';

function exactKeys(array $value, array $keys): void {
    $actual = array_keys($value); sort($actual); sort($keys);
    if ($actual !== $keys) throw new RuntimeException('config_schema');
}
function validateConfig(array $c): array {
    exactKeys($c, ['schema_version','enabled','joomla_root','webcron_url','tasks','runtime','binaries','payment_recovery']);
    if ($c['schema_version'] !== 1 || !is_bool($c['enabled']) || !is_array($c['runtime'])
        || !is_array($c['binaries']) || !is_array($c['payment_recovery'])) throw new RuntimeException('config_schema');
    exactKeys($c['runtime'], ['cycle_budget_seconds','max_general_tasks','general_timeout_seconds','payment_timeout_seconds',
        'connect_timeout_seconds','hard_timeout_seconds','kill_grace_seconds']);
    exactKeys($c['binaries'], ['curl','flock','timeout']);
    exactKeys($c['payment_recovery'], ['enabled']);
    if (!is_bool($c['payment_recovery']['enabled'])) throw new RuntimeException('config_schema');
    if (!is_array($c['tasks'])) throw new RuntimeException('config_schema');
    exactKeys($c['tasks'], ['native_cleanup','seat_release','system_tasks']);
    foreach (['native_cleanup','seat_release'] as $role) {
        if (!is_int($c['tasks'][$role]) || $c['tasks'][$role] < 1) throw new RuntimeException('config_tasks');
    }
    if ($c['tasks']['native_cleanup'] === $c['tasks']['seat_release']
        || $c['tasks']['system_tasks'] !== 'all_due') throw new RuntimeException('config_tasks');
    foreach (array_merge([$c['joomla_root']], array_values($c['binaries'])) as $path) {
        if (!is_string($path) || !preg_match('~^/[^\x00-\x20\x7f]+$~D', $path)
            || preg_match('~/(?:\.|\.\.)(?:/|$)~', $path)) throw new RuntimeException('config_path');
    }
    if (!is_string($c['webcron_url']) || preg_match('/[\x00-\x20\x7f]/', $c['webcron_url'])) throw new RuntimeException('config_url');
    $u = parse_url($c['webcron_url']);
    if (!is_array($u) || ($u['scheme'] ?? '') !== 'https' || empty($u['host'])
        || isset($u['user']) || isset($u['pass']) || isset($u['query']) || isset($u['fragment'])
        || (isset($u['port']) && $u['port'] !== 443)
        || !preg_match('~^/(?:[A-Za-z0-9_-]+/)*component/ajax/$~D', $u['path'] ?? '')) throw new RuntimeException('config_url');
    foreach ($c['runtime'] as $name => $value) {
        if (!is_int($value) || $value < 1 || $value > 300) throw new RuntimeException('config_budget');
    }
    $r = $c['runtime'];
    if ($r['max_general_tasks'] > 128 || $r['cycle_budget_seconds'] < 30
        || $r['hard_timeout_seconds'] < $r['cycle_budget_seconds'] + 15
        || $r['hard_timeout_seconds'] + $r['kill_grace_seconds'] > 270
        || $r['connect_timeout_seconds'] > min($r['general_timeout_seconds'], $r['payment_timeout_seconds'])
        || max($r['general_timeout_seconds'], $r['payment_timeout_seconds']) > $r['cycle_budget_seconds'] - 20) throw new RuntimeException('config_budget');
    return $c;
}
function readConfig(string $file, ?string &$sha256 = null): array {
    if (!is_file($file) || !is_readable($file)) throw new RuntimeException('config_missing');
    if (filesize($file) > 16384) throw new RuntimeException('config_schema');
    $raw = file_get_contents($file);
    if (!is_string($raw)) throw new RuntimeException('config_missing');
    $sha256 = hash('sha256', $raw);
    try { $value = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException('config_schema'); }
    if (!is_array($value)) throw new RuntimeException('config_schema');
    return validateConfig($value);
}
function privateMode(int $mode, bool $directory = false): bool {
    return $directory ? ($mode & 0777) === 0700 : in_array($mode & 0777, [0400,0600], true);
}
function privatePath(string $path, bool $directory = false, bool $optional = false): void {
    clearstatcache(true, $path);
    if (is_link($path)) throw new RuntimeException('private_permissions');
    if ($optional && !file_exists($path)) return;
    if (($directory ? !is_dir($path) : !is_file($path)) || !privateMode((int)@fileperms($path), $directory)
        || (function_exists('posix_geteuid') && fileowner($path) !== posix_geteuid())) throw new RuntimeException('private_permissions');
}
function loadConfig(): array {
    $c = readConfig(CMP_BASE.'/cron-config.json', $sha256);
    if (PHP_OS_FAMILY === 'Windows') throw new RuntimeException('unix_required');
    privatePath(CMP_BASE, true); privatePath(CMP_BASE.'/cron-config.json');
    $root = realpath($c['joomla_root']); $base = realpath(CMP_BASE);
    if ($root === false || !is_file($root.'/configuration.php') || $base === false
        || $base === $root || str_starts_with($base, $root.'/')) throw new RuntimeException('config_path');
    $c['joomla_root'] = $root;
    foreach ($c['binaries'] as $binary) if (!is_file($binary) || !is_executable($binary)) throw new RuntimeException('config_binary');
    privatePath(CMP_BASE.'/webcron.key');
    $key = file_get_contents(CMP_BASE.'/webcron.key');
    if (!is_string($key) || $key === '' || strlen($key) > 512 || preg_match('/[^\x21-\x7e]/', $key)) throw new RuntimeException('key_invalid');
    foreach (['joomla-webcron.lock','general-cursor','webcron-executions.log','pending-request.json'] as $name) privatePath(CMP_BASE.'/'.$name, false, true);
    $c['_sha256'] = $sha256;
    return $c;
}
function config(): array {
    static $value;
    return $value ??= loadConfig();
}
function unchangedConfig(): void {
    if (!hash_equals(config()['_sha256'], (string)@hash_file('sha256', CMP_BASE.'/cron-config.json'))) throw new RuntimeException('config_changed');
}
function tableName(string $prefix, string $table): string {
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/D', $prefix) || !preg_match('/^[a-z_]+$/D', $table)) throw new RuntimeException('site_config');
    return '`'.$prefix.$table.'`';
}

function database(): mysqli {
    require_once config()['joomla_root'] . '/configuration.php';
    $c = new JConfig();
    tableName($c->dbprefix, 'extensions');
    if (empty($c->db) || $c->offline || !in_array($c->dbtype, ['mysqli','mysql'], true)
        || !empty($c->dbencryption)) throw new RuntimeException('site_config');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = mysqli_init();
    $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    $db->options(MYSQLI_OPT_READ_TIMEOUT, 5);
    $db->real_connect($c->host, $c->user, $c->password, $c->db);
    $db->set_charset('utf8mb4');
    $db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    return $db;
}
function rows(mysqli $db, string $sql): array { return $db->query($sql)->fetch_all(MYSQLI_ASSOC); }
function dueTimestamp(string $value): int {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d H:i:s') !== $value) throw new RuntimeException('task_schedule');
    return $date->getTimestamp();
}
function taskInventory(array $tasks, int $now, bool $paymentEnabled, ?array $bindings = null): array {
    if (count($tasks) > 128) throw new RuntimeException('task_limit');
    $result = ['now'=>$now, 'tasks'=>[], 'due'=>[], 'locked'=>0, 'logs'=>[],
        'roles'=>['native_cleanup'=>[], 'seat_release'=>[], 'payment_recovery'=>[]], 'payment_id'=>null];
    $roles = [CMP_NATIVE=>'native_cleanup', CMP_CARTS=>'seat_release', CMP_PAYMENT=>'payment_recovery'];
    foreach ($tasks as $task) {
        $id = (int)$task['id'];
        if ($id < 1 || isset($result['tasks'][$id])) throw new RuntimeException('task_changed');
        if ($task['locked'] !== null) $result['locked']++;
        if (isset($roles[$task['type']])) $result['roles'][$roles[$task['type']]][] = $id;
        $active = (int)$task['state'] === 1 && (int)$task['cli_exclusive'] === 0;
        if ($task['type'] === CMP_PAYMENT && $paymentEnabled && $active) {
            if ($result['payment_id'] !== null) throw new RuntimeException('duplicate_payment_task');
            $p = json_decode($task['params'], true, 512, JSON_THROW_ON_ERROR);
            if ((int)($p['batch_size'] ?? 0) !== 1 || (int)($p['outbox_batch_size'] ?? 0) !== 1 || (int)($p['dry_run'] ?? 1) !== 0
                || (int)($p['ledger_cleanup_enabled'] ?? 0) !== 1 || (int)($p['outbox_recovery_enabled'] ?? 0) !== 1
                || (int)($p['timeout_minutes'] ?? 0) !== 60 || (int)($p['payment_reservation_minutes'] ?? 0) !== 60) throw new RuntimeException('payment_config');
            $result['payment_id'] = $id;
        }
        $task['definition_hash'] = hash('sha256', $task['created_by'].'|'.$task['params'].'|'.$task['execution_rules'].'|'.$task['cron_rules']);
        unset($task['params'], $task['execution_rules'], $task['cron_rules']);
        $result['tasks'][$id] = $task;
        if ($active && $task['next_execution'] !== null) {
            $due = dueTimestamp($task['next_execution']);
            if ($due <= $now && $task['type'] !== CMP_PAYMENT) $result['due'][] = $id;
        }
    }
    if ($paymentEnabled && $result['payment_id'] === null) throw new RuntimeException('payment_missing');
    foreach (['native_cleanup','seat_release'] as $role) {
        $active = array_filter($result['roles'][$role], fn($id)=>(int)$result['tasks'][$id]['state'] === 1);
        if (count($active) > 1) throw new RuntimeException('duplicate_cleanup_task');
    }
    if ($bindings !== null) {
        foreach (['native_cleanup'=>CMP_NATIVE,'seat_release'=>CMP_CARTS] as $role=>$type) {
            $id = $bindings[$role];
            if (!isset($result['tasks'][$id]) || $result['tasks'][$id]['type'] !== $type) throw new RuntimeException('task_binding');
            foreach ($result['roles'][$role] as $candidate) {
                if ($candidate !== $id && (int)$result['tasks'][$candidate]['state'] === 1) throw new RuntimeException('task_binding');
            }
        }
    }
    return $result;
}
function snapshot(bool $operational = true): array {
    unchangedConfig();
    $db = database();
    try {
        $prefix = (new JConfig())->dbprefix;
        $ext = tableName($prefix, 'extensions'); $tasksTable = tableName($prefix, 'scheduler_tasks'); $logsTable = tableName($prefix, 'scheduler_logs');
        $config = json_decode(rows($db, "SELECT params FROM $ext WHERE element='com_scheduler' AND type='component'")[0]['params'], true, 512, JSON_THROW_ON_ERROR);
        $key = file_get_contents(CMP_BASE . '/webcron.key');
        $plugins=rows($db,"SELECT element,enabled FROM $ext WHERE type='plugin' AND folder='system' AND element IN ('schedulerunner','tasknotification','copymypage')");
        $ready = (int)($config['lazy_scheduler']['enabled'] ?? 1) === 0 && (int)($config['webcron']['enabled'] ?? 0) === 1
            && hash_equals((string)($config['webcron']['key'] ?? ''), $key) && (int)($config['task_logging'] ?? 1) === 1
            && count($plugins) === 3 && !array_filter($plugins, fn($p)=>(int)$p['enabled'] !== 1);
        if ($operational && !$ready) throw new RuntimeException('scheduler_config');
        $tasks = rows($db, "SELECT id,type,state,cli_exclusive,created_by,params,execution_rules,cron_rules,last_execution,next_execution,last_exit_code,locked,times_executed,times_failed FROM $tasksTable ORDER BY id LIMIT 129");
        $result = taskInventory($tasks, time(), config()['payment_recovery']['enabled'], config()['tasks']);
        $result['scheduler'] = ['ready'=>$ready, 'lazy_enabled'=>(int)($config['lazy_scheduler']['enabled'] ?? 1),
            'webcron_enabled'=>(int)($config['webcron']['enabled'] ?? 0), 'key_matches'=>hash_equals((string)($config['webcron']['key'] ?? ''), $key),
            'task_logging'=>(int)($config['task_logging'] ?? 1), 'plugins'=>$plugins];
        $result['logs'] = rows($db, "SELECT id,jobid,exitcode,lastdate,duration FROM $logsTable ORDER BY id DESC LIMIT 16");
        $result['max_log'] = $result['logs'] ? (int)$result['logs'][0]['id'] : 0;
        return $result;
    } finally { $db->rollback(); $db->close(); }
}
function assess(array $before, array $after, int $id): int {
    if ($after['locked'] !== 0) throw new RuntimeException('native_lock');
    $logs = array_values(array_filter($after['logs'], fn($r)=>(int)$r['id'] > $before['max_log']));
    if (count($logs) !== 1 || (int)$logs[0]['jobid'] !== $id) throw new RuntimeException('ambiguous_history');
    foreach ($before['tasks'] as $taskId=>$b) {
        $a = $after['tasks'][$taskId] ?? null;
        if (!$a || $a['type'] !== $b['type'] || $a['state'] !== $b['state'] || $a['cli_exclusive'] !== $b['cli_exclusive'] || $a['definition_hash'] !== $b['definition_hash']) throw new RuntimeException('task_changed');
        if ($taskId !== $id && ($a['times_executed'] !== $b['times_executed'] || $a['times_failed'] !== $b['times_failed'] || $a['last_execution'] !== $b['last_execution'])) throw new RuntimeException('unexpected_execution');
    }
    if (count($before['tasks']) !== count($after['tasks'])) throw new RuntimeException('task_changed');
    $b = $before['tasks'][$id]; $a = $after['tasks'][$id]; $log = $logs[0]; $exit = (int)$log['exitcode'];
    $started=strtotime($a['last_execution'].' UTC');$observed=strtotime($log['lastdate'].' UTC');
    // Native last_execution is the start; TaskNotification.lastdate may be cached before it.
    if ($started===false || $observed===false || $started<$before['now']-2 || $started>$after['now']+2
        || $observed<$before['now']-2 || $observed>$after['now']+2
        || (int)$a['times_executed'] !== (int)$b['times_executed'] + ($exit === 123 ? 0 : 1)
        || (int)$a['last_exit_code'] !== $exit || (int)$a['times_failed'] !== (int)$b['times_failed'] + (in_array($exit,[0,123],true) ? 0 : 1)
        || $a['locked'] !== null) throw new RuntimeException('history_mismatch');
    return $exit;
}
function choose(array $s, array $attempted, int $cursor): ?int {
    $due = array_values(array_diff($s['due'], $attempted)); sort($due, SORT_NUMERIC);
    foreach ($due as $id) if ($id > $cursor) return $id;
    return $due[0] ?? null;
}
function requestTask(int $id, int $seconds): void {
    if ($id < 1 || $seconds < 1) throw new RuntimeException('request_guard');
    $file = tempnam(CMP_BASE, '.central-response-');
    if ($file === false) throw new RuntimeException('response_storage');
    chmod($file, 0600);
    try {
        unchangedConfig();
        $args = curlArguments(config(), $id, $seconds, $file, CMP_BASE.'/webcron.key');
        $proc = proc_open($args, [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['file','/dev/null','a']], $pipes);
        if (!is_resource($proc)) throw new RuntimeException('transport_unavailable');
        $http = stream_get_contents($pipes[1]); fclose($pipes[1]); $rc = proc_close($proc);
        checkResponse($rc, $http, (string)file_get_contents($file));
    } finally { unlink($file); }
}
function curlArguments(array $c, int $id, int $seconds, string $response, string $keyFile): array {
    if ($id < 1 || $seconds < 1) throw new RuntimeException('request_guard');
    return [$c['binaries']['curl'],'--silent','--fail','--proto','=https','--proto-redir','=https','--max-redirs','0',
        '--connect-timeout',(string)$c['runtime']['connect_timeout_seconds'],'--max-time',(string)$seconds,'--max-filesize','65536','--request','POST',
        '--data-urlencode','hash@'.$keyFile,'--output',$response,'--write-out','%{http_code}',
        $c['webcron_url'].'?plugin=RunSchedulerWebcron&group=system&format=json&id='.$id];
}
function checkResponse(int $rc, string $http, string $body): void {
    if ($rc !== 0 || $http !== '200') throw new RuntimeException(in_array($http,['401','403'],true) ? 'http_auth' : 'https_uncertain');
    $json = json_decode($body, true);
    if (!is_array($json)) throw new RuntimeException('json_invalid');
    if (($json['success'] ?? null) !== true) throw new RuntimeException('endpoint_rejected');
}
function pendingExists(string $file): bool { return file_exists($file) || is_link($file); }
function recordPending(string $file, array $s, int $id): void {
    $handle = @fopen($file, 'x');
    if ($handle === false) throw new RuntimeException('pending_request');
    try {
        if (!chmod($file, 0600)) throw new RuntimeException('pending_storage');
        $json = json_encode(['utc'=>gmdate('c'),'task_id'=>$id,'task_type'=>$s['tasks'][$id]['type'],
            'before_utc'=>$s['now'],'before_max_log'=>$s['max_log']], JSON_THROW_ON_ERROR)."\n";
        if (fwrite($handle, $json) !== strlen($json) || !fflush($handle) || !fsync($handle)) throw new RuntimeException('pending_storage');
    } finally { fclose($handle); }
}
function completePending(string $file): void {
    privatePath($file);
    if (!unlink($file)) throw new RuntimeException('pending_storage');
}
function safeReason(Throwable $e): string {
    $allowed = ['site_config','scheduler_config','task_limit','payment_config','payment_missing','duplicate_payment_task',
        'native_lock','ambiguous_history','task_changed','unexpected_execution','history_mismatch','request_guard',
        'response_storage','transport_unavailable','http_auth','https_uncertain','json_invalid','endpoint_rejected','cursor_storage','qualified_source',
        'config_schema','config_path','config_url','config_budget','config_missing','config_binary','config_changed','config_disabled',
        'private_permissions','key_invalid','unix_required','pending_request','pending_storage','duplicate_cleanup_task','task_schedule',
        'wrapper_required','log_unavailable','config_tasks','task_binding'];
    return in_array($e->getMessage(),$allowed,true) ? $e->getMessage() : 'audit_unavailable';
}
function sourceGuards(): array {
    $files=[
        'components/com_copymypage/src/Service/PaymentReconciliationService.php'=>CMP_FIX,
        'components/com_copymypage/src/Service/PaymentIntegrationContract.php'=>'0194e91cbee17ad7be50daba740f8002df0d6ebddb72b9f4998022a315800ddb',
        'plugins/system/schedulerunner/src/Extension/ScheduleRunner.php'=>'120ce1743e71fbc6aca6dcd5155650d614eff3a28478275019a19aac6f14c4a9',
        'administrator/components/com_scheduler/src/Scheduler/Scheduler.php'=>'7d03483d6c359c8d3bd70af5871a245408065a0bcaadeff27214d584b15e1d25',
        'administrator/components/com_scheduler/src/Model/TaskModel.php'=>'87ebacd75339c9a2cf74679931855b1080cbded2539abd78a11aadcec2faa751',
        'administrator/components/com_scheduler/src/Task/Task.php'=>'867d6b636bde31abae178cd05c771e3482ca7b63af2b13a2ebd20c3948ede52d',
        'plugins/system/copymypage/src/Extension/CopyMyPage.php'=>'c18cf0de3a7929281545dea492655bb95d1df400150f7a55d0e15de205d7a4f2',
        'components/com_copymypage/src/Service/NativeCleanupTask.php'=>'76da8d57f7c02599198e81742d27fc620b623573b9545f37e81dc7330be4b2ab',
        'components/com_copymypage/src/Service/NativeCleanupMVCFactoryProvider.php'=>'73ad524dfb4b2f58e10751a2429f6cc6f7742360abc6772ee360a62c2c1c13b9',
        'components/com_copymypage/src/Service/ReservationCleanupService.php'=>'ad0dc9592b4c0a6ed74d00e0810fab2f046e20581c2a9fbf6dd806860db9da33',
        'plugins/task/dpcalendar/src/Extension/DPCalendar.php'=>'e4b26fbbf23fcac743e56aa28c9679e014011e14f34542250ac13338f3d1fa14',
        'plugins/task/copymypage/src/Extension/CopyMyPage.php'=>'82647e77af05bbf3f4cedd170ca89a2d746e4b07e1d163a37dd14e829981e8e9',
    ];
    return $files;
}
function verifySourceFiles(string $root, array $files): void {
    foreach($files as $path=>$hash) if(!hash_equals($hash,(string)@hash_file('sha256',$root.'/'.$path))) throw new RuntimeException('qualified_source');
}
function qualifySources(?string $root = null): void {
    $root ??= config()['joomla_root'];
    verifySourceFiles($root, sourceGuards());
    if(!defined('_JEXEC'))define('_JEXEC',1);
    if(!defined('JPATH_ROOT'))define('JPATH_ROOT',$root);
    if (JPATH_ROOT !== $root) throw new RuntimeException('qualified_source');
    require_once $root.'/components/com_copymypage/src/Service/PaymentIntegrationContract.php';
    Joomla\Component\CopyMyPage\Site\Service\PaymentIntegrationContract::assertQualified();
}
function executeObservedTask(array $before, int $id, string $phase, int $seconds, callable $snap,
    callable $request, callable $emit, callable $clock, callable $begin, callable $complete): int {
    $step = $clock(); $emit(['event'=>'task_start','phase'=>$phase,'task_id'=>$id]);
    try {
        // Durable intent precedes the HTTP request. An uncertain result leaves it in place.
        $begin($before, $id);
        $request($id, $seconds);
        $after = $snap(); $native = assess($before, $after, $id);
        $complete();
    } catch (Throwable $e) {
        $emit(['event'=>'task_end','phase'=>$phase,'task_id'=>$id,'result'=>safeReason($e),'duration_s'=>round($clock()-$step,3)]);
        throw $e;
    }
    $emit(['event'=>'task_end','phase'=>$phase,'task_id'=>$id,
        'result'=>$native===0?'success':($native===123?'resume_pending':'native_failure'),
        'native_exit'=>$native,'native_log_id'=>(int)$after['logs'][0]['id'],'duration_s'=>round($clock()-$step,3)]);
    return $native;
}
function runCycle(callable $snap, callable $request, callable $emit, callable $clock, callable $saveCursor,
    int $cursor, int $budget, int $limit, array $timeouts, callable $hasPending, callable $begin, callable $complete): int {
    $start=$clock(); $count=0; $errors=0; $attempted=[]; $remaining=null; $status='success'; $code=0;
    $emit(['event'=>'cycle_start','budget_s'=>$budget,'max_general'=>$limit]);
    try {
        if ($hasPending()) throw new RuntimeException('pending_request');
        $before=$snap();
        if ($before['locked'] !== 0) throw new RuntimeException('native_lock');
        $payment = $before['payment_id'];
        $emit(['event'=>'recovery_policy','result'=>$payment===null?'excluded':'explicitly_enabled','task_id'=>$payment]);
        if ($payment !== null && $before['tasks'][$payment]['next_execution'] !== null
            && dueTimestamp($before['tasks'][$payment]['next_execution']) <= $before['now']) {
            if ($clock()-$start >= $budget-25) { $status='budget_reached'; $code=30; return $code; }
            $native = executeObservedTask($before, $payment, 'payment', min($timeouts['payment'], max(1,$budget-(int)($clock()-$start)-20)),
                $snap, $request, $emit, $clock, $begin, $complete);
            if ($native !== 0) { $status='payment_failure'; $code=20; return $code; }
        }
        while (true) {
            $before=$snap(); $remaining=count($before['due']);
            if ($before['locked'] !== 0) throw new RuntimeException('native_lock');
            $id=choose($before,$attempted,$cursor);
            if ($id === null) { $emit(['event'=>'general_queue','result'=>$remaining?'attempted_backlog':'no_due_task','remaining_due'=>$remaining]); break; }
            if ($count >= $limit || $clock()-$start >= $budget-25) { $status='budget_reached'; $code=30; break; }
            if ($before['tasks'][$id]['type'] === CMP_PAYMENT || !in_array($id,$before['due'],true)) throw new RuntimeException('request_guard');
            $attempted[]=$id;
            // A persistently failing general task must not monopolise later cycles.
            // This private cursor records an attempt, never a claimed native success.
            $cursor=$id; $saveCursor($cursor);
            $native = executeObservedTask($before, $id, 'general', min($timeouts['general'], max(1,$budget-(int)($clock()-$start)-20)),
                $snap, $request, $emit, $clock, $begin, $complete);
            $count++;
            if (!in_array($native,[0,123],true)) $errors++;
        }
        if ($errors && $code === 0) { $status='general_failure'; $code=21; }
    } catch (Throwable $e) { $status=safeReason($e); $code=22; $emit(['event'=>'cycle_error','result'=>$status]); }
    finally { $emit(['event'=>'cycle_end','result'=>$status,'exit'=>$code,'general_completed'=>$count,'general_errors'=>$errors,'remaining_due'=>$remaining,'duration_s'=>round($clock()-$start,3)]); }
    return $code;
}

function preflight(): array {
    qualifySources();
    $guards=(new ReflectionClass(Joomla\Component\CopyMyPage\Site\Service\PaymentIntegrationContract::class))->getConstant('QUALIFIED');
    $hash=hash_file('sha256',config()['joomla_root'].'/components/com_copymypage/src/Service/PaymentReconciliationService.php');
    if ($hash !== CMP_FIX || count($guards)!==9) throw new RuntimeException('qualified_source');
    $s = snapshot(false);
    return ['utc'=>gmdate('c'),'php'=>PHP_VERSION,'enabled'=>config()['enabled'], 'config_sha256'=>config()['_sha256'],
        'payment_recovery_enabled'=>config()['payment_recovery']['enabled'], 'task_bindings'=>config()['tasks'], 'fix_sha256'=>$hash,
        'source_guards'=>count(sourceGuards())+count($guards),'pending_request'=>pendingExists(CMP_BASE.'/pending-request.json'),
        'scheduler'=>$s];
}
function recoveryPreflight(): array {
    // Retain the old read-only sandbox/financial evidence separately from the
    // ordinary infrastructure preflight. Never invoked by the regular cron.
    if (!config()['payment_recovery']['enabled']) throw new RuntimeException('payment_config');
    $evidence = preflight(); $evidence['scheduler'] = snapshot(); $db = database();
    try {
        $prefix = (new JConfig())->dbprefix; $ext = tableName($prefix, 'extensions');
        $provider=json_decode(rows($db,"SELECT params FROM $ext WHERE type='plugin' AND folder='dpcalendarpay' AND element='copymypagepaypal' AND enabled=1")[0]['params']??'',true,512,JSON_THROW_ON_ERROR);
        $active=array_values(array_filter($provider['providers']??[],fn($p)=>(int)($p['state']??0)===1));
        $sandbox=count($active)>0&&!array_filter($active,fn($p)=>(int)($p['sandbox']??0)!==1);
        if(!$sandbox)throw new RuntimeException('site_config');
        $evidence['provider_sandbox']=true;
        $evidence['versions']=rows($db,"SELECT element,JSON_UNQUOTE(JSON_EXTRACT(manifest_cache,'$.version')) version FROM $ext WHERE type='component' AND element IN ('com_copymypage','com_dpcalendar')");
        $attempts = tableName($prefix, 'copymypage_payment_attempts');
        $evidence['financial']=rows($db,"SELECT booking_id,state,LEFT(SHA2(CONCAT_WS('|',id,booking_id,cart_id,owner_id,state,capture_id,provider_status,provider_evidence,captured_at,finalized_at,manual_reason),256),32) financial_fp FROM $attempts ORDER BY booking_id");
        foreach (['dpcalendar_bookings','dpcalendar_tickets','copymypage_event_seats','copymypage_mail_outbox'] as $table) {
            $records=rows($db,'SELECT * FROM '.tableName($prefix,$table).' ORDER BY id');
            $fps=[]; $states=[];
            foreach($records as $r) { $fps[(int)$r['id']]=hash('sha256',serialize($r)); $state=(string)($r['state']??$r['status']??'unknown');$states[$state]=($states[$state]??0)+1; }
            $evidence['integrity'][$table]=['count'=>count($records),'states'=>$states,'row_fingerprints'=>$fps];
        }
        return $evidence;
    } finally { $db->rollback();$db->close(); }
}
if (realpath($_SERVER['SCRIPT_FILENAME']??'') !== __FILE__) return;
if (PHP_SAPI !== 'cli') { http_response_code(404);exit(2); }
ini_set('display_errors','0');
umask(0077);
$canLog = false;
try {
    if (($argv[1]??'') === 'config-shell') {
        $c = config();
        echo implode("\n", [$c['binaries']['flock'], $c['binaries']['timeout'], $c['runtime']['hard_timeout_seconds'],
            $c['runtime']['kill_grace_seconds'], $c['_sha256']])."\n";
        exit(0);
    }
    if (($argv[1]??'') === 'config-check') {
        readConfig(CMP_BASE.'/cron-config.json'); echo "{\"config_schema\":\"valid\"}\n"; exit(0);
    }
    if (($argv[1]??'') === 'preflight') { echo json_encode(preflight(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";exit(0); }
    if (($argv[1]??'') === 'preflight-recovery') { echo json_encode(recoveryPreflight(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";exit(0); }
    if (($argv[1]??'') !== 'run') throw new RuntimeException('request_guard');
    $c = config();
    if (!$c['enabled']) throw new RuntimeException('config_disabled');
    if (!hash_equals($c['_sha256'], (string)getenv('CMP_CRON_CONFIG_SHA256'))) throw new RuntimeException('wrapper_required');
    // Use the inherited, already flocked descriptor; direct PHP starts fail closed.
    $lock = @fopen('php://fd/9', 'a');
    $stat = is_resource($lock) ? fstat($lock) : false; $expected = stat(CMP_BASE.'/joomla-webcron.lock');
    if (!$stat || !$expected || $stat['ino'] !== $expected['ino'] || $stat['dev'] !== $expected['dev']
        || !flock($lock, LOCK_EX|LOCK_NB)) throw new RuntimeException('wrapper_required');
    $canLog = true;
    qualifySources();
    $run=gmdate('Ymd\THis\Z').'-'.getmypid().'-'.bin2hex(random_bytes(3));
    $emit=static function(array $row) use($run):void {
        $line=json_encode(['utc'=>gmdate('Y-m-d\TH:i:s\Z'),'run'=>$run]+$row,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
        privatePath(CMP_BASE.'/webcron-executions.log', false, true);
        if (file_put_contents(CMP_BASE.'/webcron-executions.log',$line,FILE_APPEND|LOCK_EX)!==strlen($line)) throw new RuntimeException('log_unavailable');
        echo $line;
    };
    $cursorFile=CMP_BASE.'/general-cursor'; $raw=is_file($cursorFile)?file_get_contents($cursorFile):'0';
    if (!is_string($raw)||!preg_match('/^\d{1,10}$/D',$raw)) throw new RuntimeException('cursor_storage');
    $save=static function(int $id) use($cursorFile):void {
        $tmp=tempnam(CMP_BASE,'.central-cursor-');
        if ($tmp===false) throw new RuntimeException('cursor_storage');
        chmod($tmp,0600);
        if(file_put_contents($tmp,(string)$id)===false||!rename($tmp,$cursorFile)) { @unlink($tmp);throw new RuntimeException('cursor_storage'); }
    };
    $r = $c['runtime']; $pending = CMP_BASE.'/pending-request.json';
    exit(runCycle('snapshot','requestTask',$emit,static fn()=>hrtime(true)/1e9,$save,(int)$raw,
        $r['cycle_budget_seconds'], $r['max_general_tasks'], ['general'=>$r['general_timeout_seconds'],'payment'=>$r['payment_timeout_seconds']],
        static fn()=>pendingExists($pending), static fn($s,$id)=>recordPending($pending,$s,$id), static fn()=>completePending($pending)));
} catch(Throwable $e) {
    // Invalid config/preflight must never create private state or expose exception details.
    $line=json_encode(['utc'=>gmdate('c'),'event'=>'fatal','result'=>safeReason($e)])."\n";
    if ($canLog) {
        try { privatePath(CMP_BASE.'/webcron-executions.log', false, true);
            file_put_contents(CMP_BASE.'/webcron-executions.log',$line,FILE_APPEND|LOCK_EX);
        } catch(Throwable $ignored) { /* stdout remains the independent cron evidence */ }
    }
    echo $line; exit(22);
}
