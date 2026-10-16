<?php
/** Standalone regression test: PHP CLI, in-memory SQLite, no live Joomla data. */
namespace {
    if (PHP_SAPI !== 'cli') {
        exit;
    }

    define('_JEXEC', 1);
    $GLOBALS['cmpTestNow'] = 1800000000;
}

// Minimal dependency doubles; the reservation/context services below are the real live files.
namespace Joomla\CMS\Application {
    interface CMSWebApplicationInterface {}
}
namespace Joomla\Database {
    interface DatabaseInterface {}
    final class ParameterType
    {
        public const INTEGER = 1;
        public const STRING = 2;
    }
}
namespace Joomla\CMS\Component {
    final class ComponentHelper
    {
        public static function getParams(string $name): object
        {
            return new class {
                public function get(string $name, mixed $default): mixed { return $default; }
            };
        }
    }
}
namespace Joomla\CMS\Language {
    final class Text
    {
        public static function _(string $key): string { return $key; }
    }
}
namespace Joomla\Component\CopyMyPage\Site\Service {
    function time(): int { return $GLOBALS['cmpTestNow']; }
    function gmdate(string $format, ?int $timestamp = null): string
    {
        return \gmdate($format, $timestamp ?? time());
    }
    final class TicketCatalogService {}
    final class SeatSelectionService {}
}
namespace {
    use Joomla\Component\CopyMyPage\Site\Service\TicketCartContextService;
    use Joomla\Component\CopyMyPage\Site\Service\TicketCatalogService;
    use Joomla\Component\CopyMyPage\Site\Service\TicketReservationService;
    use Joomla\Component\CopyMyPage\Site\Service\SeatSelectionService;

    require __DIR__ . '/../src/Service/TicketCartContextService.php';
    require __DIR__ . '/../src/Service/TicketReservationService.php';

    final class TestQuery
    {
        public string $verb = 'SELECT';
        public string $table = '';
        public string $columns = '*';
        public array $where = [];
        public array $sets = [];
        public array $bindings = [];
        public function select(string $columns): self { $this->columns = $columns; return $this; }
        public function from(string $table): self { $this->table = $table; return $this; }
        public function where(string $where): self { $this->where[] = $where; return $this; }
        public function update(string $table): self { $this->verb = 'UPDATE'; return $this->from($table); }
        public function delete(string $table): self { $this->verb = 'DELETE'; return $this->from($table); }
        public function set(string $set): self { $this->sets[] = $set; return $this; }
        public function bind(string $key, mixed &$value, int $type): self
        {
            $this->bindings[$key] = $value;
            return $this;
        }
        public function __toString(): string
        {
            $sql = match ($this->verb) {
                'SELECT' => 'SELECT ' . $this->columns . ' FROM ' . $this->table,
                'UPDATE' => 'UPDATE ' . $this->table . ' SET ' . implode(', ', $this->sets),
                'DELETE' => 'DELETE FROM ' . $this->table,
            };
            return $sql . ($this->where === [] ? '' : ' WHERE ' . implode(' AND ', $this->where));
        }
    }
    final class TestDatabase implements Joomla\Database\DatabaseInterface
    {
        private string $query = '';
        private array $bindings = [];
        public function __construct(public PDO $pdo) {}
        public function getQuery(bool $new): TestQuery { return new TestQuery(); }
        public function quoteName(string $name): string { return '"' . str_replace('#__', 'test_', $name) . '"'; }
        public function quote(string $value): string { return $this->pdo->quote($value); }
        public function setQuery(TestQuery|string $query): self
        {
            $this->bindings = $query instanceof TestQuery ? $query->bindings : [];
            $this->query = (string) $query;
            return $this;
        }
        public function statement(): PDOStatement
        {
            $statement = $this->pdo->prepare($this->query);
            $statement->execute($this->bindings);
            return $statement;
        }
        public function execute(): void { $this->statement(); }
        public function loadObject(): object|false { return $this->statement()->fetchObject(); }
        public function loadResult(): mixed { return $this->statement()->fetchColumn(); }
    }
    final class TestSession
    {
        public array $data = [];
        public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
        public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
        public function remove(string $key): void { unset($this->data[$key]); }
    }
    final class TestApplication implements Joomla\CMS\Application\CMSWebApplicationInterface
    {
        public function __construct(private TestSession $session) {}
        public function getSession(): TestSession { return $this->session; }
    }
    function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
        echo 'PASS ' . $message . PHP_EOL;
    }

    // Timezone changes must not change the UTC contract. Each case has isolated data.
    foreach (['UTC', 'Europe/Berlin', 'America/New_York'] as $timezone) {
        date_default_timezone_set($timezone);
        foreach ([900, 1, 0, -1] as $remaining) {
            $pdo = new PDO('sqlite::memory:');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('CREATE TABLE test_copymypage_ticket_carts (id INTEGER, token_hash TEXT, status INTEGER, revision INTEGER, expires_at TEXT, modified TEXT)');
            $pdo->exec('CREATE TABLE test_copymypage_ticket_cart_items (cart_id INTEGER)');
            $pdo->exec('CREATE TABLE test_copymypage_ticket_customers (cart_id INTEGER)');
            $token = str_repeat('a', 64);
            $expiry = gmdate('Y-m-d H:i:s', $GLOBALS['cmpTestNow'] + $remaining);
            $insert = $pdo->prepare('INSERT INTO test_copymypage_ticket_carts VALUES (1, ?, 0, 7, ?, ?)');
            $insert->execute([hash('sha256', $token), $expiry, $expiry]);
            $pdo->exec('INSERT INTO test_copymypage_ticket_cart_items VALUES (1)');
            $session = new TestSession();
            $session->set('com_copymypage.ticket_cart_token', $token);
            $db = new TestDatabase($pdo);
            $context = new TicketCartContextService(new TestApplication($session), $db);
            $service = new TicketReservationService($db, new TicketCatalogService(), $context, new SeatSelectionService());
            $state = $service->getBasketIndicatorState();
            check($state['active'] === ($remaining > 0) && $state['secondsLeft'] === max(0, $remaining),
                "$timezone: server remainder $remaining seconds");
            $row = $pdo->query('SELECT * FROM test_copymypage_ticket_carts')->fetchObject();
            check($row->expires_at === $expiry && $row->status === ($remaining > 0 ? 0 : 2),
                "$timezone: expiry unchanged and elapsed cart rejected ($remaining)");
            $newExpiry = new ReflectionMethod($context, 'getNewExpiry');
            check($context->getReservationMinutes() === 15
                && $newExpiry->invoke($context) === gmdate('Y-m-d H:i:s', $GLOBALS['cmpTestNow'] + 900),
                "$timezone: configured 15 minutes unchanged ($remaining)");
        }
    }
}
