<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @license     GNU General Public License version 3 or later
 */

namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

/**
 * Reads the complete managed booking after the caller has authorised that booking.
 * This service grants no booking, account, payment or PDF permission.
 */
final class BookingIntegrityService
{
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /** @return array{valid: bool, booking: ?object, cart: ?object, tickets: array, seats: array} */
    public function inspect(int $bookingId, bool $forUpdate = false): array
    {
        $result = ['valid' => false, 'booking' => null, 'cart' => null, 'tickets' => [], 'seats' => []];

        if ($bookingId < 1) {
            return $result;
        }

        $suffix = $forUpdate ? ' FOR UPDATE' : '';
        // Match the established cart -> booking -> tickets -> seats lock order.
        $carts = $this->db->setQuery(
            'SELECT * FROM #__copymypage_ticket_carts WHERE booking_id = ' . $bookingId . ' ORDER BY id' . $suffix
        )->loadObjectList();

        if (\count($carts) !== 1) {
            return $result;
        }

        $cart = $carts[0];
        $cartId = (int) $cart->id;
        $booking = $this->db->setQuery(
            'SELECT * FROM #__dpcalendar_bookings WHERE id = ' . $bookingId . $suffix
        )->loadObject();
        $tickets = $this->db->setQuery(
            'SELECT t.*, e.id AS existing_event_id, e.title AS event_title'
                . ' FROM #__dpcalendar_tickets AS t LEFT JOIN #__dpcalendar_events AS e ON e.id = t.event_id'
                . ' WHERE t.booking_id = ' . $bookingId . ' ORDER BY t.id' . $suffix
        )->loadObjectList();
        $seats = $this->db->setQuery(
            'SELECT es.*, s.id AS existing_seat_id FROM #__copymypage_event_seats AS es'
                . ' LEFT JOIN #__copymypage_seats AS s ON s.id = es.seat_id'
                . ' WHERE es.cart_id = ' . $cartId
                . ' OR es.ticket_id IN (SELECT id FROM #__dpcalendar_tickets WHERE booking_id = ' . $bookingId . ')'
                . ' ORDER BY es.id' . $suffix
        )->loadObjectList();
        $result = ['valid' => false, 'booking' => $booking, 'cart' => $cart, 'tickets' => $tickets, 'seats' => $seats];

        if (!$booking || (int) $cart->status !== TicketCartContextService::STATUS_CONVERTED || $tickets === []) {
            return $result;
        }

        $snapshot = json_decode((string) ($cart->terms_snapshot ?? ''), true);
        $provider = \is_array($snapshot) ? ($snapshot['provider'] ?? null) : null;

        if (
            !\is_array($snapshot) || !\is_array($provider)
            || !\in_array((int) ($snapshot['version'] ?? 0), [1, 2], true)
            || ((int) ($snapshot['version'] ?? 0) === 2 && !\is_array($snapshot['bookingOwner'] ?? null))
            || (isset($snapshot['tickets']) && !\is_array($snapshot['tickets']))
            || (isset($snapshot['seats']) && !\is_array($snapshot['seats']))
            || (int) ($snapshot['cartRevision'] ?? -2) + 1 !== (int) $cart->revision
            || (string) ($snapshot['acceptedAt'] ?? '') !== (string) $cart->terms_accepted_at
            || (string) ($provider['id'] ?? '') !== (string) $cart->payment_provider
            || (string) $booking->payment_provider !== (string) $cart->payment_provider
            || (string) ($provider['currency'] ?? '') !== (string) $booking->currency
            || abs((float) ($provider['total'] ?? -1) - (float) $booking->price) > 0.005
            || abs((float) ($provider['fee'] ?? -1) - (float) $booking->payment_provider_fee) > 0.005
            || (isset($snapshot['bookingOwner'])
                && (int) ($snapshot['bookingOwner']['userId'] ?? -1) !== (int) $booking->user_id)
        ) {
            return $result;
        }

        $items = $this->db->setQuery(
            'SELECT * FROM #__copymypage_ticket_cart_items WHERE cart_id = ' . $cartId . ' ORDER BY event_id, price_index'
                . $suffix
        )->loadObjectList();
        $expected = [];

        foreach ($items as $item) {
            if ((int) $item->quantity < 1 || (string) $item->currency !== (string) $booking->currency) {
                return $result;
            }

            $expected[(int) $item->event_id][(int) $item->price_index] = (int) $item->quantity;
        }

        $eventIds = array_map('intval', (array) ($snapshot['eventIds'] ?? []));
        sort($eventIds, SORT_NUMERIC);

        if ($expected === [] || array_keys($expected) !== $eventIds || \count($seats) !== \count($tickets)) {
            return $result;
        }

        $seatByTicket = [];

        foreach ($seats as $seat) {
            $ticketId = (int) ($seat->ticket_id ?? 0);

            if (
                $ticketId < 1 || isset($seatByTicket[$ticketId])
                || (int) $seat->cart_id !== $cartId
                || (int) $seat->status !== EventSeatInventoryService::SEAT_STATUS_BOOKED
                || (int) $seat->assignment_order < 1
                || (int) $seat->existing_seat_id !== (int) $seat->seat_id
            ) {
                return $result;
            }

            $seatByTicket[$ticketId] = $seat;
        }

        $allowedStates = match ((int) $booking->state) {
            1 => [1, 9],
            3 => [3],
            4 => [4, 9],
            10 => [10, 9],
            default => [],
        };
        $actual = [];

        foreach ($tickets as $ticket) {
            $eventId = (int) $ticket->event_id;
            $type = (int) $ticket->type;
            $ticketId = (int) $ticket->id;
            $seat = $seatByTicket[$ticketId] ?? null;
            $acceptedGroup = $snapshot['tickets'][$eventId][$type] ?? null;
            $acceptedSeat = $snapshot['seats'][$ticketId] ?? null;

            if (
                (int) $ticket->booking_id !== $bookingId
                || $ticket->user_id === null
                || (int) $ticket->user_id !== (int) $booking->user_id
                || (int) $ticket->existing_event_id !== $eventId
                || !\in_array((int) $ticket->state, $allowedStates, true)
                || !$seat || (int) $seat->event_id !== $eventId || (int) $seat->price_index !== $type
                || (isset($snapshot['tickets']) && (!\is_array($acceptedGroup)
                    || (int) ($acceptedGroup['quantity'] ?? -1) !== ($expected[$eventId][$type] ?? 0)
                    || abs((float) ($acceptedGroup['unitPrice'] ?? -1) - (float) $ticket->price) > 0.005))
                || (isset($snapshot['seats']) && (!\is_array($acceptedSeat)
                    || (int) ($acceptedSeat['id'] ?? 0) !== (int) $seat->id
                    || (int) ($acceptedSeat['seatId'] ?? 0) !== (int) $seat->seat_id
                    || (int) ($acceptedSeat['eventId'] ?? 0) !== $eventId
                    || (int) ($acceptedSeat['type'] ?? -1) !== $type
                    || (int) ($acceptedSeat['order'] ?? 0) !== (int) $seat->assignment_order))
            ) {
                return $result;
            }

            $actual[$eventId][$type] = ($actual[$eventId][$type] ?? 0) + 1;
        }

        ksort($actual, SORT_NUMERIC);

        foreach ($actual as &$groups) {
            ksort($groups, SORT_NUMERIC);
        }
        unset($groups);

        $acceptedCounts = [];
        foreach ((array) ($snapshot['tickets'] ?? []) as $eventId => $groups) {
            foreach ((array) $groups as $type => $group) {
                $acceptedCounts[(int) $eventId][(int) $type] = (int) ($group['quantity'] ?? -1);
            }
        }
        ksort($acceptedCounts, SORT_NUMERIC);
        foreach ($acceptedCounts as &$groups) {
            ksort($groups, SORT_NUMERIC);
        }
        unset($groups);

        $result['valid'] = $actual === $expected
            && (!isset($snapshot['tickets']) || $acceptedCounts === $expected)
            && (!isset($snapshot['seats']) || \count($snapshot['seats']) === \count($tickets));

        return $result;
    }
}
