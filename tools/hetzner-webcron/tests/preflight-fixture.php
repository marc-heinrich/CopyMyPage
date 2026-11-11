<?php
declare(strict_types=1);
// Invoked only from a disposable copy prepared by offline-test.php.
namespace Joomla\Component\CopyMyPage\Site\Service {
    class PaymentIntegrationContract { const QUALIFIED = [1,2,3,4,5,6,7,8,9]; }
}
namespace {
    class FixtureRows extends \mysqli_result {
        public function __construct(private array $rows) {}
        public function fetch_all(int $mode = MYSQLI_NUM): array { return $this->rows; }
    }
    class FixtureDatabase extends \mysqli {
        public function __construct() {}
        public function query(string $query, int $result_mode = MYSQLI_STORE_RESULT): \mysqli_result|bool {
            if (str_starts_with($query,'SELECT params')) return new FixtureRows([['params'=>json_encode([
                'lazy_scheduler'=>['enabled'=>0],'webcron'=>['enabled'=>1,'key'=>'synthetic'],'task_logging'=>1])]]);
            if (str_starts_with($query,'SELECT element')) return new FixtureRows(array_map(
                static fn($element)=>['element'=>$element,'enabled'=>1], ['schedulerunner','tasknotification','copymypage']));
            if (str_starts_with($query,'SELECT id,type')) return new FixtureRows(array_map(static fn($id,$type)=>[
                'id'=>$id,'type'=>$type,'state'=>1,'cli_exclusive'=>0,'created_by'=>1,'params'=>'{}',
                'execution_rules'=>'{}','cron_rules'=>'{}','last_execution'=>null,'next_execution'=>null,
                'last_exit_code'=>0,'locked'=>null,'times_executed'=>0,'times_failed'=>0], [5,6], [CMP_NATIVE,CMP_CARTS]));
            if (str_starts_with($query,'SELECT id,jobid')) return new FixtureRows([]);
            throw new \RuntimeException('unexpected query');
        }
        public function rollback(int $flags = 0, ?string $name = null): bool { return true; }
        public function close(): true { return true; }
    }
    $fixture_root=__DIR__; $fixture_writes=0;
    $fixture_config=['joomla_root'=>__DIR__,'enabled'=>false,'_sha256'=>hash('sha256','synthetic config'),
        'payment_recovery'=>['enabled'=>false],'tasks'=>['native_cleanup'=>5,'seat_release'=>6,'system_tasks'=>'all_due']];
    require __DIR__.'/runner.php';
    // Both the file list and bytes must remain unchanged across preflight.
    $files=static function(): array {
        $result=[];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__,\FilesystemIterator::SKIP_DOTS)) as $file) {
            $result[$file->getPathname()]=hash_file('sha256',$file->getPathname());
        }
        ksort($result); return $result;
    };
    $before=$files();
    try {
        $data=preflight();
        if ($files() !== $before) $fixture_writes++;
        $data['fixture_writes']=$fixture_writes; echo json_encode($data,JSON_THROW_ON_ERROR);
    } catch (\Throwable $e) { echo json_encode(['error'=>safeReason($e)],JSON_THROW_ON_ERROR); exit(22); }
}
