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
use Joomla\CMS\Date\Date;
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
    private const BOOKING_BATCH_SIZE = 100;
    private const TICKET_BATCH_SIZE = 500;

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

        $bookings      = [];
        $beforeId      = 0;
        $now           = DPCalendarHelper::getDate();
        $today         = $now->format('Y-m-d', true, false);
        $nowTimestamp  = $now->getTimestamp();

        do {
            $bookingRows = $this->loadBookingBatch($userId, $beforeId);
            $bookingIds  = [];

            foreach ($bookingRows as $row) {
                $bookingId = (int) ($row->booking_id ?? 0);

                if ($bookingId < 1) {
                    continue;
                }

                $bookedAt = $this->parseDate((string) ($row->book_date ?? ''), false);
                $total    = trim((string) ($row->booking_price ?? ''));
                $currency = strtoupper(trim((string) ($row->booking_currency ?? '')));
                $bookingIds[] = $bookingId;
                $bookings[$bookingId] = [
                    'id'             => $bookingId,
                    'bookedAt'       => $bookedAt?->format('c', true, false),
                    'status'         => 'confirmed',
                    'total'          => preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $total) ? $total : null,
                    'currency'       => preg_match('/^[A-Z]{3}$/D', $currency) ? $currency : null,
                    'isPast'         => false,
                    'checkedInCount' => 0,
                    'ticketCount'    => 0,
                    'events'         => [],
                ];
            }

            if ($bookingIds !== []) {
                $this->addTickets($bookings, $bookingIds, $userId, $today, $nowTimestamp);

                foreach ($bookingIds as $bookingId) {
                    $events = array_values($bookings[$bookingId]['events']);
                    usort($events, static function (array $left, array $right): int {
                        if ($left['sortKey'] === null || $right['sortKey'] === null) {
                            return ($left['sortKey'] === null) <=> ($right['sortKey'] === null)
                                ?: $left['id'] <=> $right['id'];
                        }

                        return strcmp($left['sortKey'], $right['sortKey'])
                            ?: $left['id'] <=> $right['id'];
                    });

                    $isPast = $events !== [];

                    foreach ($events as &$event) {
                        $isPast = $isPast && $event['dateKnown'] && $event['endPassed'];
                        unset($event['sortKey'], $event['endPassed']);
                    }

                    unset($event);
                    $bookings[$bookingId]['events'] = $events;
                    $bookings[$bookingId]['isPast'] = $isPast;
                }
            }

            $beforeId = (int) ($bookingRows[array_key_last($bookingRows)]->booking_id ?? 0);
        } while (\count($bookingRows) === self::BOOKING_BATCH_SIZE && $beforeId > 0);

        return array_values($bookings);
    }

    /** @return list<object> */
    private function loadBookingBatch(int $userId, int $beforeId): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('b.id', 'booking_id'),
                $this->db->quoteName('b.book_date', 'book_date'),
                $this->db->quoteName('b.price', 'booking_price'),
                $this->db->quoteName('b.currency', 'booking_currency'),
            ])
            ->from($this->db->quoteName('#__dpcalendar_bookings', 'b'))
            ->where($this->db->quoteName('b.user_id') . ' = :userId')
            ->where($this->db->quoteName('b.state') . ' = 1')
            ->where($this->convertedCartCondition())
            ->order($this->db->quoteName('b.id') . ' DESC')
            ->bind(':userId', $userId, ParameterType::INTEGER);

        if ($beforeId > 0) {
            $query->where($this->db->quoteName('b.id') . ' < :beforeId')
                ->bind(':beforeId', $beforeId, ParameterType::INTEGER);
        }

        return (array) $this->db->setQuery($query, 0, self::BOOKING_BATCH_SIZE)->loadObjectList();
    }

    /**
     * Read every ticket for the selected bookings without a raw joined-row ceiling.
     *
     * @param array<int, array<string, mixed>> $bookings
     * @param list<int> $bookingIds
     */
    private function addTickets(
        array &$bookings,
        array $bookingIds,
        int $userId,
        string $today,
        int $nowTimestamp
    ): void {
        $afterTicketId = 0;

        do {
            $query = $this->db->getQuery(true)
                ->select([
                    $this->db->quoteName('t.booking_id', 'booking_id'),
                    $this->db->quoteName('t.id', 'ticket_id'),
                    $this->db->quoteName('t.uid', 'ticket_uid'),
                    $this->db->quoteName('t.type', 'ticket_type'),
                    $this->db->quoteName('t.state', 'ticket_state'),
                    $this->db->quoteName('t.event_id', 'ticket_event_id'),
                    $this->db->quoteName('e.id', 'event_id'),
                    $this->db->quoteName('e.title', 'event_title'),
                    $this->db->quoteName('e.start_date', 'event_start_date'),
                    $this->db->quoteName('e.end_date', 'event_end_date'),
                    $this->db->quoteName('e.all_day', 'event_all_day'),
                    $this->db->quoteName('e.prices', 'event_prices'),
                ])
                ->from($this->db->quoteName('#__dpcalendar_tickets', 't'))
                ->innerJoin(
                    $this->db->quoteName('#__dpcalendar_bookings', 'b')
                        . ' ON ' . $this->db->quoteName('b.id')
                        . ' = ' . $this->db->quoteName('t.booking_id')
                )
                ->leftJoin(
                    $this->db->quoteName('#__dpcalendar_events', 'e')
                        . ' ON ' . $this->db->quoteName('e.id')
                        . ' = ' . $this->db->quoteName('t.event_id')
                )
                ->where($this->db->quoteName('t.booking_id') . ' IN (' . implode(',', $bookingIds) . ')')
                ->where($this->db->quoteName('b.user_id') . ' = :userId')
                ->where($this->db->quoteName('b.state') . ' = 1')
                ->where($this->db->quoteName('t.state') . ' IN (1, 9)')
                ->where($this->convertedCartCondition())
                ->order($this->db->quoteName('t.id') . ' ASC')
                ->bind(':userId', $userId, ParameterType::INTEGER);

            if ($afterTicketId > 0) {
                $query->where($this->db->quoteName('t.id') . ' > :afterTicketId')
                    ->bind(':afterTicketId', $afterTicketId, ParameterType::INTEGER);
            }

            $rows = (array) $this->db->setQuery($query, 0, self::TICKET_BATCH_SIZE)->loadObjectList();
            $seats = $this->ticketSeats->getForTickets(array_map(
                static fn(object $row): int => (int) ($row->ticket_id ?? 0),
                $rows
            ));

            foreach ($rows as $row) {
                $bookingId = (int) ($row->booking_id ?? 0);
                $ticketId  = (int) ($row->ticket_id ?? 0);
                $eventId   = max(0, (int) ($row->ticket_event_id ?? 0));
                $ticketUid = trim((string) ($row->ticket_uid ?? ''));

                if (!isset($bookings[$bookingId]) || $ticketId < 1 || $ticketUid === '') {
                    continue;
                }

                if (!isset($bookings[$bookingId]['events'][$eventId])) {
                    $bookings[$bookingId]['events'][$eventId] = $this->projectEvent(
                        $row,
                        $eventId,
                        $today,
                        $nowTimestamp
                    );
                }

                $seat      = $seats[$ticketId] ?? null;
                $checkedIn = (int) ($row->ticket_state ?? 0) === 9;
                $bookings[$bookingId]['events'][$eventId]['tickets'][] = [
                    'checkedIn'   => $checkedIn,
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

                $bookings[$bookingId]['ticketCount']++;

                if ($checkedIn) {
                    $bookings[$bookingId]['checkedInCount']++;
                }
            }

            $afterTicketId = (int) ($rows[array_key_last($rows)]->ticket_id ?? 0);
        } while (\count($rows) === self::TICKET_BATCH_SIZE && $afterTicketId > 0);
    }

    private function convertedCartCondition(): string
    {
        return 'EXISTS (SELECT 1 FROM ' . $this->db->quoteName('#__copymypage_ticket_carts', 'c')
            . ' WHERE ' . $this->db->quoteName('c.booking_id') . ' = ' . $this->db->quoteName('b.id')
            . ' AND ' . $this->db->quoteName('c.status') . ' = ' . TicketCartContextService::STATUS_CONVERTED
            . ')';
    }

    /** @return array<string, mixed> */
    private function projectEvent(\stdClass $row, int $eventId, string $today, int $nowTimestamp): array
    {
        $allDay = (int) ($row->event_all_day ?? 0) === 1;
        $exists = (int) ($row->event_id ?? 0) === $eventId && $eventId > 0;
        $start  = $exists ? $this->parseDate((string) ($row->event_start_date ?? ''), $allDay) : null;
        $end    = $exists ? $this->parseDate((string) ($row->event_end_date ?? ''), $allDay) : null;
        $known  = $start !== null && $end !== null && $end->getTimestamp() >= $start->getTimestamp();
        $title  = $exists ? trim((string) ($row->event_title ?? '')) : '';
        $format = $allDay ? Text::_('DATE_FORMAT_LC1') : Text::_('DATE_FORMAT_LC2');

        return [
            'id'        => $eventId,
            'title'     => $title !== ''
                ? $title
                : Text::sprintf('COM_COPYMYPAGE_BOOKING_COMPLETION_EVENT_FALLBACK', $eventId),
            'startsAt'  => $start?->format($allDay ? 'Y-m-d' : 'c', true, false),
            'endsAt'    => $end?->format($allDay ? 'Y-m-d' : 'c', true, false),
            'allDay'    => $allDay,
            'dateKnown' => $known,
            'dateIso'   => $start?->format($allDay ? 'Y-m-d' : 'c', true, false) ?? '',
            'dateLabel' => $start?->format($format, true) ?? '',
            'tickets'   => [],
            'sortKey'   => $start?->format('Y-m-d H:i:s', true, false),
            'endPassed' => $known && ($allDay
                ? $end->format('Y-m-d', true, false) < $today
                : $end->getTimestamp() <= $nowTimestamp),
        ];
    }

    private function parseDate(string $value, bool $allDay): ?Date
    {
        $value  = trim($value);
        $format = $allDay && \strlen($value) === 10 ? 'Y-m-d' : 'Y-m-d H:i:s';
        $date   = \DateTimeImmutable::createFromFormat('!' . $format, $value, new \DateTimeZone('UTC'));

        if ($date === false || $date->format($format) !== $value) {
            return null;
        }

        try {
            return DPCalendarHelper::getDate($value, $allDay);
        } catch (\Throwable) {
            return null;
        }
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
