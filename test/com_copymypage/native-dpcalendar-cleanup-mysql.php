<?php
/**
 * Native DPCalendar 10.12.4 deletion qualification; synthetic data, no network/mail.
 * --driver=mysqli (default) or --driver=mysql (PDO MySQL); --baseline reproduces the old PDO rejection.
 * CopyMyPage classes come from this repository; data always stays in the test DB.
 * --source-root=C:/wamp/www/joomla6[_test] selects installed native plugins (default: joomla6).
 * --checkout-only limits the run; --runner=.../scheduler-central.php also simulates the existing central runner.
 * Concurrency uses two real connections without launching helper processes.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
ob_start();
ini_set('display_errors','0'); ini_set('log_errors','0'); ini_set('zend.exception_ignore_args','1');
register_shutdown_function(static function(): void {$e=error_get_last();if($e && in_array($e['type'],[E_ERROR,E_COMPILE_ERROR],true)){fwrite(STDERR,$e['message']."\n");}});
set_exception_handler(static function(Throwable $e): void { fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n");exit(1); });
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','mail'] as $f) {
    if(function_exists($f)) { throw new RuntimeException('Network/mail boundary must be disabled.'); }
}
if(ini_get('allow_url_fopen')) { throw new RuntimeException('URL streams must be disabled.'); }
define('_JEXEC',1); define('JDEBUG',false); define('JVERSION','6.2.0');
define('JPATH_BASE','C:/wamp/www/joomla6_test');
require JPATH_BASE.'/includes/defines.php';
$loader=require JPATH_LIBRARIES.'/vendor/autoload.php';
require JPATH_ADMINISTRATOR.'/components/com_dpcalendar/vendor/autoload.php';
$sourceRoot189=getopt('', ['source-root:', 'driver:', 'baseline', 'checkout-only', 'runner:'])['source-root'] ?? 'C:/wamp/www/joomla6';
$sourceRoot189=realpath($sourceRoot189);
if(!in_array(strtolower(str_replace('\\','/',(string)$sourceRoot189)),['c:/wamp/www/joomla6','c:/wamp/www/joomla6_test'],true)){
    throw new RuntimeException('Unexpected installed source root.');
}
foreach(['Joomla\\CMS\\'=>JPATH_LIBRARIES.'/src',
 'Joomla\\Component\\CopyMyPage\\Site\\'=>dirname(__DIR__,2).'/src/com_copymypage/site/src',
 'Joomla\\Plugin\\System\\CopyMyPage\\'=>dirname(__DIR__,2).'/src/plg_system_copymypage/src',
 'DigitalPeak\\Component\\DPCalendar\\Administrator\\'=>JPATH_ADMINISTRATOR.'/components/com_dpcalendar/src',
 'DigitalPeak\\Component\\DPCalendar\\Site\\'=>JPATH_BASE.'/components/com_dpcalendar/src',
 'DigitalPeak\\Plugin\\Task\\DPCalendar\\'=>$sourceRoot189.'/plugins/task/dpcalendar/src',
 'DigitalPeak\\Plugin\\DPCalendarPay\\PayPal\\'=>$sourceRoot189.'/plugins/dpcalendarpay/paypal/src',
 'Joomla\\Component\\Scheduler\\Administrator\\'=>JPATH_ADMINISTRATOR.'/components/com_scheduler/src',
 'Joomla\\Component\\Actionlogs\\Administrator\\'=>JPATH_ADMINISTRATOR.'/components/com_actionlogs/src'] as $ns=>$path){$loader->addPsr4($ns,$path);}

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Component\CopyMyPage\Site\Service\ReservationCleanupService;
use Joomla\Component\CopyMyPage\Site\Service\NativeReservationService;

function verifyN189(bool $value,string $message): void { if(!$value){throw new RuntimeException($message);} $GLOBALS['assertionsN189']=($GLOBALS['assertionsN189']??0)+1; }

/** Keep the real configured reservation logger in memory; never write test logs. */
class CleanupMemoryLogger189 extends Joomla\CMS\Log\Logger {
    public function addEntry(Joomla\CMS\Log\LogEntry $entry) {
        $GLOBALS['cleanupMessages189'][]=$entry->message;
        $GLOBALS['cleanupLogFiles189'][]=$this->options['text_file']??'';
    }
}
class CleanupLog189 extends Joomla\CMS\Log\Log {
    public function __construct(){parent::__construct();}
}
$memoryLog189=new CleanupLog189();Joomla\CMS\Log\Log::setInstance($memoryLog189);
$memoryLog189->registerLogger('formattedtext',CleanupMemoryLogger189::class,true);

/** Exercise the installed vendor's capture mapping; only its HTTP transport is replaced. */
class CleanupPayPal189 extends DigitalPeak\Plugin\DPCalendarPay\PayPal\Extension\PayPal {
    public function finish189(object $booking): array {
        return $this->finishTransaction(['PayerID'=>'SYNTHETIC-PAYER'],$booking,
            (object)['sandbox'=>true,'rest_client_id'=>'SYNTHETIC','rest_secret'=>'SYNTHETIC']);
    }
}
class CleanupHttp189 implements DigitalPeak\ThinHTTP\ClientInterface {
    public int $captures=0;
    public function __construct(private bool $timeout){}
    public function get(string $url,?string $userOrToken=null,?string $password=null,array $headers=[],array $options=[]): stdClass {throw new RuntimeException('Unexpected GET.');}
    public function post(string $url,$body,?string $userOrToken=null,?string $password=null,array $headers=[],array $options=[]): stdClass {
        if($url==='https://api-m.sandbox.paypal.com/v1/oauth2/token'){return (object)['access_token'=>'SYNTHETIC','dp'=>(object)['info'=>(object)['http_code'=>200]]];}
        verifyN189($url==='https://api-m.sandbox.paypal.com/v2/checkout/orders/SYNTHETICORDER189/capture','Capture binding changed.');
        ++$this->captures;
        if($this->timeout){throw new RuntimeException('SYNTHETIC PRIVATE CAPTURE TIMEOUT');}
        return json_decode(json_encode(['id'=>'SYNTHETICORDER189','status'=>'COMPLETED',
            'payer'=>['payer_id'=>'SYNTHETIC','email_address'=>'synthetic@example.invalid'],
            'purchase_units'=>[['payments'=>['captures'=>[['id'=>'SYNTHETICCAPTURE189','status'=>'COMPLETED',
                'seller_receivable_breakdown'=>['gross_amount'=>['currency_code'=>'EUR','value'=>'195.00'],
                'paypal_fee'=>['value'=>'0.00'],'net_amount'=>['value'=>'195.00']]]]]]],
            'dp'=>['info'=>['http_code'=>201]]]),false);
    }
    public function put(string $url,$body,?string $userOrToken=null,?string $password=null,array $headers=[],array $options=[]): stdClass {throw new RuntimeException('Unexpected PUT.');}
    public function delete(string $url,?string $userOrToken=null,?string $password=null,array $headers=[],array $options=[]): stdClass {throw new RuntimeException('Unexpected DELETE.');}
    public function request(string $url,$body='',?string $userOrToken=null,?string $password=null,array $headers=[],array $options=[],string $method='get'): stdClass {throw new RuntimeException('Unexpected request.');}
}
class NativeAdmin189 extends Joomla\CMS\User\User {
    public function authorise($action,$assetname=null){return true;}
    public function getAuthorisedViewLevels(){return [1,2,3];}
}
class NativeMenu189 extends Joomla\CMS\Menu\AbstractMenu { public function load(){return true;} }
class NativeFixtureFactory189 implements Joomla\CMS\MVC\Factory\MVCFactoryInterface {
    public function __construct(private Joomla\CMS\MVC\Factory\MVCFactoryInterface $inner){}
    public function createModel($name,$prefix='',array $config=[]){
        $model=$this->inner->createModel($name,$prefix,$config);
        if($model){$model->getState();$model->setState('params',Joomla\CMS\Component\ComponentHelper::getParams('com_dpcalendar'));}
        return $model;
    }
    public function createTable($name,$prefix='',array $config=[]){return $this->inner->createTable($name,$prefix,$config);}
    public function createView($name,$prefix='',$type='',array $config=[]){return $this->inner->createView($name,$prefix,$type,$config);}
    public function createController($name,$prefix,array $config,Joomla\CMS\Application\CMSApplicationInterface $app,Joomla\Input\Input $input){return $this->inner->createController($name,$prefix,$config,$app,$input);}
}
class QuietNativeTask189 extends DigitalPeak\Plugin\Task\DPCalendar\Extension\DPCalendar {
    protected function logTask(string $message,string $priority='info'): void {}
}
class NativeCache189 implements Joomla\CMS\Cache\CacheControllerFactoryInterface {
    public function createCacheController($type='output',$options=[]): Joomla\CMS\Cache\CacheController {
        return new class extends Joomla\CMS\Cache\CacheController {
            public function __construct() {} public function clean(){return true;}
            public function contains($id,$group=null){return false;}
            public function get($id,$group=null){return false;} public function store($data,$id,$group=null){return true;}
        };
    }
}
function nativeApp189(Joomla\Database\DatabaseInterface $db, bool $realAcl = false, ?Joomla\Http\Http $statusHttp = null): array {
    Joomla\CMS\Factory::$dates=[];
    $container=new Joomla\DI\Container(); $container->share(DatabaseInterface::class,$db,true);
    $container->share('config',new Joomla\Registry\Registry(['secret'=>'SYNTHETIC-189','mailonline'=>false,'offset'=>'UTC','cache_path'=>JPATH_BASE.'/cache']),true);
    $dispatcher=new Joomla\Event\Dispatcher();
    $container->share(Joomla\Event\DispatcherInterface::class,$dispatcher,true);
    $container->share(Joomla\CMS\Form\FormFactoryInterface::class,new Joomla\CMS\Form\FormFactory(),true);
    $container->share(Joomla\CMS\Language\LanguageFactoryInterface::class,new Joomla\CMS\Language\LanguageFactory(),true);
    $container->share(Joomla\CMS\Cache\CacheControllerFactoryInterface::class,new NativeCache189(),true);
    $user=new NativeAdmin189();$user->id=189;$user->guest=0;$user->name='SYNTHETIC ADMIN';
    $container->share(Joomla\CMS\User\UserFactoryInterface::class,$realAcl ? new Joomla\CMS\User\UserFactory($db) : new class($user) implements Joomla\CMS\User\UserFactoryInterface {
        public function __construct(private Joomla\CMS\User\User $user){}
        public function loadUserById(int $id): Joomla\CMS\User\User{return $this->user;}
        public function loadUserByUsername(string $name): Joomla\CMS\User\User{return $this->user;}
    },true);
    if($realAcl){
        $user=new Joomla\CMS\User\User();
        Joomla\CMS\Access\Access::clearStatics();
    }
    Factory::$container=$container;
    $_SERVER['HTTP_HOST']='localhost';$_SERVER['SERVER_PORT']=80;$_SERVER['HTTPS']='off';
    $_SERVER['REQUEST_URI']='/native-cleanup-qualification/';$_SERVER['SCRIPT_NAME']='/index.php';
    $app=new Joomla\CMS\Application\SiteApplication(new Joomla\Input\Input(),
        new Joomla\Registry\Registry(['session'=>false,'mailonline'=>false,'offset'=>'UTC','cache_path'=>JPATH_BASE.'/cache']),null,$container);
    $app->setDispatcher($dispatcher);
    $app->setSession(new Joomla\CMS\Session\Session(new Joomla\Session\Storage\RuntimeStorage()));
    (new ReflectionProperty($app,'language'))->setValue($app,new Joomla\CMS\Language\Language('en-GB'));
    (new ReflectionProperty($app,'template'))->setValue($app,(object)['template'=>'cassiopeia','parent'=>'','params'=>new Joomla\Registry\Registry()]);
    $app->loadDocument(new Joomla\CMS\Document\HtmlDocument(['webAssetManager'=>new Joomla\CMS\WebAsset\WebAssetManager(new Joomla\CMS\WebAsset\WebAssetRegistry())]));
    $container->share(Joomla\CMS\Menu\MenuFactoryInterface::class,new class implements Joomla\CMS\Menu\MenuFactoryInterface {
        public function createMenu(string $client,array $options=[]): Joomla\CMS\Menu\AbstractMenu{return new NativeMenu189();}
    },true);
    $app->loadIdentity($user);Factory::$application=$app;Factory::$language=$app->getLanguage();
    (new ReflectionProperty(Joomla\CMS\Router\Route::class,'_router'))->setValue(null,[$app->getName()=>new Joomla\CMS\Router\Router()]);
    (new ReflectionProperty(Joomla\CMS\Plugin\PluginHelper::class,'plugins'))->setValue(null,[]);
    (new ReflectionProperty(Joomla\CMS\Component\ComponentHelper::class,'components'))->setValue(null,[
        'com_dpcalendar'=>new Joomla\CMS\Component\ComponentRecord(['id'=>1,'option'=>'com_dpcalendar','enabled'=>1,'params'=>'{"component_notifications":[],"booking_send_mail_paid":0,"booking_send_mail_new":0,"booking_send_mail_author":0,"booking_registration":0}']),
        'com_copymypage'=>new Joomla\CMS\Component\ComponentRecord(['id'=>2,'option'=>'com_copymypage','enabled'=>1,'params'=>'{}']),
        'com_menus'=>new Joomla\CMS\Component\ComponentRecord(['id'=>3,'option'=>'com_menus','enabled'=>1,'params'=>'{}']),
        'com_scheduler'=>new Joomla\CMS\Component\ComponentRecord(['id'=>4,'option'=>'com_scheduler','enabled'=>1,'params'=>'{}']),
    ]);
    if($statusHttp !== null){$container->share(Joomla\Component\CopyMyPage\Site\Service\NativePayPalOrderStatus::class,
        static fn($c)=>new Joomla\Component\CopyMyPage\Site\Service\NativePayPalOrderStatus($c->get(Joomla\Component\CopyMyPage\Site\Service\NativePayPalProviderRegistry::class),$statusHttp),true);}
    $container->registerServiceProvider(new Joomla\Component\CopyMyPage\Site\Service\PaymentReconciliationServiceProvider());
    $system=new Joomla\Plugin\System\CopyMyPage\Extension\CopyMyPage(['name'=>'copymypage','type'=>'system']);$system->setApplication($app);
    $dispatcher->addListener('onContentBeforeDelete',[$system,'guardNativeReservationDeletion']);
    $dispatcher->addListener('onContentAfterDelete',[$system,'onContentAfterDelete']);
    $provider=new Joomla\Component\CopyMyPage\Site\Service\NativeCleanupMVCFactoryProvider('\\DigitalPeak\\Component\\DPCalendar');
    $factory=(new ReflectionMethod($provider,'createMVCFactory'))->invoke($provider);
    $factory->setDatabase($db);$factory->setDispatcher($dispatcher);
    $factory->setUserFactory($container->get(Joomla\CMS\User\UserFactoryInterface::class));
    $factory->setFormFactory($container->get(Joomla\CMS\Form\FormFactoryInterface::class));
    $factory->setCacheControllerFactory(new NativeCache189());
    $factory=new NativeFixtureFactory189($factory);
    $component=new DigitalPeak\Component\DPCalendar\Administrator\Extension\DPCalendarComponent(
        new Joomla\CMS\Dispatcher\ComponentDispatcherFactory('\\DigitalPeak\\Component\\DPCalendar',$factory));
    $component->setMVCFactory($factory);
    $categories=new Joomla\CMS\Categories\CategoryFactory('\\DigitalPeak\\Component\\DPCalendar');$categories->setDatabase($db);
    $component->setCategoryFactory($categories);
    Joomla\CMS\Extension\ExtensionHelper::$extensions[Joomla\CMS\Extension\ComponentInterface::class]['dpcalendar']=$component;
    return [$app,$factory,$container,$user];
}

require JPATH_BASE.'/configuration.php';$cfg=new JConfig();
verifyN189($cfg->db==='joomla_db_test' && in_array($cfg->host,['localhost','127.0.0.1','::1'],true),'Unexpected test DB.');
$options=getopt('', ['source-root:', 'driver:', 'baseline', 'checkout-only', 'runner:']);
$driver=$options['driver'] ?? 'mysqli';
verifyN189(in_array($driver,['mysqli','mysql'],true),'Unsupported test driver.');
$driverClass=$driver==='mysql' ? Joomla\Database\Mysql\MysqlDriver::class : Joomla\Database\Mysqli\MysqliDriver::class;
$prefix='cmp189n_'.bin2hex(random_bytes(6)).'_';
verifyN189((bool)preg_match('/^cmp189n_[a-f0-9]{12}_$/D',$prefix),'Invalid isolated prefix.');
$connectionOptions=['host'=>$cfg->host,'user'=>$cfg->user,'password'=>$cfg->password,
 'database'=>$cfg->db,'prefix'=>$prefix,'utf8mb4'=>true];
$db=new $driverClass($connectionOptions);$db->connect();
echo 'DRIVER ', $db::class, '; CONNECTION ', $db->getConnection()::class, PHP_EOL;
foreach($db->setQuery('SHOW GRANTS')->loadColumn() as $grant) {
 verifyN189((bool)preg_match('/^GRANT (?:USAGE ON \*\.\*|.* ON `joomla_db_test`\.\*) TO /',$grant),'Unexpected test grants.');
}
$tables=['dpcalendar_bookings','dpcalendar_tickets','dpcalendar_events','dpcalendar_events_hosts','users',
 'copymypage_ticket_carts','copymypage_ticket_cart_items','copymypage_ticket_customers','copymypage_payment_attempts',
 'copymypage_event_seats','copymypage_event_seating','copymypage_seats','copymypage_seat_layouts','copymypage_layout_tables',
 'extensions','action_logs','modules','languages','categories','assets','usergroups','user_usergroup_map','viewlevels','scheduler_tasks'];
$created=[];
function insertN189(string $table,array $data): void {
    global $db;
    foreach($db->getTableColumns('#__'.$table,false) as $field=>$meta){
        if(array_key_exists($field,$data) || $meta->Default!==null || $meta->Null==='YES' || str_contains($meta->Extra,'auto_increment')){continue;}
        $data[$field]=preg_match('/int|decimal|float|double/',$meta->Type)?0:(preg_match('/date|time/',$meta->Type)?gmdate('Y-m-d H:i:s'):'');
    }
    $row=(object)$data;$db->insertObject('#__'.$table,$row);
}
function fixtureN189(int $eventCount = 1, int $owner = 189): void {
    global $db,$tables;
    foreach(array_reverse($tables) as $t){$db->setQuery('DELETE FROM #__'.$t)->execute();}
    $eventIds=range(19003,19002+$eventCount);
    foreach($eventIds as $eventId){insertN189('dpcalendar_events',['id'=>$eventId,'title'=>'SYNTHETIC NATIVE CLEANUP','start_date'=>'2027-12-01 18:00:00','end_date'=>'2027-12-01 22:00:00','created_by'=>$owner,'catid'=>0,'capacity'=>10,'capacity_used'=>0]);}
    insertN189('dpcalendar_bookings',['id'=>19001,'uid'=>'SYNTHETIC-189','user_id'=>$owner,'state'=>3,'price'=>65*$eventCount,'currency'=>'EUR','payment_provider'=>'paypal-73','token'=>'SYNTHETIC-TOKEN','transaction_id'=>'SYNTHETICORDER189','book_date'=>'2026-10-01 10:00:00','country'=>0,'coupon_id'=>0,'name'=>'SYNTHETIC','email'=>'synthetic@example.invalid']);
    $snapshot=['version'=>2,'cartRevision'=>0,'acceptedAt'=>'2026-10-01 10:00:00','bookingOwner'=>['userId'=>$owner],
        'provider'=>['id'=>'paypal-73','currency'=>'EUR','total'=>65*$eventCount,'fee'=>0],'eventIds'=>$eventIds,
        'tickets'=>[],'seats'=>[]];
    foreach($eventIds as $eventId){$snapshot['tickets'][$eventId]=[0=>['quantity'=>5,'unitPrice'=>13]];}
    for($i=0;$i<5*$eventCount;++$i){$snapshot['seats'][19011+$i]=['id'=>19031+$i,'seatId'=>19021+($i%5),'eventId'=>19003+intdiv($i,5),'type'=>0,'order'=>$i%5+1];}
    insertN189('copymypage_ticket_carts',['id'=>19002,'booking_id'=>19001,'status'=>1,'user_id'=>$owner,'payment_provider'=>'paypal-73',
        'native_payment_phase'=>2,'revision'=>1,'terms_accepted_at'=>'2026-10-01 10:00:00','terms_snapshot'=>json_encode($snapshot),'expires_at'=>'2026-10-01 11:00:00']);
    foreach($eventIds as $index=>$eventId){
        insertN189('copymypage_ticket_cart_items',['id'=>19004+$index,'cart_id'=>19002,'event_id'=>$eventId,'quantity'=>5,'price_index'=>0,'unit_price'=>13,'currency'=>'EUR']);
        insertN189('copymypage_event_seating',['event_id'=>$eventId,'layout_id'=>19040,'status'=>1,'inventory_version'=>5]);
    }
    insertN189('copymypage_seat_layouts',['id'=>19040,'alias'=>'synthetic-189','version'=>1,'status'=>1,'title'=>'SYNTHETIC','logical_width'=>100,'logical_height'=>100,'geometry_json'=>'{}']);
    insertN189('copymypage_layout_tables',['id'=>19041,'layout_id'=>19040,'table_code'=>'T1','table_number'=>1,'shape'=>'rect','width'=>100,'height'=>100]);
    for($i=0;$i<5*$eventCount;++$i){
        insertN189('dpcalendar_tickets',['id'=>19011+$i,'uid'=>'SYNTHETIC-TICKET-'.$i,'booking_id'=>19001,'event_id'=>19003+intdiv($i,5),'user_id'=>$owner,'state'=>3,'price'=>13,'type'=>0,'country'=>0]);
        if($i<5){insertN189('copymypage_seats',['id'=>19021+$i,'layout_table_id'=>19041,'seat_code'=>'S'.($i+1),'seat_number'=>$i+1,'sort_order'=>$i]);}
        insertN189('copymypage_event_seats',['id'=>19031+$i,'seat_id'=>19021+($i%5),'event_id'=>19003+intdiv($i,5),'cart_id'=>19002,'ticket_id'=>19011+$i,'status'=>2,'price_index'=>0,'assignment_order'=>$i%5+1,'allocation_type'=>0]);
    }
    insertN189('extensions',['extension_id'=>1,'type'=>'plugin','folder'=>'dpcalendarpay','element'=>'paypal','enabled'=>1,'params'=>json_encode(['providers'=>[['id'=>73,'state'=>1,'sandbox'=>1,'rest_client_id'=>'SYNTHETIC','rest_secret'=>'SYNTHETIC']]])]);
}
function snapshotN189(bool $includeTask = true): string { global $db,$tables;$all=[];foreach($tables as $t){if(!$includeTask && $t==='scheduler_tasks'){continue;}$all[$t]=$db->setQuery('SELECT * FROM #__'.$t)->loadAssocList();}return hash('sha256',json_encode($all)); }
function taskN189(): int {
    global $app,$db;
    $task=new QuietNativeTask189(['name'=>'dpcalendar','type'=>'task']);$task->setApplication($app);$task->setDatabase($db);
    $subject=new Joomla\Component\Scheduler\Administrator\Task\Task((object)['id'=>189,'type'=>'plg_task_dpcalendar_booking_deletions','taskOption'=>null,'params'=>'{}']);
    return $task->deleteBookings(new Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent('onExecuteTask',[
        'subject'=>$subject,'params'=>(object)['deletions_states'=>[3],'deletions_date_type'=>1,'deletions_date'=>'2026-10-30 00:00:00']]));
}
function schedulerFixtureN189(): void {
    global $db;
    fixtureN189(3,190);
    // Automatic cleanup is permitted only for a positively unstarted payment.
    $db->setQuery("UPDATE #__dpcalendar_bookings SET token='',transaction_id=''")->execute();
    $db->setQuery('UPDATE #__copymypage_ticket_carts SET native_payment_phase=1')->execute();
    foreach([['id'=>1,'parent_id'=>0,'lft'=>0,'rgt'=>5,'title'=>'Public'],['id'=>8,'parent_id'=>1,'lft'=>1,'rgt'=>2,'title'=>'Scheduler operator'],['id'=>9,'parent_id'=>1,'lft'=>3,'rgt'=>4,'title'=>'Customer']] as $g){insertN189('usergroups',$g);}
    foreach([189=>8,190=>9,191=>9] as $id=>$group){
        insertN189('users',['id'=>$id,'name'=>'SYNTHETIC '.$id,'username'=>'synthetic-'.$id,'email'=>'synthetic-'.$id.'@example.invalid','block'=>0,'params'=>'{}']);
        insertN189('user_usergroup_map',['user_id'=>$id,'group_id'=>$group]);
    }
    foreach([
        ['id'=>1,'parent_id'=>0,'lft'=>0,'rgt'=>7,'level'=>0,'name'=>'root.1','rules'=>'{}'],
        ['id'=>2,'parent_id'=>1,'lft'=>1,'rgt'=>2,'level'=>1,'name'=>'com_dpcalendar','rules'=>'{"core.delete":{"8":1},"dpcalendar.admin.book":{"8":1}}'],
        ['id'=>3,'parent_id'=>1,'lft'=>3,'rgt'=>6,'level'=>1,'name'=>'com_scheduler','rules'=>'{"core.testrun":{"8":1}}'],
        ['id'=>4,'parent_id'=>3,'lft'=>4,'rgt'=>5,'level'=>2,'name'=>'com_scheduler.task.189','rules'=>'{}'],
    ] as $a){insertN189('assets',$a);}
    insertN189('viewlevels',['id'=>1,'title'=>'Public','rules'=>'[1]']);
    insertN189('scheduler_tasks',['id'=>189,'asset_id'=>4,'title'=>'SYNTHETIC native cleanup','type'=>'plg_task_dpcalendar_booking_deletions','state'=>1,'created_by'=>189,
        'locked'=>null,'params'=>json_encode(['individual_log'=>false,'deletions_states'=>['3'],'deletions_date_type'=>'1','deletions_date'=>'-15 minutes']),
        'execution_rules'=>'{"rule-type":"interval-minutes","interval-minutes":1}','cron_rules'=>'{"type":"interval","exp":"* * * * *"}']);
    Joomla\CMS\Access\Access::clearStatics();
}
function bootSchedulerTaskN189(bool $fixed): object {
    global $app,$container,$db;
    $dispatcher=$container->get(Joomla\Event\DispatcherInterface::class);
    $config=['name'=>'dpcalendar','type'=>'task','params'=>'{}'];
    (new ReflectionProperty(Joomla\CMS\Plugin\PluginHelper::class,'plugins'))->setValue(null,[(object)$config]);
    try {
        if($fixed){
            $child=$container->createChild();
            $system=new Joomla\Plugin\System\CopyMyPage\Extension\CopyMyPage(['name'=>'copymypage','type'=>'system']);$system->setApplication($app);
            $system->attachNativeCleanup(new Joomla\Event\Event('onAfterExtensionBoot',[
                'extensionName'=>'dpcalendar:task','type'=>Joomla\CMS\Extension\PluginInterface::class,'container'=>$child]));
            $plugin=$child->get(Joomla\CMS\Extension\PluginInterface::class);
            verifyN189($plugin instanceof Joomla\Component\CopyMyPage\Site\Service\NativeCleanupTask,'Task boot hook did not install the adapter.');
        }else{
            $plugin=new DigitalPeak\Plugin\Task\DPCalendar\Extension\DPCalendar($dispatcher,$config);
            $plugin->setApplication($app);$plugin->setDatabase($db);$plugin->setUserFactory($container->get(Joomla\CMS\User\UserFactoryInterface::class));
        }
    }finally{(new ReflectionProperty(Joomla\CMS\Plugin\PluginHelper::class,'plugins'))->setValue(null,[]);}
    $dispatcher->addSubscriber($plugin);
    (new ReflectionProperty(Joomla\Component\Scheduler\Administrator\Helper\SchedulerHelper::class,'taskOptionsCache'))->setValue(null,null);
    return $plugin;
}
function schedulerRunN189(): bool {
    global $db;
    // Supply the pre-acquired row lock used by Joomla's normal scheduler queue.
    // Task::acquireLock()'s legacy shortcut checks affected rows after UNLOCK TABLES on PDO.
    $db->setQuery('UPDATE #__scheduler_tasks SET locked=UTC_TIMESTAMP() WHERE id=189 AND locked IS NULL')->execute();
    verifyN189($db->getAffectedRows()===1,'Fixture scheduler task is already locked.');
    $record=$db->setQuery('SELECT * FROM #__scheduler_tasks WHERE id=189')->loadObject();
    $record->taskOption=Joomla\Component\Scheduler\Administrator\Helper\SchedulerHelper::getTaskOptions()->findOption($record->type);
    $record->cron_rules=json_decode($record->cron_rules,true);$record->execution_rules=json_decode($record->execution_rules,true);
    $task=new Joomla\Component\Scheduler\Administrator\Task\Task($record);$task->setLogger(new Psr\Log\NullLogger());
    return $task->run();
}

/** Actual adapter/model deletion after an intentionally stale vendor candidate selection. */
function selectedDeleteN189(?callable $afterSelection=null): array {
    global $db,$container,$factory;
    $db->setQuery('UPDATE #__scheduler_tasks SET locked=UTC_TIMESTAMP() WHERE id=189')->execute();
    $task=$db->setQuery('SELECT id,type,state,created_by,locked,params FROM #__scheduler_tasks WHERE id=189')->loadObject();
    $service=$container->get(ReservationCleanupService::class);
    $user=$container->get(Joomla\CMS\User\UserFactoryInterface::class)->loadUserById(189);
    $service->withNativeTaskUser($user,static function()use($factory,$afterSelection):int {
        $ids=[19001]; // Native query already selected state 3.
        if($afterSelection){$afterSelection();}
        verifyN189($factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids),'Selected native delete failed.');
        return 0;
    },$task);
    $db->setQuery('UPDATE #__scheduler_tasks SET locked=NULL WHERE id=189')->execute();
    return $service->getNativeTaskReport();
}

function otherNativeN189(Joomla\Database\DatabaseInterface $connection): NativeReservationService {
    return new NativeReservationService($connection,
        new Joomla\Component\CopyMyPage\Site\Service\NativePayPalProviderRegistry($connection),
        new Joomla\Component\CopyMyPage\Site\Service\BookingIntegrityService($connection));
}

function finishCallbackN189(NativeReservationService $native): void {
    // Simulated shutdown of this synthetic callback; production keeps it until request shutdown.
    $lock=(new ReflectionMethod($native,'bookingLockName'))->invoke($native,19001);
    (new ReflectionMethod($native,'releaseBookingLock'))->invoke($native,$lock);
}

function safeDeletionMatrixN189(): void {
    global $db,$app,$factory,$container,$actor,$driverClass,$connectionOptions,$prefix;
    schedulerFixtureN189();[$app,$factory,$container,$actor]=nativeApp189($db,true);bootSchedulerTaskN189(true);
    $GLOBALS['cleanupMessages189']=[];
    foreach([0,2,3,4,5,6] as $phase){
        schedulerFixtureN189();$db->setQuery('UPDATE #__copymypage_ticket_carts SET native_payment_phase='.$phase)->execute();
        $before=snapshotN189(false);verifyN189(schedulerRunN189() && snapshotN189(false)===$before,'Unsafe payment phase deleted: '.$phase);
        $report=$container->get(ReservationCleanupService::class)->getNativeTaskReport();
        $reason=$phase===2 ? 'payment_start_incomplete' : 'payment_started_or_unknown';
        verifyN189(($report['reasons'][$reason]??0)===1 && $report['deleted']===0,'Missing financial hold report.');
    }
    echo "PASS unknown/callback/released phases and incomplete starts retained\n";
    foreach(['token','transaction_id','payer_id','payer_email','raw_data','txn_type','txn_currency','gross_amount','net_amount','payment_fee'] as $field){
        schedulerFixtureN189();$value=in_array($field,['gross_amount','net_amount','payment_fee'],true)?'195':$db->quote($field==='txn_currency'?'EUR':'SYNTHETIC-PRIVATE');
        $db->setQuery('UPDATE #__dpcalendar_bookings SET '.$field.'='.$value)->execute();
        $before=snapshotN189(false);verifyN189(schedulerRunN189() && snapshotN189(false)===$before,'Payment evidence was ignored: '.$field);
    }
    echo "PASS ten native payment evidence fields prevent deletion even with contradictory NOT_STARTED\n";
    foreach(['paid_cancel','refunded_cancel','cancel_without_evidence'] as $case){
        schedulerFixtureN189();$db->setQuery('UPDATE #__scheduler_tasks SET params='.$db->quote('{"deletions_date":"-15 minutes","deletions_date_type":1,"deletions_states":["3","6"]}'))->execute();
        $db->setQuery('UPDATE #__dpcalendar_bookings SET state=6'.($case==='cancel_without_evidence'?'':",transaction_id='SYNTHETICCAPTURE189',raw_data='SYNTHETIC REFUND OR CAPTURE'").($case==='paid_cancel'?',gross_amount=195':''))->execute();
        $db->setQuery('UPDATE #__dpcalendar_tickets SET state=6')->execute();
        $before=snapshotN189(false);verifyN189(schedulerRunN189() && snapshotN189(false)===$before,'State 6 was deleted: '.$case);
        verifyN189(($container->get(ReservationCleanupService::class)->getNativeTaskReport()['reasons']['booking_not_unpaid']??0)===1,'State 6 did not reach explicit exclusion.');
    }
    echo "PASS paid/refunded/customer-canceled state 6 always retained, including no-evidence cancellation\n";
    foreach(['state','book_date','task_params','task_owner','tickets','seats','owner','snapshot','missing_cart','ambiguous_cart'] as $case){
        schedulerFixtureN189();$expected='';$before='';
        $report=selectedDeleteN189(static function()use($case,$db,&$expected,&$before):void {
            if($case==='state'){$db->setQuery('UPDATE #__dpcalendar_bookings SET state=1')->execute();$db->setQuery('UPDATE #__dpcalendar_tickets SET state=1')->execute();$expected='booking_not_unpaid';}
            if($case==='book_date'){$db->setQuery('UPDATE #__dpcalendar_bookings SET book_date=UTC_TIMESTAMP()')->execute();$expected='cleanup_criteria_changed';}
            if($case==='task_params'){$db->setQuery("UPDATE #__scheduler_tasks SET params='{}'")->execute();$expected='cleanup_task_changed';}
            if($case==='task_owner'){$db->setQuery('UPDATE #__scheduler_tasks SET created_by=191')->execute();$expected='cleanup_task_changed';}
            if($case==='tickets'){$db->setQuery('UPDATE #__dpcalendar_tickets SET state=1 WHERE id=19011')->execute();$expected='native_integrity_requires_review';}
            if($case==='seats'){$db->setQuery('UPDATE #__copymypage_event_seats SET ticket_id=NULL WHERE id=19031')->execute();$expected='native_integrity_requires_review';}
            if($case==='owner'){$db->setQuery('UPDATE #__dpcalendar_bookings SET user_id=191')->execute();$expected='native_integrity_requires_review';}
            if($case==='snapshot'){$db->setQuery("UPDATE #__copymypage_ticket_carts SET terms_snapshot='{}'")->execute();$expected='native_integrity_requires_review';}
            if($case==='missing_cart'){$db->setQuery('DELETE FROM #__copymypage_ticket_carts')->execute();$expected='native_cart_binding_requires_review';}
            if($case==='ambiguous_cart'){insertN189('copymypage_ticket_carts',['id'=>19999,'booking_id'=>19001,'token_hash'=>hash('sha256','SYNTHETIC-AMBIGUOUS')]);$expected='native_cart_binding_requires_review';}
            $before=snapshotN189(false);
        });
        verifyN189(snapshotN189(false)===$before && ($report['reasons'][$expected]??0)===1,'Stale selection/integrity guard failed: '.$case);
    }
    echo "PASS selected 3 -> current 1, changed age/task/creator, and five broken bindings retain all current data\n";
    foreach([2,3] as $dateType){
        schedulerFixtureN189();$db->setQuery('UPDATE #__scheduler_tasks SET params='.$db->quote(json_encode(['deletions_date'=>'-15 minutes','deletions_date_type'=>$dateType,'deletions_states'=>['3']])))->execute();
        $column=$dateType===2?'start_date':'end_date';
        $db->setQuery('UPDATE #__dpcalendar_events SET '.$column."='2026-10-01 10:00:00'")->execute();
        $before='';$report=selectedDeleteN189(static function()use($db,$column,&$before):void{$db->setQuery('UPDATE #__dpcalendar_events SET '.$column."='2027-12-01 18:00:00'")->execute();$before=snapshotN189(false);});
        verifyN189(snapshotN189(false)===$before && ($report['reasons']['cleanup_criteria_changed']??0)===1,'Stale event date allowed deletion.');
        $db->setQuery('UPDATE #__dpcalendar_events SET '.$column."='2026-10-01 10:00:00'")->execute();
        verifyN189(schedulerRunN189() && (int)$db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_bookings')->loadResult()===0,'Matching event date was not deleted.');
    }
    echo "PASS fresh event-start/end criteria rechecked; unchanged eligible native event criteria delete safely\n";

    schedulerFixtureN189();$native=$container->get(NativeReservationService::class);
    $token=$native->beginPayment(19001);verifyN189(is_string($token),'Native start failed.');
    $before=snapshotN189(false);verifyN189(schedulerRunN189() && schedulerRunN189() && snapshotN189(false)===$before,'Delayed callback/resume was deleted.');
    echo "PASS committed payment start without order/with delayed callback remains held across repeated runs\n";
    foreach([false,true] as $timeout){
        schedulerFixtureN189();$other=new $driverClass($connectionOptions);$other->connect();$callback=otherNativeN189($other);
        verifyN189(is_string($callback->beginPayment(19001)),'Callback preparation start failed.');
        $other->setQuery("UPDATE #__dpcalendar_bookings SET transaction_id='SYNTHETICORDER189'")->execute();
        $booking=$other->setQuery('SELECT * FROM #__dpcalendar_bookings')->loadObject();$callback->assertCallback(19001,false,$booking);
        $client=new CleanupHttp189($timeout);$vendor=new CleanupPayPal189(new Joomla\Event\Dispatcher(),['name'=>'paypal','type'=>'dpcalendarpay']);
        $vendor->setClientFactory(new class($client) implements DigitalPeak\ThinHTTP\ClientFactoryInterface {
            public function __construct(private CleanupHttp189 $client){} public function create(): DigitalPeak\ThinHTTP\ClientInterface{return $this->client;}
        });
        $thrown=false;$updates=[];try{$updates=$vendor->finish189($booking);}catch(RuntimeException){$thrown=true;}
        verifyN189($client->captures===1 && $thrown===$timeout && ($timeout || ($updates['state']===1 && $updates['transaction_id']==='SYNTHETICCAPTURE189')),'Original capture boundary not exercised.');
        // Deliberately interrupt before vendor model save, or simulate ambiguous capture timeout.
        verifyN189((int)$db->setQuery('SELECT state FROM #__dpcalendar_bookings')->loadResult()===3,'Fixture accidentally persisted capture.');
        $before=snapshotN189(false);verifyN189(schedulerRunN189() && snapshotN189(false)===$before,'Active callback lock not respected.');
        verifyN189(($container->get(ReservationCleanupService::class)->getNativeTaskReport()['reasons']['booking_busy']??0)===1,'Callback lock was not reported.');
        finishCallbackN189($callback);$other->disconnect();
        verifyN189(schedulerRunN189() && snapshotN189(false)===$before,'Persisted phase 4 was deleted after callback request ended.');
    }
    echo "PASS original PayPal COMPLETED before interrupted save and capture timeout retained during and after callback lock\n";

    schedulerFixtureN189();$other=new $driverClass($connectionOptions);$other->connect();$competingStart=otherNativeN189($other);$native=$container->get(NativeReservationService::class);
    $dispatcher=$container->get(Joomla\Event\DispatcherInterface::class);$blocked=0;
    $startDuringDelete=static function(Joomla\CMS\Event\Model\BeforeDeleteEvent $event)use($native,$competingStart,&$blocked):void {
        if($event->getContext()!=='com_dpcalendar.booking'){return;}
        foreach([$native,$competingStart] as $service){try{$service->beginPayment(19001);}catch(DomainException){++$blocked;}}
    };
    $dispatcher->addListener('onContentBeforeDelete',$startDuringDelete);
    try{verifyN189(schedulerRunN189() && $blocked===2,'Concurrent or reentrant payment start crossed deletion.');}
    finally{$dispatcher->removeListener('onContentBeforeDelete',$startDuringDelete);$other->disconnect();}
    $before=snapshotN189(false);verifyN189($native->beginPayment(19001)===null,'Deleted booking restarted payment.');
    $blocked=false;try{$native->assertCallback(19001,false);}catch(DomainException){$blocked=true;}
    verifyN189($blocked && snapshotN189(false)===$before,'Late callback changed deleted booking.');
    echo "PASS deletion wins: same-request and second-connection starts blocked; late start/callback rejected\n";

    // A hook tries to start payment and then rejects native deletion. The start must
    // not commit the enclosing transaction: full database snapshot must survive.
    schedulerFixtureN189();$native=$container->get(NativeReservationService::class);$before=snapshotN189(false);$blocked=false;
    $reject=static function(Joomla\CMS\Event\Model\BeforeDeleteEvent $event)use($native,&$blocked):void {
        if($event->getContext()!=='com_dpcalendar.booking'){return;}
        try{$native->beginPayment(19001);}catch(DomainException){$blocked=true;}
        throw new RuntimeException('SYNTHETIC PRIVATE CUSTOMER SQL');
    };
    $dispatcher->addListener('onContentBeforeDelete',$reject);
    try{verifyN189(!schedulerRunN189() && $blocked && snapshotN189(false)===$before,'Rejected deletion/start did not fully roll back.');
        verifyN189((int)$db->setQuery('SELECT last_exit_code FROM #__scheduler_tasks')->loadResult()===5 && $container->get(ReservationCleanupService::class)->getNativeTaskReport()['errors']===1,'Real deletion error not surfaced as scheduler failure.');}
    finally{$dispatcher->removeListener('onContentBeforeDelete',$reject);}
    $logged=implode("\n",$GLOBALS['cleanupMessages189']);
    verifyN189(str_contains($logged,'native_cleanup_held') && str_contains($logged,'native_cleanup_error') && str_contains($logged,'19001')
        && !str_contains($logged,'SYNTHETIC PRIVATE') && !str_contains($logged,'SYNTHETIC-PRIVATE') && !str_contains($logged,'synthetic@example.invalid'),'Unsafe or missing financial/error log.');
    verifyN189(in_array('copymypage-reservations.php',$GLOBALS['cleanupLogFiles189'],true),'Persistent reservation log was not configured.');
    verifyN189(schedulerRunN189(),'Retry after rejected deletion failed.');
    $before=snapshotN189(false);verifyN189(schedulerRunN189() && snapshotN189(false)===$before,'Repeated safe cleanup changed tombstone or audit.');
    echo "PASS rejected native delete is error 5 with complete rollback, sanitized hold/error logs, retry and idempotence\n";

    foreach(['direct','claimed'] as $entry){
        // Each fixture is a new synthetic request; claims must not survive fixture resets.
        schedulerFixtureN189();[$app,$factory,$container,$actor]=nativeApp189($db,true);bootSchedulerTaskN189(true);
        $native=$container->get(NativeReservationService::class);$booking=$db->setQuery('SELECT * FROM #__dpcalendar_bookings')->loadObject();
        if($entry==='claimed'){verifyN189($container->get(Joomla\Component\CopyMyPage\Site\Service\PaymentReconciliationService::class)->beginPaymentAttempt(19001),'Template payment claim failed.');}
        $system=new Joomla\Plugin\System\CopyMyPage\Extension\CopyMyPage(['name'=>'copymypage','type'=>'system']);$system->setApplication($app);
        $events=new Joomla\Event\Dispatcher();$dispatched=0;
        $events->addListener('onDPPaymentNew',[$system,'guardNativeProviderStart'],Joomla\Event\Priority::MAX);
        $events->addListener('onDPPaymentNew',static function()use($db,$booking,&$dispatched):void {
            verifyN189((int)$db->setQuery('SELECT native_payment_phase FROM #__copymypage_ticket_carts')->loadResult()===2
                && strlen($booking->token)===64,'Vendor dispatch preceded durable phase 2.');++$dispatched;
        });
        $events->dispatch('onDPPaymentNew',new Joomla\Event\Event('onDPPaymentNew',[$booking]));
        $before=snapshotN189(false);verifyN189($dispatched===1 && schedulerRunN189() && snapshotN189(false)===$before,'Payment entry path was not excluded.');
        $repeated=false;try{$events->dispatch('onDPPaymentNew',new Joomla\Event\Event('onDPPaymentNew',[$booking]));}catch(DomainException){$repeated=true;}
        verifyN189($repeated && $dispatched===1,'Repeated start reached provider listener.');
    }
    echo "PASS direct native event and CopyMyPage template claim persist phase 2 before provider dispatch; repeats denied\n";

    $dispatcher=$container->get(Joomla\Event\DispatcherInterface::class);
    schedulerFixtureN189();$before=snapshotN189(false);$reject=static function(Joomla\CMS\Event\Model\BeforeDeleteEvent $event):void{if($event->getContext()==='com_dpcalendar.booking'){$event->addResult(false);}};
    $dispatcher->addListener('onContentBeforeDelete',$reject);
    try{verifyN189(!schedulerRunN189() && snapshotN189(false)===$before,'Native false return was silently accepted.');}
    finally{$dispatcher->removeListener('onContentBeforeDelete',$reject);}
    echo "PASS native boolean-false deletion becomes scheduler error with full rollback\n";

    schedulerFixtureN189();insertN189('dpcalendar_bookings',['id'=>19999,'uid'=>'SYNTHETIC-UNMANAGED','state'=>3,'book_date'=>'2026-10-01 10:00:00','price'=>195,'payment_provider'=>'paypal-73']);
    verifyN189(schedulerRunN189() && (int)$db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_bookings WHERE id=19001')->loadResult()===0
        && (int)$db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_bookings WHERE id=19999')->loadResult()===1,'Mixed safe/held batch failed.');
    $report=$container->get(ReservationCleanupService::class)->getNativeTaskReport();verifyN189($report['scanned']===2 && $report['deleted']===1 && $report['held']===1 && $report['errors']===0,'Mixed batch counts incorrect.');
    echo "PASS mixed task deletes safe candidate and retains unmanaged candidate without bypassing the guard\n";
}
require __DIR__.'/native-checkout-scheduler-matrix.php';
try {
    foreach($tables as $table){
        $db->getConnection()->query('CREATE TABLE `'.$prefix.$table.'` LIKE `'.$cfg->dbprefix.$table.'`');$created[]=$table;
    }
    [$app,$factory,$container,$actor]=nativeApp189($db);
    fixtureN189();
    if(array_key_exists('checkout-only',$options)) { checkoutSchedulerMatrix189(); echo 'RESULT checkout matrix: ',$GLOBALS['assertionsN189']," assertions passed; external calls: 0\n"; return; }
    if(array_key_exists('baseline',$options)){
        $before=snapshotN189();$rejected=false;
        try{$ids=[19001];$factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids);}
        catch(DomainException $error){$rejected=$error->getMessage()==='Native cleanup requires the verified mysqli driver.';}
        verifyN189($driver==='mysql' && $rejected && snapshotN189()===$before,'Original PDO rejection was not reproduced safely.');
        echo "PASS reproduced original mysqli-only rejection on real PDO MySQL; full rollback\n";
        return;
    }
    verifyN189(taskN189()===0,'Native task failed.');
    verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_bookings')->loadResult()===0,'Native booking remains.');
    verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_tickets')->loadResult()===0,'Native tickets remain.');
    verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__copymypage_event_seats WHERE status=0 AND cart_id IS NULL AND ticket_id IS NULL')->loadResult()===5,'Native seat release failed.');
    verifyN189((int)$db->setQuery('SELECT native_payment_phase FROM #__copymypage_ticket_carts')->loadResult()===6,'Native tombstone missing.');
    verifyN189((int)$db->setQuery('SELECT capacity_used FROM #__dpcalendar_events')->loadResult()===0,'Pending capacity was decremented.');
    echo "PASS original native task -> BookingModel::delete -> TicketModel::delete -> CopyMyPage five-seat release\n";
    $after=snapshotN189();$ids=[19001];verifyN189($factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids),'Repeat failed.');
    verifyN189(snapshotN189()===$after,'Repeat changed data.');echo "PASS repeated native deletion is idempotent\n";
    $token=str_repeat('a',64);$app->getSession()->set('com_copymypage.ticket_cart_token',$token);
    insertN189('copymypage_ticket_carts',['id'=>19102,'status'=>0,'user_id'=>189,'token_hash'=>hash('sha256',$token),
        'native_payment_phase'=>0,'revision'=>0,'expires_at'=>gmdate('Y-m-d H:i:s',time()+3600)]);
    insertN189('copymypage_ticket_cart_items',['id'=>19104,'cart_id'=>19102,'event_id'=>19003,'quantity'=>5,'price_index'=>0,'unit_price'=>13,'currency'=>'EUR']);
    $selection=new Joomla\Component\CopyMyPage\Site\Service\SeatSelectionService($db,
        new Joomla\Component\CopyMyPage\Site\Service\TicketCatalogService($app,$db),
        new Joomla\Component\CopyMyPage\Site\Service\TicketCartContextService($app,$db));
    verifyN189($selection->getEventState(19003)['event']['selectedCount']===0,'New cart has unexpected seats.');
    verifyN189($selection->assignSeats(19003,[19031,19032,19033,19034,19035],0)['event']['selectedCount']===5,'SeatSelectionService could not reserve released seats.');
    $resold=snapshotN189();$ids=[19001];$factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids);
    try{$container->get(NativeReservationService::class)->assertCallback(19001,false);throw new RuntimeException('Old callback allowed.');}catch(DomainException){}
    verifyN189(snapshotN189()===$resold,'Old deletion/callback changed new reservation.');echo "PASS real five-seat reservation and late callback/repeat preserve new owner\n";
    fixtureN189();$ids=[19011];verifyN189($factory->createModel('Ticket','Administrator',['ignore_request'=>true])->delete($ids),'Native single-ticket delete failed.');
    verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__copymypage_event_seats WHERE status=0')->loadResult()===1,'Single ticket did not release exactly its seat.');
    verifyN189((int)$db->setQuery('SELECT status FROM #__copymypage_ticket_carts')->loadResult()===1,'Partial cart closed too early.');
    $ids=[19001];verifyN189($factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids),'Remaining native deletion failed.');
    verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__copymypage_event_seats WHERE status=0')->loadResult()===5,'Remaining seats not released.');echo "PASS native ticket deletion releases only its seat before remaining booking cleanup\n";
    fixtureN189();foreach(range(19011,19015) as $ticketId){$ids=[$ticketId];verifyN189($factory->createModel('Ticket','Administrator',['ignore_request'=>true])->delete($ids),'Individual native ticket deletion failed.');}
    $beforeEmpty=snapshotN189();$cartEmpty=$db->setQuery('SELECT * FROM #__copymypage_ticket_carts')->loadAssocList();
    $ids=[19001];verifyN189($factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids),'Native empty booking deletion failed.');
    verifyN189($db->setQuery('SELECT * FROM #__copymypage_ticket_carts')->loadAssocList()===$cartEmpty,'Empty booking deletion released the cart twice.');
    echo "PASS native empty booking can follow individual ticket deletions without a second release\n";
    fixtureN189();$before=snapshotN189();
    $db->getConnection()->query('CREATE TRIGGER `'.$prefix.'audit_failure` BEFORE INSERT ON `'.$prefix."action_logs` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SYNTHETIC AUDIT FAILURE'");
    try{$ids=[19001];$failed=false;try{$factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids);}catch(Throwable){$failed=true;}
        verifyN189($failed && snapshotN189()===$before,'Audit failure left partial native deletion.');}
    finally{$db->getConnection()->query('DROP TRIGGER `'.$prefix.'audit_failure`');}
    echo "PASS audit failure rolls native booking/ticket deletion and seat release back together\n";
    fixtureN189();$other=new $driverClass($connectionOptions);$other->connect();
    $providers=new Joomla\Component\CopyMyPage\Site\Service\NativePayPalProviderRegistry($other);
    $integrity=new Joomla\Component\CopyMyPage\Site\Service\BookingIntegrityService($other);
    $otherNative=new NativeReservationService($other,$providers,$integrity);
    $otherNative->assertCallback(19001,false);$before=snapshotN189();
    try{
        $blocked=false;try{$ids=[19001];$factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids);}catch(DomainException){$blocked=true;}
        verifyN189($blocked && snapshotN189()===$before,'Native deletion raced the active callback.');
    }finally{
        // Simulate the callback request's shutdown handler; PDO statements can retain the handle after disconnect().
        $lock=(new ReflectionMethod($otherNative,'bookingLockName'))->invoke($otherNative,19001);
        (new ReflectionMethod($otherNative,'releaseBookingLock'))->invoke($otherNative,$lock);
        $other->disconnect();
    }
    $ids=[19001];verifyN189($factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids),'Native cleanup failed after callback connection ended.');
    echo "PASS independent real callback connection excludes native deletion until its lock is released\n";
    fixtureN189();$sameRequestBlocked=false;
    $reentrant=function(Joomla\CMS\Event\Model\BeforeDeleteEvent $event)use($container,&$sameRequestBlocked):void {
        if($event->getContext()!=='com_dpcalendar.booking'){return;}
        try{$container->get(NativeReservationService::class)->assertCallback(19001,false);}
        catch(DomainException){$sameRequestBlocked=true;}
    };
    $dispatcher=$container->get(Joomla\Event\DispatcherInterface::class);$dispatcher->addListener('onContentBeforeDelete',$reentrant);
    try{$ids=[19001];verifyN189($factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids) && $sameRequestBlocked,'Same-request callback crossed native deletion.');}
    finally{$dispatcher->removeListener('onContentBeforeDelete',$reentrant);}
    echo "PASS same-request callback cannot restart or commit the native deletion transaction\n";
    fixtureN189();$db->setQuery('UPDATE #__dpcalendar_bookings SET state=1')->execute();$db->setQuery('UPDATE #__dpcalendar_tickets SET state=1')->execute();
    $before=snapshotN189();verifyN189(taskN189()===0 && snapshotN189()===$before,'Native state filter deleted a paid booking.');echo "PASS native state-3 filter preserves paid booking\n";
    schedulerFixtureN189();[$app,$factory,$container,$actor]=nativeApp189($db,true);bootSchedulerTaskN189(false);
    $before=snapshotN189(false);$rejected=false;
    try{schedulerRunN189();}catch(DomainException $e){$rejected=$e->getMessage()==='DPCalendar rejected native deletion.';}
    verifyN189($rejected && snapshotN189(false)===$before && (int)$db->setQuery('SELECT last_exit_code FROM #__scheduler_tasks')->loadResult()===5,'Original guest scheduler failure was not reproduced.');
    echo "PASS reproduced original guest web Task::run rejection, code 5, three events/15 tickets/195 EUR unchanged\n";

    schedulerFixtureN189();[$app,$factory,$container,$actor]=nativeApp189($db,true);$plugin=bootSchedulerTaskN189(true);
    $operator=$container->get(Joomla\CMS\User\UserFactoryInterface::class)->loadUserById(189);
    verifyN189(!$operator->authorise('core.admin') && $operator->authorise('core.delete','com_dpcalendar'),'Fixture must use real limited ACL, not a root user.');
    $itemsBefore=$db->setQuery('SELECT * FROM #__copymypage_ticket_cart_items ORDER BY id')->loadAssocList();
    verifyN189(schedulerRunN189(),'Adapted guest web scheduler failed.');
    verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_bookings')->loadResult()===0 && (int)$db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_tickets')->loadResult()===0,'Multi-event native deletion incomplete.');
    verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__copymypage_event_seats WHERE status=0 AND cart_id IS NULL AND ticket_id IS NULL AND price_index IS NULL AND assignment_order IS NULL')->loadResult()===15,'Multi-event seats not fully released.');
    $cart=$db->setQuery('SELECT * FROM #__copymypage_ticket_carts')->loadObject();
    verifyN189((int)$cart->status===3 && (int)$cart->native_payment_phase===6 && (int)$cart->revision===2,'Multi-event cart inconsistent.');
    verifyN189($db->setQuery('SELECT * FROM #__copymypage_ticket_cart_items ORDER BY id')->loadAssocList()===$itemsBefore,'Historical cart items changed.');
    verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__copymypage_event_seating WHERE inventory_version=6')->loadResult()===3 && (int)$db->setQuery('SELECT SUM(capacity_used) FROM #__dpcalendar_events')->loadResult()===0,'Multi-event inventory/capacity inconsistent.');
    $selection=new Joomla\Component\CopyMyPage\Site\Service\SeatSelectionService($db,new Joomla\Component\CopyMyPage\Site\Service\TicketCatalogService($app,$db),new Joomla\Component\CopyMyPage\Site\Service\TicketCartContextService($app,$db));
    $constraints=$selection->getInventoryConstraints([19003,19004,19005]);
    verifyN189(count($constraints)===3,'Missing event inventory.');
    foreach($constraints as $inventory){verifyN189($inventory['ready'] && $inventory['booked']===0 && $inventory['sellableCapacity']===5,'Released event inventory is not sellable.');}
    $audit=$db->setQuery('SELECT * FROM #__action_logs')->loadAssocList();verifyN189(count($audit)===1,'Expected one atomic multi-event audit.');
    $message=json_decode($audit[0]['message'],true);
    verifyN189((int)$audit[0]['user_id']===189 && $message['ticketIds']===implode(',',range(19011,19025)) && $message['seatIds']===implode(',',range(19031,19045))
        && $message['originalState']===3 && (float)$message['amount']===195.0 && $message['currency']==='EUR' && $message['decision']==='dpcalendar_native_delete'
        && $message['paymentExclusionProven']===true && $message['paymentExclusionBasis']==='not_started'
        && $message['orderId']==='' && $message['provider']==='paypal-73','Incomplete native audit.');
    verifyN189($app->getIdentity()===$actor && (int)$actor->id===0 && $container->get(ReservationCleanupService::class)->getNativeTaskUser()===null,'Scheduler identity escaped its scope.');
    echo "PASS real guest web Task::run, task boot hook, limited owner ACL: all 15 native tickets/seats, three inventories, cart and full audit consistent\n";
    $after=snapshotN189(false);verifyN189(schedulerRunN189() && snapshotN189(false)===$after,'Repeated multi-event task changed business data.');
    echo "PASS repeated multi-event scheduler task is idempotent\n";

    schedulerFixtureN189();$before=snapshotN189();$denied=0;
    foreach(['Booking'=>19001,'Ticket'=>19011] as $name=>$id){try{$ids=[$id];if(!$factory->createModel($name,'Administrator',['ignore_request'=>true])->delete($ids)){$denied++;}}catch(DomainException){$denied++;}}
    verifyN189($denied===2 && snapshotN189()===$before,'Ordinary guest obtained task permissions after task completion.');
    echo "PASS ordinary guest booking and ticket deletions stay forbidden after the task\n";
    $app->loadIdentity($container->get(Joomla\CMS\User\UserFactoryInterface::class)->loadUserById(191));$before=snapshotN189();$denied=false;
    try{$ids=[19001];$denied=!$factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids);}catch(DomainException){$denied=true;}
    verifyN189($denied && snapshotN189()===$before,'Foreign customer deletion was allowed.');$app->loadIdentity($actor);
    echo "PASS foreign customer cannot delete another customer's booking\n";

    schedulerFixtureN189();$db->setQuery('UPDATE #__dpcalendar_bookings SET state=1')->execute();$db->setQuery('UPDATE #__dpcalendar_tickets SET state=1')->execute();
    $before=snapshotN189(false);verifyN189(schedulerRunN189() && snapshotN189(false)===$before,'Adapted scheduler deleted paid booking.');
    echo "PASS real web task keeps paid multi-event booking, all tickets and seats unchanged\n";
    foreach(['delete_permission','scheduler_permission','blocked_owner','missing_owner'] as $case){
        schedulerFixtureN189();
        if($case==='delete_permission'){$db->setQuery('UPDATE #__assets SET rules='.$db->quote('{"dpcalendar.admin.book":{"8":1}}').' WHERE id=2')->execute();}
        if($case==='scheduler_permission'){$db->setQuery("UPDATE #__assets SET rules='{}' WHERE id=3")->execute();}
        if($case==='blocked_owner'){$db->setQuery('UPDATE #__users SET block=1 WHERE id=189')->execute();}
        if($case==='missing_owner'){$db->setQuery('UPDATE #__scheduler_tasks SET created_by=9999')->execute();}
        Joomla\CMS\Access\Access::clearStatics();$before=snapshotN189(false);$denied=false;
        try{$denied=!schedulerRunN189();}catch(DomainException){$denied=true;}
        verifyN189($denied && snapshotN189(false)===$before && $container->get(ReservationCleanupService::class)->getNativeTaskUser()===null,'Task owner guard failed: '.$case);
    }
    echo "PASS revoked delete/scheduler rights, blocked or missing task owners cannot delete\n";
    schedulerFixtureN189();$before=snapshotN189(false);
    $db->getConnection()->query('CREATE TRIGGER `'.$prefix.'audit_failure` BEFORE INSERT ON `'.$prefix."action_logs` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SYNTHETIC AUDIT FAILURE'");
    try{$failed=false;try{$failed=!schedulerRunN189();}catch(Throwable){$failed=true;}
        verifyN189($failed && snapshotN189(false)===$before && $container->get(ReservationCleanupService::class)->getNativeTaskUser()===null && $app->getIdentity()===$actor,'Multi-event rollback/identity restoration failed.');}
    finally{$db->getConnection()->query('DROP TRIGGER `'.$prefix.'audit_failure`');}
    verifyN189(schedulerRunN189(),'Retry after multi-event audit rollback failed.');
    echo "PASS three-event audit failure rolls back all tickets/seats/inventory; task identity restored and retry succeeds\n";
    schedulerFixtureN189();$before=snapshotN189();
    $record=$db->setQuery('SELECT * FROM #__scheduler_tasks WHERE id=189')->loadObject();$record->taskOption=null;
    $subject=new Joomla\Component\Scheduler\Administrator\Task\Task($record);$denied=false;
    try{$plugin->deleteBookings(new Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent('onExecuteTask',['subject'=>$subject,'params'=>$subject->get('params')]));}catch(DomainException){$denied=true;}
    verifyN189($denied && snapshotN189()===$before,'Unlocked task obtained scheduler identity.');
    echo "PASS direct invocation without Joomla scheduler lock is forbidden\n";

    fixtureN189();[$app,$factory,$container,$actor]=nativeApp189($db);
    $before=snapshotN189();$other=new $driverClass($connectionOptions);$other->connect();
    $db->transactionStart();
    try {
        $db->setQuery("UPDATE #__dpcalendar_events SET title='SYNTHETIC OUTER CHANGE'")->execute();
        $ids=[19001];verifyN189($factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids),'Nested native deletion failed.');
        verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_bookings')->loadResult()===0
            && (int)$other->setQuery('SELECT COUNT(*) FROM #__dpcalendar_bookings')->loadResult()===1,'Inner deletion committed its outer transaction.');
    } finally { $db->transactionRollback();$other->disconnect(); }
    verifyN189(snapshotN189()===$before,'Outer rollback did not restore native deletion and audit.');
    echo "PASS nested successful deletion remains uncommitted; outer rollback restores all business data\n";

    fixtureN189();$before=snapshotN189();$db->transactionStart();
    try {
        $ids=[19001];verifyN189($factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids),'Nested committed deletion failed.');
        $db->transactionCommit();
    } catch(Throwable $error) { $db->transactionRollback();throw $error; }
    $other=new $driverClass($connectionOptions);$other->connect();
    verifyN189((int)$other->setQuery('SELECT COUNT(*) FROM #__dpcalendar_bookings')->loadResult()===0
        && (int)$other->setQuery('SELECT COUNT(*) FROM #__action_logs')->loadResult()===1,'Outer commit did not persist deletion and audit.');
    $other->disconnect();
    echo "PASS nested deletion persists exactly once when the outer transaction commits\n";

    fixtureN189();
    // DDL is deliberately outside the transaction under test: MySQL implicitly commits DDL.
    $db->getConnection()->query('CREATE TRIGGER `'.$prefix.'nested_audit_failure` BEFORE INSERT ON `'.$prefix."action_logs` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SYNTHETIC NESTED FAILURE'");
    $before=snapshotN189();$db->transactionStart();
    try {
        $db->setQuery("UPDATE #__dpcalendar_events SET title='SYNTHETIC OUTER CHANGE'")->execute();$outer=snapshotN189();
        $failed=false;try{$ids=[19001];$factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids);}catch(Throwable){$failed=true;}
        verifyN189($failed && snapshotN189()===$outer,'Inner failure rolled back the outer change or retained partial deletion.');
    } finally { $db->transactionRollback();$db->getConnection()->query('DROP TRIGGER `'.$prefix.'nested_audit_failure`'); }
    verifyN189(snapshotN189()===$before,'Outer transaction was not usable after nested failure.');
    echo "PASS nested audit failure rolls back only deletion; outer change survives until outer rollback\n";

    fixtureN189();$before=snapshotN189();$dispatcher=$container->get(Joomla\Event\DispatcherInterface::class);
    $removeSentinel=static function(Joomla\CMS\Event\Model\BeforeDeleteEvent $event) use($db):void {
        if($event->getContext()==='com_dpcalendar.booking'){$db->getConnection()->query('RELEASE SAVEPOINT '.$db->quoteName('cmp_native_19001'));}
    };
    $dispatcher->addListener('onContentBeforeDelete',$removeSentinel);
    try {
        $failed=false;try{$ids=[19001];$factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids);}catch(Throwable){$failed=true;}
        verifyN189($failed && snapshotN189()===$before,'Missing physical savepoint was accepted or did not roll back.');
    } finally { $dispatcher->removeListener('onContentBeforeDelete',$removeSentinel); }
    echo "PASS missing physical savepoint fails closed and rolls the complete deletion back\n";

    fixtureN189();$other=new $driverClass($connectionOptions);$other->connect();
    $providers=new Joomla\Component\CopyMyPage\Site\Service\NativePayPalProviderRegistry($other);
    $integrity=new Joomla\Component\CopyMyPage\Site\Service\BookingIntegrityService($other);
    $native=new NativeReservationService($other,$providers,$integrity);
    $competitor=new ReservationCleanupService($other,$integrity,$providers,$native,new Joomla\Component\CopyMyPage\Site\Service\PaymentReconciliationService($other,$native));
    $blocked=false;
    $compete=static function(Joomla\CMS\Event\Model\BeforeDeleteEvent $event) use($competitor,$actor,&$blocked):void {
        if($event->getContext()!=='com_dpcalendar.booking'){return;}
        try{$competitor->deleteNative('booking',19001,static function():bool{throw new RuntimeException('Competing vendor deletion must not run.');},$actor);}
        catch(DomainException){$blocked=true;}
    };
    $dispatcher->addListener('onContentBeforeDelete',$compete);
    try {$ids=[19001];verifyN189($factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids) && $blocked,'Competing deletion was not locked out.');}
    finally {$dispatcher->removeListener('onContentBeforeDelete',$compete);$other->disconnect();}
    verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__action_logs')->loadResult()===1,'Competing deletion duplicated the audit.');
    echo "PASS two real cleanup connections exclude concurrent deletion and persist one audit\n";

    fixtureN189();$before=snapshotN189();$other=new $driverClass($connectionOptions);$other->connect();
    $oldWait=(int)$db->setQuery('SELECT @@SESSION.innodb_lock_wait_timeout')->loadResult();
    $db->setQuery('SET SESSION innodb_lock_wait_timeout=1')->execute();
    $other->transactionStart();$other->setQuery('SELECT id FROM #__copymypage_event_seats WHERE id=19031 FOR UPDATE')->loadResult();
    try {
        $failed=false;try{$ids=[19001];$factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids);}catch(Throwable){$failed=true;}
        verifyN189($failed && snapshotN189()===$before,'Row-lock timeout left partial native deletion.');
    } finally {$other->transactionRollback();$other->disconnect();$db->setQuery('SET SESSION innodb_lock_wait_timeout='.$oldWait)->execute();}
    $ids=[19001];verifyN189($factory->createModel('Booking','Administrator',['ignore_request'=>true])->delete($ids),'Retry after row-lock timeout failed.');
    echo "PASS real seat row-lock timeout fully rolls back; retry succeeds after lock release\n";
    safeDeletionMatrixN189();
    checkoutSchedulerMatrix189();
    echo "RESULT native qualification: ",$GLOBALS['assertionsN189']," assertions passed; real task/ACL/DB; vendor HTTP simulated; external provider/mail calls: 0\n";
} finally { foreach(array_reverse($created) as $table){$db->getConnection()->query('DROP TABLE IF EXISTS `'.$prefix.$table.'`');} }
