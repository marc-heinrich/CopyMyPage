<?php
declare(strict_types=1);
// Synthetic local fixtures only: no Joomla installation, database, HTTP or mail.
require dirname(__DIR__).'/scheduler-central.php';

if (($argv[1] ?? '') === '--site-config') {
    try { echo json_encode(['site_offline'=>(bool)siteConfig($argv[2])->offline], JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { echo json_encode(['error'=>safeReason($e)], JSON_THROW_ON_ERROR); exit(22); }
    exit(0);
}

$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function child(array $args): array {
    $process = proc_open(array_merge([PHP_BINARY], $args), [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('child unavailable');
    fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
    check($err === '', 'unexpected child stderr: '.$err);
    return [$code, json_decode($out, true, 512, JSON_THROW_ON_ERROR)];
}
function writeSite(string $root, string $offline, string $db = 'fixture'): void {
    file_put_contents($root.'/configuration.php', '<?php class JConfig {'
        .'public $offline = '.$offline.'; public $dbprefix = "fx_"; public $db = "'.$db.'";'
        .'public $dbtype = "mysqli"; public $host = "fixture.invalid";'
        .'public $user = "synthetic"; public $password = "synthetic"; public $dbencryption = 0; }');
}
function state(): array {
    $task = ['id'=>7,'type'=>'synthetic.task','state'=>1,'cli_exclusive'=>0,'locked'=>null,
        'created_by'=>1,'params'=>'{}','execution_rules'=>'{}','cron_rules'=>'{}',
        'last_execution'=>'2026-01-01 00:00:00','next_execution'=>'2026-01-02 00:00:00',
        'last_exit_code'=>0,'times_executed'=>0,'times_failed'=>0];
    $s = taskInventory([$task], strtotime('2026-01-03 UTC'), false);
    return $s + ['site_offline'=>false,'max_log'=>0];
}
function cycle(array $s, string $pending, ?callable $request = null, ?callable $snap = null): array {
    $events = []; $requests = []; $cursors = []; $begins = 0; $completed = 0; $snapshots = 0;
    $snapshot = static function() use (&$s, &$snapshots, $snap): array {
        $snapshots++; return $snap ? $snap($s) : $s;
    };
    $send = static function(int $id, int $seconds) use (&$s, &$requests, $request): void {
        $requests[] = [$id,$seconds];
        if ($request) { $request($s); return; }
        $s['tasks'][$id]['times_executed']++;
        $s['tasks'][$id]['last_execution'] = gmdate('Y-m-d H:i:s', $s['now']);
        $s['tasks'][$id]['next_execution'] = '2026-01-04 00:00:00';
        $s['logs'] = [['id'=>1,'jobid'=>$id,'exitcode'=>0,'lastdate'=>gmdate('Y-m-d H:i:s', $s['now']),'duration'=>0]];
        $s['max_log'] = 1; $s['due'] = [];
    };
    $exit = runCycle($snapshot, $send, static function($row) use (&$events): void { $events[]=$row; },
        static fn()=>0.0, static function($id) use (&$cursors): void { $cursors[]=$id; },
        0, 210, 6, ['general'=>45,'payment'=>90], static fn()=>pendingExists($pending),
        static function($before,$id) use ($pending,&$begins): void { $begins++; recordPending($pending,$before,$id); },
        static function() use ($pending,&$completed): void { $completed++; unlink($pending); });
    return compact('exit','events','requests','cursors','begins','completed','snapshots');
}
function ended(array $result, int $exit, string $status): array {
    $end = $result['events'][array_key_last($result['events'])];
    check($result['exit'] === $exit && $end['exit'] === $exit && $end['result'] === $status, $status.' exit/log');
    check($end['event'] === 'cycle_end', $status.' cycle_end');
    return $end;
}
// Replace environment boundaries in a temporary copy, leaving snapshot/preflight
// and their SQL/validation unchanged. Hash qualification gets a synthetic source
// set; DB rows come from an in-memory mysqli subclass with no connection.
function replaceFunction(string $source, string $name, string $replacement): string {
    $tokens = token_get_all($source); $out = ''; $replaced = 0;
    for ($i=0; $i<count($tokens); $i++) {
        $token = $tokens[$i];
        if (is_array($token) && $token[0] === T_FUNCTION) {
            $j=$i+1; while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
            if (is_array($tokens[$j]) && $tokens[$j][1] === $name) {
                while ($tokens[$j] !== '{') $j++;
                $depth=1;
                while ($depth) { $j++; if ($tokens[$j] === '{') $depth++; if ($tokens[$j] === '}') $depth--; }
                $out .= $replacement; $i=$j; $replaced++; continue;
            }
        }
        $out .= is_array($token) ? $token[1] : $token;
    }
    check($replaced === 1, 'fixture boundary '.$name);
    return $out;
}

$temp = sys_get_temp_dir().'/cmp-webcron-'.bin2hex(random_bytes(8));
mkdir($temp, 0700); $pending = $temp.'/pending-request.json';
$directories=['components','components/com_copymypage','components/com_copymypage/src','components/com_copymypage/src/Service'];
try {
    // Fresh processes prove online -> offline -> online without runner state.
    foreach (['false'=>false,'true'=>true,'0'=>false,'1'=>true,"'0'"=>false,"'1'"=>true,'false '=>false] as $value=>$offline) {
        writeSite($temp, (string)$value);
        [$code,$data] = child([__FILE__,'--site-config',$temp]);
        check($code === 0 && $data === ['site_offline'=>$offline], 'JConfig value '.$value);
    }
    foreach (['null','""','2','[]','"offline"'] as $invalid) {
        writeSite($temp, $invalid); [$code,$data] = child([__FILE__,'--site-config',$temp]);
        check($code === 22 && $data === ['error'=>'site_config'], 'invalid offline '.$invalid);
    }
    writeSite($temp, 'true', ''); [$code,$data] = child([__FILE__,'--site-config',$temp]);
    check($code === 22 && $data['error'] === 'site_config', 'invalid DB despite offline');
    foreach (['<?php class JConfig { broken', '<?php class JConfig {}', '<?php class OtherConfig {}'] as $invalid) {
        file_put_contents($temp.'/configuration.php', $invalid);
        [$code,$data] = child([__FILE__,'--site-config',$temp]);
        check($code === 22 && $data['error'] === 'site_config', 'malformed/missing JConfig');
    }
    unlink($temp.'/configuration.php'); [$code,$data] = child([__FILE__,'--site-config',$temp]);
    check($code === 22 && $data['error'] === 'site_config', 'missing configuration');

    foreach ([false,true,false] as $offline) {
        $s=state(); $s['site_offline']=$offline; $r=cycle($s,$pending);
        $end=ended($r,$offline?10:0,$offline?'site_offline':'success');
        check(count($r['requests']) === ($offline?0:1) && $r['begins'] === ($offline?0:1), 'request count');
        check($end['general_completed'] === ($offline?0:1) && $end['general_errors'] === 0, 'counters');
        check(!pendingExists($pending), 'no pending after known result');
        if ($offline) check($r['cursors'] === [] && $r['snapshots'] === 1, 'offline no cursor or extra snapshot');
    }
    $s=state(); $s['site_offline']=true; $s['payment_id']=7;
    $r=cycle($s,$pending); ended($r,10,'site_offline'); check($r['requests'] === [], 'offline blocks payment phase');
    file_put_contents($pending, 'existing unresolved request');
    $r=cycle($s,$pending); ended($r,22,'pending_request');
    check($r['snapshots'] === 0 && $r['requests'] === [] && file_get_contents($pending) === 'existing unresolved request', 'pending priority/preservation');
    unlink($pending);
    $s['locked']=1; $r=cycle($s,$pending); ended($r,22,'native_lock'); check($r['begins'] === 0, 'native lock priority');
    foreach (['scheduler_config','site_config','qualified_source'] as $reason) {
        $r=cycle($s,$pending,null,static function() use ($reason): never { throw new RuntimeException($reason); });
        ended($r,22,$reason); check($r['requests'] === [], $reason.' blocks requests');
    }
    $r=cycle(state(),$pending,static function(&$s): void { $s['site_offline']=true; checkResponse(22,'503',''); });
    ended($r,22,'https_uncertain'); check(count($r['requests']) === 1 && pendingExists($pending), '503 no retry, durable pending'); unlink($pending);
    $r=cycle(state(),$pending,static function(&$s): void {
        $s['site_offline']=true; $s['max_log']=1;
        $s['logs']=[['id'=>1,'jobid'=>7,'exitcode'=>0,'lastdate'=>gmdate('Y-m-d H:i:s',$s['now'])]];
        $s['tasks'][7]['last_execution']=gmdate('Y-m-d H:i:s',$s['now']); // missing execution increment
    });
    ended($r,22,'history_mismatch'); check(pendingExists($pending), 'offline cannot hide native mismatch'); unlink($pending);

    file_put_contents($temp.'/guard.php', 'synthetic qualified source'); $hash=hash_file('sha256',$temp.'/guard.php');
    verifySourceFiles($temp,['guard.php'=>$hash]);
    try { verifySourceFiles($temp,['guard.php'=>str_repeat('0',64)]); check(false,'bad source accepted'); }
    catch (RuntimeException $e) { check(safeReason($e) === 'qualified_source','real hash guard'); }
    $lock1=fopen($temp.'/lock','a'); $lock2=fopen($temp.'/lock','a');
    check(flock($lock1,LOCK_EX|LOCK_NB) && !flock($lock2,LOCK_EX|LOCK_NB), 'exclusive native PHP lock');
    fclose($lock1); check(flock($lock2,LOCK_EX|LOCK_NB), 'lock released'); fclose($lock2);

    $source=file_get_contents(dirname(__DIR__).'/scheduler-central.php');
    $source=replaceFunction($source,'config','function config(): array { return $GLOBALS["fixture_config"]; }');
    $source=replaceFunction($source,'database','function database(): mysqli { siteConfig($GLOBALS["fixture_root"]); return new FixtureDatabase(); }');
    $source=replaceFunction($source,'qualifySources','function qualifySources(?string $root = null): void { verifySourceFiles($GLOBALS["fixture_root"],["guard.php"=>CMP_FIX]); }');
    $source=str_replace("const CMP_FIX = '".CMP_FIX."';", "const CMP_FIX = '".$hash."';", $source, $replacements);
    check($replacements === 1, 'synthetic qualification hash');
    file_put_contents($temp.'/runner.php',$source);
    copy(__DIR__.'/preflight-fixture.php',$temp.'/preflight-fixture.php');
    file_put_contents($temp.'/webcron.key','synthetic');
    file_put_contents($temp.'/cron-config.json','synthetic config');
    foreach ($directories as $directory) mkdir($temp.'/'.$directory,0700);
    file_put_contents($temp.'/components/com_copymypage/src/Service/PaymentReconciliationService.php','synthetic qualified source');
    foreach ([false,true,false] as $offline) {
        writeSite($temp,$offline?'true':'false');
        [$code,$data]=child([$temp.'/preflight-fixture.php']);
        check($code === 0 && $data['site_offline'] === $offline && $data['scheduler']['site_offline'] === $offline, 'preflight offline');
        check($data['scheduler']['scheduler']['ready'] && !$data['pending_request'], 'preflight scheduler preserved');
        check($data['fixture_writes'] === 0, 'preflight read only');
    }
    file_put_contents($pending,'unresolved'); writeSite($temp,'true');
    [$code,$data]=child([$temp.'/preflight-fixture.php']); check($code === 0 && $data['site_offline'] && $data['pending_request'], 'offline preflight keeps pending visible');
    check(file_get_contents($pending) === 'unresolved','preflight preserves pending bytes');
    file_put_contents($temp.'/guard.php','changed'); [$code,$data]=child([$temp.'/preflight-fixture.php']);
    check($code === 22 && $data['error'] === 'qualified_source','offline preflight qualification priority');
    echo "OK: $checks PHP fixture checks (no external I/O)\n";
} finally {
    // Remove only the known fixture files/directories under this unique root.
    $service=$temp.'/components/com_copymypage/src/Service/PaymentReconciliationService.php';
    if (is_file($service)) unlink($service);
    foreach (array_reverse($directories) as $directory) if (is_dir($temp.'/'.$directory)) rmdir($temp.'/'.$directory);
    foreach (new DirectoryIterator($temp) as $entry) if ($entry->isFile() && !$entry->isLink()) unlink($entry->getPathname());
    rmdir($temp);
}
