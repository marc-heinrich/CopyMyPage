<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.20
 */

namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use DigitalPeak\Component\DPCalendar\Administrator\Helper\DPCalendarHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Projects confirmed CopyMyPage tickets for the authenticated account area.
 *
 * @since  0.0.20
 */
final class AccountTicketsService
{
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly TicketSeatProjectionService $ticketSeats
    ) {
    }

    /**
     * Return only confirmed managed bookings owned by one Joomla user.
     *
     * @return list<array<string, mixed>>
     */
    public function getForUser(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select([
                'DISTINCT ' . $this->db->quoteName('b.id', 'booking_id'),
                $this->db->quoteName('b.book_date', 'book_date'),
                $this->db->quoteName('t.id', 'ticket_id'),
                $this->db->quoteName('t.uid', 'ticket_uid'),
                $this->db->quoteName('t.type', 'ticket_type'),
                $this->db->quoteName('e.id', 'event_id'),
                $this->db->quoteName('e.title', 'event_title'),
                $this->db->quoteName('e.start_date', 'event_start_date'),
                $this->db->quoteName('e.end_date', 'event_end_date'),
                $this->db->quoteName('e.all_day', 'event_all_day'),
                $this->db->quoteName('e.prices', 'event_prices'),
            ])
            ->from($this->db->quoteName('#__dpcalendar_bookings', 'b'))
            ->innerJoin(
                $this->db->quoteName('#__copymypage_ticket_carts', 'c')
                    . ' ON ' . $this->db->quoteName('c.booking_id')
                    . ' = ' . $this->db->quoteName('b.id')
            )
            ->innerJoin(
                $this->db->quoteName('#__dpcalendar_tickets', 't')
                    . ' ON ' . $this->db->quoteName('t.booking_id')
                    . ' = ' . $this->db->quoteName('b.id')
            )
            ->leftJoin(
                $this->db->quoteName('#__dpcalendar_events', 'e')
                    . ' ON ' . $this->db->quoteName('e.id')
                    . ' = ' . $this->db->quoteName('t.event_id')
            )
            ->where($this->db->quoteName('b.user_id') . ' = :userId')
            ->where($this->db->quoteName('b.state') . ' = 1')
            ->where($this->db->quoteName('t.state') . ' = 1')
            ->where(
                $this->db->quoteName('c.status') . ' = '
                    . TicketCartContextService::STATUS_CONVERTED
            )
            ->order([
                $this->db->quoteName('e.start_date') . ' DESC',
                $this->db->quoteName('b.id') . ' DESC',
                $this->db->quoteName('t.id') . ' ASC',
            ])
            ->bind(':userId', $userId, ParameterType::INTEGER);
        $rows    = (array) $this->db->setQuery($query, 0, 1000)->loadObjectList();
        $entries = [];

        foreach ($rows as $row) {
            $bookingId = max(0, (int) ($row->booking_id ?? 0));
            $ticketId  = max(0, (int) ($row->ticket_id ?? 0));
            $eventId   = max(0, (int) ($row->event_id ?? 0));
            $ticketUid = trim((string) ($row->ticket_uid ?? ''));

            if ($bookingId < 1 || $ticketId < 1 || $ticketUid === '') {
                continue;
            }

            $entryKey = $bookingId . ':' . $eventId;

            if (!isset($entries[$entryKey])) {
                $eventTitle = trim((string) ($row->event_title ?? ''));
                $entries[$entryKey] = [
                    'dateIso'   => $this->formatEventDateIso($row),
                    'dateLabel' => $this->formatEventDateLabel($row),
                    'eventId'   => $eventId,
                    'tickets'   => [],
                    'title'     => $eventTitle !== ''
                        ? $eventTitle
                        : Text::sprintf('COM_COPYMYPAGE_BOOKING_COMPLETION_EVENT_FALLBACK', $eventId),
                ];
            }

            $seat = $this->ticketSeats->getForTicket($ticketId, $bookingId);
            $entries[$entryKey]['tickets'][] = [
                'downloadUrl' => Route::link(
                    'site',
                    'index.php?option=com_dpcalendar&task=ticket.pdfdownload&uid='
                        . rawurlencode($ticketUid),
                    false
                ),
                'id'          => $ticketId,
                'seatLabel'   => \is_array($seat) ? trim((string) ($seat['label'] ?? '')) : '',
                'typeLabel'   => $this->getTicketTypeLabel(
                    (string) ($row->event_prices ?? ''),
                    (int) ($row->ticket_type ?? 0)
                ),
            ];
        }

        return array_values($entries);
    }

    private function formatEventDateIso(\stdClass $row): string
    {
        $startDate = trim((string) ($row->event_start_date ?? ''));

        return $startDate === ''
            ? ''
            : DPCalendarHelper::getDate($startDate)->format('c', true, false);
    }

    private function formatEventDateLabel(\stdClass $row): string
    {
        $startDate = trim((string) ($row->event_start_date ?? ''));

        if ($startDate === '') {
            return '';
        }

        $format = (int) ($row->event_all_day ?? 0) === 1
            ? Text::_('DATE_FORMAT_LC1')
            : Text::_('DATE_FORMAT_LC2');

        return DPCalendarHelper::getDate($startDate)->format($format, true);
    }

    private function getTicketTypeLabel(string $prices, int $type): string
    {
        $priceData = json_decode($prices);
        $key       = 'prices' . max(0, $type);
        $label     = \is_object($priceData) && isset($priceData->{$key}->label)
            ? trim((string) $priceData->{$key}->label)
            : '';

        return $label !== ''
            ? $label
            : Text::_('COM_COPYMYPAGE_TICKET_SELECTION_TICKET_TYPE_DEFAULT');
    }
}
