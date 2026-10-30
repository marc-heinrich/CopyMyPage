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

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly ?NativeReservationService $nativeReservations = null
    )
    {
    }

    /**
     * Claim the native booking token before invoking its payment provider.
     *
     * The cart timestamp is the payment-attempt heartbeat used by reconciliation.
     * Terms acceptance remains immutable and the cart revision is not a UI revision.
     * The booking lock and persisted token exclude another managed payment start.
     * The token without a provider reference preserves an unresolved start for
     * manual review. This does not supply provider or callback idempotency.
     *
     * @since   0.0.19
     */
    public function beginPaymentAttempt(int $bookingId, ?string &$bookingToken = null): bool
    {
        $bookingToken = null;

        if ($bookingId < 1) {
            return false;
        }

        if ($this->nativeReservations?->protectsBooking($bookingId)) {
            $bookingToken = $this->nativeReservations->beginPayment($bookingId);
            return $bookingToken !== null;
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
                || trim((string) ($booking->token ?? '')) !== ''
                || (float) ($booking->price ?? 0.0) <= 0
                || !$this->providersMatch($carts[0], $booking)
            ) {
                $this->db->transactionCommit();

                return false;
            }

            $modified = $this->now();
            $cartId   = (int) $carts[0]->id;
            $token    = bin2hex(random_bytes(32));
            $query = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__dpcalendar_bookings'))
                ->set($this->db->quoteName('token') . ' = :token')
                ->where($this->db->quoteName('id') . ' = :bookingId')
                ->where($this->db->quoteName('state') . ' = ' . self::BOOKING_STATE_PENDING)
                ->where('(' . $this->db->quoteName('token') . ' IS NULL OR '
                    . $this->db->quoteName('token') . " = '')")
                ->bind(':token', $token, ParameterType::STRING)
                ->bind(':bookingId', $bookingId, ParameterType::INTEGER);
            $this->db->setQuery($query)->execute();

            if ($this->db->getAffectedRows() !== 1) {
                throw new \RuntimeException('The CopyMyPage payment start could not be claimed.');
            }

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

            // The cart is already locked and validated. Starting immediately after
            // checkout can write the same second-precision timestamp, changing no row.
            if (!\in_array($this->db->getAffectedRows(), [0, 1], true)) {
                throw new \RuntimeException('The CopyMyPage payment attempt could not be claimed.');
            }

            $this->db->transactionCommit();
            $bookingToken = $token;

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
                $this->db->quoteName('c.modified'),
                $this->db->quoteName('c.revision'),
            ])
            ->from($this->db->quoteName('#__copymypage_ticket_carts', 'c'))
            ->leftJoin(
                $this->db->quoteName('#__dpcalendar_bookings', 'b')
                    . ' ON ' . $this->db->quoteName('b.id') . ' = ' . $this->db->quoteName('c.booking_id')
            )
            ->where($this->db->quoteName('c.status') . ' = ' . TicketCartContextService::STATUS_CONVERTED)
            ->where($this->db->quoteName('c.booking_id') . ' IS NOT NULL')
            ->where($this->db->quoteName('c.modified') . ' <= :cutoff')
            // Native PayPal, including unresolved historical instance IDs, has a separate policy.
            ->where("COALESCE(c.payment_provider, '') NOT LIKE 'paypal-%'")
            ->where("COALESCE(b.payment_provider, '') NOT LIKE 'paypal-%'")
            // Every financial attempt belongs to ledger reconciliation, never legacy TTL release.
            ->where('NOT EXISTS (SELECT 1 FROM #__copymypage_payment_attempts AS p'
                . ' WHERE p.booking_id = c.booking_id)')
            ->where(
                '(' . $this->db->quoteName('b.id') . ' IS NULL OR '
                    . $this->db->quoteName('b.state') . ' <> ' . self::BOOKING_STATE_COMPLETED . ')'
            )
            ->order($this->db->quoteName('c.modified') . ' ASC')
            ->order($this->db->quoteName('c.id') . ' ASC')
            ->bind(':cutoff', $cutoff, ParameterType::STRING);
        $candidates = (array) $this->db->setQuery($query, 0, $batchSize)->loadObjectList();

        foreach ($candidates as $candidate) {
            $cartId    = max(0, (int) ($candidate->id ?? 0));
            $bookingId = max(0, (int) ($candidate->booking_id ?? 0));
            $deferred  = false;
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
                    $deferred = true;
                    $report['manualReview']++;
                    $report['manualReviewIds'][] = $bookingId;
                } else {
                    $report['skipped']++;
                }
            } catch (\Throwable) {
                $deferred = true;
                $report['errors']++;
                $report['errorIds'][] = $bookingId;
            }

            if ($deferred && !$dryRun) {
                try {
                    $this->deferCandidate($candidate);
                } catch (\Throwable) {
                    $report['errors']++;
                    $report['errorIds'][] = $bookingId;
                }
            }
        }

        foreach (['errorIds', 'manualReviewIds', 'releasedIds', 'repairedIds'] as $key) {
            $report[$key] = array_values(array_unique(array_map('intval', $report[$key])));
        }

        return $report;
    }

    /**
     * Audit a deferred legacy observation without changing payment evidence or deadlines.
     * Oldest observation first plus this bounded cooldown gives later carts their turn,
     * including when several manual/error cases precede them and the batch is one.
     * A payment start or any concurrent cart change must win over this audit CAS.
     */
    private function deferCandidate(object $candidate): void
    {
        $modified = $this->now();
        $cartId = (int) $candidate->id;
        $bookingId = (int) $candidate->booking_id;
        $revision = (int) $candidate->revision;
        $observed = (string) $candidate->modified;
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__copymypage_ticket_carts'))
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->where($this->db->quoteName('id') . ' = :cartId')
            ->where($this->db->quoteName('booking_id') . ' = :bookingId')
            ->where($this->db->quoteName('revision') . ' = :revision')
            ->where($this->db->quoteName('modified') . ' = :observed')
            ->where($this->db->quoteName('status') . ' = ' . TicketCartContextService::STATUS_CONVERTED)
            ->where('NOT EXISTS (SELECT 1 FROM #__copymypage_payment_attempts AS p'
                . ' WHERE p.booking_id = :ledgerBookingId)')
            ->bind(':modified', $modified, ParameterType::STRING)
            ->bind(':cartId', $cartId, ParameterType::INTEGER)
            ->bind(':bookingId', $bookingId, ParameterType::INTEGER)
            ->bind(':ledgerBookingId', $bookingId, ParameterType::INTEGER)
            ->bind(':revision', $revision, ParameterType::INTEGER)
            ->bind(':observed', $observed, ParameterType::STRING);
        $this->db->setQuery($query)->execute();
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

        // Native pay/abort can fall back to a token for a different booking.
        // A managed capability must not bypass this guard through another ID.
        $requestToken = trim($requestToken);
        if ($requestToken !== '') {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName(['b.id', 'b.token']))
                ->from($this->db->quoteName('#__dpcalendar_bookings', 'b'))
                ->innerJoin(
                    $this->db->quoteName('#__copymypage_ticket_carts', 'c')
                        . ' ON ' . $this->db->quoteName('c.booking_id') . ' = ' . $this->db->quoteName('b.id')
                )
                ->where($this->db->quoteName('b.token') . ' = :requestToken')
                ->bind(':requestToken', $requestToken, ParameterType::STRING);
            $tokenBooking = $this->db->setQuery($query, 0, 1)->loadObject();

            if ($tokenBooking && ((int) $tokenBooking->id !== $bookingId
                || !hash_equals((string) $tokenBooking->token, $requestToken))) {
                throw new \DomainException('Invalid managed payment callback booking binding.', 403);
            }
        }

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
        if (PaymentProviderConfig::isOwned((string) $booking->payment_provider) && $bookingToken !== '') {
            $query = $this->db->getQuery(true)->select('order_id')->from('#__copymypage_payment_attempts')
                ->where('booking_id = :booking')->where('provider_id = :provider')
                ->bind(':booking', $bookingId, ParameterType::INTEGER)->bind(':provider', $booking->payment_provider);
            // A committed owned order remains reconcilable if saving its native reference was interrupted.
            $transactionStarted = $transactionStarted || (string) $this->db->setQuery($query)->loadResult() !== '';
        }
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

            if (NativePayPalProviderRegistry::isReference((string) ($cart->payment_provider ?? ''))
                || NativePayPalProviderRegistry::isReference((string) ($booking->payment_provider ?? ''))) {
                $this->db->transactionCommit();
                return ['type' => 'manual'];
            }

            if ($booking === null) {
                $this->db->transactionCommit();

                return ['type' => 'manual'];
            }

            $state         = (int) ($booking->state ?? -1);
            $transactionId = trim((string) ($booking->transaction_id ?? ''));

            if ((int) $this->db->setQuery('SELECT COUNT(*) FROM #__copymypage_payment_attempts WHERE booking_id = ' . $bookingId)->loadResult() > 0) {
                // The financial ledger, including unconfirmed captures, takes precedence over the old TTL cleanup.
                $this->db->transactionCommit();
                return ['type' => 'manual'];
            }

            if ($state === self::BOOKING_STATE_COMPLETED) {
                $this->db->transactionCommit();

                return ['type' => 'skipped'];
            }

            $isAbandoned = $state === self::BOOKING_STATE_PENDING;
            $isRepair    = \in_array($state, self::RELEASED_BOOKING_STATES, true);

            if (
                (!$isAbandoned && !$isRepair)
                || ($isAbandoned && ($transactionId !== '' || trim((string) ($booking->token ?? '')) !== ''))
                || ($isAbandoned && ((float) ($booking->price ?? 0.0) <= 0 || !$this->providersMatch($cart, $booking)))
            ) {
                $this->db->transactionCommit();

                return ['type' => 'manual'];
            }

            $tickets = $this->lockTickets($bookingId);
            $seats   = $this->lockSeats($cartId);

            if (!$this->hasExactSeatIntegrity($tickets, $seats, $state)) {
                $this->db->transactionCommit();

                return ['type' => 'manual'];
            }

            if (!$dryRun) {
                $terminalState = $isAbandoned ? self::BOOKING_STATE_CANCELLED : $state;
                $this->releaseBooking($bookingId, $cartId, $terminalState, $isAbandoned, $tickets, $seats);
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
                $this->db->quoteName('token'),
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
                $this->db->quoteName('allocation_type'),
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
    private function hasExactSeatIntegrity(array $tickets, array $seats, int $bookingState): bool
    {
        // Pending 3 -> cancelled 6: only pending tickets. Terminal repairs may
        // finish pending tickets or retain that SAME terminal state (-2/6/7).
        // Active 1, checked-in 9, other terminal states and unknowns always hold.
        $allowedStates = $bookingState === self::BOOKING_STATE_PENDING
            ? [self::BOOKING_STATE_PENDING]
            : (\in_array($bookingState, self::RELEASED_BOOKING_STATES, true)
                ? [self::BOOKING_STATE_PENDING, $bookingState] : []);
        if ($tickets === [] || \count($tickets) !== \count($seats)) {
            return false;
        }

        $ticketEvents = [];

        foreach ($tickets as $ticket) {
            $ticketId = max(0, (int) ($ticket->id ?? 0));
            $eventId  = max(0, (int) ($ticket->event_id ?? 0));

            if ($ticketId < 1 || $eventId < 1 || isset($ticketEvents[$ticketId])
                || $ticket->state === null || !\in_array((int) $ticket->state, $allowedStates, true)) {
                return false;
            }

            $ticketEvents[$ticketId] = $eventId;
        }

        $seenTickets = [];

        foreach ($seats as $seat) {
            $ticketId = max(0, (int) ($seat->ticket_id ?? 0));

            if (
                (int) ($seat->status ?? -1) !== EventSeatInventoryService::SEAT_STATUS_BOOKED
                || (int) ($seat->allocation_type ?? -1) !== EventSeatInventoryService::SEAT_ALLOCATION_STANDARD
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

    /** Caller owns the transaction and must hold cart, booking, ticket and seat locks. */
    public function releaseUnstartedNativeWithinTransaction(array $integrity): void
    {
        $cart = $integrity['cart'];
        $booking = $integrity['booking'];
        if (!$integrity['valid'] || !$cart || !$booking
            || (int) ($cart->native_payment_phase ?? 0) !== NativeReservationService::NOT_STARTED
            || !NativePayPalProviderRegistry::isReference((string) $booking->payment_provider)
            || !$this->providersMatch($cart, $booking) || (int) $booking->state !== 3) {
            throw new \DomainException('Native reservation is not safely unstarted.');
        }
        $this->releaseBooking((int) $booking->id, (int) $cart->id, self::BOOKING_STATE_CANCELLED,
            true, $integrity['tickets'], $integrity['seats']);
        $this->db->setQuery('UPDATE #__copymypage_ticket_carts SET native_payment_phase = '
            . NativeReservationService::RELEASED_BEFORE_START . ' WHERE id = ' . (int) $cart->id
            . ' AND status = 3 AND native_payment_phase = ' . NativeReservationService::NOT_STARTED)->execute();
        if ($this->db->getAffectedRows() !== 1) {
            throw new \RuntimeException('Native reservation release evidence changed.');
        }
    }

    /** Caller holds the callback named lock, all row locks and a fresh verified provider proof. */
    public function releaseExcludedNativeWithinTransaction(array $integrity, array $proof): void
    {
        $cart = $integrity['cart'];
        $booking = $integrity['booking'];
        if (!$integrity['valid'] || !$cart || !$booking || (int) $booking->state !== 3
            || !\in_array((int) $cart->native_payment_phase,
                [NativeReservationService::STARTED_OR_UNKNOWN, NativeReservationService::CALLBACK_STARTED_OR_UNKNOWN], true)
            || !NativePayPalProviderRegistry::isReference((string) $booking->payment_provider)
            || !$this->providersMatch($cart, $booking) || trim((string) $booking->transaction_id) === ''
            || !$this->nativeReservations?->hasAdministrativeLock((int) $booking->id)
            || ($proof['status'] ?? '') !== 'VOIDED' || ($proof['environment'] ?? '') !== 'sandbox'
            || ($proof['provider'] ?? '') !== (string) $booking->payment_provider
            || ($proof['orderHash'] ?? '') !== hash('sha256', (string) $booking->transaction_id)) {
            throw new \DomainException('Native reservation exclusion context is invalid.');
        }
        $this->releaseBooking((int) $booking->id, (int) $cart->id, self::BOOKING_STATE_CANCELLED,
            true, $integrity['tickets'], $integrity['seats'], (string) $booking->transaction_id);
        $this->db->setQuery('UPDATE #__copymypage_ticket_carts SET native_payment_phase = '
            . NativeReservationService::RELEASED_AFTER_EXCLUSION . ' WHERE id = ' . (int) $cart->id
            . ' AND status = 3 AND native_payment_phase = ' . (int) $cart->native_payment_phase)->execute();
        if ($this->db->getAffectedRows() !== 1) {
            throw new \RuntimeException('Native reservation exclusion changed.');
        }
    }

    private function releaseBooking(
        int $bookingId,
        int $cartId,
        int $terminalState,
        bool $updateBooking,
        array $tickets,
        array $seats,
        ?string $excludedOrderId = null
    ): void {
        $sourceState = $updateBooking ? self::BOOKING_STATE_PENDING : $terminalState;
        if (!\in_array($terminalState, self::RELEASED_BOOKING_STATES, true)
            || ($updateBooking && $terminalState !== self::BOOKING_STATE_CANCELLED)
            || !$this->hasExactSeatIntegrity($tickets, $seats, $sourceState)) {
            throw new \RuntimeException('The legacy ticket release is not permitted.');
        }
        $ticketIds = array_map(static fn(object $ticket): int => (int) $ticket->id, $tickets);
        $seatIds = array_map(static fn(object $seat): int => (int) $seat->id, $seats);
        $pendingCount = \count(array_filter($tickets,
            static fn(object $ticket): bool => (int) $ticket->state === self::BOOKING_STATE_PENDING));

        if ($updateBooking) {
            $query = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__dpcalendar_bookings'))
                ->set($this->db->quoteName('state') . ' = :terminalState')
                ->where($this->db->quoteName('id') . ' = :bookingId')
                ->where($this->db->quoteName('state') . ' = ' . self::BOOKING_STATE_PENDING)
                ->bind(':terminalState', $terminalState, ParameterType::INTEGER)
                ->bind(':bookingId', $bookingId, ParameterType::INTEGER);
            if ($excludedOrderId !== null) {
                $query->where($this->db->quoteName('transaction_id') . ' = :excludedOrderId')
                    ->bind(':excludedOrderId', $excludedOrderId, ParameterType::STRING);
            } else {
                $query->where(
                    '(' . $this->db->quoteName('transaction_id') . ' IS NULL OR TRIM('
                        . $this->db->quoteName('transaction_id') . ") = '')"
                )
                ->where('(' . $this->db->quoteName('token') . ' IS NULL OR TRIM('
                    . $this->db->quoteName('token') . ") = '')");
            }
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
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', $ticketIds) . ')')
            ->where($this->db->quoteName('state') . ' = ' . self::BOOKING_STATE_PENDING)
            ->bind(':terminalState', $terminalState, ParameterType::INTEGER)
            ->bind(':bookingId', $bookingId, ParameterType::INTEGER);
        $this->db->setQuery($query)->execute();

        if ($this->db->getAffectedRows() !== $pendingCount) {
            throw new \RuntimeException('The legacy ticket states changed during reconciliation.');
        }
        $actualTickets = $this->lockTickets($bookingId);
        if (array_map(static fn(object $ticket): int => (int) $ticket->id, $actualTickets) !== $ticketIds
            || !$this->hasExactSeatIntegrity($actualTickets, $seats, $terminalState)
            || \count(array_filter($actualTickets,
                static fn(object $ticket): bool => (int) $ticket->state === $terminalState)) !== \count($tickets)) {
            throw new \RuntimeException('The legacy ticket release postcondition failed.');
        }

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
            ->where($this->db->quoteName('allocation_type') . ' = ' . EventSeatInventoryService::SEAT_ALLOCATION_STANDARD)
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', $seatIds) . ')')
            ->where($this->db->quoteName('ticket_id') . ' IN (' . implode(',', $ticketIds) . ')')
            ->bind(':modified', $modified, ParameterType::STRING)
            ->bind(':cartId', $cartId, ParameterType::INTEGER);
        $this->db->setQuery($query)->execute();

        if ($this->db->getAffectedRows() !== \count($seats)) {
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

        $released = $this->db->getQuery(true)->select('COUNT(*)')->from('#__copymypage_event_seats')
            ->where('id IN (' . implode(',', $seatIds) . ')')->where('status = 0')->where('allocation_type = 0')
            ->where('cart_id IS NULL')->where('ticket_id IS NULL')
            ->where('price_index IS NULL')->where('assignment_order IS NULL');
        $actualCarts = $this->lockCartsForBooking($bookingId);
        if ((int) $this->db->setQuery($released)->loadResult() !== \count($seats)
            || $this->lockSeats($cartId) !== []
            || \count($actualCarts) !== 1 || (int) $actualCarts[0]->id !== $cartId
            || (int) $actualCarts[0]->status !== TicketCartContextService::STATUS_RELEASED
            || (int) ($this->lockBooking($bookingId)->state ?? -1) !== $terminalState) {
            throw new \RuntimeException('The legacy booking release postcondition failed.');
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
