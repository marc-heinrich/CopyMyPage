<?php
/**
 * Issue #192: real candidate audit payload and Joomla 6.2.0 rendering, without a database.
 * PHP 8.4; --candidate=.../ReservationCleanupService.php defaults to authoritative joomla6.
 * Joomla dependencies default to joomla6_test; override with --joomla-root=... .
 * Disable network/mail functions and URL streams as documented in test/README.md.
 * Expected: PASS output, exit 0. An unfixed candidate must fail.
 * The audit block is executed from the candidate, not duplicated here. Transaction safety
 * is covered separately by native-dpcalendar-cleanup-mysql.php against synthetic MySQL tables.
 */

/** Validate the stored shape and both actual Joomla rendering modes. */
function checkActionLog192(object $audit, string $helper = Joomla\Component\Actionlogs\Administrator\Helper\ActionlogsHelper::class): array
{
    $message = json_decode($audit->message, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($message) || !array_key_exists('providerCheck', $message)) {
        throw new RuntimeException('Action-log payload or providerCheck is missing.');
    }
    foreach ($message as $value) {
        if ($value !== null && !is_scalar($value)) {
            throw new RuntimeException('Action-log top-level value is structured.');
        }
    }
    if ($message['providerCheck'] !== null && !is_string($message['providerCheck'])) {
        throw new RuntimeException('Provider proof must be a JSON string or null.');
    }
    foreach ([true, false] as $links) {
        $rendered = $helper::getHumanReadableLogMessage($audit, $links);
        if (!str_contains($rendered, (string) $message['booking_id']) || str_contains($rendered, '{booking_id}')) {
            throw new RuntimeException('Joomla did not render the booking substitution.');
        }
    }
    return $message;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}
if (PHP_SAPI !== 'cli') { exit(1); }
ini_set('display_errors', '0');
ini_set('log_errors', '0');
ini_set('zend.exception_ignore_args', '1');
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
foreach (['curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'mail'] as $function) {
    if (function_exists($function)) { throw new RuntimeException('Network/mail boundary must be disabled.'); }
}
if (ini_get('allow_url_fopen')) { throw new RuntimeException('URL streams must be disabled.'); }
$options192 = getopt('', ['candidate:', 'joomla-root:']);
$candidate192 = realpath($options192['candidate'] ?? 'C:/wamp/www/joomla6/components/com_copymypage/src/Service/ReservationCleanupService.php');
$joomla192 = realpath($options192['joomla-root'] ?? 'C:/wamp/www/joomla6_test');
if (!$candidate192 || !$joomla192) { throw new RuntimeException('Candidate or Joomla dependencies missing.'); }
define('_JEXEC', 1);
define('JDEBUG', false);
define('JPATH_BASE', $joomla192);
require JPATH_BASE . '/includes/defines.php';
$loader192 = require JPATH_LIBRARIES . '/vendor/autoload.php';
$loader192->addPsr4('Joomla\\CMS\\', JPATH_LIBRARIES . '/src');
$loader192->addPsr4('Joomla\\Component\\Actionlogs\\Administrator\\', JPATH_ADMINISTRATOR . '/components/com_actionlogs/src');
require $candidate192;
Joomla\CMS\Factory::$language = new Joomla\CMS\Language\Language('en-GB');
Joomla\CMS\Factory::$application = new class {
    public function get($key, $default = null) { return $default; }
};
// Only language-plugin discovery is omitted offline; rendering itself is inherited unchanged.
class OfflineActionlogsHelper192 extends Joomla\Component\Actionlogs\Administrator\Helper\ActionlogsHelper
{
    public static function loadActionLogPluginsLanguage() {}
}
$source192 = file_get_contents($candidate192);
if (preg_match('/\$audit = \(object\) \[\x27message_language_key\x27=>self::NATIVE_DELETE_LOG,.*?;(?=\s*\$this->db->insertObject)/s', $source192, $match192) !== 1) {
    throw new RuntimeException('Native audit block not found in candidate.');
}
$reflection192 = new ReflectionClass(Joomla\Component\CopyMyPage\Site\Service\ReservationCleanupService::class);
$block192 = str_replace('self::NATIVE_DELETE_LOG', var_export($reflection192->getConstant('NATIVE_DELETE_LOG'), true), $match192[0]);
$booking = (object) ['payment_provider' => 'paypal-73', 'transaction_id' => 'SYNTHETIC-ORDER',
    'token' => 'SYNTHETIC-TOKEN', 'uid' => 'SYNTHETIC-UID', 'book_date' => '2026-11-18 00:00:00',
    'state' => 3, 'price' => '13.00', 'currency' => 'EUR'];
$actor = (object) ['id' => 192];
$payload192 = eval('return function ($paymentProof, $automatic = true) use ($booking, $actor) {
    $bookingId = 192; $cartId = 193; $deleted = [194]; $seatIds = [195];
    ' . $block192 . ' return $audit; };');
$proof192 = ['status' => 'PAYER_ACTION_REQUIRED', 'environment' => 'sandbox',
    'detail' => '<script>"Ä & \' / \\ 日本語 😀</script>',
    'nested' => ['list' => [0, false, null, ['kept' => true]]]];
$cases192 = [
    'nested array' => [['providerCheck' => $proof192], $proof192],
    'object' => [['providerCheck' => (object) $proof192], $proof192],
    'null' => [['providerCheck' => null], null],
    'missing key' => [[], null],
    'manual deletion' => [null, null],
    'empty array' => [['providerCheck' => []], []],
    'zero' => [['providerCheck' => 0], 0],
    'false' => [['providerCheck' => false], false],
    'empty string' => [['providerCheck' => ''], ''],
];
echo 'CANDIDATE ', $reflection192->getFileName(), '; SHA256 ', hash_file('sha256', $candidate192), PHP_EOL;
foreach ($cases192 as $name192 => [$paymentProof192, $expected192]) {
    $audit192 = $payload192($paymentProof192, $paymentProof192 !== null);
    $message192 = checkActionLog192($audit192, OfflineActionlogsHelper192::class);
    if ($expected192 === null && $message192['providerCheck'] !== null) {
        throw new RuntimeException('Absent provider evidence must remain null.');
    }
    $actual192 = $message192['providerCheck'] === null ? null : json_decode($message192['providerCheck'], true, 512, JSON_THROW_ON_ERROR);
    if ($actual192 !== $expected192) { throw new RuntimeException('Provider evidence changed: ' . $name192); }
    echo 'PASS ', $name192, ': compatible payload, lossless roundtrip, Joomla with/without links', PHP_EOL;
}
$audit192 = $payload192(['providerCheck' => $proof192]);
$audit192->message_language_key = 'Audit {booking_id}: {providerCheck}';
foreach ([true, false] as $links192) {
    $rendered192 = OfflineActionlogsHelper192::getHumanReadableLogMessage($audit192, $links192);
    $displayed192 = substr(html_entity_decode($rendered192, ENT_QUOTES | ENT_HTML5, 'UTF-8'), strlen('Audit 192: '));
    if (str_contains($rendered192, '<script>') || !str_contains($rendered192, '&lt;script&gt;')
        || !str_contains($rendered192, '&amp;') || json_decode($displayed192, true, 512, JSON_THROW_ON_ERROR) !== $proof192) {
        throw new RuntimeException('Unsafe or damaged special-character output.');
    }
}
echo "PASS special characters and HTML escaping in both Joomla rendering modes\n";
$failed192 = false;
try { $payload192(['providerCheck' => ['invalid' => "\xB1\x31"]]); }
catch (JsonException $error192) { $failed192 = $error192->getCode() === JSON_ERROR_UTF8; }
if (!$failed192) { throw new RuntimeException('Invalid UTF-8 did not fail serialization.'); }
echo "PASS inner JSON serializer throws on invalid UTF-8; no partial audit produced\n";
echo "RESULT Issue 192: 11 cases passed; database writes: 0; external calls: 0\n";
