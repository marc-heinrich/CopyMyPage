<?php
namespace Joomla\Component\CopyMyPage\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

/** Idempotent additive migration; historical reservations remain UNKNOWN. */
final class ReservationSchema
{
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    public function ensure(): void
    {
        $columns = $this->db->getTableColumns('#__copymypage_ticket_carts', false);
        if (!isset($columns['native_payment_phase'])) {
            $this->db->setQuery('ALTER TABLE #__copymypage_ticket_carts'
                . ' ADD COLUMN native_payment_phase TINYINT UNSIGNED NOT NULL DEFAULT 0')->execute();
            $columns = $this->db->getTableColumns('#__copymypage_ticket_carts', false);
        }
        $column = $columns['native_payment_phase'] ?? null;
        if ($column === null || preg_match('/^tinyint(?:\([0-9]+\))? unsigned$/iD', $column->Type) !== 1
            || $column->Null !== 'NO' || (string) $column->Default !== '0') {
            throw new \RuntimeException('Native reservation schema requires review; existing data was retained.');
        }
    }
}
