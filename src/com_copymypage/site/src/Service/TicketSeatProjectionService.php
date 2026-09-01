<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Projects CopyMyPage seat assignments onto DPCalendar tickets.
 *
 * @since  0.0.19
 */
final class TicketSeatProjectionService
{
    /** @var array<int, array<int, array<string, mixed>>> */
    private array $bookingCache = [];

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Load all seat assignments for one DPCalendar booking in a single query.
     *
     * @return array<int, array<string, mixed>> Assignments indexed by ticket ID.
     */
    public function getForBooking(int $bookingId): array
    {
        if ($bookingId < 1) {
            return [];
        }

        if (array_key_exists($bookingId, $this->bookingCache)) {
            return $this->bookingCache[$bookingId];
        }

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('es.ticket_id', 'ticket_id'),
                $this->db->quoteName('es.event_id', 'event_id'),
                $this->db->quoteName('es.status', 'event_seat_status'),
                $this->db->quoteName('lt.table_number', 'table_number'),
                $this->db->quoteName('lt.label', 'table_label'),
                $this->db->quoteName('s.seat_number', 'seat_number'),
                $this->db->quoteName('s.seat_code', 'seat_code'),
            ])
            ->from($this->db->quoteName('#__copymypage_event_seats', 'es'))
            ->innerJoin(
                $this->db->quoteName('#__dpcalendar_tickets', 'dt')
                    . ' ON ' . $this->db->quoteName('dt.id')
                    . ' = ' . $this->db->quoteName('es.ticket_id')
            )
            ->innerJoin(
                $this->db->quoteName('#__copymypage_seats', 's')
                    . ' ON ' . $this->db->quoteName('s.id')
                    . ' = ' . $this->db->quoteName('es.seat_id')
            )
            ->innerJoin(
                $this->db->quoteName('#__copymypage_layout_tables', 'lt')
                    . ' ON ' . $this->db->quoteName('lt.id')
                    . ' = ' . $this->db->quoteName('s.layout_table_id')
            )
            ->where($this->db->quoteName('dt.booking_id') . ' = :bookingId')
            ->where($this->db->quoteName('es.ticket_id') . ' IS NOT NULL')
            ->order([
                $this->db->quoteName('es.event_id') . ' ASC',
                $this->db->quoteName('lt.sort_order') . ' ASC',
                $this->db->quoteName('s.sort_order') . ' ASC',
                $this->db->quoteName('es.id') . ' ASC',
            ])
            ->bind(':bookingId', $bookingId, ParameterType::INTEGER);
        $assignments = [];

        foreach ((array) $this->db->setQuery($query)->loadObjectList() as $row) {
            $ticketId   = max(0, (int) ($row->ticket_id ?? 0));
            $table      = trim((string) ($row->table_number ?? ''));
            $seat       = trim((string) ($row->seat_number ?? ''));
            $label      = $table !== '' && $seat !== ''
                ? Text::sprintf('COM_COPYMYPAGE_SEAT_SELECTION_SEAT_LABEL', $table, $seat)
                : '';

            if ($ticketId < 1 || isset($assignments[$ticketId])) {
                continue;
            }

            $assignments[$ticketId] = [
                'eventId'        => max(0, (int) ($row->event_id ?? 0)),
                'eventSeatStatus' => (int) ($row->event_seat_status ?? 0),
                'label'          => $label,
                'seatCode'       => trim((string) ($row->seat_code ?? '')),
                'seatNumber'     => $seat,
                'tableLabel'     => trim((string) ($row->table_label ?? '')),
                'tableNumber'    => $table,
                'ticketId'       => $ticketId,
            ];
        }

        $this->bookingCache[$bookingId] = $assignments;

        return $assignments;
    }

    /**
     * Get one ticket's seat from the booking-level projection cache.
     *
     * @return array<string, mixed>|null
     */
    public function getForTicket(int $ticketId, int $bookingId): ?array
    {
        if ($ticketId < 1 || $bookingId < 1) {
            return null;
        }

        return $this->getForBooking($bookingId)[$ticketId] ?? null;
    }

    /**
     * Invalidate a projection built before Step 4 linked seats to tickets.
     */
    public function clearBooking(int $bookingId): void
    {
        unset($this->bookingCache[$bookingId]);
    }
}
