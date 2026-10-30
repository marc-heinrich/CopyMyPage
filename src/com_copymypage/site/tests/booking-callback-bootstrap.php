<?php
/**
 * Issue #190 regression: PHP CLI, installed Joomla/DPCalendar classes, SQLite :memory: only.
 * Run: php -d allow_url_fopen=0 -d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail components/com_copymypage/tests/booking-callback-bootstrap.php
 * Never loads configuration.php, provider plugins, real sessions, or a Joomla database.
 * The native booking model is a fixture; native pay() stops at the provider boundary.
 * SQLite omits FOR UPDATE, so this test makes no claim about MySQL locking/concurrency.
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
define('_JEXEC', 1);
define('JDEBUG', false);
define('JPATH_BASE', dirname(__DIR__, 3));
require JPATH_BASE . '/includes/defines.php';
$loader = require JPATH_LIBRARIES . '/vendor/autoload.php';
foreach ([
    'Joomla\\CMS\\' => JPATH_LIBRARIES . '/src',
    'DigitalPeak\\Component\\DPCalendar\\Site\\' => JPATH_SITE . '/components/com_dpcalendar/src',
    'Joomla\\Component\\CopyMyPage\\Site\\' => dirname(__DIR__) . '/src',
    'Joomla\\Plugin\\System\\CopyMyPage\\' => JPATH_PLUGINS . '/system/copymypage/src',
] as $namespace => $path) {
    $loader->addPsr4($namespace, $path);
}

use DigitalPeak\Component\DPCalendar\Site\Controller\BookingController;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Event\Application\AfterRouteEvent;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\Component\CopyMyPage\Site\Service\BookingCallbackContextService;
use Joomla\Component\CopyMyPage\Site\Service\BookingIntegrityService;
use Joomla\Component\CopyMyPage\Site\Service\NativePayPalProviderRegistry;
use Joomla\Component\CopyMyPage\Site\Service\NativeReservationService;
use Joomla\Component\CopyMyPage\Site\Service\PaymentReconciliationService;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\Event\Dispatcher;
use Joomla\Event\Priority;
use Joomla\Input\Input;
use Joomla\Plugin\System\CopyMyPage\Extension\CopyMyPage;
use Joomla\Registry\Registry;

function check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

function test(string $label, Closure $run): void
{
    $run();
    ++$GLOBALS['passed'];
    echo 'PASS ', $label, PHP_EOL;
}

/** Adapt only SQL locks and SQLite's uppercase metadata keys. */
final class MemoryDatabase extends Joomla\Database\Sqlite\SqliteDriver
{
    public function connect()
    {
        parent::connect();
        // SQL compatibility only; real named-lock behaviour is tested by native-reservation-mysql.php.
        $this->connection->sqliteCreateFunction('DATABASE', static fn() => 'fixture190');
        $this->connection->sqliteCreateFunction('GET_LOCK', static fn($name, $wait) => 1);
        $this->connection->sqliteCreateFunction('RELEASE_LOCK', static fn($name) => 1);
        $this->connection->sqliteCreateFunction('UTC_TIMESTAMP', static fn() => gmdate('Y-m-d H:i:s'));
    }

    public function setQuery($query, $offset = 0, $limit = 0)
    {
        if (is_string($query)) {
            $query = preg_replace('/ FOR UPDATE$/', '', $query);
        }
        return parent::setQuery($query, $offset, $limit);
    }

    public function getTableColumns($table, $typeOnly = true)
    {
        return array_change_key_case(parent::getTableColumns($table, $typeOnly), CASE_LOWER);
    }
}

final class FixtureUser extends Joomla\CMS\User\User
{
    public function authorise($action, $assetname = null)
    {
        return false;
    }
}

final class ProviderBoundaryReached extends RuntimeException {}

final class FixtureBookingModel
{
    public array $nativeTicketIds = [19011, 19012];

    public function getItem($key): object|false
    {
        global $db, $input;
        $booking = $db->setQuery('SELECT * FROM #__dpcalendar_bookings WHERE id = 19001')->loadObject();
        if (!$booking || (is_array($key) && ($key['token'] ?? null) !== $booking->token)) {
            return false;
        }
        check($input->getString('token') === $booking->token, 'Native model did not receive booking capability');
        check($input->getString('dptoken') === $booking->token, 'Native model lost dptoken context');
        $booking->tickets = array_map(static fn(int $id): object => (object) ['id' => $id], $this->nativeTicketIds);
        return $booking;
    }
}

/** Inherits the installed native pay(); replaces only its data-loading boundary. */
final class FixtureBookingController extends BookingController
{
    public function getModel($name = 'Booking', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return $GLOBALS['model'];
    }
}

final class FixtureMVCFactory extends MVCFactory
{
    public function createModel($name, $prefix = '', array $config = [])
    {
        check($name === 'Booking' && $prefix === 'Administrator', 'Unexpected native model');
        return $GLOBALS['model'];
    }

    protected function getClassName(string $suffix, string $prefix)
    {
        check($suffix === 'Controller\\BookingController' && $prefix === 'Site', 'Unexpected native controller');
        return FixtureBookingController::class;
    }
}

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/provider-free-regression/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_GET = ['token' => 'synthetic-provider-order', 'PayerID' => 'synthetic-payer'];
$_POST = [];
$input = new Input(['option' => 'com_dpcalendar', 'task' => 'booking.pay', 'b_id' => 19001,
    'token' => 'synthetic-provider-order', 'dptoken' => 'synthetic-booking-capability']);
$container = new Container();
$container->share(Joomla\CMS\Cache\CacheControllerFactoryInterface::class, new Joomla\CMS\Cache\CacheControllerFactory(), true);
$app = new SiteApplication($input, new Registry(['session' => false, 'mailonline' => false]), null, $container);
$app->setLogger(new Psr\Log\NullLogger());
$app->setSession(new Joomla\CMS\Session\Session(new Joomla\Session\Storage\RuntimeStorage()));
(new ReflectionProperty($app, 'language'))->setValue($app, new Joomla\CMS\Language\Language('en-GB'));
$identity = new FixtureUser();
$app->loadIdentity($identity);
Factory::$container = $container;
Factory::$application = $app;
Factory::$language = $app->getLanguage();
// No installed payment plugin can be imported by native pay().
(new ReflectionProperty(Joomla\CMS\Plugin\PluginHelper::class, 'plugins'))->setValue(null, []);

$db = new MemoryDatabase(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'reg190_']);
foreach ([
    'dpcalendar_bookings' => 'id INTEGER PRIMARY KEY,user_id INTEGER,uid TEXT,state INTEGER,price REAL,currency TEXT,payment_provider TEXT,payment_provider_fee REAL,transaction_id TEXT,token TEXT',
    'copymypage_ticket_carts' => 'id INTEGER PRIMARY KEY,booking_id INTEGER,user_id INTEGER,status INTEGER,payment_provider TEXT,native_payment_phase TINYINT UNSIGNED NOT NULL DEFAULT 0,revision INTEGER,terms_accepted_at TEXT,terms_snapshot TEXT,modified TEXT',
    'dpcalendar_tickets' => 'id INTEGER PRIMARY KEY,booking_id INTEGER,event_id INTEGER,user_id INTEGER,state INTEGER,price REAL,type INTEGER',
    'dpcalendar_events' => 'id INTEGER PRIMARY KEY,title TEXT,created_by INTEGER',
    'copymypage_ticket_cart_items' => 'id INTEGER PRIMARY KEY,cart_id INTEGER,event_id INTEGER,price_index INTEGER,quantity INTEGER,unit_price REAL,currency TEXT',
    'copymypage_seats' => 'id INTEGER PRIMARY KEY',
    'copymypage_event_seats' => 'id INTEGER PRIMARY KEY,seat_id INTEGER,event_id INTEGER,cart_id INTEGER,ticket_id INTEGER,status INTEGER,assignment_order INTEGER,price_index INTEGER',
    'copymypage_payment_attempts' => 'id INTEGER PRIMARY KEY,booking_id INTEGER,provider_id TEXT,order_id TEXT',
    'extensions' => 'id INTEGER PRIMARY KEY,type TEXT,folder TEXT,element TEXT,enabled INTEGER,params TEXT',
] as $table => $columns) {
    $db->setQuery('CREATE TABLE #__' . $table . ' (' . $columns . ')')->execute();
}

function fixture(string $provider = 'paypal-73'): void
{
    global $db, $input, $app, $identity, $model;
    foreach (['dpcalendar_bookings', 'copymypage_ticket_carts', 'dpcalendar_tickets', 'dpcalendar_events',
        'copymypage_ticket_cart_items', 'copymypage_seats', 'copymypage_event_seats',
        'copymypage_payment_attempts', 'extensions'] as $table) {
        $db->setQuery('DELETE FROM #__' . $table)->execute();
    }
    $id = substr($provider, 7);
    $params = json_encode(['providers' => ['instance' => ['id' => $id, 'state' => '1', 'sandbox' => '1']]]);
    $db->setQuery("INSERT INTO #__extensions VALUES (1,'plugin','dpcalendarpay','paypal',1," . $db->quote($params) . ')')->execute();
    $db->setQuery("INSERT INTO #__dpcalendar_bookings VALUES (19001,190,'synthetic-uid',3,26,'EUR'," . $db->quote($provider) . ",0,'synthetic-order','synthetic-booking-capability')")->execute();
    $snapshot = ['version' => 2, 'cartRevision' => 0, 'acceptedAt' => '2026-10-29 00:00:00',
        'bookingOwner' => ['userId' => 190], 'provider' => ['id' => $provider, 'currency' => 'EUR', 'total' => 26, 'fee' => 0],
        'eventIds' => [19003], 'tickets' => [19003 => [0 => ['quantity' => 2, 'unitPrice' => 13]]], 'seats' => []];
    foreach ([19011, 19012] as $n => $ticket) {
        $seat = 19021 + $n;
        $inventory = 19031 + $n;
        $snapshot['seats'][$ticket] = ['id' => $inventory, 'seatId' => $seat, 'eventId' => 19003, 'type' => 0, 'order' => $n + 1];
        $db->setQuery("INSERT INTO #__dpcalendar_tickets VALUES ($ticket,19001,19003,190,3,13,0)")->execute();
        $db->setQuery("INSERT INTO #__copymypage_seats VALUES ($seat)")->execute();
        $db->setQuery("INSERT INTO #__copymypage_event_seats VALUES ($inventory,$seat,19003,19002,$ticket,2," . ($n + 1) . ',0)')->execute();
    }
    $db->setQuery('INSERT INTO #__copymypage_ticket_carts VALUES (19002,19001,190,1,' . $db->quote($provider)
        . ",2,1,'2026-10-29 00:00:00'," . $db->quote(json_encode($snapshot)) . ',NULL)')->execute();
    $db->setQuery("INSERT INTO #__dpcalendar_events VALUES (19003,'SYNTHETIC REGRESSION',0)")->execute();
    $db->setQuery("INSERT INTO #__copymypage_ticket_cart_items VALUES (19004,19002,19003,0,2,13,'EUR')")->execute();
    $identity->id = 0;
    $identity->guest = 1;
    $app->getSession()->set('com_dpcalendar.booking_id', 0);
    $input->set('b_id', 19001);
    $input->set('token', 'synthetic-provider-order');
    $input->set('dptoken', 'synthetic-booking-capability');
    $model->nativeTicketIds = [19011, 19012];
    $GLOBALS['boundaries'] = 0;
    $GLOBALS['nativeGuardPassed'] = false;
}

$registry = new NativePayPalProviderRegistry($db);
$integrity = new BookingIntegrityService($db);
$native = new NativeReservationService($db, $registry, $integrity);
$payments = new PaymentReconciliationService($db, $native);
$context = new BookingCallbackContextService($app, $payments, $integrity);
$container->share(DatabaseInterface::class, $db, true);
$container->share(NativeReservationService::class, $native, true);
$container->share(PaymentReconciliationService::class, $payments, true);
$container->share(BookingCallbackContextService::class, $context, true);
$model = new FixtureBookingModel();
$fixtureFactory = new FixtureMVCFactory('DigitalPeak\\Component\\DPCalendar');
$component = new MVCComponent(new Joomla\CMS\Dispatcher\ComponentDispatcherFactory('DigitalPeak\\Component\\DPCalendar', $fixtureFactory));
$component->setMVCFactory($fixtureFactory);
ExtensionHelper::$extensions[ComponentInterface::class]['dpcalendar'] = $component;
$system = new CopyMyPage(['name' => 'copymypage', 'type' => 'system']);
$system->setApplication($app);

$GLOBALS['passed'] = 0;
test('old configuration reproduces JPATH_COMPONENT error during real onAfterRoute; fixed configuration builds native controller', function () use ($app, $input) {
    $dispatcher = new Dispatcher();
    $app->setDispatcher($dispatcher);
    $ran = false;
    $dispatcher->addListener('onAfterRoute', function () use ($app, $input, &$ran) {
        check(!defined('JPATH_COMPONENT'), 'Test must run before component dispatch');
        $factory = new MVCFactory('DigitalPeak\\Component\\DPCalendar');
        $caught = false;
        try {
            $factory->createController('Booking', 'Site', [], $app, $input);
        } catch (Error $error) {
            $caught = str_contains($error->getMessage(), 'JPATH_COMPONENT');
        }
        check($caught, 'Original failure was not reproduced');
        $controller = $factory->createController('Booking', 'Site', ['base_path' => JPATH_SITE . '/components/com_dpcalendar'], $app, $input);
        check(get_class($controller) === BookingController::class, 'Expected installed native BookingController');
        check((new ReflectionProperty($controller, 'basePath'))->getValue($controller) === JPATH_SITE . '/components/com_dpcalendar', 'Incorrect controller base path');
        check(!defined('JPATH_COMPONENT'), 'Fix must not define the component constant');
        $ran = true;
    });
    $dispatcher->dispatch('onAfterRoute', new AfterRouteEvent('onAfterRoute', ['subject' => $app]));
    check($ran, 'Bootstrap was not exercised');
});

$dispatcher = new Dispatcher();
$app->setDispatcher($dispatcher);
$dispatcher->addListener('onAfterRoute', [$system, 'guardDPCalendarPaymentCallback'], Priority::MAX);
$dispatcher->addListener('onDPPaymentCallBack', function ($event) use ($system) {
    $system->guardNativeProviderCallback($event);
    $GLOBALS['nativeGuardPassed'] = true;
}, Priority::MAX);
$dispatcher->addListener('onDPPaymentCallBack', function ($event) {
    check($GLOBALS['nativeGuardPassed'], 'Native reservation guard was bypassed');
    $data = $event->getArgument(1);
    check(($data['token'] ?? null) === 'synthetic-provider-order', 'Provider query token was overwritten');
    check(($data['PayerID'] ?? null) === 'synthetic-payer', 'Provider payer context was lost');
    ++$GLOBALS['boundaries'];
    throw new ProviderBoundaryReached();
}, Priority::MIN);

function runReturn(): void
{
    global $dispatcher, $app;
    $dispatcher->dispatch('onAfterRoute', new AfterRouteEvent('onAfterRoute', ['subject' => $app]));
}

function expectError(string $class, ?int $code = null): void
{
    $caught = false;
    try {
        runReturn();
    } catch (Throwable $error) {
        check($error instanceof $class && ($code === null || $error->getCode() === $code), 'Unexpected callback failure: ' . get_class($error));
        $caught = true;
    }
    check($caught, 'Expected callback rejection was not reached');
    check($GLOBALS['boundaries'] === 0, 'Rejected callback reached provider boundary');
}

foreach (['paypal-0', 'paypal-1', 'paypal-73', 'paypal-9842'] as $provider) {
    test('authorized native return reaches reservation guard and provider boundary for ' . $provider, function () use ($provider, $input, $db, $native) {
        fixture($provider);
        check($native->hasSchema(), 'Synthetic reservation schema was rejected');
        try {
            runReturn();
        } catch (ProviderBoundaryReached) {
        }
        check($GLOBALS['boundaries'] === 1 && $GLOBALS['nativeGuardPassed'], 'Valid native callback was blocked');
        check($input->getString('token') === 'synthetic-provider-order' && $input->getString('dptoken') === 'synthetic-booking-capability', 'Original request context was not restored');
        check((int) $db->setQuery('SELECT native_payment_phase FROM #__copymypage_ticket_carts')->loadResult() === 4, 'Callback claim was not persisted');
        check((int) $db->setQuery('SELECT state FROM #__dpcalendar_bookings')->loadResult() === 3, 'Native booking state changed');
    });
}

test('unauthorized token is rejected before native controller', function () use ($context, $input) {
    fixture();
    $input->set('dptoken', 'synthetic-invalid-capability');
    $caught = false;
    try {
        $context->execute(19001, 'booking.pay');
    } catch (RuntimeException $error) {
        $caught = $error->getCode() === 403;
    }
    check($caught && $GLOBALS['boundaries'] === 0, 'Unauthorized callback accepted');
});

test('owner and guest session authorizations remain supported', function () use ($payments) {
    fixture();
    check($payments->getCallbackDecision(19001, '', 0, 190)['authorized'], 'Owner authorization lost');
    check($payments->getCallbackDecision(19001, '', 19001, 0)['authorized'], 'Guest session authorization lost');
    check(!$payments->getCallbackDecision(19001, '', 0, 0)['authorized'], 'Unrelated guest authorized');
});

test('capability cannot be rebound to a different booking ID', function () use ($payments) {
    fixture();
    $caught = false;
    try {
        $payments->getCallbackDecision(19009, 'synthetic-booking-capability', 0, 0);
    } catch (DomainException) {
        $caught = true;
    }
    check($caught, 'Cross-booking capability accepted');
});

test('missing ticket fails integrity before native callback', function () use ($db) {
    fixture();
    $db->setQuery('DELETE FROM #__dpcalendar_tickets WHERE id = 19012')->execute();
    expectError(RuntimeException::class, 409);
});

test('native ticket set mismatch is rejected and token context restored', function () use ($model, $input) {
    fixture();
    $model->nativeTicketIds = [19011];
    expectError(RuntimeException::class, 409);
    check($input->getString('token') === 'synthetic-provider-order', 'Exception did not restore original token');
});

foreach ([
    'released reservation' => 'UPDATE #__copymypage_ticket_carts SET native_payment_phase = 3',
    'disabled provider' => 'UPDATE #__extensions SET enabled = 0',
    'ledger conflict' => "INSERT INTO #__copymypage_payment_attempts VALUES (1,19001,'paypal-73','synthetic-order')",
] as $label => $sql) {
    test($label . ' is rejected by NativeReservationService before provider', function () use ($db, $sql) {
        fixture();
        $db->setQuery($sql)->execute();
        expectError(DomainException::class);
        check(!$GLOBALS['nativeGuardPassed'], 'Invalid native reservation passed');
    });
}

test('missing order, provider mismatch and terminal booking block the outer callback decision', function () use ($db, $payments) {
    foreach (["UPDATE #__dpcalendar_bookings SET transaction_id = ''",
        "UPDATE #__copymypage_ticket_carts SET payment_provider = 'paypal-74'",
        'UPDATE #__dpcalendar_bookings SET state = 1'] as $sql) {
        fixture();
        $db->setQuery($sql)->execute();
        $decision = $payments->getCallbackDecision(19001, 'synthetic-booking-capability', 0, 0);
        check($decision['authorized'] && $decision['block'], 'Unsafe callback was not blocked');
    }
});

test('native cancellation remains fail closed', function () use ($native) {
    fixture();
    $caught = false;
    try {
        $native->assertCallback(19001, true);
    } catch (DomainException) {
        $caught = true;
    }
    check($caught, 'Cancellation protection lost');
});

check(!defined('JPATH_COMPONENT'), 'Regression defined JPATH_COMPONENT');
foreach (get_included_files() as $file) {
    check(basename($file) !== 'configuration.php', 'Unexpected real Joomla configuration');
    check(!str_contains(str_replace('\\', '/', $file), '/plugins/dpcalendarpay/'), 'Payment provider code was loaded');
}
echo 'RESULT ', json_encode(['passed' => $GLOBALS['passed'], 'database' => 'SQLite :memory:',
    'providerCalls' => 0, 'mailCalls' => 0, 'nativeBookingModel' => 'fixture', 'JPATH_COMPONENT_defined' => false]), PHP_EOL;
