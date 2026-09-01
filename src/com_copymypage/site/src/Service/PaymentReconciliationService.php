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

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Reconciles abandoned CopyMyPage payment bookings without guessing provider outcomes.
 *
 * @since  0.0.19
 */
final class PaymentReconciliationService
{
    private const BOOKING_STATE_CANCELLED = 6;

    private const BOOKING_STATE_COMPLETED = 1;

    private const BOOKING_STATE_PENDING = 3;

    private const BOOKING_STATE_PROCESSING = 4;

    /** @var list<int> */
    private const RELEASED_BOOKING_STATES = [-2, 6, 7];

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Claim a pending booking before invoking its external payment provider.
     *
     * The cart timestamp is the payment-attempt heartbeat used by reconciliation.
     * Terms acceptance remains immutable and the cart revision is not a UI revision.
     *
     * @since   0.0.19
     */
    public function beginPaymentAttempt(int $bookingId): bool
    {
        if ($bookingId < 1) {
            return false;
        }

        $this->db->transactionStart();

        try {
            $carts = $this->lockCartsForBooking($bookingId);

            if (
                \count($carts) !== 1
                || (int) ($carts[0]->status ?? -1) !== TicketCartContextService::STATUS_CONVERTED
            ) {
                $this->db->transactionCommit();

                return false;
            }

            $booking = $this->lockBooking($bookingId);

            if (
                $booking === null
                || (int) ($booking->state ?? -1) !== self::BOOKING_STATE_PENDING
                || trim((string) ($booking->transaction_id ?? '')) !== ''
                || (float) ($booking->price ?? 0.0) <= 0
                || !$this->providersMatch($carts[0], $booking)
            ) {
                $this->db->transactionCommit();

                return false;
            }

            $modified = $this->now();
            $cartId   = (int) $carts[0]->id;
            $query    = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__copymypage_ticket_carts'))
                ->set($this->db->quoteName('modified') . ' = :modified')
                ->where($this->db->quoteName('id') . ' = :cartId')
                ->where($this->db->quoteName('booking_id') . ' = :bookingId')
                ->where($this->db->quoteName('status') . ' = ' . TicketCartContextService::STATUS_CONVERTED)
                ->bind(':modified', $modified, ParameterType::STRING)
                ->bind(':cartId', $cartId, ParameterType::INTEGER)
                ->bind(':bookingId', $bookingId, ParameterType::INTEGER);
            $this->db->setQuery($query)->execute();

            if ($this->db->getAffectedRows() !== 1) {
                throw new \RuntimeException('The CopyMyPage payment attempt could not be claimed.');
            }

            $this->db->transactionCommit();

            return true;
        } catch (\Throwable $exception) {
            $this->db->transactionRollback();

            throw $exception;
        }
    }

    /**
     * Reconcile stale converted carts in bounded, independently atomic units.
     *
     * @return array{
     *     dryRun: bool,
     *     errors: int,
     *     errorIds: list<int>,
     *     manualReview: int,
     *     manualReviewIds: list<int>,
     *     released: int,
     *     releasedIds: list<int>,
     *     repaired: int,
     *     repairedIds: list<int>,
     *     scanned: int,
     *     skipped: int
     * }
     *
     * @since   0.0.19
     */
    public function reconcilePending(
        int $timeoutMinutes = 60,
        int $batchSize = 50,
        bool $dryRun = false
    ): array {
        $timeoutMinutes = min(1440, max(15, $timeoutMinutes));
        $batchSize      = min(500, max(1, $batchSize));
        $cutoff         = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->sub(new \DateInterval('PT' . $timeoutMinutes . 'M'))
            ->format('Y-m-d H:i:s');
        $report = [
            'dryRun'          => $dryRun,
            'errors'          => 0,
            'errorIds'        => [],
            'manualReview'    => 0,
            'manualReviewIds' => [],
            'released'        => 0,
            'releasedIds'     => [],
            'repaired'        => 0,
            'repairedIds'     => [],
            'scanned'         => 0,
            'skipped'         => 0,
        ];

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('c.id'),
                $this->db->quoteName('c.booking_id'),
            ])
            ->from($this->db->quoteName('#__copymypage_ticket_carts', 'c'))
            ->leftJoin(
                $this->db->quoteName('#__dpcalendar_bookings', 'b')
                    . ' ON ' . $this->db->quoteName('b.id') . ' = ' . $this->db->quoteName('c.booking_id')
            )
            ->where($this->db->quoteName('c.status') . ' = ' . TicketCartContextService::STATUS_CONVERTED)
            ->where($this->db->quoteName('c.booking_id') . ' IS NOT NULL')
            ->where($this->db->quoteName('c.modified') . ' <= :cutoff')
            ->where(
                '(' . $this->db->quoteName('b.id') . ' IS NULL OR '
                    . $this->db->quoteName('b.state') . ' <> ' . self::BOOKING_STATE_COMPLETED . ')'
            )
            ->order($this->db->quoteName('c.id') . ' ASC')
            ->bind(':cutoff', $cutoff, ParameterType::STRING);
        $candidates = (array) $this->db->setQuery($query, 0, $batchSize)->loadObjectList();

        foreach ($candidates as $candidate) {
            $cartId    = max(0, (int) ($candidate->id ?? 0));
            $bookingId = max(0, (int) ($candidate->booking_id ?? 0));
            $report['scanned']++;

            try {
                $outcome = $this->reconcileCandidate($cartId, $bookingId, $cutoff, $dryRun);
                $type    = (string) ($outcome['type'] ?? 'skipped');

                if ($type === 'released') {
                    $report['released']++;
                    $report['releasedIds'][] = $bookingId;
                } elseif ($type === 'repaired') {
                    $report['repaired']++;
                    $report['repairedIds'][] = $bookingId;
                } elseif ($type === 'manual') {
                    $report['manualReview']++;
                    $report['manualReviewIds'][] = $bookingId;
                } else {
                    $report['skipped']++;
                }
            } catch (\Throwable) {
                $report['errors']++;
                $report['errorIds'][] = $bookingId;
            }
        }

        foreach (['errorIds', 'manualReviewIds', 'releasedIds', 'repairedIds'] as $key) {
            $report[$key] = array_values(array_unique(array_map('intval', $report[$key])));
        }

        return $report;
    }

    /**
     * Decide whether a DPCalendar payment callback may reach the provider.
     *
     * Unmanaged and unauthorised requests deliberately remain with DPCalendar so
     * its own access checks do not leak booking existence through this guard.
     *
     * @return array{
     *     authorized: bool,
     *     block: bool,
     *     bookingToken: string,
     *     bookingUid: string,
     *     managed: bool,
     *     state: int
     * }
     *
     * @since   0.0.19
     */
    public function getCallbackDecision(
        int $bookingId,
        string $requestToken,
        int $sessionBookingId,
        int $userId,
        bool $privileged = false
    ): array {
        $decision = [
            'authorized'   => false,
            'block'        => false,
            'bookingToken' => '',
            'bookingUid'   => '',
            'managed'      => false,
            'state'        => -1,
        ];

        if ($bookingId < 1) {
            return $decision;
        }

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('status'),
                $this->db->quoteName('payment_provider'),
            ])
            ->from($this->db->quoteName('#__copymypage_ticket_carts'))
            ->where($this->db->quoteName('booking_id') . ' = ' . $bookingId)
            ->order($this->db->quoteName('id') . ' ASC');
        $carts = (array) $this->db->setQuery($query)->loadObjectList();

        if ($carts === []) {
            return $decision;
        }

        $decision['managed'] = true;
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('user_id'),
                $this->db->quoteName('uid'),
                $this->db->quoteName('state'),
                $this->db->quoteName('transaction_id'),
                $this->db->quoteName('payment_provider'),
                $this->db->quoteName('token'),
            ])
            ->from($this->db->quoteName('#__dpcalendar_bookings'))
            ->where($this->db->quoteName('id') . ' = ' . $bookingId);
        $booking = $this->db->setQuery($query)->loadObject();

        if (!\is_object($booking)) {
            return $decision;
        }

        $bookingToken             = trim((string) ($booking->token ?? ''));
        $requestToken             = trim($requestToken);
        $decision['bookingToken'] = $bookingToken;
        $decision['bookingUid']   = trim((string) ($booking->uid ?? ''));
        $decision['state']        = (int) ($booking->state ?? -1);
        $tokenMatches = $bookingToken !== ''
            && $requestToken !== ''
            && hash_equals($bookingToken, $requestToken);
        $isOwner = $userId > 0 && $userId === (int) ($booking->user_id ?? 0);
        $isGuestSession = $userId === 0 && $sessionBookingId === $bookingId;
        $isEventAuthor = $userId > 0 && $this->isEventAuthor($bookingId, $userId);
        $decision['authorized'] = $tokenMatches
            || $isOwner
            || $isGuestSession
            || $isEventAuthor
            || $privileged;

        if (!$decision['authorized']) {
            return $decision;
        }

        $cartIntegrity = \count($carts) === 1
            && (int) ($carts[0]->status ?? -1) === TicketCartContextService::STATUS_CONVERTED
            && $this->providersMatch($carts[0], $booking);
        $callbackState = \in_array(
            (int) ($booking->state ?? -1),
            [self::BOOKING_STATE_PENDING, self::BOOKING_STATE_PROCESSING],
            true
        );
        $transactionStarted = trim((string) ($booking->transaction_id ?? '')) !== '';
        $decision['block'] = !$cartIntegrity || !$callbackState || !$transactionStarted;

        return $decision;
    }

    /**
     * @return array{type: string}
     */
    private function reconcileCandidate(
        int $cartId,
        int $bookingId,
        string $cutoff,
        bool $dryRun
    ): array {
        if ($cartId < 1 || $bookingId < 1) {
            return ['type' => 'manual'];
        }

        $this->db->transactionStart();

        try {
            // Payment start and reconciliation always lock cart(s) before booking.
            $carts = $this->lockCartsForBooking($bookingId);

            if (\count($carts) !== 1) {
                $this->db->transactionCommit();

                return ['type' => 'manual'];
            }

            $cart = $carts[0];

            if (
                (int) ($cart->id ?? 0) !== $cartId
                || (int) ($cart->status ?? -1) !== TicketCartContextService::STATUS_CONVERTED
                || (string) ($cart->modified ?? '') > $cutoff
            ) {
                $this->db->transactionCommit();

                return ['type' => 'skipped'];
            }

            $booking = $this->lockBooking($bookingId);

            if ($booking === null) {
                $this->db->transactionCommit();

                return ['type' => 'manual'];
            }

            $state         = (int) ($booking->state ?? -1);
            $transactionId = trim((string) ($booking->transaction_id ?? ''));

            if ($state === self::BOOKING_STATE_COMPLETED) {
                $this->db->transactionCommit();

                return ['type' => 'skipped'];
            }

            $isAbandoned = $state === self::BOOKING_STATE_PENDING;
            $isRepair    = \in_array($state, self::RELEASED_BOOKING_STATES, true);

            if (
                (!$isAbandoned && !$isRepair)
                || ($isAbandoned && $transactionId !== '')
                || ($isAbandoned && ((float) ($booking->price ?? 0.0) <= 0 || !$this->providersMatch($cart, $booking)))
            ) {
                $this->db->transactionCommit();

                return ['type' => 'manual'];
            }

            $tickets = $this->lockTickets($bookingId);
            $seats   = $this->lockSeats($cartId);

            if (!$this->hasExactSeatIntegrity($tickets, $seats)) {
                $this->db->transactionCommit();

                return ['type' => 'manual'];
            }

            if (!$dryRun) {
                $terminalState = $isAbandoned ? self::BOOKING_STATE_CANCELLED : $state;
                $this->releaseBooking($bookingId, $cartId, $terminalState, $isAbandoned, \count($seats));
            }

            $this->db->transactionCommit();

            return ['type' => $isAbandoned ? 'released' : 'repaired'];
        } catch (\Throwable $exception) {
            $this->db->transactionRollback();

            throw $exception;
        }
    }

    /**
     * @return list<object>
     */
    private function lockCartsForBooking(int $bookingId): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('status'),
                $this->db->quoteName('booking_id'),
                $this->db->quoteName('payment_provider'),
                $this->db->quoteName('modified'),
            ])
            ->from($this->db->quoteName('#__copymypage_ticket_carts'))
            ->where($this->db->quoteName('booking_id') . ' = ' . $bookingId)
            ->order($this->db->quoteName('id') . ' ASC');

        return (array) $this->db->setQuery((string) $query . ' FOR UPDATE')->loadObjectList();
    }

    private function lockBooking(int $bookingId): ?object
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('state'),
                $this->db->quoteName('transaction_id'),
                $this->db->quoteName('payment_provider'),
                $this->db->quoteName('price'),
            ])
            ->from($this->db->quoteName('#__dpcalendar_bookings'))
            ->where($this->db->quoteName('id') . ' = ' . $bookingId);
        $booking = $this->db->setQuery((string) $query . ' FOR UPDATE')->loadObject();

        return \is_object($booking) ? $booking : null;
    }

    /**
     * @return list<object>
     */
    private function lockTickets(int $bookingId): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('event_id'),
                $this->db->quoteName('state'),
            ])
            ->from($this->db->quoteName('#__dpcalendar_tickets'))
            ->where($this->db->quoteName('booking_id') . ' = ' . $bookingId)
            ->order($this->db->quoteName('id') . ' ASC');

        return (array) $this->db->setQuery((string) $query . ' FOR UPDATE')->loadObjectList();
    }

    /**
     * @return list<object>
     */
    private function lockSeats(int $cartId): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('event_id'),
                $this->db->quoteName('status'),
                $this->db->quoteName('ticket_id'),
            ])
            ->from($this->db->quoteName('#__copymypage_event_seats'))
            ->where($this->db->quoteName('cart_id') . ' = ' . $cartId)
            ->order($this->db->quoteName('id') . ' ASC');

        return (array) $this->db->setQuery((string) $query . ' FOR UPDATE')->loadObjectList();
    }

    /**
     * @param   list<object>  $tickets
     * @param   list<object>  $seats
     */
    private function hasExactSeatIntegrity(array $tickets, array $seats): bool
    {
        if ($tickets === [] || \count($tickets) !== \count($seats)) {
            return false;
        }

        $ticketEvents = [];

        foreach ($tickets as $ticket) {
            $ticketId = max(0, (int) ($ticket->id ?? 0));
            $eventId  = max(0, (int) ($ticket->event_id ?? 0));

            if ($ticketId < 1 || $eventId < 1 || isset($ticketEvents[$ticketId])) {
                return false;
            }

            $ticketEvents[$ticketId] = $eventId;
        }

        $seenTickets = [];

        foreach ($seats as $seat) {
            $ticketId = max(0, (int) ($seat->ticket_id ?? 0));

            if (
                (int) ($seat->status ?? -1) !== EventSeatInventoryService::SEAT_STATUS_BOOKED
                || !isset($ticketEvents[$ticketId])
                || $ticketEvents[$ticketId] !== (int) ($seat->event_id ?? 0)
                || isset($seenTickets[$ticketId])
            ) {
                return false;
            }

            $seenTickets[$ticketId] = true;
        }

        ksort($ticketEvents, SORT_NUMERIC);
        ksort($seenTickets, SORT_NUMERIC);

        return array_keys($ticketEvents) === array_keys($seenTickets);
    }

    private function releaseBooking(
        int $bookingId,
        int $cartId,
        int $terminalState,
        bool $updateBooking,
        int $seatCount
    ): void {
        if ($updateBooking) {
            $query = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__dpcalendar_bookings'))
                ->set($this->db->quoteName('state') . ' = :terminalState')
                ->where($this->db->quoteName('id') . ' = :bookingId')
                ->where($this->db->quoteName('state') . ' = ' . self::BOOKING_STATE_PENDING)
                ->where(
                    '(' . $this->db->quoteName('transaction_id') . ' IS NULL OR TRIM('
                        . $this->db->quoteName('transaction_id') . ") = '')"
                )
                ->bind(':terminalState', $terminalState, ParameterType::INTEGER)
                ->bind(':bookingId', $bookingId, ParameterType::INTEGER);
            $this->db->setQuery($query)->execute();

            if ($this->db->getAffectedRows() !== 1) {
                throw new \RuntimeException('The abandoned DPCalendar booking changed during reconciliation.');
            }
        }

        // State 3 never contributes to DPCalendar's booked capacity, so no capacity counter is adjusted here.
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__dpcalendar_tickets'))
            ->set($this->db->quoteName('state') . ' = :terminalState')
            ->where($this->db->quoteName('booking_id') . ' = :bookingId')
            ->bind(':terminalState', $terminalState, ParameterType::INTEGER)
            ->bind(':bookingId', $bookingId, ParameterType::INTEGER);
        $this->db->setQuery($query)->execute();

        $modified = $this->now();
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__copymypage_event_seats'))
            ->set($this->db->quoteName('status') . ' = ' . EventSeatInventoryService::SEAT_STATUS_AVAILABLE)
            ->set($this->db->quoteName('cart_id') . ' = NULL')
            ->set($this->db->quoteName('price_index') . ' = NULL')
            ->set($this->db->quoteName('assignment_order') . ' = NULL')
            ->set($this->db->quoteName('ticket_id') . ' = NULL')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->set($this->db->quoteName('modified_by') . ' = 0')
            ->where($this->db->quoteName('cart_id') . ' = :cartId')
            ->where($this->db->quoteName('status') . ' = ' . EventSeatInventoryService::SEAT_STATUS_BOOKED)
            ->bind(':modified', $modified, ParameterType::STRING)
            ->bind(':cartId', $cartId, ParameterType::INTEGER);
        $this->db->setQuery($query)->execute();

        if ($this->db->getAffectedRows() !== $seatCount) {
            throw new \RuntimeException('The CopyMyPage seat assignment changed during reconciliation.');
        }

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__copymypage_ticket_carts'))
            ->set($this->db->quoteName('status') . ' = ' . TicketCartContextService::STATUS_RELEASED)
            ->set($this->db->quoteName('revision') . ' = ' . $this->db->quoteName('revision') . ' + 1')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->where($this->db->quoteName('id') . ' = :cartId')
            ->where($this->db->quoteName('booking_id') . ' = :bookingId')
            ->where($this->db->quoteName('status') . ' = ' . TicketCartContextService::STATUS_CONVERTED)
            ->bind(':modified', $modified, ParameterType::STRING)
            ->bind(':cartId', $cartId, ParameterType::INTEGER)
            ->bind(':bookingId', $bookingId, ParameterType::INTEGER);
        $this->db->setQuery($query)->execute();

        if ($this->db->getAffectedRows() !== 1) {
            throw new \RuntimeException('The CopyMyPage cart changed during reconciliation.');
        }
    }

    private function providersMatch(object $cart, object $booking): bool
    {
        $cartProvider    = trim((string) ($cart->payment_provider ?? ''));
        $bookingProvider = trim((string) ($booking->payment_provider ?? ''));

        return $cartProvider !== ''
            && $bookingProvider !== ''
            && hash_equals($cartProvider, $bookingProvider);
    }

    private function isEventAuthor(int $bookingId, int $userId): bool
    {
        $query = $this->db->getQuery(true)
            ->select('1')
            ->from($this->db->quoteName('#__dpcalendar_tickets', 't'))
            ->innerJoin(
                $this->db->quoteName('#__dpcalendar_events', 'e')
                    . ' ON ' . $this->db->quoteName('e.id') . ' = ' . $this->db->quoteName('t.event_id')
            )
            ->where($this->db->quoteName('t.booking_id') . ' = :bookingId')
            ->where($this->db->quoteName('e.created_by') . ' = :userId')
            ->bind(':bookingId', $bookingId, ParameterType::INTEGER)
            ->bind(':userId', $userId, ParameterType::INTEGER);

        return (bool) $this->db->setQuery($query, 0, 1)->loadResult();
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
