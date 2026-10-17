<?php
/** CMP #177: additive installation/update of the component-owned payment schema. */
namespace Joomla\Component\CopyMyPage\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Installer\Installer;
use Joomla\Database\DatabaseInterface;

final class PaymentSchema
{
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /** No native-table changes, data adoption, automatic drift repair or destructive downgrade. */
    public function ensure(): void
    {
        $lock = 'cmp177-schema-' . substr(hash('sha256', $this->db->getPrefix()), 0, 32);
        if ((int) $this->db->setQuery('SELECT GET_LOCK(' . $this->db->quote($lock) . ', 10)')->loadResult() !== 1) {
            throw new \RuntimeException('CopyMyPage payment schema is locked.');
        }

        try {
            $sql = file_get_contents(JPATH_ADMINISTRATOR . '/components/com_copymypage/sql/payments.mysql.utf8.sql');
            if ($sql === false || trim($sql) === '') {
                throw new \RuntimeException('CopyMyPage payment schema source is missing.');
            }
            foreach (Installer::splitSql($sql) as $statement) {
                if (trim($statement) !== '') {
                    $this->db->setQuery($statement)->execute();
                }
            }
            // Verify the known old/new contract before applying only the approved additive delta.
            $this->verify(true);
            $columns = $this->db->getTableColumns('#__copymypage_payment_attempts');
            foreach (['reservation_minutes' => 'SMALLINT UNSIGNED', 'reservation_expires_at' => 'DATETIME(6)'] as $name => $type) {
                if (!array_key_exists($name, $columns)) {
                    $this->db->setQuery('ALTER TABLE ' . $this->db->quoteName('#__copymypage_payment_attempts')
                        . ' ADD COLUMN ' . $this->db->quoteName($name) . ' ' . $type . ' NULL DEFAULT NULL')->execute();
                }
            }
            $indexes = $this->db->setQuery('SHOW INDEX FROM ' . $this->db->quoteName('#__copymypage_payment_attempts'))->loadAssocList();
            if (!in_array('idx_cmp_payment_reservation', array_column($indexes, 'Key_name'), true)) {
                $this->db->setQuery('ALTER TABLE ' . $this->db->quoteName('#__copymypage_payment_attempts')
                    . ' ADD INDEX idx_cmp_payment_reservation (reservation_expires_at, state, id)')->execute();
            }
            $this->verify();
        } finally {
            $this->db->setQuery('SELECT RELEASE_LOCK(' . $this->db->quote($lock) . ')')->loadResult();
        }
    }

    /** Validate the actual contract; CREATE IF NOT EXISTS must not hide an incompatible table. */
    public function verify(bool $allowMissingReservations = false): void
    {
        $contracts = [
            '#__copymypage_payment_attempts' => [
                'columns' => ['id' => 'char(36)', 'booking_id' => 'int unsigned', 'cart_id' => 'int unsigned', 'owner_id' => 'int unsigned', 'provider_id' => 'varchar(100)', 'provider_account_hash' => 'char(64)', 'sandbox' => 'tinyint unsigned', 'snapshot_hash' => 'char(64)', 'expected_amount' => 'decimal(15,2)', 'expected_currency' => 'char(3)', 'expected_payee' => 'varchar(64)', 'return_base_uri' => 'varchar(512)', 'create_key' => 'char(36)', 'capture_key' => 'char(36)', 'create_payload_hash' => 'char(64)', 'order_id' => 'varchar(36)', 'capture_id' => 'varchar(36)', 'state' => 'varchar(24)', 'provider_status' => 'varchar(32)', 'provider_evidence' => 'mediumtext', 'manual_reason' => 'varchar(80)', 'lease_id' => 'char(36)', 'lease_expires' => 'datetime(6)', 'create_started' => 'datetime(6)', 'create_safe_until' => 'datetime(6)', 'capture_started' => 'datetime(6)', 'captured_at' => 'datetime(6)', 'finalized_at' => 'datetime(6)', 'retry_count' => 'int unsigned', 'version' => 'int unsigned', 'created' => 'datetime(6)', 'modified' => 'datetime(6)'],
                'unique' => ['PRIMARY' => ['id'], 'uq_cmp_payment_booking' => ['booking_id'], 'uq_cmp_payment_create' => ['create_key'], 'uq_cmp_payment_capture' => ['capture_key'], 'uq_cmp_payment_order' => ['provider_id', 'order_id'], 'uq_cmp_payment_capture_id' => ['provider_id', 'capture_id']],
                'constraints' => ['cmp_payment_amount', 'cmp_payment_keys', 'cmp_payment_state'],
            ],
            '#__copymypage_mail_outbox' => [
                'columns' => ['id' => 'bigint unsigned', 'dedupe_key' => 'char(64)', 'booking_id' => 'int unsigned', 'attempt_id' => 'char(36)', 'snapshot_hash' => 'char(64)', 'kind' => 'varchar(96)', 'state' => 'varchar(16)', 'sender' => 'varchar(254)', 'recipients' => 'text', 'mime' => 'longblob', 'lease_id' => 'char(36)', 'lease_expires' => 'datetime(6)', 'attempts' => 'int unsigned', 'last_error' => 'varchar(80)', 'sent_at' => 'datetime(6)', 'created' => 'datetime(6)', 'modified' => 'datetime(6)'],
                'unique' => ['PRIMARY' => ['id'], 'uq_cmp_outbox_job' => ['dedupe_key']],
                'constraints' => ['cmp_outbox_attempt', 'cmp_outbox_state'],
            ],
        ];

        $contracts['#__copymypage_payment_attempts']['columns']['reservation_minutes'] = 'smallint unsigned';
        $contracts['#__copymypage_payment_attempts']['columns']['reservation_expires_at'] = 'datetime(6)';

        foreach ($contracts as $table => $contract) {
            $columns = $this->db->getTableColumns($table, false);
            $nullable = $table === '#__copymypage_payment_attempts'
                ? ['create_payload_hash','order_id','capture_id','provider_status','provider_evidence','manual_reason','lease_id','lease_expires','create_started','create_safe_until','capture_started','captured_at','finalized_at','reservation_minutes','reservation_expires_at']
                : ['attempt_id','lease_id','lease_expires','last_error','sent_at'];
            $fullColumns = [];
            foreach ($this->db->setQuery('SHOW FULL COLUMNS FROM ' . $this->db->quoteName($table))->loadAssocList() as $column) {
                $fullColumns[$column['Field']] = $column;
            }
            foreach ($contract['columns'] as $name => $type) {
                if ($allowMissingReservations && !isset($columns[$name]) && str_starts_with($name, 'reservation_')) {
                    continue;
                }
                $actual = strtolower((string) ($columns[$name]->Type ?? ''));
                $actual = preg_replace('/\b(int|bigint|tinyint|smallint)\(\d+\)/', '$1', $actual);
                if ($actual !== $type) {
                    throw new \RuntimeException('Incompatible CopyMyPage payment column: ' . $name);
                }
                if (($columns[$name]->Null ?? '') !== (in_array($name, $nullable, true) ? 'YES' : 'NO')) {
                    throw new \RuntimeException('Incompatible CopyMyPage payment nullability: ' . $name);
                }
                if (str_starts_with($name, 'reservation_') && ($columns[$name]->Default ?? null) !== null) {
                    throw new \RuntimeException('Payment reservations require a NULL default.');
                }
                $binaryIdentity = str_starts_with($type, 'char(') || in_array($name, ['provider_id','expected_payee','order_id','capture_id','state','provider_status','manual_reason','kind','last_error'], true);
                if ($binaryIdentity && ($fullColumns[$name]['Collation'] ?? '') !== 'ascii_bin') {
                    throw new \RuntimeException('Incompatible CopyMyPage payment identity collation: ' . $name);
                }
            }
            $indexes = [];
            foreach ($this->db->setQuery('SHOW INDEX FROM ' . $this->db->quoteName($table))->loadAssocList() as $index) {
                if ((int) $index['Non_unique'] === 0) {
                    if ($index['Sub_part'] !== null) {
                        throw new \RuntimeException('CopyMyPage payment uniqueness requires full columns.');
                    }
                    $indexes[$index['Key_name']][(int) $index['Seq_in_index']] = $index['Column_name'];
                }
            }
            foreach ($contract['unique'] as $name => $expected) {
                $actual = $indexes[$name] ?? [];
                ksort($actual);
                if (array_values($actual) !== $expected) {
                    throw new \RuntimeException('Incompatible CopyMyPage payment unique index: ' . $name);
                }
            }
            if ($table === '#__copymypage_payment_attempts') {
                $reservationIndex = [];
                foreach ($this->db->setQuery('SHOW INDEX FROM ' . $this->db->quoteName($table))->loadAssocList() as $index) {
                    if ($index['Key_name'] === 'idx_cmp_payment_reservation') {
                        if ($index['Sub_part'] !== null || (int) $index['Non_unique'] !== 1) {
                            throw new \RuntimeException('Incompatible payment reservation index.');
                        }
                        $reservationIndex[(int) $index['Seq_in_index']] = $index['Column_name'];
                    }
                }
                ksort($reservationIndex);
                if ((!$allowMissingReservations || $reservationIndex !== [])
                    && array_values($reservationIndex) !== ['reservation_expires_at', 'state', 'id']) {
                    throw new \RuntimeException('Incompatible payment reservation index.');
                }
            }
            $definition = (string) $this->db->setQuery('SHOW CREATE TABLE ' . $this->db->quoteName($table))->loadRow()[1];
            if (stripos($definition, 'ENGINE=InnoDB') === false) {
                throw new \RuntimeException('CopyMyPage payments require InnoDB transactions.');
            }
            foreach ($contract['constraints'] as $name) {
                if (!str_contains($definition, $this->db->getPrefix() . $name)) {
                    throw new \RuntimeException('Missing CopyMyPage payment constraint: ' . $name);
                }
            }
        }
        $source = file_get_contents(JPATH_ADMINISTRATOR . '/components/com_copymypage/sql/payments.mysql.utf8.sql');
        preg_match_all('/CONSTRAINT `([^`]+)` CHECK \(([^\r\n]+)\)/', (string) $source, $checks, PREG_SET_ORDER);
        foreach ($checks as $check) {
            $name = $this->db->replacePrefix($check[1]);
            $actual = $this->db->setQuery('SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ' . $this->db->quote($name))->loadResult();
            $normalize = static function (string $value): string {
                // MySQL's information_schema emits escaped, charset-prefixed string literals.
                $value = str_replace("\\'", "'", $value);
                $value = preg_replace("/_(?:utf8mb4|utf8mb3|ascii)(?=')/i", '', $value);
                return strtolower(preg_replace('/[\s`()]/', '', $value));
            };
            if (!is_string($actual) || $normalize($actual) !== $normalize($check[2])) {
                throw new \RuntimeException('Incompatible CopyMyPage payment check constraint.');
            }
        }
        $foreign = $this->db->setQuery('SELECT REFERENCED_TABLE_NAME,DELETE_RULE,UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '
            . $this->db->quote($this->db->getPrefix() . 'cmp_outbox_attempt'))->loadObject();
        if (!$foreign || $foreign->REFERENCED_TABLE_NAME !== $this->db->getPrefix() . 'copymypage_payment_attempts'
            || !in_array($foreign->DELETE_RULE, ['RESTRICT','NO ACTION'], true) || !in_array($foreign->UPDATE_RULE, ['RESTRICT','NO ACTION'], true)) {
            throw new \RuntimeException('Incompatible CopyMyPage outbox retention foreign key.');
        }
    }
}
