<?php

/**
 * Reservation cleanup regression: real MySQL/InnoDB, synthetic tables only.
 * php -d xdebug.mode=off -d allow_url_fopen=0
 *     -d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail
 *     test/com_copymypage/reservation-cleanup-mysql.php [--baseline]
 * Reads CopyMyPage code from this repository; writes only randomly prefixed joomla_db_test tables.
 * No Joomla application, scheduler, provider, session or mail transport is executed.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

ini_set('display_errors', '0');
ini_set('log_errors', '0');
ini_set('zend.exception_ignore_args', '1');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});

foreach (['curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'mail'] as $function) {
    if (function_exists($function)) {
        throw new RuntimeException('Network and mail functions must be disabled.');
    }
}

if (ini_get('allow_url_fopen')) {
    throw new RuntimeException('URL streams must be disabled.');
}

define('_JEXEC', 1);
define('JDEBUG', false);
$testRoot = realpath('C:/wamp/www/joomla6_test');
if (strtolower(str_replace('\\', '/', (string) $testRoot)) !== 'c:/wamp/www/joomla6_test') {
    throw new RuntimeException('Unexpected test root.');
}

define('JPATH_BASE', $testRoot);
require $testRoot . '/includes/defines.php';
$loader = require $testRoot . '/libraries/vendor/autoload.php';
$loader->addPsr4('Joomla\\CMS\\', $testRoot . '/libraries/src');
$loader->addPsr4('Joomla\\Component\\CopyMyPage\\Site\\', dirname(__DIR__, 2) . '/src/com_copymypage/site/src');
$loader->addPsr4('Joomla\\Component\\Scheduler\\Administrator\\', $testRoot . '/administrator/components/com_scheduler/src');
$loader->addPsr4('Joomla\\Plugin\\Task\\CopyMyPage\\', dirname(__DIR__, 2) . '/src/plg_task_copymypage/src');
require $testRoot . '/configuration.php';
$config = new JConfig();
if ($config->db !== 'joomla_db_test' || !in_array($config->host, ['localhost', '127.0.0.1', '::1'], true)) {
    throw new RuntimeException('Test database isolation changed.');
}

use Joomla\CMS\Log\Log;
use Joomla\Component\CopyMyPage\Site\Service\BookingIntegrityService;
use Joomla\Component\CopyMyPage\Site\Service\NativePayPalProviderRegistry;
use Joomla\Component\CopyMyPage\Site\Service\NativeReservationService;
use Joomla\Component\CopyMyPage\Site\Service\PaymentReconciliationService;
use Joomla\Component\CopyMyPage\Site\Service\ReservationCleanupService;

/** Test-only fault injection and interleaving of two real database connections. */
final class CleanupFixtureDatabase extends Joomla\Database\Mysqli\MysqliDriver
{
    public ?Closure $beforeExecute = null;

    public function execute()
    {
        if ($this->beforeExecute !== null) {
            ($this->beforeExecute)((string) $this->sql);
        }

        return parent::execute();
    }
}

function checkCleanup(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cleanupServices(CleanupFixtureDatabase $db): ReservationCleanupService
{
    $providers = new NativePayPalProviderRegistry($db);
    $integrity = new BookingIntegrityService($db);
    $native = new NativeReservationService($db, $providers, $integrity);

    return new ReservationCleanupService(
        $db,
        $integrity,
        $providers,
        $native,
        new PaymentReconciliationService($db, $native)
    );
}

$prefix = 'cmp94_' . bin2hex(random_bytes(6)) . '_';
checkCleanup((bool) preg_match('/^cmp94_[a-f0-9]{12}_$/D', $prefix), 'Invalid fixture prefix.');
$options = ['host' => $config->host, 'user' => $config->user, 'password' => $config->password,
    'database' => $config->db, 'prefix' => $prefix, 'utf8mb4' => true];
$db = new CleanupFixtureDatabase($options);
$db->connect();
foreach ($db->getConnection()->query('SHOW GRANTS')->fetch_all(MYSQLI_NUM) as [$grant]) {
    checkCleanup(
        (bool) preg_match('/^GRANT (?:USAGE ON \*\.\*|.* ON `joomla_db_test`\.\*) TO /', $grant),
        'Test credentials have unexpected grants.'
    );
}

$tables = ['copymypage_ticket_carts', 'copymypage_ticket_cart_items', 'copymypage_payment_attempts',
    'copymypage_event_seats', 'copymypage_event_seating', 'dpcalendar_events', 'dpcalendar_bookings',
    'dpcalendar_tickets'];
$created = [];
$logs = [];
$logDirectory = $testRoot . '/tmp/' . $prefix;
checkCleanup(str_starts_with(strtolower(str_replace('\\', '/', (string) realpath(dirname($logDirectory)))),
    'c:/wamp/www/joomla6_test/'), 'Log directory is outside the test installation.');
// Configuration-only double for Joomla's file logger; no application is booted or executed.
Joomla\CMS\Factory::$application = new class ($logDirectory) {
    public function __construct(private readonly string $logDirectory)
    {
    }

    public function get($key, $default = null)
    {
        return $key === 'log_path' ? $this->logDirectory : $default;
    }

    public function getLanguage(): Joomla\CMS\Language\Language
    {
        return Joomla\CMS\Factory::$language ??= new Joomla\CMS\Language\Language('en-GB');
    }
};
Log::addLogger(['logger' => 'callback', 'callback' => static function ($entry) use (&$logs): void {
    $logs[] = $entry->message;
}], Log::ALL, ['com_copymypage.reservations']);
$service = cleanupServices($db);
$passed = 0;

function insertCleanup(string $table, array $data): void
{
    global $db;
    foreach ($db->getTableColumns('#__' . $table, false) as $field => $meta) {
        if (array_key_exists($field, $data) || $meta->Default !== null || $meta->Null === 'YES'
            || str_contains($meta->Extra, 'auto_increment')) {
            continue;
        }
        $data[$field] = preg_match('/int|decimal|float|double/', $meta->Type)
            ? 0 : (preg_match('/date|time/', $meta->Type) ? gmdate('Y-m-d H:i:s') : '');
    }
    $row = (object) $data;
    $db->insertObject('#__' . $table, $row);
}

function fixtureCleanup(int $cartId = 94, int $eventId = 2): void
{
    global $db, $tables, $logs;
    $db->beforeExecute = null;
    foreach (array_reverse($tables) as $table) {
        $db->setQuery('DELETE FROM #__' . $table)->execute();
    }
    insertCleanup('copymypage_ticket_carts', ['id' => $cartId, 'booking_id' => null, 'status' => 2,
        'native_payment_phase' => 0, 'payment_provider' => null, 'revision' => 3,
        'terms_snapshot' => '', 'terms_accepted_at' => null, 'expires_at' => '2026-10-16 19:05:04']);
    insertCleanup('copymypage_ticket_cart_items', ['id' => 114, 'cart_id' => $cartId,
        'event_id' => $eventId, 'quantity' => 2, 'price_index' => 0, 'currency' => 'EUR', 'unit_price' => 13]);
    insertCleanup('dpcalendar_events', ['id' => $eventId, 'title' => 'SYNTHETIC CLEANUP EVENT']);
    insertCleanup('copymypage_event_seating', ['event_id' => $eventId, 'layout_id' => 1,
        'status' => 1, 'inventory_version' => 45]);
    foreach ([204, 205] as $index => $id) {
        insertCleanup('copymypage_event_seats', ['id' => $id, 'event_id' => $eventId, 'seat_id' => $id,
            'cart_id' => $cartId, 'status' => 1, 'allocation_type' => 0, 'ticket_id' => null,
            'price_index' => 0, 'assignment_order' => $index + 1]);
    }
    $logs = [];
}

function snapshotCleanup(): string
{
    global $db, $tables;
    $rows = [];
    foreach ($tables as $table) {
        $columns = array_keys($db->getTableColumns('#__' . $table));
        $rows[$table] = $db->setQuery('SELECT * FROM #__' . $table . ' ORDER BY ' . $db->quoteName($columns[0]))->loadAssocList();
    }

    return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
}

function caseCleanup(string $name, callable $test): void
{
    global $passed;
    $test();
    ++$passed;
    echo 'PASS ', $name, PHP_EOL;
}

function expectHeldCleanup(string $reason): void
{
    global $service;
    $before = snapshotCleanup();
    foreach ([true, false, false] as $dryRun) {
        $report = $service->run(60, 50, $dryRun);
        checkCleanup($report['released'] === 0 && $report['wouldRelease'] === 0
            && $report['held'] === 1 && $report['errors'] === 0
            && $report['decisions'][0]['reason'] === $reason, 'Unexpected review decision: ' . json_encode($report));
        checkCleanup(snapshotCleanup() === $before, 'Review mutated reservation data.');
    }
}

/** Invoke only the real plugin callback with synthetic parameters, never Task::run(). */
function taskCleanup(array $params): int
{
    global $service;
    $reflection = new ReflectionClass(Joomla\Plugin\Task\CopyMyPage\Extension\CopyMyPage::class);
    $plugin = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('reservations')->setValue($plugin, $service);
    $reflection->getProperty('snapshot')->setValue($plugin, ['logCategory' => 'com_copymypage.reservations']);
    $subject = (new ReflectionClass(Joomla\Component\Scheduler\Administrator\Task\Task::class))->newInstanceWithoutConstructor();
    $event = new Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent('onExecuteTask', [
        'subject' => $subject, 'params' => (object) $params,
    ]);

    return $reflection->getMethod('releaseReservations')->invoke($plugin, $event);
}

try {
    foreach ($tables as $table) {
        checkCleanup((bool) preg_match('/^[a-z_]+$/D', $table), 'Invalid fixture table.');
        $db->getConnection()->query('CREATE TABLE `' . $prefix . $table . '` LIKE `'
            . $config->dbprefix . $table . '`');
        $created[] = $table;
    }
    // Each clone is checked individually; CREATE TABLE LIKE retains the installed column/index contract.
    foreach ($tables as $table) {
        $engine = $db->getConnection()->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
            . " AND TABLE_NAME = '" . $prefix . $table . "'")->fetch_row()[0];
        checkCleanup($engine === 'InnoDB', 'Fixture table must be transactional.');
    }

    if (($argv[1] ?? '') === '--baseline') {
        fixtureCleanup();
        $db->setQuery('DELETE FROM #__dpcalendar_events WHERE id = 2')->execute();
        $before = snapshotCleanup();
        $report = $service->run(60, 50, false);
        checkCleanup($report['errors'] === 1 && $report['decisions'][0]['reason'] === 'transaction_failed',
            'Original failure was not reproduced.');
        checkCleanup(snapshotCleanup() === $before, 'Baseline failure mutated data.');
        try {
            (new ReflectionMethod($service, 'process'))->invoke($service, 94, gmdate('Y-m-d H:i:s'), false);
            throw new RuntimeException('Expected original exception.');
        } catch (DomainException $error) {
            echo 'REPRODUCED ', $error::class, ': ', $error->getMessage(), PHP_EOL;
        }
        echo json_encode($report, JSON_THROW_ON_ERROR), PHP_EOL;
    } else {
        caseCleanup('cleanup form removes timeout while payment reconciliation retains it', static function (): void {
            $forms = dirname(__DIR__, 2) . '/src/plg_task_copymypage/forms/';
            $release = simplexml_load_file($forms . 'releaseReservations.xml');
            $payments = simplexml_load_file($forms . 'reconcilePayments.xml');
            checkCleanup($release !== false && $payments !== false, 'Task XML could not be parsed.');
            $names = array_map(static fn(SimpleXMLElement $field): string => (string) $field['name'], $release->xpath('//field'));
            checkCleanup($names === ['dry_run', 'batch_size', 'max_seconds'], 'Unexpected cleanup task fields.');
            checkCleanup(count($payments->xpath('//field[@name="timeout_minutes"]')) === 1, 'Payment timeout field was removed.');
        });
        foreach ([1, 0, -1] as $remaining) {
            foreach ([true, false] as $dryRun) {
                caseCleanup('legacy timeout cannot shift expiry boundary ' . $remaining . ', dry-run ' . (int) $dryRun,
                    static function () use ($db, $service, $remaining, $dryRun): void {
                        $fixedNow = '2030-01-01 12:00:00';
                        $expiresAt = gmdate('Y-m-d H:i:s', strtotime($fixedNow . ' UTC') + $remaining);
                        $baseline = null;
                        foreach ([-1, 0, 1, 14, 15, 60, 1440, 1441, PHP_INT_MAX] as $legacyMinutes) {
                            fixtureCleanup();
                            $db->setQuery('UPDATE #__copymypage_ticket_carts SET expires_at = ' . $db->quote($expiresAt))->execute();
                            $db->beforeExecute = static function (string $sql) use ($db, $fixedNow): void {
                                if ($sql === 'SELECT UTC_TIMESTAMP()') {
                                    $db->setQuery('SELECT ' . $db->quote($fixedNow));
                                }
                            };
                            $before = snapshotCleanup();
                            $report = $service->run($legacyMinutes, 50, $dryRun);
                            $baseline ??= $report;
                            checkCleanup($report === $baseline, 'Legacy timeout changed the cleanup decision.');
                            $expired = $remaining <= 0;
                            checkCleanup($report['scanned'] === (int) $expired && $report['errors'] === 0
                                && $report['held'] === 0 && !$report['busy']
                                && $report['wouldRelease'] === (int) ($expired && $dryRun)
                                && $report['released'] === (int) ($expired && !$dryRun), 'Stored expiry boundary changed.');
                            checkCleanup($db->setQuery('SELECT expires_at FROM #__copymypage_ticket_carts')->loadResult() === $expiresAt,
                                'Cleanup rewrote the stored expiry.');
                            if ($dryRun || !$expired) {
                                checkCleanup(snapshotCleanup() === $before, 'Protected or dry-run cart changed.');
                            } else {
                                $after = snapshotCleanup();
                                checkCleanup($service->run(minutes: $legacyMinutes, dryRun: false)['scanned'] === 0
                                    && snapshotCleanup() === $after, 'Legacy named call or repeat changed a released cart.');
                            }
                        }
                        $db->beforeExecute = null;
                    });
            }
        }
        foreach ([true, false] as $dryRun) {
            caseCleanup('real task callback ignores stored timeout, dry-run ' . (int) $dryRun,
                static function () use ($db, $dryRun, &$logs): void {
                    foreach ([null, 0, 15, 60, 1440, 'invalid-old-value'] as $legacyMinutes) {
                        fixtureCleanup();
                        $params = ['batch_size' => 1, 'dry_run' => (int) $dryRun, 'max_seconds' => 5];
                        if ($legacyMinutes !== null) {
                            $params['timeout_minutes'] = $legacyMinutes;
                        }
                        $before = snapshotCleanup();
                        checkCleanup(taskCleanup($params) === Joomla\Component\Scheduler\Administrator\Task\Status::OK,
                            'Old task parameters caused callback failure.');
                        $message = end($logs);
                        $report = json_decode(substr($message, strpos($message, 'CopyMyPage reservations: ')
                            + strlen('CopyMyPage reservations: ')), true, 512, JSON_THROW_ON_ERROR);
                        checkCleanup($report['scanned'] === 1 && $report['dryRun'] === $dryRun
                            && $report['released'] === (int) !$dryRun && $report['wouldRelease'] === (int) $dryRun,
                            'Task options changed meaning after removing timeout.');
                        if ($dryRun) {
                            checkCleanup(snapshotCleanup() === $before, 'Task dry-run changed data.');
                        }
                    }
                });
        }
        caseCleanup('task without stored options still defaults to dry-run', static function (): void {
            fixtureCleanup();
            $before = snapshotCleanup();
            checkCleanup(taskCleanup([]) === Joomla\Component\Scheduler\Administrator\Task\Status::OK
                && snapshotCleanup() === $before, 'Default task options changed data.');
        });
        caseCleanup('historical cart 94: missing event is held, diagnosed and repeatable', static function () use ($db, &$logs, $logDirectory): void {
            fixtureCleanup();
            $db->setQuery('DELETE FROM #__dpcalendar_events WHERE id = 2')->execute();
            expectHeldCleanup('event_missing_requires_review');
            checkCleanup(count($logs) === 3 && str_contains($logs[0], 'data_inconsistency')
                && str_contains($logs[0], '"cartId":94') && str_contains($logs[0], '"eventIds":[2]'),
                'Structured orphan diagnostics missing.');
            $file = $logDirectory . '/copymypage-reservations.php';
            checkCleanup(is_file($file) && str_contains(file_get_contents($file), '"cause":"event_missing_requires_review"'),
                'Persistent server diagnostics missing.');
        });
        caseCleanup('orphan handling is independent of historical IDs', static function () use ($db): void {
            fixtureCleanup(9031, 9081);
            $db->setQuery('DELETE FROM #__dpcalendar_events')->execute();
            expectHeldCleanup('event_missing_requires_review');
        });
        foreach ([0, 2, 3] as $status) {
            caseCleanup('existing event: dry-run, release and repeat for cart status ' . $status, static function () use ($db, $service, $status): void {
                fixtureCleanup();
                $db->setQuery('UPDATE #__copymypage_ticket_carts SET status = ' . $status)->execute();
                $before = snapshotCleanup();
                checkCleanup($service->run()['wouldRelease'] === 1 && snapshotCleanup() === $before, 'Dry-run mutated data.');
                checkCleanup($service->run(60, 50, false)['released'] === 1, 'Expired cart not released.');
                checkCleanup((int) $db->setQuery('SELECT COUNT(*) FROM #__copymypage_event_seats WHERE status = 0 AND cart_id IS NULL AND ticket_id IS NULL AND price_index IS NULL AND assignment_order IS NULL')->loadResult() === 2, 'Seats not fully released.');
                $cart = $db->setQuery('SELECT status,revision FROM #__copymypage_ticket_carts')->loadObject();
                checkCleanup((int) $cart->status === 2 && (int) $cart->revision === 4, 'Cart revision/status incorrect.');
                checkCleanup((int) $db->setQuery('SELECT inventory_version FROM #__copymypage_event_seating')->loadResult() === 46, 'Inventory revision incorrect.');
                $after = snapshotCleanup();
                checkCleanup($service->run(60, 50, false)['scanned'] === 0 && snapshotCleanup() === $after, 'Repeat mutated released cart.');
            });
        }
        foreach (['active' => "status = 0, expires_at = '2099-01-01 00:00:00'",
            'native booking' => 'booking_id = 6001', 'converted paid booking' => 'status = 1, booking_id = 6001'] as $name => $change) {
            caseCleanup($name . ' excluded from cart cleanup', static function () use ($db, $service, $change): void {
                fixtureCleanup();
                insertCleanup('dpcalendar_bookings', ['id' => 6001, 'state' => 1, 'price' => 26]);
                $db->setQuery('UPDATE #__copymypage_ticket_carts SET ' . $change)->execute();
                $before = snapshotCleanup();
                checkCleanup($service->run(60, 50, false)['scanned'] === 0 && snapshotCleanup() === $before, 'Protected cart changed.');
            });
        }
        foreach (['payment provider' => "payment_provider = 'paypal-73'", 'accepted terms' => "terms_accepted_at = '2026-10-01 00:00:00'",
            'terms snapshot' => "terms_snapshot = '{}'", 'unclear payment phase' => 'native_payment_phase = 2',
            'callback started' => 'native_payment_phase = 4'] as $name => $change) {
            caseCleanup($name . ' holds entire cart', static function () use ($db, $change): void {
                fixtureCleanup();
                $db->setQuery('UPDATE #__copymypage_ticket_carts SET ' . $change)->execute();
                expectHeldCleanup('cart_requires_review');
            });
        }
        caseCleanup('payment attempt holds entire cart', static function (): void {
            fixtureCleanup();
            insertCleanup('copymypage_payment_attempts', ['id' => 6002, 'cart_id' => 94, 'booking_id' => 6001,
                'expected_amount' => 26, 'create_key' => 'SYNTHETIC-CREATE', 'capture_key' => 'SYNTHETIC-CAPTURE',
                'state' => 'capture_unknown']);
            expectHeldCleanup('cart_requires_review');
        });
        foreach (['hotline' => 'allocation_type = 1', 'blocked' => 'status = 3', 'booked' => 'status = 2',
            'ticket binding' => 'ticket_id = 6003', 'foreign event' => 'event_id = 6004'] as $name => $change) {
            caseCleanup($name . ' seat holds entire cart', static function () use ($db, $change): void {
                fixtureCleanup();
                $db->setQuery('UPDATE #__copymypage_event_seats SET ' . $change . ' WHERE id = 204')->execute();
                expectHeldCleanup('seat_requires_review');
            });
        }
        caseCleanup('foreign reservation remains untouched', static function () use ($db, $service): void {
            fixtureCleanup();
            $db->setQuery('UPDATE #__copymypage_event_seats SET cart_id = 9999 WHERE id = 204')->execute();
            $foreign = $db->setQuery('SELECT * FROM #__copymypage_event_seats WHERE id = 204')->loadAssoc();
            checkCleanup($service->run(60, 50, false)['released'] === 1, 'Own remaining hold not released.');
            checkCleanup($db->setQuery('SELECT * FROM #__copymypage_event_seats WHERE id = 204')->loadAssoc() === $foreign, 'Foreign reservation changed.');
        });
        foreach (['missing' => 'DELETE FROM #__copymypage_event_seating', 'inactive' => 'UPDATE #__copymypage_event_seating SET status = 0'] as $name => $sql) {
            caseCleanup($name . ' inventory holds entire cart', static function () use ($db, $sql): void {
                fixtureCleanup();
                $db->setQuery($sql)->execute();
                expectHeldCleanup('inventory_requires_review');
            });
        }
        caseCleanup('multiple events release atomically; one orphan holds all', static function () use ($db, $service): void {
            foreach ([false, true] as $missing) {
                fixtureCleanup();
                insertCleanup('copymypage_ticket_cart_items', ['id' => 115, 'cart_id' => 94, 'event_id' => 3,
                    'quantity' => 1, 'price_index' => 0, 'currency' => 'EUR', 'unit_price' => 13]);
                insertCleanup('copymypage_event_seating', ['event_id' => 3, 'layout_id' => 1, 'status' => 1, 'inventory_version' => 7]);
                insertCleanup('copymypage_event_seats', ['id' => 206, 'event_id' => 3, 'seat_id' => 206,
                    'cart_id' => 94, 'status' => 1, 'allocation_type' => 0, 'ticket_id' => null]);
                if ($missing) {
                    expectHeldCleanup('event_missing_requires_review');
                } else {
                    insertCleanup('dpcalendar_events', ['id' => 3, 'title' => 'SYNTHETIC SECOND EVENT']);
                    checkCleanup($service->run(60, 50, false)['released'] === 1, 'Multiple-event release failed.');
                    checkCleanup(array_map('intval', $db->setQuery('SELECT inventory_version FROM #__copymypage_event_seating ORDER BY event_id')->loadColumn()) === [46, 8], 'Multiple-event inventory revisions incorrect.');
                }
            }
        });
        caseCleanup('two cleanup connections: concurrent attempt is busy; first releases once', static function () use ($db, $options, $service): void {
            fixtureCleanup();
            $other = new CleanupFixtureDatabase($options);
            $competitor = cleanupServices($other);
            $observed = false;
            $db->beforeExecute = static function (string $sql) use ($db, $competitor, &$observed): void {
                if (str_starts_with($sql, 'UPDATE #__copymypage_event_seats')) {
                    $db->beforeExecute = null;
                    $report = $competitor->run(60, 50, false);
                    $observed = $report['busy'] && $report['scanned'] === 0;
                }
            };
            checkCleanup($service->run(60, 50, false)['released'] === 1 && $observed, 'Concurrent cleanup was not excluded.');
            checkCleanup($competitor->run(60, 50, false)['scanned'] === 0, 'Competing repeat released twice.');
            $other->disconnect();
        });
        caseCleanup('real row-lock timeout rolls back and is classified', static function () use ($db, $options, $service, &$logs): void {
            fixtureCleanup();
            $other = new CleanupFixtureDatabase($options);
            $other->transactionStart();
            $other->setQuery('SELECT id FROM #__copymypage_ticket_carts WHERE id = 94 FOR UPDATE')->loadResult();
            $before = snapshotCleanup();
            try {
                $report = $service->run(60, 50, false);
                checkCleanup($report['errors'] === 1 && $report['decisions'][0]['reason'] === 'locking_conflict', 'Lock timeout not classified.');
            } finally {
                $other->transactionRollback();
                $other->disconnect();
            }
            checkCleanup(snapshotCleanup() === $before && str_contains($logs[0], 'locking_conflict'), 'Lock failure mutated data or lacks diagnostics.');
        });
        caseCleanup('inventory SQL failure fully rolls back seats, cart and earlier inventory', static function () use ($db, $service, $prefix, &$logs): void {
            fixtureCleanup();
            insertCleanup('dpcalendar_events', ['id' => 3, 'title' => 'SYNTHETIC SECOND EVENT']);
            insertCleanup('copymypage_ticket_cart_items', ['id' => 115, 'cart_id' => 94, 'event_id' => 3,
                'quantity' => 1, 'price_index' => 0, 'currency' => 'EUR', 'unit_price' => 13]);
            insertCleanup('copymypage_event_seating', ['event_id' => 3, 'layout_id' => 1,
                'status' => 1, 'inventory_version' => 7]);
            insertCleanup('copymypage_event_seats', ['id' => 206, 'event_id' => 3, 'seat_id' => 206,
                'cart_id' => 94, 'status' => 1, 'allocation_type' => 0, 'ticket_id' => null]);
            $before = snapshotCleanup();
            $db->getConnection()->query('CREATE TRIGGER `' . $prefix . 'reject_inventory` BEFORE UPDATE ON `'
                . $prefix . "copymypage_event_seating` FOR EACH ROW BEGIN IF NEW.event_id = 3 THEN"
                . " SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SYNTHETIC PRIVATE SQL DATA'; END IF; END");
            try {
                $report = $service->run(60, 50, false);
                checkCleanup($report['errors'] === 1 && $report['released'] === 0, 'Injected SQL failure was not rejected.');
                checkCleanup(snapshotCleanup() === $before, 'Transaction rollback incomplete.');
                checkCleanup(!str_contains(json_encode($report) . implode('', $logs), 'SYNTHETIC PRIVATE'), 'Raw SQL error leaked.');
                checkCleanup(str_contains($logs[0], 'database_error'), 'Database failure not classified.');
            } finally {
                $db->getConnection()->query('DROP TRIGGER `' . $prefix . 'reject_inventory`');
            }
        });
        caseCleanup('unexpected program failure rolls back and logs no exception payload', static function () use ($db, $service, &$logs): void {
            fixtureCleanup();
            $before = snapshotCleanup();
            $db->beforeExecute = static function (string $sql) use ($db): void {
                if (str_starts_with($sql, 'UPDATE #__copymypage_ticket_carts SET status = 2')) {
                    $db->beforeExecute = null;
                    throw new TypeError('SYNTHETIC PRIVATE TOKEN AND CUSTOMER');
                }
            };
            $report = $service->run(60, 50, false);
            checkCleanup($report['errors'] === 1 && snapshotCleanup() === $before, 'Program failure did not roll back.');
            checkCleanup(str_contains($logs[0], 'program_error') && str_contains($logs[0], 'TypeError')
                && !str_contains(implode('', $logs) . json_encode($report), 'SYNTHETIC PRIVATE'), 'Unsafe program diagnostics.');
        });
        caseCleanup('logging failure preserves orphan holds and the scheduler decision', static function () use ($db): void {
            fixtureCleanup();
            $db->setQuery('DELETE FROM #__dpcalendar_events')->execute();
            Log::addLogger(['logger' => 'callback', 'callback' => static function (): void {
                throw new RuntimeException('SYNTHETIC LOG SINK FAILURE');
            }], Log::ALL, ['com_copymypage.reservations']);
            expectHeldCleanup('event_missing_requires_review');
        });
        echo 'PASS TOTAL ', $passed, PHP_EOL;
    }
} finally {
    $db->beforeExecute = null;
    foreach (array_reverse($created) as $table) {
        $db->getConnection()->query('DROP TABLE `' . $prefix . $table . '`');
    }
    $db->disconnect();
    if (is_file($logDirectory . '/copymypage-reservations.php')) {
        unlink($logDirectory . '/copymypage-reservations.php');
    }
    if (is_dir($logDirectory)) {
        rmdir($logDirectory);
    }
}
