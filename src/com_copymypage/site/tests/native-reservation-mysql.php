<?php
/**
 * Issue #189: real MySQL/InnoDB connections and separate PHP request workers.
 * Run with PHP 8.4 CLI:
 * php -d xdebug.mode=off -d zend.exception_ignore_args=1 -d allow_url_fopen=0 -d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail native-reservation-mysql.php
 * Uses only joomla6_test's restricted DB account and randomly prefixed synthetic tables.
 * Never executes a regular Joomla web application, real sessions, mail or scheduler plugins.
 * Seat selection uses an unexecuted SiteApplication with an in-memory session only.
 * The installed original PayPal finishTransaction() is exercised through an HTTP mock.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
ini_set('display_errors', '0');
ini_set('log_errors', '0');
ini_set('zend.exception_ignore_args', '1');
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
foreach (['curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'mail'] as $function) {
    if (function_exists($function)) { throw new RuntimeException('Network/mail functions must be disabled.'); }
}
if (ini_get('allow_url_fopen')) { throw new RuntimeException('URL streams must be disabled.'); }
define('_JEXEC', 1);
define('JDEBUG', false);
define('JPATH_BASE', dirname(__DIR__, 3));
require JPATH_BASE . '/includes/defines.php';
$loader = require JPATH_LIBRARIES . '/vendor/autoload.php';
require JPATH_ADMINISTRATOR . '/components/com_dpcalendar/vendor/autoload.php';
foreach ([
    'Joomla\\CMS\\' => JPATH_LIBRARIES . '/src',
    'Joomla\\Component\\CopyMyPage\\Site\\' => dirname(__DIR__) . '/src',
    'Joomla\\Plugin\\System\\CopyMyPage\\' => JPATH_PLUGINS . '/system/copymypage/src',
    'DigitalPeak\\Component\\DPCalendar\\Administrator\\' => JPATH_ADMINISTRATOR . '/components/com_dpcalendar/src',
    'DigitalPeak\\Plugin\\DPCalendarPay\\PayPal\\' => JPATH_PLUGINS . '/dpcalendarpay/paypal/src',
    'Joomla\\Component\\Actionlogs\\Administrator\\' => JPATH_ADMINISTRATOR . '/components/com_actionlogs/src',
] as $namespace => $path) { $loader->addPsr4($namespace, $path); }

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\Component\CopyMyPage\Site\Service\BookingIntegrityService;
use Joomla\Component\CopyMyPage\Site\Service\NativePayPalOrderStatus;
use Joomla\Component\CopyMyPage\Site\Service\NativePayPalProviderRegistry;
use Joomla\Component\CopyMyPage\Site\Service\NativeReservationService;
use Joomla\Component\CopyMyPage\Site\Service\PaymentReconciliationService;
use Joomla\Component\CopyMyPage\Site\Service\ReservationCleanupService;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\Event\Dispatcher;
use Joomla\Event\Event;
use Joomla\Event\Priority;
use Joomla\Http\Http;
use Joomla\Plugin\System\CopyMyPage\Extension\CopyMyPage;

const ORDER189 = 'ORDER189SYNTHETIC';
const MERCHANT189 = 'MERCHANT189';
const DENY189 = 'curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail';

function ensure189(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function case189(string $name, callable $test): void
{
    $test();
    ++$GLOBALS['passed189'];
    echo 'PASS ', $name, PHP_EOL;
}

function reject189(callable $operation): void
{
    try { $operation(); } catch (DomainException) { return; }
    throw new RuntimeException('Unsafe operation was not rejected.');
}

final class Admin189 extends User
{
    public bool $allowed = true;
    public function authorise($action, $assetname = null) { return $this->allowed; }
}

final class Console189 extends Joomla\CMS\Application\ConsoleApplication
{
    protected function getDefaultCommands(): array { return []; }
}

/** Test-only SQL boundary. Ordinary installed Joomla tables cannot be addressed. */
final class Mysql189 extends Joomla\Database\Mysqli\MysqliDriver
{
    public function setQuery($query, $offset = 0, $limit = 0)
    {
        $sql = (string) $query;
        preg_match_all('/\b(?:FROM|JOIN|UPDATE|INTO|TABLE(?: IF EXISTS)?)\s+([`#A-Za-z0-9_.]+)/i', $sql, $matches);
        foreach ($matches[1] as $name) {
            $name = str_replace('`', '', $name);
            if (in_array(strtolower($name), ['information_schema.tables'], true)) { continue; }
            if (str_starts_with($name, '#__') || str_starts_with($name, $this->getPrefix())) { continue; }
            // ON DUPLICATE KEY and other SQL clauses are not table references.
            if (in_array(strtoupper($name), ['DUPLICATE'], true)) { continue; }
            throw new RuntimeException('Fixture SQL tried to address a non-fixture table.');
        }
        return parent::setQuery($query, $offset, $limit);
    }
}

function database189(string $prefix): Mysql189
{
    ensure189((bool) preg_match('/^cmp189_[a-f0-9]{12}_$/D', $prefix), 'Invalid fixture prefix.');
    $testRoot = realpath('C:/wamp/www/joomla6_test');
    ensure189($testRoot !== false && strtolower(str_replace('\\', '/', $testRoot)) === 'c:/wamp/www/joomla6_test', 'Unexpected test root.');
    if (!class_exists('JConfig', false)) { require $testRoot . '/configuration.php'; }
    $config = new JConfig();
    ensure189($config->db === 'joomla_db_test' && in_array($config->host, ['localhost', '127.0.0.1', '::1'], true), 'Test database isolation changed.');
    $db = new Mysql189(['host' => $config->host, 'user' => $config->user, 'password' => $config->password,
        'database' => $config->db, 'prefix' => $prefix, 'driver' => 'mysqli', 'utf8mb4' => true]);
    $db->connect();
    $grants = $db->getConnection()->query('SHOW GRANTS')->fetch_all(MYSQLI_NUM);
    foreach ($grants as [$grant]) {
        ensure189((bool) preg_match('/^GRANT (?:USAGE ON \*\.\*|.* ON `joomla_db_test`\.\*) TO /', $grant), 'Test credentials have unexpected grants.');
    }
    $db->setQuery('SET SESSION innodb_lock_wait_timeout = 2')->execute();
    return $db;
}

function services189(Mysql189 $db): array
{
    $registry = new NativePayPalProviderRegistry($db);
    $integrity = new BookingIntegrityService($db);
    $native = new NativeReservationService($db, $registry, $integrity);
    $payments = new PaymentReconciliationService($db, $native);
    return [$registry, $integrity, $native, $payments,
        new ReservationCleanupService($db, $integrity, $registry, $native, $payments)];
}

final class StatusHttp189 extends Http
{
    public array $order;
    public int $gets = 0;
    public int $code = 200;
    public bool $fail = false;
    public ?Closure $atGet = null;
    public function __construct()
    {
        $this->order = ['id' => ORDER189, 'status' => 'VOIDED', 'intent' => 'CAPTURE',
            'purchase_units' => [['amount' => ['currency_code' => 'EUR', 'value' => '26.00'],
                'description' => '[19001] SYNTHETIC REGRESSION', 'payee' => ['merchant_id' => MERCHANT189]]]];
    }
    public function post($url, $data, array $headers = [], $timeout = null)
    {
        ensure189($url === 'https://api-m.sandbox.paypal.com/v1/oauth2/token' && $data === 'grant_type=client_credentials', 'Unexpected provider write.');
        return $this->response(200, '{"access_token":"SYNTHETIC-NOT-A-TOKEN"}');
    }
    public function get($url, array $headers = [], $timeout = null)
    {
        ensure189($url === 'https://api-m.sandbox.paypal.com/v2/checkout/orders/' . ORDER189, 'Unexpected status endpoint.');
        ++$this->gets;
        if ($this->atGet) { ($this->atGet)(); }
        if ($this->fail) { throw new RuntimeException('SYNTHETIC-SENSITIVE-TRANSPORT'); }
        return $this->response($this->code, json_encode($this->order));
    }
    private function response(int $code, string $body): Joomla\Http\Response
    {
        $stream = new Laminas\Diactoros\Stream('php://memory', 'rw');
        $stream->write($body);
        return new Joomla\Http\Response($stream, $code);
    }
}

function release189(array $services, ?StatusHttp189 $http = null, bool $dryRun = false, string $provider = 'paypal-73', ?Admin189 $actor = null): array
{
    $actor ??= new Admin189();
    $actor->id = 189;
    return $services[4]->releaseExcludedNative(19001, $provider, ORDER189, MERCHANT189, $actor,
        'SYNTHETIC-189', new NativePayPalOrderStatus($services[0], $http ?? new StatusHttp189()), $dryRun);
}

function schemas189(): array
{
    return [
        'dpcalendar_bookings' => 'id INT PRIMARY KEY,user_id INT,uid VARCHAR(100),state INT,price DECIMAL(12,2),currency VARCHAR(3),payment_provider VARCHAR(80),payment_provider_fee DECIMAL(12,2),transaction_id VARCHAR(80),token VARCHAR(100),payer_id VARCHAR(80),payer_email VARCHAR(100),raw_data LONGTEXT,txn_type VARCHAR(40),txn_currency VARCHAR(3),gross_amount DECIMAL(12,2) DEFAULT 0,net_amount DECIMAL(12,2) DEFAULT 0,payment_fee DECIMAL(12,2) DEFAULT 0',
        'copymypage_ticket_carts' => 'id INT PRIMARY KEY,booking_id INT NULL,user_id INT,status INT,payment_provider VARCHAR(80),native_payment_phase TINYINT UNSIGNED NOT NULL DEFAULT 0,revision INT,terms_accepted_at DATETIME NULL,terms_snapshot LONGTEXT,modified DATETIME NULL,expires_at DATETIME,token_hash VARCHAR(64)',
        'dpcalendar_tickets' => 'id INT PRIMARY KEY,booking_id INT,event_id INT,user_id INT,state INT,price DECIMAL(12,2),type INT',
        'dpcalendar_events' => 'id INT PRIMARY KEY,title VARCHAR(100),created_by INT,capacity_used INT DEFAULT 0,start_date DATETIME NULL,end_date DATETIME NULL,all_day INT DEFAULT 0',
        'copymypage_ticket_cart_items' => 'id INT PRIMARY KEY,cart_id INT,event_id INT,price_index INT,quantity INT,unit_price DECIMAL(12,2),currency VARCHAR(3)',
        'copymypage_seats' => 'id INT PRIMARY KEY,layout_table_id INT DEFAULT 19041,sort_order INT DEFAULT 1,seat_code VARCHAR(20),seat_number VARCHAR(20),x INT DEFAULT 0,y INT DEFAULT 0',
        'copymypage_seat_layouts' => 'id INT PRIMARY KEY,status INT,title VARCHAR(100),logical_width INT,logical_height INT,geometry_json LONGTEXT',
        'copymypage_layout_tables' => 'id INT PRIMARY KEY,layout_id INT,sort_order INT,table_code VARCHAR(20),table_number VARCHAR(20),label VARCHAR(40),shape VARCHAR(20),x INT,y INT,width INT,height INT,rotation INT',
        'copymypage_ticket_customers' => 'cart_id INT PRIMARY KEY',
        'modules' => 'id INT PRIMARY KEY,module VARCHAR(80),client_id INT,published INT,position VARCHAR(80),ordering INT,params LONGTEXT',
        'copymypage_event_seats' => 'id INT PRIMARY KEY,seat_id INT,event_id INT,cart_id INT NULL,ticket_id INT NULL,status INT,assignment_order INT NULL,price_index INT NULL,allocation_type INT DEFAULT 0,modified DATETIME NULL,modified_by INT DEFAULT 0',
        'copymypage_event_seating' => 'event_id INT PRIMARY KEY,status INT,inventory_version INT,modified DATETIME NULL,modified_by INT DEFAULT 0,layout_id INT DEFAULT 19040',
        'copymypage_payment_attempts' => 'id INT PRIMARY KEY,booking_id INT,provider_id VARCHAR(80),order_id VARCHAR(80),cart_id INT',
        'extensions' => 'extension_id INT PRIMARY KEY,type VARCHAR(40),folder VARCHAR(40),element VARCHAR(40),enabled INT,params LONGTEXT,state INT DEFAULT 0,ordering INT DEFAULT 0',
        'action_logs' => 'id INT AUTO_INCREMENT PRIMARY KEY,message_language_key VARCHAR(255),message LONGTEXT,log_date DATETIME,extension VARCHAR(80),user_id INT,item_id INT,ip_address VARCHAR(80)',
    ];
}

function fixture189(Mysql189 $db, string $provider = 'paypal-73', int $phase = 2): void
{
    foreach (array_keys(schemas189()) as $table) { $db->setQuery('DELETE FROM #__' . $table)->execute(); }
    $old = gmdate('Y-m-d H:i:s', time() - 172800);
    $params = json_encode(['providers' => [['id' => substr($provider, 7), 'state' => '1', 'sandbox' => '1',
        'rest_client_id' => 'SYNTHETIC-CLIENT', 'rest_secret' => 'SYNTHETIC-SECRET']]]);
    $db->setQuery("INSERT INTO #__extensions (extension_id,type,folder,element,enabled,params) VALUES (1,'plugin','dpcalendarpay','paypal',1," . $db->quote($params) . ')')->execute();
    $booking = (object) ['id' => 19001, 'user_id' => 190, 'uid' => 'SYNTHETIC',
        'state' => 3, 'price' => 26, 'currency' => 'EUR', 'payment_provider' => $provider, 'payment_provider_fee' => 0,
        'transaction_id' => $phase === 1 ? '' : ORDER189, 'token' => $phase === 1 ? '' : 'SYNTHETIC-BOOKING-TOKEN'];
    $db->insertObject('#__dpcalendar_bookings', $booking);
    $snapshot = ['version' => 2, 'cartRevision' => 0, 'acceptedAt' => $old, 'bookingOwner' => ['userId' => 190],
        'provider' => ['id' => $provider, 'currency' => 'EUR', 'total' => 26, 'fee' => 0], 'eventIds' => [19003],
        'tickets' => [19003 => [0 => ['quantity' => 2, 'unitPrice' => 13]]], 'seats' => []];
    foreach ([19011, 19012] as $n => $ticket) {
        $seat = 19021 + $n; $inventory = 19031 + $n;
        $snapshot['seats'][$ticket] = ['id' => $inventory, 'seatId' => $seat, 'eventId' => 19003, 'type' => 0, 'order' => $n + 1];
        $db->setQuery("INSERT INTO #__dpcalendar_tickets VALUES ($ticket,19001,19003,190,3,13,0)")->execute();
        $db->setQuery("INSERT INTO #__copymypage_seats (id) VALUES ($seat)")->execute();
        $inventoryRow = (object) ['id' => $inventory, 'seat_id' => $seat, 'event_id' => 19003,
            'cart_id' => 19002, 'ticket_id' => $ticket, 'status' => 2, 'assignment_order' => $n + 1, 'price_index' => 0];
        $db->insertObject('#__copymypage_event_seats', $inventoryRow);
    }
    $cart = (object) ['id' => 19002, 'booking_id' => 19001, 'user_id' => 190,
        'status' => 1, 'payment_provider' => $provider, 'native_payment_phase' => $phase, 'revision' => 1,
        'terms_accepted_at' => $old, 'terms_snapshot' => json_encode($snapshot), 'expires_at' => $old];
    $db->insertObject('#__copymypage_ticket_carts', $cart);
    $db->setQuery("INSERT INTO #__dpcalendar_events (id,title,created_by,capacity_used) VALUES (19003,'SYNTHETIC REGRESSION',0,0)")->execute();
    $db->setQuery("INSERT INTO #__copymypage_ticket_cart_items VALUES (19004,19002,19003,0,2,13,'EUR')")->execute();
    $db->setQuery('INSERT INTO #__copymypage_event_seating (event_id,status,inventory_version) VALUES (19003,1,5)')->execute();
}

function snapshot189(Mysql189 $db): string
{
    $rows = [];
    foreach (array_keys(schemas189()) as $table) {
        $rows[$table] = $db->setQuery('SELECT * FROM #__' . $table)->loadAssocList();
    }
    return hash('sha256', json_encode($rows));
}

function waitGate189(string $file): void
{
    $deadline = microtime(true) + 20;
    do {
        clearstatcache(true, $file);
        if (is_file($file)) { return; }
        foreach (($GLOBALS['workers189'] ?? []) as $worker) {
            if (dirname($file) === $worker['dir'] && is_resource($worker['process'])
                && !proc_get_status($worker['process'])['running']) {
                throw new RuntimeException('Worker exited before ' . basename($file) . ': '
                    . stream_get_contents($worker['pipes'][1]) . stream_get_contents($worker['pipes'][2]));
            }
        }
        usleep(25000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('Worker barrier timed out: ' . basename($file));
}

function startWorker189(string $prefix, string $mode): array
{
    $dir = sys_get_temp_dir() . '/cmp189-' . bin2hex(random_bytes(10));
    ensure189(mkdir($dir), 'Cannot create worker barrier.');
    $process = proc_open([PHP_BINARY, '-d', 'xdebug.mode=off', '-d', 'zend.exception_ignore_args=1', '-d', 'allow_url_fopen=0', '-d', 'disable_functions=' . DENY189,
        __FILE__, '--worker', $prefix, $dir, $mode], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes,
        sys_get_temp_dir(), null, ['bypass_shell' => true]);
    ensure189(is_resource($process), 'Cannot start callback worker.');
    fclose($pipes[0]);
    $worker = ['process' => $process, 'pipes' => $pipes, 'dir' => $dir];
    $GLOBALS['workers189'][] = $worker;
    return $worker;
}

function finishWorker189(array $worker, bool $terminate = false): array
{
    if ($terminate) { proc_terminate($worker['process']); }
    $stdout = stream_get_contents($worker['pipes'][1]);
    $stderr = stream_get_contents($worker['pipes'][2]);
    fclose($worker['pipes'][1]); fclose($worker['pipes'][2]);
    $code = proc_close($worker['process']);
    if ($terminate) { return []; }
    ensure189($code === 0 && $stderr === '', 'Worker failed: ' . $stderr . $stdout);
    $result = json_decode(trim($stdout), true);
    ensure189(is_array($result), 'Invalid worker result.');
    return $result;
}

/** Real vendor finishTransaction(), with the transport replaced; no real provider is imported. */
final class NativePayPal189 extends DigitalPeak\Plugin\DPCalendarPay\PayPal\Extension\PayPal
{
    public function finish189(object $booking): array
    {
        return $this->finishTransaction(['PayerID' => 'SYNTHETIC-PAYER'], $booking,
            (object) ['sandbox' => true, 'rest_client_id' => 'SYNTHETIC', 'rest_secret' => 'SYNTHETIC']);
    }
}

final class ThinClient189 implements DigitalPeak\ThinHTTP\ClientInterface
{
    public int $captures = 0;
    public function get(string $url, ?string $userOrToken = null, ?string $password = null, array $headers = [], array $options = []): stdClass { throw new RuntimeException('Unexpected native GET.'); }
    public function post(string $url, $body, ?string $userOrToken = null, ?string $password = null, array $headers = [], array $options = []): stdClass
    {
        if (str_ends_with($url, '/v1/oauth2/token')) { return (object) ['access_token' => 'SYNTHETIC', 'dp' => (object) ['info' => (object) ['http_code' => 200]]]; }
        ensure189($url === 'https://api-m.sandbox.paypal.com/v2/checkout/orders/' . ORDER189 . '/capture', 'Wrong native capture binding.');
        ++$this->captures;
        return json_decode(json_encode(['id' => ORDER189, 'status' => 'COMPLETED',
            'payer' => ['payer_id' => 'SYNTHETIC', 'email_address' => 'synthetic@example.invalid'],
            'purchase_units' => [['payments' => ['captures' => [['id' => 'CAPTURE189', 'status' => 'COMPLETED',
                'seller_receivable_breakdown' => ['gross_amount' => ['currency_code' => 'EUR', 'value' => '26.00'],
                    'paypal_fee' => ['value' => '0.00'], 'net_amount' => ['value' => '26.00']]]]]]],
            'dp' => ['info' => ['http_code' => 201]]]), false);
    }
    public function put(string $url, $body, ?string $userOrToken = null, ?string $password = null, array $headers = [], array $options = []): stdClass { throw new RuntimeException('Unexpected native PUT.'); }
    public function delete(string $url, ?string $userOrToken = null, ?string $password = null, array $headers = [], array $options = []): stdClass { throw new RuntimeException('Unexpected native DELETE.'); }
    public function request(string $url, $body = '', ?string $userOrToken = null, ?string $password = null, array $headers = [], array $options = [], string $method = 'get'): stdClass { throw new RuntimeException('Unexpected native request.'); }
}

function worker189(string $prefix, string $dir, string $mode): void
{
    $db = database189($prefix); $services = services189($db);
    $client = new ThinClient189();
    try {
        if ($mode === 'admin') { echo json_encode(release189($services)); return; }
        $container = new Container();
        $container->share(DatabaseInterface::class, $db, true);
        $container->share(NativeReservationService::class, $services[2], true);
        Factory::$container = $container;
        $system = new CopyMyPage(['name' => 'copymypage', 'type' => 'system']);
        $dispatcher = new Dispatcher();
        $booking = $db->setQuery('SELECT * FROM #__dpcalendar_bookings WHERE id=19001')->loadObject();
        if ($mode === 'stale') {
            file_put_contents($dir . '/loaded', '1'); waitGate189($dir . '/continue');
        }
        $dispatcher->addListener('onDPPaymentCallBack', [$system, 'guardNativeProviderCallback'], Priority::MAX);
        $dispatcher->addListener('onDPPaymentCallBack', function () use ($db, $booking, $mode, $dir, $dispatcher, $client): void {
            file_put_contents($dir . '/admitted', '1');
            if ($mode === 'held' || $mode === 'paid') { waitGate189($dir . '/continue'); }
            $vendor = new NativePayPal189($dispatcher, ['name' => 'paypal', 'type' => 'dpcalendarpay']);
            $vendor->setClientFactory(new class($client) implements DigitalPeak\ThinHTTP\ClientFactoryInterface {
                public function __construct(private ThinClient189 $client) {}
                public function create(): DigitalPeak\ThinHTTP\ClientInterface { return $this->client; }
            });
            $updates = $vendor->finish189($booking);
            ensure189($updates['state'] === 1 && $updates['transaction_id'] === 'CAPTURE189', 'Native completion mapping changed.');
            file_put_contents($dir . '/capture-called', '1');
            // The vendor model is deliberately replaced: no mails, sessions or external plugins.
            $db->setQuery('UPDATE #__dpcalendar_bookings SET state=1, transaction_id=' . $db->quote($updates['transaction_id'])
                . ',raw_data=' . $db->quote($updates['raw_data']) . ',gross_amount=26')->execute();
            $db->setQuery('UPDATE #__dpcalendar_tickets SET state=1')->execute();
            $db->setQuery('UPDATE #__dpcalendar_events SET capacity_used=2')->execute();
            file_put_contents($dir . '/saved', '1');
            if ($mode === 'paid') { waitGate189($dir . '/finish'); }
        });
        $dispatcher->dispatch('onDPPaymentCallBack', new Event('onDPPaymentCallBack', [$booking, ['PayerID' => 'SYNTHETIC']]));
        echo json_encode(['blocked' => false, 'captureCalls' => $client->captures]);
    } catch (DomainException $error) {
        echo json_encode(['blocked' => true, 'captureCalls' => $client->captures, 'reason' => $error->getMessage()]);
    }
}

if (($argv[1] ?? '') === '--worker') { worker189($argv[2], $argv[3], $argv[4]); exit; }

$prefix = 'cmp189_' . bin2hex(random_bytes(6)) . '_';
$db = database189($prefix); $services = services189($db);
$GLOBALS['workers189'] = []; $GLOBALS['passed189'] = 0;
try {
    foreach (schemas189() as $table => $columns) {
        $db->setQuery('CREATE TABLE #__' . $table . ' (' . $columns . ') ENGINE=InnoDB')->execute();
    }
    case189('paid booking and all allocations remain protected', function () use ($db, $services): void {
        fixture189($db); $db->setQuery('UPDATE #__dpcalendar_bookings SET state=1,gross_amount=26')->execute();
        $db->setQuery('UPDATE #__dpcalendar_tickets SET state=1')->execute();
        $before = snapshot189($db);
        ensure189($services[4]->run(60, 50, false)['released'] === 0, 'Scheduler released paid booking.');
        reject189(fn() => release189($services));
        ensure189(snapshot189($db) === $before, 'Paid booking changed.');
    });
    case189('own scheduler leaves unstarted bookings to DPCalendar', function () use ($db, $services): void {
        fixture189($db, 'paypal-73', 1);
        $before = snapshot189($db);
        ensure189($services[4]->run(60, 50, false)['scanned'] === 0 && snapshot189($db) === $before, 'Own scheduler interfered with native booking eligibility.');
    });
    case189('expired cart without booking releases held seats', function () use ($db, $services): void {
        fixture189($db);
        $db->setQuery('DELETE FROM #__dpcalendar_tickets')->execute(); $db->setQuery('DELETE FROM #__dpcalendar_bookings')->execute();
        $db->setQuery("UPDATE #__copymypage_ticket_carts SET status=0,booking_id=NULL,native_payment_phase=0,terms_accepted_at=NULL,terms_snapshot='',payment_provider=''")->execute();
        $db->setQuery('UPDATE #__copymypage_event_seats SET status=1,ticket_id=NULL')->execute();
        ensure189($services[4]->run(60, 50, false)['released'] === 1, 'Expired cart not released.');
        ensure189((int) $db->setQuery('SELECT COUNT(*) FROM #__copymypage_event_seats WHERE status=0 AND cart_id IS NULL')->loadResult() === 2, 'Held seat leak.');
    });
    foreach (['APPROVED', 'PAYER_ACTION_REQUIRED', 'CREATED', 'COMPLETED'] as $state) {
        case189('started order with ' . $state . ' remains protected', function () use ($db, $services, $state): void {
            fixture189($db); $http = new StatusHttp189(); $http->order['status'] = $state;
            $before = snapshot189($db);
            reject189(fn() => release189($services, $http));
            ensure189($services[4]->run(60, 50, false)['released'] === 0 && snapshot189($db) === $before, 'Unproven order changed.');
        });
    }
    case189('read-only status inspection uses real PSR responses and is never a release proof', function () use ($db, $services): void {
        fixture189($db); $http = new StatusHttp189(); $http->order['status'] = 'APPROVED';
        $http->order['payer'] = ['email_address' => 'SENSITIVE-PAYER'];
        $status = new NativePayPalOrderStatus($services[0], $http);
        $booking = $db->setQuery('SELECT * FROM #__dpcalendar_bookings')->loadObject();
        $before = snapshot189($db); $review = $status->inspect($booking, ORDER189);
        ensure189($review['status'] === 'APPROVED' && $review['merchantId'] === MERCHANT189
            && $review['payments'] === [] && !str_contains(json_encode($review), 'SENSITIVE-PAYER'), 'Unsafe or incomplete inspection.');
        reject189(fn() => $status->verifyExclusion($booking, ORDER189, MERCHANT189));
        ensure189(snapshot189($db) === $before, 'Inspection wrote reservation data.');
    });
    case189('repeated phase-2 HOLD carts cannot starve new eligible carts, even with batch size one', function () use ($db, $services): void {
        fixture189($db, 'paypal-73', 1);
        for ($id = 1; $id <= 120; ++$id) {
            $db->setQuery("INSERT INTO #__copymypage_ticket_carts (id,booking_id,status,payment_provider,native_payment_phase)"
                . " VALUES ($id," . (20000 + $id) . ",1,'paypal-73',2)")->execute();
        }
        $held = $db->setQuery('SELECT * FROM #__copymypage_ticket_carts WHERE id<=120 ORDER BY id')->loadAssocList();
        $report = $services[4]->run(60, 1, false);
        ensure189($report['scanned'] === 0, 'Own scheduler selected native bookings.');
        $old = gmdate('Y-m-d H:i:s', time() - 7200);
        $db->setQuery("INSERT INTO #__copymypage_ticket_carts (id,status,revision,payment_provider,terms_snapshot,expires_at) VALUES (19202,0,0,'',''," . $db->quote($old) . ')')->execute();
        $db->setQuery("INSERT INTO #__copymypage_ticket_cart_items VALUES (19204,19202,19003,0,2,13,'EUR')")->execute();
        $db->setQuery('UPDATE #__copymypage_event_seats SET status=1,cart_id=19202,ticket_id=NULL')->execute();
        $report = $services[4]->run(60, 1, false);
        ensure189($report['released'] === 1 && $report['decisions'][0]['cartId'] === 19202, 'HOLD backlog starved an expired cart.');
        ensure189($db->setQuery('SELECT * FROM #__copymypage_ticket_carts WHERE id<=120 ORDER BY id')->loadAssocList() === $held, 'HOLD rows were changed to implement fairness.');
    });
    foreach (['paypal-0', 'paypal-1', 'paypal-73', 'paypal-9842'] as $provider) {
        case189('VOIDED dry-run, atomic release and repeat for ' . $provider, function () use ($db, $services, $provider, $prefix): void {
            fixture189($db, $provider); $before = snapshot189($db);
            ensure189(release189($services, null, true, $provider)['reason'] === 'would_release_excluded_order', 'No dry-run eligibility.');
            ensure189(snapshot189($db) === $before, 'Dry-run changed rows.');
            ensure189(release189($services, null, false, $provider)['reason'] === 'released_excluded_order', 'Excluded release failed.');
            ensure189((int) $db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_tickets WHERE state=6')->loadResult() === 2, 'Ticket cancellation incomplete.');
            ensure189((int) $db->setQuery('SELECT COUNT(*) FROM #__copymypage_event_seats WHERE status=0 AND cart_id IS NULL AND ticket_id IS NULL')->loadResult() === 2, 'Seat release incomplete.');
            ensure189((int) $db->setQuery('SELECT native_payment_phase FROM #__copymypage_ticket_carts')->loadResult() === 5, 'No persistent tombstone.');
            ensure189((int) $db->setQuery('SELECT inventory_version FROM #__copymypage_event_seating')->loadResult() === 6, 'Inventory revision wrong.');
            ensure189((int) $db->setQuery('SELECT capacity_used FROM #__dpcalendar_events')->loadResult() === 0, 'Capacity changed.');
            $audit = $db->setQuery('SELECT * FROM #__action_logs')->loadObjectList();
            ensure189(count($audit) === 1 && (int) $audit[0]->user_id === 189 && str_contains($audit[0]->message, 'responseHash'), 'Atomic audit missing.');
            ensure189(!str_contains($audit[0]->message, 'SYNTHETIC-SECRET') && !str_contains($audit[0]->message, ORDER189), 'Audit contains raw provider secrets/references.');
            $after = snapshot189($db); $http = new StatusHttp189();
            ensure189(release189($services, $http, false, $provider)['reason'] === 'already_released' && $http->gets === 0, 'Repeat not idempotent.');
            ensure189(snapshot189($db) === $after, 'Repeat changed audit/inventory.');
            $worker = startWorker189($prefix, 'once');
            ensure189(finishWorker189($worker)['captureCalls'] === 0, 'Released order captured.');
        });
    }
    foreach (['capture', 'authorization', 'null-payments', 'malformed-units', 'merchant', 'remote-booking', 'amount', 'currency', 'order', 'network', '404', 'local', 'history', 'integrity', 'actor', 'live', 'duplicate-provider'] as $problem) {
        case189('fail closed: ' . $problem, function () use ($db, $services, $problem): void {
            fixture189($db); $http = new StatusHttp189(); $actor = new Admin189();
            switch ($problem) {
                case 'capture': $http->order['purchase_units'][0]['payments'] = ['captures' => [['status' => 'PENDING']]]; break;
                case 'authorization': $http->order['purchase_units'][0]['payments'] = ['authorizations' => [['status' => 'VOIDED']]]; break;
                case 'null-payments': $http->order['purchase_units'][0]['payments'] = null; break;
                case 'malformed-units': $http->order['purchase_units'] = ['invalid-object' => $http->order['purchase_units'][0]]; break;
                case 'merchant': $http->order['purchase_units'][0]['payee']['merchant_id'] = 'OTHER189'; break;
                case 'remote-booking': $http->order['purchase_units'][0]['description'] = '[19099] OTHER BOOKING'; break;
                case 'amount': $http->order['purchase_units'][0]['amount']['value'] = '25.99'; break;
                case 'currency': $http->order['purchase_units'][0]['amount']['currency_code'] = 'USD'; break;
                case 'order': $http->order['id'] = 'OTHER189'; break;
                case 'network': $http->fail = true; break;
                case '404': $http->code = 404; break;
                case 'local': $db->setQuery("UPDATE #__dpcalendar_bookings SET raw_data='{}'")->execute(); break;
                case 'history': $db->setQuery('UPDATE #__copymypage_ticket_carts SET native_payment_phase=0')->execute(); break;
                case 'integrity': $db->setQuery('DELETE FROM #__dpcalendar_tickets WHERE id=19012')->execute(); break;
                case 'actor': $actor->allowed = false; break;
                case 'live': $params = ['providers' => [['id' => 73, 'sandbox' => '0', 'rest_client_id' => 'X', 'rest_secret' => 'Y']]]; $db->setQuery('UPDATE #__extensions SET params=' . $db->quote(json_encode($params)))->execute(); break;
                case 'duplicate-provider': $params = ['providers' => [['id' => 73], ['id' => 73]]]; $db->setQuery('UPDATE #__extensions SET params=' . $db->quote(json_encode($params)))->execute(); break;
            }
            $before = snapshot189($db);
            reject189(fn() => release189($services, $http, false, 'paypal-73', $actor));
            ensure189(snapshot189($db) === $before, 'Rejected release changed rows.');
        });
    }
    case189('audit failure rolls booking/tickets/cart/seats/inventory back together', function () use ($db, $services, $prefix): void {
        fixture189($db); $before = snapshot189($db);
        $db->getConnection()->query('CREATE TRIGGER `' . $prefix . 'reject_audit` BEFORE INSERT ON `' . $prefix . "action_logs` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SYNTHETIC AUDIT FAILURE'");
        try {
            $failed = false;
            try { release189($services); } catch (Throwable) { $failed = true; }
            ensure189($failed && snapshot189($db) === $before, 'Audit failure left a partial release.');
        } finally { $db->getConnection()->query('DROP TRIGGER `' . $prefix . 'reject_audit`'); }
    });
    case189('live callback excludes concurrent scheduler, duplicate and admin; crash stays held', function () use ($db, $services, $prefix): void {
        fixture189($db); $worker = startWorker189($prefix, 'held'); waitGate189($worker['dir'] . '/admitted');
        ensure189((int) $db->setQuery('SELECT native_payment_phase FROM #__copymypage_ticket_carts')->loadResult() === 4, 'Callback claim not visible to another connection.');
        $before = snapshot189($db); $http = new StatusHttp189();
        ensure189($services[2]->withBookingLock(19009, static fn() => true), 'Callback lock blocks an unrelated booking.');
        reject189(fn() => release189($services, $http));
        ensure189($http->gets === 0, 'Admin queried provider during in-flight callback.');
        $report = $services[4]->run(60, 50, false);
        ensure189($report['released'] === 0 && $report['scanned'] === 0, 'Own scheduler interfered with native callback booking.');
        $duplicate = startWorker189($prefix, 'once');
        ensure189(finishWorker189($duplicate)['captureCalls'] === 0, 'Duplicate callback crossed provider boundary.');
        ensure189(snapshot189($db) === $before, 'Concurrent observers changed reservation.');
        finishWorker189($worker, true);
        ensure189($services[4]->run(60, 50, false)['released'] === 0, 'Crashed callback expired automatically.');
        $retry = startWorker189($prefix, 'once');
        ensure189(finishWorker189($retry)['captureCalls'] === 0, 'Uncertain callback retried capture.');
        ensure189(release189($services)['reason'] === 'released_excluded_order', 'Fresh terminal proof could not recover crashed callback.');
    });
    case189('normal native completion holds request lock through persistence', function () use ($db, $services, $prefix): void {
        fixture189($db); $worker = startWorker189($prefix, 'paid'); waitGate189($worker['dir'] . '/admitted');
        file_put_contents($worker['dir'] . '/continue', '1'); waitGate189($worker['dir'] . '/saved');
        reject189(fn() => release189($services));
        file_put_contents($worker['dir'] . '/finish', '1');
        ensure189(finishWorker189($worker)['captureCalls'] === 1, 'Native completion did not capture once through mock.');
        ensure189($services[4]->run(60, 50, false)['released'] === 0, 'Paid callback allocation released.');
        reject189(fn() => release189($services));
        $repeat = startWorker189($prefix, 'once');
        ensure189(finishWorker189($repeat)['captureCalls'] === 0, 'Paid callback captured twice.');
    });
    case189('admin release wins against a callback with an already loaded stale booking', function () use ($db, $services, $prefix): void {
        fixture189($db); $worker = startWorker189($prefix, 'stale'); waitGate189($worker['dir'] . '/loaded');
        release189($services); file_put_contents($worker['dir'] . '/continue', '1');
        ensure189(finishWorker189($worker)['captureCalls'] === 0, 'Stale native object bypassed release guard.');
    });
    case189('admin proof excludes a simultaneous callback and competing administrator', function () use ($db, $services, $prefix): void {
        fixture189($db); $http = new StatusHttp189();
        $http->atGet = function () use ($prefix): void {
            $callback = startWorker189($prefix, 'once');
            ensure189(finishWorker189($callback)['captureCalls'] === 0, 'Concurrent callback bypassed admin proof lock.');
            $admin = startWorker189($prefix, 'admin');
            ensure189(finishWorker189($admin)['blocked'], 'Competing admin acquired release.');
        };
        release189($services, $http);
        ensure189((int) $db->setQuery('SELECT COUNT(*) FROM #__action_logs')->loadResult() === 1, 'Duplicate administrative audit.');
    });
    case189('release helper rejects calls without the administrative request lock', function () use ($db, $services): void {
        fixture189($db);
        $before = snapshot189($db);
        $proof = (new NativePayPalOrderStatus($services[0], new StatusHttp189()))->verifyExclusion(
            $db->setQuery('SELECT * FROM #__dpcalendar_bookings')->loadObject(), ORDER189, MERCHANT189);
        $db->transactionStart();
        try { reject189(fn() => $services[3]->releaseExcludedNativeWithinTransaction($services[1]->inspect(19001, true), $proof)); }
        finally { $db->transactionRollback(); }
        ensure189(snapshot189($db) === $before, 'Unlocked release changed rows.');
    });
    case189('idempotent repeat rejects missing tickets and altered merchant binding', function () use ($db, $services): void {
        fixture189($db); release189($services);
        $actor = new Admin189(); $actor->id = 189;
        reject189(fn() => $services[4]->releaseExcludedNative(19001, 'paypal-73', ORDER189, 'OTHER189M', $actor,
            'SYNTHETIC-189', new NativePayPalOrderStatus($services[0], new StatusHttp189()), false));
        $db->setQuery('DELETE FROM #__dpcalendar_tickets WHERE id=19012')->execute();
        reject189(fn() => release189($services));
    });
    case189('real SeatSelectionService reserves released seats; old callbacks and repeat releases preserve them', function () use ($db, $services, $prefix): void {
        fixture189($db);
        $before = snapshot189($db);
        ensure189(release189($services, null, true)['reason'] === 'would_release_excluded_order' && snapshot189($db) === $before, 'Dry-run changed data.');
        release189($services);
        $container = new Container(); $container->share(DatabaseInterface::class, $db, true);
        $_SERVER['HTTP_HOST'] = 'localhost'; $_SERVER['HTTPS'] = 'off'; $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['REQUEST_URI'] = '/provider-free-reservation-test/'; $_SERVER['SCRIPT_NAME'] = '/index.php';
        $app = new Joomla\CMS\Application\SiteApplication(new Joomla\Input\Input(),
            new Joomla\Registry\Registry(['session'=>false,'mailonline'=>false]), null, $container);
        $app->setSession(new Joomla\CMS\Session\Session(new Joomla\Session\Storage\RuntimeStorage()));
        (new ReflectionProperty($app, 'language'))->setValue($app, new Joomla\CMS\Language\Language('en-GB'));
        $app->loadIdentity(new Admin189());
        Factory::$container = $container; Factory::$application = $app; Factory::$language = $app->getLanguage();
        (new ReflectionProperty(Joomla\CMS\Component\ComponentHelper::class, 'components'))->setValue(null, [
            'com_copymypage' => new Joomla\CMS\Component\ComponentRecord(['id'=>189,'option'=>'com_copymypage','enabled'=>1,'params'=>'{}']),
        ]);
        $token = str_repeat('a', 64);
        $app->getSession()->set('com_copymypage.ticket_cart_token', $token);
        $db->setQuery("INSERT INTO #__copymypage_seat_layouts VALUES (19040,1,'SYNTHETIC LAYOUT',100,100,'{}')")->execute();
        $db->setQuery("INSERT INTO #__copymypage_layout_tables VALUES (19041,19040,1,'T1','1','Test','rect',0,0,100,100,0)")->execute();
        $newCart = (object) ['id' => 19102, 'user_id' => 0, 'status' => 0, 'payment_provider' => '',
            'native_payment_phase' => 0, 'revision' => 0, 'terms_snapshot' => '',
            'token_hash' => hash('sha256', $token), 'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600)];
        $db->insertObject('#__copymypage_ticket_carts', $newCart);
        $db->setQuery("INSERT INTO #__copymypage_ticket_cart_items VALUES (19104,19102,19003,0,2,13,'EUR')")->execute();
        $context = new Joomla\Component\CopyMyPage\Site\Service\TicketCartContextService($app, $db);
        $selection = new Joomla\Component\CopyMyPage\Site\Service\SeatSelectionService($db,
            new Joomla\Component\CopyMyPage\Site\Service\TicketCatalogService($app, $db), $context);
        $available = $selection->getEventState(19003);
        ensure189($available['event']['ready'] && $available['event']['selectedCount'] === 0, 'Released inventory unavailable.');
        foreach ($available['event']['layout']['tables'] as $table) {
            foreach ($table['seats'] as $seat) { ensure189($seat['status'] === 'available', 'Released seat not projected available.'); }
        }
        $reserved = $selection->assignSeats(19003, [19031,19032], 0);
        ensure189($reserved['event']['selectedCount'] === 2 && $reserved['cartRevision'] === 1, 'Real seat reservation failed.');
        ensure189((int) $db->setQuery('SELECT COUNT(*) FROM #__copymypage_event_seats WHERE status=1 AND cart_id=19102 AND ticket_id IS NULL')->loadResult() === 2, 'Seats did not belong to new cart.');
        $before = snapshot189($db); $old = startWorker189($prefix, 'once');
        ensure189(finishWorker189($old)['captureCalls'] === 0, 'Old payment revived after resale.');
        ensure189(snapshot189($db) === $before, 'Old callback stole the new reservation.');
        ensure189(release189($services)['reason'] === 'already_released' && snapshot189($db) === $before, 'Repeated old release damaged new allocation.');
    });
    case189('Joomla CLI registration, default dry-run, explicit execute and action-log rendering', function () use ($db, $services): void {
        fixture189($db);
        if (!defined('JVERSION')) { define('JVERSION', '6.2.0'); }
        $container = new Container();
        $container->share(DatabaseInterface::class, $db, true);
        $container->share(NativePayPalOrderStatus::class, new NativePayPalOrderStatus($services[0], new StatusHttp189()), true);
        $container->share(Joomla\CMS\User\UserFactoryInterface::class, new class implements Joomla\CMS\User\UserFactoryInterface {
            public function loadUserById(int $id): User { $user = new Admin189(); $user->id = $id; return $user; }
            public function loadUserByUsername(string $username): User { return new Admin189(); }
        }, true);
        Factory::$container = $container;
        $dispatcher = new Dispatcher();
        $app = new Console189(new Joomla\Registry\Registry(['session' => false, 'mailonline' => false]),
            $dispatcher, $container, new Joomla\CMS\Language\Language('en-GB'));
        Factory::$application = $app;
        Factory::$language = $app->getLanguage();
        $system = new CopyMyPage(['name' => 'copymypage', 'type' => 'system']);
        $dispatcher->addListener(Joomla\Application\ApplicationEvents::BEFORE_EXECUTE, [$system, 'registerReservationCommand']);
        $dispatcher->dispatch(Joomla\Application\ApplicationEvents::BEFORE_EXECUTE,
            new Joomla\Application\Event\ApplicationEvent(Joomla\Application\ApplicationEvents::BEFORE_EXECUTE, $app));
        $command = $app->getCommand('copymypage:reservation:release');
        $output = new Symfony\Component\Console\Output\BufferedOutput();
        ensure189($command->execute(new Symfony\Component\Console\Input\ArrayInput([]), $output) === 1, 'CLI accepted missing options.');
        $before = snapshot189($db);
        $options = ['--booking' => '19001', '--provider' => 'paypal-73', '--order' => ORDER189,
            '--merchant' => MERCHANT189, '--actor' => '189', '--case' => 'SYNTHETIC-189'];
        ensure189($command->execute(new Symfony\Component\Console\Input\ArrayInput($options), $output) === 0, 'CLI dry-run failed.');
        ensure189(snapshot189($db) === $before, 'CLI default committed a release.');
        $options['--execute'] = true;
        ensure189($command->execute(new Symfony\Component\Console\Input\ArrayInput($options), $output) === 0, 'CLI explicit commit failed.');
        $audit = $db->setQuery('SELECT * FROM #__action_logs')->loadObject();
        $rendered = Joomla\Component\Actionlogs\Administrator\Helper\ActionlogsHelper::getHumanReadableLogMessage($audit, false);
        ensure189(str_contains($rendered, '19001') && !str_contains($rendered, '{booking_id}'), 'Joomla audit message cannot be displayed.');
        ensure189(!str_contains($output->fetch(), 'SYNTHETIC-SECRET'), 'CLI leaked credentials.');
    });
    echo 'RESULT ', json_encode(['passed' => $GLOBALS['passed189'], 'database' => 'isolated MySQL/InnoDB fixtures',
        'concurrency' => 'separate PHP workers, independent MySQL connections, explicit barriers',
        'realProviderCalls' => 0, 'realBookingWrites' => 0, 'mailCalls' => 0, 'nativeSaveModel' => 'fixture']), PHP_EOL;
} finally {
    foreach ($GLOBALS['workers189'] as $worker) {
        if (is_resource($worker['process'])) { proc_terminate($worker['process']); proc_close($worker['process']); }
        foreach (['loaded', 'continue', 'admitted', 'capture-called', 'saved', 'finish'] as $file) {
            if (is_file($worker['dir'] . '/' . $file)) { unlink($worker['dir'] . '/' . $file); }
        }
        rmdir($worker['dir']);
    }
    foreach (array_reverse(array_keys(schemas189())) as $table) {
        $db->setQuery('DROP TABLE IF EXISTS #__' . $table)->execute();
    }
}
