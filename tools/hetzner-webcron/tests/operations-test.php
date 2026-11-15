<?php
declare(strict_types=1);
require dirname(__DIR__).'/scheduler-central.php';
// Only fresh temporary files, synthetic tasks and injected HTTP transports.
if (($argv[1] ?? '') === '--writer') {
    for ($i=0;$i<160;$i++) opsEvent($argv[2],['event'=>'synthetic','writer'=>(int)$argv[3],'seq'=>$i,'padding'=>str_repeat('x',900)]);
    exit(0);
}
$checks=0;
function expect(bool $ok, string $name): void {
    global $checks; if (!$ok) throw new RuntimeException('FAIL: '.$name); $checks++;
}
function rejects(callable $work, string $reason): void {
    try { $work(); throw new LogicException('accepted '.$reason); }
    catch (RuntimeException $e) { expect($e->getMessage()===$reason,$reason); }
}
function settings(string $base, array $changes=[]): void {
    $c = ['max_bytes'=>65536,'archives'=>64,'retention_days'=>30,'stale_seconds'=>900,'monitor_enabled'=>false];
    file_put_contents($base.'/operations-config.json',json_encode(array_replace($c,$changes))); chmod($base.'/operations-config.json',0600);
}
function finishCycle(string $base, string $run, string $result='success', int $errors=0, int|null $due=0): void {
    opsEvent($base,['run'=>$run,'event'=>'cycle_end','result'=>$result,'general_errors'=>$errors,'remaining_due'=>$due,'exit'=>$result==='site_offline'?10:0]);
}
$base=sys_get_temp_dir().'/cmp-cron-operations-'.bin2hex(random_bytes(8));
if (!mkdir($base,0700)) throw new RuntimeException('fixture_create');
try {
    settings($base);
    expect(opsHealth($base)['reason']==='missing_runs','initial missing heartbeat');
    opsStart($base,'good'); finishCycle($base,'good');
    expect(opsFinish($base,'good','success',0,true,1),'successful finish');
    expect(opsHealth($base)['healthy'],'current successful run');
    expect(opsHealth($base,time()+901)['reason']==='missing_runs','missed schedule');
    expect(opsHealth($base,time()-10)['reason']==='missing_runs','clock regression');
    opsStart($base,'offline'); finishCycle($base,'offline','site_offline',0,null);
    expect(opsFinish($base,'offline','site_offline',0,true,0) && opsHealth($base)['healthy'],'offline is a known suspension');
    opsStart($base,'active');
    expect(opsHealth($base,time()+271)['reason']==='run_timeout','unfinished run timeout');
    opsFinish($base,'collision','lock_busy',75,false,0);
    expect(opsReadState($base)['run']==='active' && isset(opsReadState($base)['started']),'collision preserves active run');
    finishCycle($base,'active'); opsFinish($base,'active','success',0,true,1);
    expect(opsHealth($base)['healthy'],'subsequent success resolves collision even within the same second');
    foreach (['native_failure','resume_pending','https_uncertain'] as $problem) {
        opsStart($base,$problem); opsEvent($base,['run'=>$problem,'event'=>'task_end','result'=>$problem]);
        finishCycle($base,$problem);
        expect(!opsFinish($base,$problem,'success',0,true,1),'task problem survives success footer '.$problem);
        expect(opsHealth($base)['reason']==='run_failed','task failure health '.$problem);
    }
    foreach ([20,21,22,30,124,137] as $exit) {
        $run='exit-'.$exit; opsStart($base,$run); finishCycle($base,$run);
        expect(!opsFinish($base,$run,'runner_error',$exit,true,1),'exit '.$exit.' unhealthy');
    }
    opsStart($base,'backlog'); finishCycle($base,'backlog','success',0,1);
    expect(!opsFinish($base,'backlog','success',0,true,1),'remaining due tasks alert');
    opsStart($base,'errors'); finishCycle($base,'errors','success',1,0);
    expect(!opsFinish($base,'errors','success',0,true,1),'general errors alert');
    opsStart($base,'no-evidence');
    expect(!opsFinish($base,'no-evidence','success',0,true,1),'no native cycle evidence is unhealthy');
    opsStart($base,'pending'); finishCycle($base,'pending'); file_put_contents($base.'/pending-request.json','unknown capture/task outcome');
    expect(!opsFinish($base,'pending','success',0,true,1) && opsHealth($base)['reason']==='pending_request','pending guard takes priority');
    expect(file_get_contents($base.'/pending-request.json')==='unknown capture/task outcome','pending bytes preserved'); unlink($base.'/pending-request.json');
    // Health is a read-only command: bytes and directory contents stay unchanged.
    $before=[]; foreach (glob($base.'/*') as $file) $before[$file]=hash_file('sha256',$file);
    opsHealth($base);
    $after=[]; foreach (glob($base.'/*') as $file) $after[$file]=hash_file('sha256',$file);
    expect($before===$after,'health has no writes');
    $sent=[]; $transport=static function(string $input) use(&$sent): bool { $sent[]=$input; return true; };
    expect(opsPing($base,'success',$transport)==='disabled' && $sent===[],'monitor opt-in');
    settings($base,['monitor_enabled'=>true]);
    file_put_contents($base.'/monitoring.url','https://monitor.example.invalid/ping/12345678-1234-1234-1234-123456789012'); chmod($base.'/monitoring.url',0600);
    foreach (['success','fail'] as $signal) expect(opsPing($base,$signal,$transport)==='sent','monitor '.$signal);
    expect(!str_contains($sent[0],'/fail') && str_contains($sent[1],'/fail"'),'Healthchecks URL protocol');
    // Finish can exercise delivery errors without invoking curl or real network I/O.
    settings($base); opsStart($base,'delivery'); finishCycle($base,'delivery'); settings($base,['monitor_enabled'=>true]);
    rejects(static fn()=>opsFinish($base,'delivery','success',0,true,1,static fn()=>false),'monitor_transport');
    expect(opsHealth($base)['reason']==='monitor_transport','delivery failure visible locally');
    expect(!str_contains((string)file_get_contents($base.'/webcron-executions.log'),'12345678-1234'),'monitor secret not logged');
    file_put_contents($base.'/monitoring.url','https://user:secret@monitor.example.invalid/');
    rejects(static fn()=>opsPing($base,'success',$transport),'monitor_config');
    settings($base,['max_bytes'=>1]); rejects(static fn()=>opsSettings($base),'operations_config'); settings($base);
    $lock=fopen($base.'/operations.lock','c'); flock($lock,LOCK_EX);
    $bytes=file_get_contents($base.'/webcron-executions.log');
    rejects(static fn()=>opsEvent($base,['event'=>'blocked']),'operations_lock');
    expect(file_get_contents($base.'/webcron-executions.log')===$bytes,'lock timeout preserves log bytes');
    fclose($lock); opsEvent($base,['event'=>'after_lock']);
    expect(str_contains((string)file_get_contents($base.'/webcron-executions.log'),'after_lock'),'retry after resolved log lock');
    file_put_contents($base.'/webcron-executions.log','synthetic interrupted record');
    opsEvent($base,['event'=>'after_partial']);
    expect(json_decode(trim((string)file_get_contents($base.'/webcron-executions.log')),true)['event']==='after_partial','new line isolated from interrupted record');
    expect((bool)array_filter(array_keys(opsArchives($base)),static fn($file)=>file_get_contents($file)==='synthetic interrupted record'),'interrupted evidence archived without truncation');
    // Destructive rotation cases affect only this newly-created synthetic directory.
    $old=$base.'/webcron-executions.20000101T000000Z-000000000000.log';
    file_put_contents($old,'expired synthetic archive'); chmod($old,0600); touch($old,time()-31*86400);
    opsEvent($base,['event'=>'retention']); expect(!file_exists($old),'retention pruning');
    $unrelated=$base.'/webcron-executions.keep.log'; file_put_contents($unrelated,'unrelated');
    opsEvent($base,['event'=>'unrelated']); expect(file_get_contents($unrelated)==='unrelated','unrelated files preserved');
    unlink($unrelated);
    // Reset only synthetic logs, so exact coverage can be measured after rotation.
    foreach (array_keys(opsArchives($base)) as $file) unlink($file); unlink($base.'/webcron-executions.log');
    $workers=[];
    for ($i=0;$i<4;$i++) {
        $p=proc_open([PHP_BINARY,__FILE__,'--writer',$base,(string)$i],[0=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($p)) throw new RuntimeException('worker_start');
        $workers[]=[$p,$pipes];
    }
    foreach ($workers as [$p,$pipes]) {
        $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        expect(proc_close($p)===0 && $stdout==='' && $stderr==='','concurrent writer completes');
    }
    $seen=[]; $logs=array_merge(array_keys(opsArchives($base)),[$base.'/webcron-executions.log']);
    expect(count($logs)>1,'actual rotation occurred');
    foreach ($logs as $file) {
        clearstatcache(true,$file); expect(filesize($file)<=65536,'segment size bound');
        foreach (file($file,FILE_IGNORE_NEW_LINES) as $line) {
            $row=json_decode($line,true,512,JSON_THROW_ON_ERROR); $key=$row['writer'].':'.$row['seq'];
            expect(!isset($seen[$key]),'no duplicated record'); $seen[$key]=true;
        }
    }
    expect(count($seen)===640,'all concurrent records survive rotation');
    settings($base,['archives'=>2]); opsEvent($base,['event'=>'limit']);
    expect(count(opsArchives($base))===2,'archive count limit');
    $cfg=json_decode((string)file_get_contents(dirname(__DIR__).'/cron-config.json.example'),true);
    $legacy=$cfg; unset($legacy['native_cleanup']); expect(!validateConfig($legacy)['native_cleanup']['enabled'],'legacy config deletion disabled');
    $bad=$cfg; $bad['native_cleanup']['enabled']=true; rejects(static fn()=>validateConfig($bad),'cleanup_policy');
    $task=static fn(int $id,string $type): array=>['id'=>$id,'type'=>$type,'state'=>1,'cli_exclusive'=>0,'created_by'=>1,
        'params'=>'{"deletions_date":"-30 days","deletions_date_type":1,"deletions_states":[3]}',
        'execution_rules'=>'{}','cron_rules'=>'{}','last_execution'=>null,'next_execution'=>'2000-01-01 00:00:00','locked'=>null];
    $tasks=[$task(5,CMP_NATIVE),$task(6,CMP_CARTS),$task(4,CMP_PAYMENT)];
    $inventory=taskInventory($tasks,time(),false,$cfg['tasks'],$cfg['native_cleanup']);
    expect($inventory['due']===[6],'cleanup and payment excluded; seat release remains');
    expect($inventory['tasks'][5]['deletion_criteria']['deletions_states']===[3],'criteria available for read-only review');
    $policy=['enabled'=>true,'approved_definition_sha256'=>$inventory['tasks'][5]['definition_hash']];
    expect(taskInventory($tasks,time(),false,$cfg['tasks'],$policy)['due']===[5,6],'approved unchanged native task');
    $tasks[0]['created_by']=2; rejects(static fn()=>taskInventory($tasks,time(),false,$cfg['tasks'],$policy),'cleanup_policy');
    file_put_contents($base.'/source.php','known revision');
    $known=hash_file('sha256',$base.'/source.php');
    verifySourceFiles($base,['source.php'=>[str_repeat('0',64),$known]]);
    expect(true,'exact source allow-list accepts reviewed revision');
    file_put_contents($base.'/source.php','unknown revision');
    rejects(static fn()=>verifySourceFiles($base,['source.php'=>[str_repeat('0',64),$known]]),'qualified_source');
    echo "OK: $checks operations/rotation/policy assertions; 640 concurrent records, synthetic files only, HTTP simulated\n";
} finally {
    // A unique flat fixture; do not follow links or recurse outside the Temp root.
    foreach (new DirectoryIterator($base) as $entry) if (!$entry->isDot() && ($entry->isFile() || $entry->isLink())) unlink($entry->getPathname());
    rmdir($base);
}
