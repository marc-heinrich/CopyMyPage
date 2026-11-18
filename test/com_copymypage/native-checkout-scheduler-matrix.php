<?php
if (PHP_SAPI !== 'cli' || !defined('_JEXEC')) { exit(1); }
use Joomla\Component\CopyMyPage\Site\Service\NativeReservationService;
use Joomla\Component\CopyMyPage\Site\Service\ReservationCleanupService;
$loader->addPsr4('Joomla\\Plugin\\System\\Schedulerunner\\',JPATH_BASE.'/plugins/system/schedulerunner/src');
$loader->addPsr4('Joomla\\Component\\Users\\Site\\',JPATH_BASE.'/components/com_users/src');
$loader->addPsr4('Joomla\\Component\\Fields\\Administrator\\',JPATH_ADMINISTRATOR.'/components/com_fields/src');
$loader->addPsr4('Joomla\\Plugin\\System\\TaskNotification\\',JPATH_BASE.'/plugins/system/tasknotification/src');
$tables[]='scheduler_logs';
$tables[]='dpcalendar_events_location';
$tables[]='dpcalendar_locations';
$tables[]='dpcalendar_countries';
$tables[]='dpcalendar_taxrates';
$tables[]='dpcalendar_coupons';
$tables[]='contact_details';
$tables[]='fields';$tables[]='fields_categories';$tables[]='fields_groups';$tables[]='fields_values';
define('JPATH_COMPONENT',JPATH_BASE.'/components/com_dpcalendar');
/** Synthetic integration extension; no process spawning, no external I/O. */
class CheckoutRedirect189 extends RuntimeException {}
class CheckoutStatusHttp189 extends Joomla\Http\Http {
    public int $gets=0; public int $tokens=0; public int $code=200; public ?Closure $duringGet=null;
    public array $urls=[];
    public function __construct(public array $order){}
    private function response189(int $code,array $data): Joomla\Http\Response {
        $stream=new Laminas\Diactoros\Stream('php://memory','rw');$stream->write(json_encode($data));
        return new Joomla\Http\Response($stream,$code);
    }
    public function post($url,$data,array $headers=[],$timeout=null){
        verifyN189(str_ends_with($url,'/v1/oauth2/token') && $data==='grant_type=client_credentials','Unexpected provider write.');
        ++$this->tokens;$this->urls[]=$url;return $this->response189(200,['access_token'=>'SYNTHETIC']);
    }
    public function get($url,array $headers=[],$timeout=null){
        ++$this->gets;$this->urls[]=$url;if($this->duringGet){($this->duringGet)();}
        return $this->response189($this->code,$this->order);
    }
}
class CheckoutVendorHttp189 implements DigitalPeak\ThinHTTP\ClientInterface {
    public int $creates=0;public int $captures=0;public int $gets=0;public array $createBody=[];
    public string $status='PAYER_ACTION_REQUIRED';public bool $timeout=false;
    public function post(string $url,$body,?string $userOrToken=null,?string $password=null,array $headers=[],array $options=[]): stdClass {
        if(str_ends_with($url,'/v1/oauth2/token')){return (object)['access_token'=>'SYNTHETIC','dp'=>(object)['info'=>(object)['http_code'=>200]]];}
        if(str_ends_with($url,'/v2/checkout/orders')){
            ++$this->creates;$this->createBody=json_decode($body,true);
            return (object)['id'=>'SYNTHETICORDER189','status'=>'PAYER_ACTION_REQUIRED','links'=>[(object)['href'=>'https://example.invalid/self'],(object)['href'=>'https://example.invalid/approve']],'dp'=>(object)['info'=>(object)['http_code'=>201]]];
        }
        verifyN189(str_ends_with($url,'/SYNTHETICORDER189/capture'),'Unexpected vendor POST.');++$this->captures;
        if($this->timeout){throw new RuntimeException('Synthetic capture timeout.');}
        return json_decode(json_encode(['id'=>'SYNTHETICORDER189','status'=>'COMPLETED','purchase_units'=>[['payments'=>['captures'=>[['id'=>'SYNTHETICCAPTURE189','status'=>'COMPLETED','seller_receivable_breakdown'=>['gross_amount'=>['currency_code'=>'EUR','value'=>'195.00'],'paypal_fee'=>['value'=>'0.00'],'net_amount'=>['value'=>'195.00']]]]]]],'dp'=>['info'=>['http_code'=>201]]]));
    }
    public function get(string $url,?string $userOrToken=null,?string $password=null,array $headers=[],array $options=[]): stdClass {
        verifyN189(str_ends_with($url,'/SYNTHETICORDER189'),'Unexpected vendor GET.');++$this->gets;
        return (object)['id'=>'SYNTHETICORDER189','status'=>$this->status,'dp'=>(object)['info'=>(object)['http_code'=>200]]];
    }
    public function put(string $url,$body,?string $userOrToken=null,?string $password=null,array $headers=[],array $options=[]): stdClass {throw new RuntimeException('Unexpected PUT.');}
    public function delete(string $url,?string $userOrToken=null,?string $password=null,array $headers=[],array $options=[]): stdClass {throw new RuntimeException('Unexpected DELETE.');}
    public function request(string $url,$body='',?string $userOrToken=null,?string $password=null,array $headers=[],array $options=[],string $method='get'): stdClass {throw new RuntimeException('Unexpected request.');}
}
function walletOrder189(string $status='PAYER_ACTION_REQUIRED'): array {
    return ['id'=>'SYNTHETICORDER189','intent'=>'CAPTURE','status'=>$status,'payment_source'=>['paypal'=>['account_id'=>'SYNTHETICMERCHANT']],
        'purchase_units'=>[['description'=>'[19001] SYNTHETIC native checkout','payee'=>['merchant_id'=>'SYNTHETICMERCHANT'],'amount'=>['currency_code'=>'EUR','value'=>'195.00']]]];
}
function checkoutEnvironment189(): array {
    global $db,$app,$factory,$container,$actor;
    $http=new CheckoutStatusHttp189(walletOrder189());
    schedulerFixtureN189();[$app,$factory,$container,$actor]=nativeApp189($db,true,$http);bootSchedulerTaskN189(true);
    $db->setQuery('UPDATE #__scheduler_tasks SET params='.$db->quote(json_encode(['individual_log'=>false,'deletions_states'=>['3'],'deletions_date_type'=>1,'deletions_date'=>'-60 minutes','notifications'=>['success_mail'=>0,'failure_mail'=>0,'fatal_failure_mail'=>0,'orphan_mail'=>0]])))->execute();
    $system=new Joomla\Plugin\System\CopyMyPage\Extension\CopyMyPage(['name'=>'copymypage','type'=>'system']);$system->setApplication($app);
    $dispatcher=$container->get(Joomla\Event\DispatcherInterface::class);
    $dispatcher->addListener(Joomla\Application\ApplicationEvents::BEFORE_RESPOND,static function():void{throw new CheckoutRedirect189('Synthetic response boundary.');});
    $dispatcher->addListener('onDPPaymentNew',[$system,'guardNativeProviderStart'],Joomla\Event\Priority::MAX);
    $dispatcher->addListener('onDPPaymentCallBack',[$system,'guardNativeProviderCallback'],Joomla\Event\Priority::MAX);
    $dispatcher->addListener('onContentBeforeSave',[$system,'guardNativeReservationSave'],Joomla\Event\Priority::MAX);
    $client=new CheckoutVendorHttp189();
    $vendor=new DigitalPeak\Plugin\DPCalendarPay\PayPal\Extension\PayPal($dispatcher,['name'=>'paypal','type'=>'dpcalendarpay','params'=>json_encode(['providers'=>[['id'=>73,'state'=>1,'sandbox'=>1,'rest_client_id'=>'SYNTHETIC','rest_secret'=>'SYNTHETIC']]])]);
    $vendor->setApplication($app);$vendor->setClientFactory(new class($client) implements DigitalPeak\ThinHTTP\ClientFactoryInterface {
        public function __construct(private CheckoutVendorHttp189 $client){}public function create(): DigitalPeak\ThinHTTP\ClientInterface{return $this->client;}
    });
    $vendor->registerListeners();
    $app->getSession()->set('com_dpcalendar.booking_id',19001);
    $app->getInput()->set('option','com_dpcalendar');
    // Each fixture represents a fresh web request; Joomla caches these in function statics.
    $app->getParams()->merge(Joomla\CMS\Component\ComponentHelper::getParams('com_dpcalendar'));
    $app->getParams()->set('bookingsys_enable_token',false);
    registerCheckoutComponent189('users','\\Joomla\\Component\\Users');
    registerCheckoutComponent189('fields','\\Joomla\\Component\\Fields');
    $container->share(Joomla\CMS\Router\SiteRouter::class,new Joomla\CMS\Router\SiteRouter($app,$app->getMenu()),true);
    return [$http,$client,$vendor,$dispatcher];
}
function registerCheckoutComponent189(string $name,string $namespace): void {
    global $db,$container;
    $f=new Joomla\CMS\MVC\Factory\MVCFactory($namespace);$f->setDatabase($db);
    $f->setDispatcher($container->get(Joomla\Event\DispatcherInterface::class));
    $f->setUserFactory($container->get(Joomla\CMS\User\UserFactoryInterface::class));
    $f->setFormFactory($container->get(Joomla\CMS\Form\FormFactoryInterface::class));$f->setCacheControllerFactory(new NativeCache189());
    $component=new Joomla\CMS\Extension\MVCComponent(new Joomla\CMS\Dispatcher\ComponentDispatcherFactory($namespace,$f));$component->setMVCFactory($f);
    Joomla\CMS\Extension\ExtensionHelper::$extensions[Joomla\CMS\Extension\ComponentInterface::class][$name]=$component;
}
function checkoutStart189(Joomla\Event\Dispatcher $dispatcher): stdClass {
    global $db;
    $booking=$db->setQuery('SELECT * FROM #__dpcalendar_bookings WHERE id=19001')->loadObject();$booking->tickets=[];
    try{$dispatcher->dispatch('onDPPaymentNew',new Joomla\Event\Event('onDPPaymentNew',[$booking]));}catch(CheckoutRedirect189){}
    return $db->setQuery('SELECT * FROM #__dpcalendar_bookings WHERE id=19001')->loadObject();
}
function checkoutCallback189(Joomla\Event\Dispatcher $dispatcher,stdClass $booking,array $data): void {
    try{$dispatcher->dispatch('onDPPaymentCallBack',new Joomla\Event\Event('onDPPaymentCallBack',[$booking,$data]));}catch(CheckoutRedirect189){}
}
function checkoutRelease189(): void {
    global $db,$container;
    verifyN189(schedulerRunN189(),'Scheduler did not succeed.');
    verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_bookings')->loadResult()===0,'Native booking retained: '.json_encode($container->get(ReservationCleanupService::class)->getNativeTaskReport()));
    verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_tickets')->loadResult()===0,'Native tickets retained.');
    verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__copymypage_event_seats WHERE status=0 AND cart_id IS NULL AND ticket_id IS NULL')->loadResult()===15,'Seats not released.');
    verifyN189((int)$db->setQuery('SELECT status FROM #__copymypage_ticket_carts')->loadResult()===3 && (int)$db->setQuery('SELECT native_payment_phase FROM #__copymypage_ticket_carts')->loadResult()===6,'Cart not closed.');
    $audits192=$db->setQuery('SELECT * FROM #__action_logs')->loadObjectList();
    verifyN189(count($audits192)===1,'Expected one native cleanup audit.');
    $message192=checkActionLog192($audits192[0]);
    if($message192['providerCheck']!==null){
        $proof192=json_decode($message192['providerCheck'],true,512,JSON_THROW_ON_ERROR);
        $keys192=array_keys($proof192);sort($keys192);
        $providerParams192=json_decode($db->setQuery("SELECT params FROM #__extensions WHERE element='paypal'")->loadResult(),true,512,JSON_THROW_ON_ERROR);
        $expectedEnvironment192=$providerParams192['providers'][0]['sandbox'] ? 'sandbox' : 'live';
        verifyN189($keys192===['accountHash','checkedAt','environment','orderHash','responseHash','status']
            && $proof192['environment']===$expectedEnvironment192 && $proof192['orderHash']===hash('sha256','SYNTHETICORDER189')
            && in_array($proof192['status'],['CREATED','PAYER_ACTION_REQUIRED','VOIDED'],true),
            'Native audit lost provider evidence: keys='.implode(',',$keys192).'; status='.($proof192['status']??'missing')
                .'; environment='.($proof192['environment']??'missing').'; order binding='.(int)(($proof192['orderHash']??null)===hash('sha256','SYNTHETICORDER189')));
    }
    $after=snapshotN189(false);verifyN189(schedulerRunN189() && snapshotN189(false)===$after,'Repeat altered business data.');
}
function checkoutSchedulerMatrix189(): void {
    global $db,$app,$factory,$container,$actor,$driverClass,$connectionOptions;
    nativeCreation189();
    [$http,$client,$vendor,$dispatcher]=checkoutEnvironment189();
    $db->setQuery('UPDATE #__dpcalendar_bookings SET book_date=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 59 MINUTE)')->execute();
    verifyN189(schedulerRunN189() && (int)$db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_bookings')->loadResult()===1 && $http->gets===0,'60-minute boundary ignored.');
    $db->setQuery('UPDATE #__dpcalendar_bookings SET book_date=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 61 MINUTE)')->execute();
    checkoutRelease189();verifyN189($http->gets===0,'Unstarted booking queried provider.');
    echo "PASS checkout 1: unstarted booking retained at 59 minutes, deleted at 61; no provider call; 15 seats/cart released\n";

    foreach(['cancel','browser_closed','return_again','repeated_start'] as $scenario){
        [$http,$client,$vendor,$dispatcher]=checkoutEnvironment189();$booking=checkoutStart189($dispatcher);
        verifyN189($client->creates===1 && $booking->transaction_id==='SYNTHETICORDER189' && strlen($booking->token)===64,'Native order start not persisted.');
        verifyN189($client->createBody['intent']==='CAPTURE' && $client->createBody['payment_source']['paypal']['experience_context']['cancel_url']===$client->createBody['payment_source']['paypal']['experience_context']['return_url'],'Original cancel routing changed.');
        if($scenario==='cancel' || $scenario==='return_again'){
            checkoutCallback189($dispatcher,$booking,[]);
            verifyN189($client->gets===1 && $client->captures===0 && str_contains($app->getResponse()->getHeaderLine('Location'),'booking.paycancel'),'Cancel did not use native GET/redirect.');
            verifyN189((int)$db->setQuery('SELECT native_payment_phase FROM #__copymypage_ticket_carts')->loadResult()===2,'Read-only cancel poisoned capture phase.');
            finishCallbackN189($container->get(NativeReservationService::class));
        }
        if($scenario==='return_again' || $scenario==='repeated_start'){
            for($attempt=0;$attempt<3;++$attempt){
                $blocked=false;try{checkoutStart189($dispatcher);}catch(DomainException){$blocked=true;}
                verifyN189($blocked && $client->creates===1,'Repeated start created another order.');
            }
        }
        // End the synthetic callback request; no second request-local retained capability.
        $native=$container->get(NativeReservationService::class);
        (new ReflectionProperty($native,'callbackLocks'))->setValue($native,[]);
        checkoutRelease189();verifyN189($http->gets===1 && $client->captures===0,'Missing bounded GET or unexpected capture.');
        $blocked=false;try{checkoutCallback189($dispatcher,$booking,['PayerID'=>'SYNTHETIC']);}catch(DomainException){$blocked=true;}
        verifyN189($blocked && $client->captures===0,'Late approval reached capture after deletion.');
        echo 'PASS checkout ', $scenario, ": native order/cancel path, one status GET, native scheduler deletion, stale capture blocked\n";
    }
    foreach(['APPROVED','COMPLETED','SAVED','unknown','captured','processing_instruction','wrong_order','wrong_amount','wrong_currency','wrong_booking','missing_wallet','404','timeout'] as $case){
        [$http,$client,$vendor,$dispatcher]=checkoutEnvironment189();checkoutStart189($dispatcher);
        if(in_array($case,['APPROVED','COMPLETED','SAVED','unknown'],true)){$http->order['status']=$case;}
        if($case==='captured'){$http->order['purchase_units'][0]['payments']=['captures'=>[['id'=>'SYNTHETIC','status'=>'COMPLETED']]];}
        if($case==='processing_instruction'){$http->order['processing_instruction']='ORDER_COMPLETE_ON_PAYMENT_APPROVAL';}
        if($case==='wrong_order'){$http->order['id']='WRONGORDER';}
        if($case==='wrong_amount'){$http->order['purchase_units'][0]['amount']['value']='194.00';}
        if($case==='wrong_currency'){$http->order['purchase_units'][0]['amount']['currency_code']='USD';}
        if($case==='wrong_booking'){$http->order['purchase_units'][0]['description']='[19002] wrong';}
        if($case==='missing_wallet'){unset($http->order['payment_source']);}
        if($case==='404'){$http->code=404;}
        if($case==='timeout'){$http->duringGet=static function():void{throw new RuntimeException('Synthetic timeout.');};}
        $before=snapshotN189(false);verifyN189(schedulerRunN189() && snapshotN189(false)===$before,'Unsafe provider state deleted: '.$case);
        $report=$container->get(ReservationCleanupService::class)->getNativeTaskReport();verifyN189($report['held']===1 && $report['errors']===0,'Provider HOLD counted as technical failure.');
    }
    echo "PASS provider matrix: approved/completed/capture/resources/auto-capture/binding errors/404/timeout all HOLD, code 0, unchanged seats/cart\n";
    foreach(['CREATED','VOIDED'] as $status){[$http,$client,$vendor,$dispatcher]=checkoutEnvironment189();checkoutStart189($dispatcher);$http->order['status']=$status;checkoutRelease189();}
    foreach([0,false,1,true] as $sandbox){
        [$http,$client,$vendor,$dispatcher]=checkoutEnvironment189();checkoutStart189($dispatcher);
        $params=json_decode($db->setQuery("SELECT params FROM #__extensions WHERE element='paypal'")->loadResult(),true);$params['providers'][0]['sandbox']=$sandbox;
        $db->setQuery('UPDATE #__extensions SET params='.$db->quote(json_encode($params))." WHERE element='paypal'")->execute();
        checkoutRelease189();verifyN189($http->urls[0]===($sandbox?'https://api-m.sandbox.paypal.com':'https://api-m.paypal.com').'/v1/oauth2/token','Wrong provider environment selected.');
    }
    [$http,$client,$vendor,$dispatcher]=checkoutEnvironment189();checkoutStart189($dispatcher);
    $report=selectedDeleteN189(static function()use($container):void{(new ReflectionProperty(ReservationCleanupService::class,'nativeStatusDeadline'))->setValue($container->get(ReservationCleanupService::class),0.0);});
    verifyN189(($report['reasons']['provider_check_budget']??0)===1 && $http->gets===0,'Provider time budget did not HOLD.');
    echo "PASS CREATED/VOIDED, synthetic live/sandbox endpoints and provider budget; zero external calls\n";

    [$http,$client,$vendor,$dispatcher]=checkoutEnvironment189();$booking=checkoutStart189($dispatcher);
    $other=new $driverClass($connectionOptions);$other->connect();$competitor=otherNativeN189($other);$blocked=false;
    $http->duringGet=static function()use($competitor,$booking,&$blocked):void{try{$competitor->assertCallback(19001,false,$booking);}catch(DomainException){$blocked=true;}};
    checkoutRelease189();verifyN189($blocked,'Callback crossed status-read/deletion lock.');$other->disconnect();
    echo "PASS checkout 12: second real DB connection cannot capture during provider GET/native deletion\n";

    [$http,$client,$vendor,$dispatcher]=checkoutEnvironment189();$booking=checkoutStart189($dispatcher);
    checkoutCallback189($dispatcher,$booking,['PayerID'=>'SYNTHETIC']);
    verifyN189($client->captures===1 && (int)$db->setQuery('SELECT state FROM #__dpcalendar_bookings')->loadResult()===1,'Successful native capture/save failed.');
    verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_tickets WHERE state=1')->loadResult()===15,'Paid native tickets not active.');
    finishCallbackN189($container->get(NativeReservationService::class));
    $before=snapshotN189(false);verifyN189(schedulerRunN189() && snapshotN189(false)===$before && $http->gets===0,'Paid booking changed or provider queried.');
    echo "PASS checkout 6: original plugin capture, real native model save, 15 active tickets; scheduler preserves paid booking\n";
    $system=new Joomla\Plugin\System\CopyMyPage\Extension\CopyMyPage(['name'=>'copymypage','type'=>'system']);$system->setApplication($app);
    $app->getInput()->set('task','booking.cancel');$app->getInput()->set('b_id',19001);$blocked=false;
    try{(new ReflectionMethod($system,'guardDPCalendarCustomerBookingMutation'))->invoke($system,new Joomla\CMS\Event\Application\AfterRouteEvent('onAfterRoute',['subject'=>$app]));}catch(CheckoutRedirect189){$blocked=str_contains($app->getResponse()->getHeaderLine('Location'),'ticketselection');}
    verifyN189($blocked && snapshotN189(false)===$before,'Paid customer cancellation changed booking.');
    echo "PASS checkout 10: actual customer cancellation guard redirects before mutation; paid booking/seats unchanged\n";

    [$http,$client,$vendor,$dispatcher]=checkoutEnvironment189();
    $db->setQuery('UPDATE #__dpcalendar_bookings SET state=2,token=NULL')->execute();$db->setQuery('UPDATE #__dpcalendar_tickets SET state=2')->execute();
    $service=(new ReflectionClass(Joomla\Component\CopyMyPage\Site\Service\OrderCheckoutService::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($service,'app'))->setValue($service,$app);(new ReflectionProperty($service,'db'))->setValue($service,$db);
    $seenToken=false;
    $dispatcher->addListener('onContentBeforeSave',static function($event)use(&$seenToken):void{if($event->getContext()==='com_dpcalendar.booking' && (int)$event->getItem()->state===3){$seenToken=strlen((string)$event->getItem()->token)===64;}});
    $confirmed=(new ReflectionMethod($service,'confirmDPCalendarBooking'))->invoke($service,19001,'paypal-73');
    verifyN189($seenToken && (int)$confirmed->state===3 && empty($confirmed->token) && empty($confirmed->transaction_id),'Checkout confirmation/token lifecycle incorrect.');
    verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_tickets WHERE state=3')->loadResult()===15 && $client->creates===0,'Confirmation started provider or omitted tickets.');
    checkoutRelease189();
    echo "PASS checkout confirmation: real confirm() sets state 3 with temporary token; finally clears token before PayPal; normal NOT_STARTED cleanup works\n";

    foreach(['lazy','webcron'] as $entry){
        [$http,$client,$vendor,$dispatcher]=checkoutEnvironment189();checkoutStart189($dispatcher);
        registerCheckoutComponent189('scheduler','\\Joomla\\Component\\Scheduler');
        Joomla\CMS\Component\ComponentHelper::getParams('com_scheduler')->set('lazy_scheduler.enabled',true);
        Joomla\CMS\Component\ComponentHelper::getParams('com_scheduler')->set('webcron.enabled',true);
        Joomla\CMS\Component\ComponentHelper::getParams('com_scheduler')->set('webcron.key','SYNTHETIC-WEBCRON');
        $db->setQuery('UPDATE #__scheduler_tasks SET next_execution=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE)')->execute();
        $runner=new Joomla\Plugin\System\Schedulerunner\Extension\ScheduleRunner($dispatcher,['name'=>'schedulerunner','type'=>'system']);$runner->setApplication($app);
        $notification=new Joomla\Plugin\System\TaskNotification\Extension\TaskNotification($dispatcher,['name'=>'tasknotification','type'=>'system']);$notification->setApplication($app);$notification->setDatabase($db);$dispatcher->addSubscriber($notification);
        if($entry==='lazy'){$runner->runLazyCron(new Joomla\Event\Event('onAjaxRunSchedulerLazy'));}
        else{$app->getInput()->set('hash','SYNTHETIC-WEBCRON');$app->getInput()->set('id',(int)$db->setQuery("SELECT id FROM #__scheduler_tasks WHERE type='plg_task_dpcalendar_booking_deletions'")->loadResult());$runner->runWebCron(new Joomla\Event\Event('onAjaxRunSchedulerWebcron'));}
        verifyN189((int)$db->setQuery('SELECT times_executed FROM #__scheduler_tasks')->loadResult()===1 && (int)$db->setQuery('SELECT last_exit_code FROM #__scheduler_tasks')->loadResult()===0 && $db->setQuery('SELECT locked FROM #__scheduler_tasks')->loadResult()===null,'Regular scheduler entry did not run/unlock task: '.$entry);
        verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_bookings')->loadResult()===0 && (int)$db->setQuery('SELECT COUNT(*) FROM #__copymypage_event_seats WHERE status=0')->loadResult()===15,'Regular scheduler did not release: '.$entry);
        verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__scheduler_logs WHERE exitcode=0')->loadResult()===1,'Native task log missing.');
        echo 'PASS regular ', $entry, ": installed ScheduleRunner -> Scheduler -> TaskModel lock -> Task::run -> native adapter, code 0, 15 seats freed\n";
    }
    centralRunnerMatrix189();
}
function nativeCreation189(): void {
    global $db,$app,$factory;
    foreach([false,true] as $withToken){
        checkoutEnvironment189();
        $db->setQuery('DELETE FROM #__dpcalendar_tickets')->execute();$db->setQuery('DELETE FROM #__dpcalendar_bookings')->execute();
        $calendar=new DigitalPeak\Component\DPCalendar\Administrator\Calendar\Calendar('0','SYNTHETIC BOOKABLE CALENDAR',$app->getIdentity());$calendar->setCanBook(true);
        (new ReflectionProperty(DigitalPeak\Component\DPCalendar\Administrator\Model\CalendarModel::class,'calendars'))->setValue(null,[0=>$calendar]);
        $prices=json_encode(['prices0'=>['label'=>'SYNTHETIC standard','value'=>13,'currency'=>'EUR','capacity'=>10]]);
        $db->setQuery("UPDATE #__dpcalendar_events SET state=1,max_tickets=10,created='2026-01-01 00:00:00',booking_opening_date='2026-01-01',booking_closing_date='2027-12-01',prices=".$db->quote($prices))->execute();
        Joomla\CMS\Component\ComponentHelper::getParams('com_dpcalendar')->set('bookingsys_enable_token',$withToken);
        $app->getParams('com_dpcalendar')->set('bookingsys_enable_token',$withToken);
        $app->getSession()->set('com_dpcalendar.booking_id',0);$app->getInput()->set('b_id',0);
        $service=(new ReflectionClass(Joomla\Component\CopyMyPage\Site\Service\OrderCheckoutService::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($service,'app'))->setValue($service,$app);(new ReflectionProperty($service,'db'))->setValue($service,$db);
        $input=['city'=>'SYNTHETIC','country'=>'','email'=>'synthetic@example.invalid','event_id'=>[19003=>['tickets'=>[0=>5]],19004=>['tickets'=>[0=>5]],19005=>['tickets'=>[0=>5]]],
            'first_name'=>'SYNTHETIC','name'=>'CUSTOMER','number'=>'1','province'=>'','series'=>0,'state'=>2,'street'=>'SYNTHETIC','telephone'=>'','user_id'=>0,'zip'=>'00000','latitude'=>50,'longitude'=>10];
        $id=(new ReflectionMethod($service,'createDPCalendarBooking'))->invoke($service,$input);
        $booking=$db->setQuery('SELECT * FROM #__dpcalendar_bookings WHERE id='.$id)->loadObject();
        verifyN189((int)$booking->state===2 && (float)$booking->price===195.0 && empty($booking->transaction_id),'Native creation state/amount/reference incorrect: '.json_encode([$booking->state,$booking->price,$booking->transaction_id]));
        verifyN189((int)$db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_tickets WHERE booking_id='.$id.' AND state=2')->loadResult()===15,'Native creation missed tickets.');
        verifyN189($withToken ? strlen((string)$booking->token)===32 : empty($booking->token),'Native pre-payment token configuration misunderstood.');
    }
    (new ReflectionProperty(DigitalPeak\Component\DPCalendar\Administrator\Model\CalendarModel::class,'calendars'))->setValue(null,[]);
    echo "PASS native creation: real model/save pipeline creates state 2 and 15 tickets/195 EUR; optional native token exists BEFORE PayPal\n";
}
function centralRunnerMatrix189(): void {
    global $options,$db,$app,$container;
    if(!isset($options['runner'])){return;}
    require_once $options['runner'];
    $rejected=false;try{verifySourceFiles(JPATH_BASE,sourceGuards());}catch(RuntimeException $e){$rejected=$e->getMessage()==='qualified_source';}
    verifyN189($rejected,'Expected archived runner qualification to reject changed candidate.');
    echo "PASS archived runner source guard refuses unqualified current candidate; no guard weakened\n";
    foreach(['release','hold','technical_error'] as $case){
        [$http,$client,$vendor,$dispatcher]=checkoutEnvironment189();checkoutStart189($dispatcher);
        if($case==='hold'){$http->code=404;}
        if($case==='technical_error'){$dispatcher->addListener('onContentBeforeDelete',static function($event):void{if($event->getContext()==='com_dpcalendar.booking'){throw new RuntimeException('Synthetic deletion failure.');}});}
        registerCheckoutComponent189('scheduler','\\Joomla\\Component\\Scheduler');
        $params=Joomla\CMS\Component\ComponentHelper::getParams('com_scheduler');$params->set('webcron.enabled',true);$params->set('webcron.key','SYNTHETIC-WEBCRON');
        $db->setQuery('UPDATE #__scheduler_tasks SET next_execution=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE)')->execute();
        $runner=new Joomla\Plugin\System\Schedulerunner\Extension\ScheduleRunner($dispatcher,['name'=>'schedulerunner','type'=>'system']);$runner->setApplication($app);
        $notification=new Joomla\Plugin\System\TaskNotification\Extension\TaskNotification($dispatcher,['name'=>'tasknotification','type'=>'system']);$notification->setApplication($app);$notification->setDatabase($db);$dispatcher->addSubscriber($notification);
        $snap=static function()use($db):array{
            $rows=$db->setQuery('SELECT id,type,state,cli_exclusive,created_by,params,execution_rules,cron_rules,last_execution,next_execution,last_exit_code,locked,times_executed,times_failed FROM #__scheduler_tasks')->loadAssocList();
            $s=taskInventory($rows,time(),false);$s['logs']=$db->setQuery('SELECT id,jobid,exitcode,lastdate,duration FROM #__scheduler_logs ORDER BY id DESC LIMIT 16')->loadAssocList();$s['max_log']=$s['logs']?(int)$s['logs'][0]['id']:0;return $s;
        };
        $request=static function($id,$seconds)use($runner,$app):void{verifyN189($seconds>0 && $seconds<=45,'Central timeout guard lost.');$app->getInput()->set('hash','SYNTHETIC-WEBCRON');$app->getInput()->set('id',$id);$runner->runWebCron(new Joomla\Event\Event('onAjaxRunSchedulerWebcron'));};
        $pending=false;$events=[];
        $rc=runCycle($snap,$request,static function($e)use(&$events):void{$events[]=$e;},static fn()=>microtime(true),static function($id):void{},0,210,6,['general'=>45,'payment'=>90],static function()use(&$pending):bool{return $pending;},static function()use(&$pending):void{verifyN189(!$pending,'Concurrent central request.');$pending=true;},static function()use(&$pending):void{$pending=false;});
        verifyN189($rc===($case==='technical_error'?21:0) && !$pending,'Central cycle misclassified native result: '.$case);
        $report=$container->get(ReservationCleanupService::class)->getNativeTaskReport();
        verifyN189($report['deleted']===($case==='release'?1:0) && $report['held']===($case==='hold'?1:0) && $report['errors']===($case==='technical_error'?1:0),'Central HOLD/error separation incorrect.');
        echo 'PASS central runner core -> real local Webcron/Task/log/adapter ', $case, ': exit ', $rc,"; transport/OS wrapper simulated\n";
    }
    $s=$snap();$calls=0;
    foreach(['native_lock','pending_request','budget','timeout'] as $case){
        $test=$s;if($case==='native_lock'){$test['locked']=1;}
        if(in_array($case,['timeout','budget'],true)){$id=array_key_first($test['tasks']);$test['tasks'][$id]['next_execution']=gmdate('Y-m-d H:i:s',time()-60);$test['due']=[$id];}
        $pending=$case==='pending_request';$clock=0;
        $rc=runCycle(static fn()=>$test,static function()use(&$calls):void{++$calls;throw new RuntimeException('https_uncertain');},static function($e):void{},static function()use(&$clock,$case):int{return $case==='budget'?$clock+=200:0;},static function($id):void{},0,210,6,['general'=>45,'payment'=>90],static function()use(&$pending):bool{return $pending;},static function()use(&$pending):void{$pending=true;},static function()use(&$pending):void{$pending=false;});
        verifyN189($rc===($case==='budget'?30:22),'Central lock/pending/budget/timeout guard failed: '.$case);
    }
    verifyN189($calls===1,'Guarded central request was dispatched.');
    echo "PASS central simulation: native lock, durable-pending guard, budget and uncertain transport stop safely\n";
}
