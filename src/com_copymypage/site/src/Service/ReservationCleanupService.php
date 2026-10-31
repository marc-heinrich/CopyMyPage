<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Log\Log;
use Joomla\CMS\User\User;

/** Cart housekeeping and atomic accompaniment of DPCalendar's own ticket deletions. */
final class ReservationCleanupService
{
    private const RELEASE_LOG = 'CopyMyPage reservation {booking_id}: payment excluded, seats released.';
    private const NATIVE_DELETE_LOG = 'DPCalendar deleted booking tickets {booking_id}; CopyMyPage released their seats.';
    private array $nativeDeletions = [];
    private ?User $nativeTaskUser = null;
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly BookingIntegrityService $integrity,
        private readonly NativePayPalProviderRegistry $providers,
        private readonly NativeReservationService $nativeReservations,
        private readonly PaymentReconciliationService $reconciliation
    ) {
    }

    public function run(int $minutes = 60, int $batchSize = 50, bool $dryRun = true, int $maxSeconds = 20): array
    {
        $report = ['scanned' => 0, 'released' => 0, 'wouldRelease' => 0, 'held' => 0,
            'errors' => 0, 'busy' => false, 'dryRun' => $dryRun, 'decisions' => []];
        $batchSize = max(1, min(500, $batchSize));
        $maxSeconds = max(5, min(60, $maxSeconds));
        $minutes = max(15, min(1440, $minutes));
        $deadline = microtime(true) + $maxSeconds;
        if (!$this->nativeReservations->hasSchema()) {
            throw new \RuntimeException('Native reservation schema is unavailable.');
        }
        $database = (string) $this->db->setQuery('SELECT DATABASE()')->loadResult();
        $mutex = $this->db->quote('cmp.reservations.' . substr(hash('sha256', $database . $this->db->getPrefix()), 0, 32));
        if ((int) $this->db->setQuery('SELECT GET_LOCK(' . $mutex . ', 0)')->loadResult() !== 1) {
            $report['busy'] = true;
            return $report;
        }
        $oldWait = null;
        try {
            $oldWait = (int) $this->db->setQuery('SELECT @@SESSION.innodb_lock_wait_timeout')->loadResult();
            $this->db->setQuery('SET SESSION innodb_lock_wait_timeout = 2')->execute();
            $now = (string) $this->db->setQuery('SELECT UTC_TIMESTAMP()')->loadResult();
            $query = 'SELECT c.id FROM #__copymypage_ticket_carts c'
                . ' WHERE (c.status IN (0, 2, 3) AND c.booking_id IS NULL AND c.expires_at <= ' . $this->db->quote($now)
                . ' AND (c.status = 0 OR EXISTS (SELECT 1 FROM #__copymypage_event_seats s WHERE s.cart_id = c.id AND s.status = 1)))'
                . ' ORDER BY c.id';
            $ids = $this->db->setQuery($query, 0, $batchSize)->loadColumn();
            foreach ($ids as $id) {
                if (microtime(true) >= $deadline) {
                    break;
                }
                ++$report['scanned'];
                try {
                    $reason = $this->process((int) $id, $now, $dryRun);
                    if ($reason === 'expired_cart') {
                        ++$report[$dryRun ? 'wouldRelease' : 'released'];
                    } else {
                        ++$report['held'];
                    }
                    $report['decisions'][] = ['cartId' => (int) $id, 'reason' => $reason];
                } catch (ReservationCleanupReviewException $exception) {
                    ++$report['held'];
                    $report['decisions'][] = ['cartId' => (int) $id, 'reason' => $exception->reason];
                    $this->logCleanupFailure((int) $id, $exception);
                } catch (\Throwable $exception) {
                    ++$report['errors'];
                    $category = $this->logCleanupFailure((int) $id, $exception);
                    $report['decisions'][] = ['cartId' => (int) $id,
                        'reason' => $category === 'locking_conflict' ? 'locking_conflict' : 'transaction_failed'];
                }
            }
        } finally {
            try {
                if ($oldWait !== null) {
                    $this->db->setQuery('SET SESSION innodb_lock_wait_timeout = ' . $oldWait)->execute();
                }
            } finally {
                $this->db->setQuery('SELECT RELEASE_LOCK(' . $mutex . ')')->loadResult();
            }
        }
        return $report;
    }

    /** Log only internal IDs, classes and controlled causes, never exception payloads or SQL. */
    private function logCleanupFailure(int $cartId, \Throwable $exception): string
    {
        $review = $exception instanceof ReservationCleanupReviewException;
        $category = $review ? 'data_inconsistency' : 'program_error';
        $databaseCode = null;
        $cause = $review ? $exception->reason : match ($exception->getMessage()) {
            'Expired hold postcondition failed.' => 'hold_postcondition_failed',
            'Expired cart postcondition failed.' => 'cart_postcondition_failed',
            'Reservation changed concurrently.' => 'affected_rows_mismatch',
            default => 'unexpected_exception',
        };
        for ($error = $exception; $error !== null; $error = $error->getPrevious()) {
            $isDatabaseError = $error instanceof \mysqli_sql_exception
                || $error instanceof \Joomla\Database\Exception\ExecutionFailureException;
            if (!$isDatabaseError) {
                continue;
            }
            $databaseCode = (int) $error->getCode();
            if (\in_array((string) $error->getCode(), ['1205', '1213', '40001'], true)) {
                $category = 'locking_conflict';
                $cause = 'database_lock_conflict';
                break;
            }
            $category = 'database_error';
            $cause = 'database_execution_failed';
        }
        $eventIds = $review && $exception->eventId !== null ? [$exception->eventId] : [];
        if ($eventIds === []) {
            try {
                $eventIds = array_map('intval', $this->db->setQuery('SELECT DISTINCT event_id'
                    . ' FROM #__copymypage_ticket_cart_items WHERE cart_id = ' . $cartId . ' ORDER BY event_id')->loadColumn());
            } catch (\Throwable) {
                // A failed connection must not hide the original failure or permit a release.
            }
        }
        try {
            Log::addLogger([
                'text_file' => 'copymypage-reservations.php',
                'text_entry_format' => "{DATETIME}\t{PRIORITY}\t{CATEGORY}\t{MESSAGE}",
            ], Log::WARNING | Log::ERROR, ['com_copymypage.reservations']);
            Log::add('CopyMyPage reservation cleanup: ' . json_encode([
                'cartId' => $cartId,
                'eventIds' => $eventIds,
                'category' => $category,
                'exception' => $exception::class,
                'databaseCode' => $databaseCode,
                'cause' => $cause,
            ], JSON_THROW_ON_ERROR), $review ? Log::WARNING : Log::ERROR, 'com_copymypage.reservations');
        } catch (\Throwable) {
            // Logging availability never changes the fail-closed transaction outcome.
        }

        return $category;
    }

    private function process(int $cartId, string $now, bool $dryRun): string
    {
        $this->db->transactionStart();
        try {
            $cart = $this->db->setQuery('SELECT * FROM #__copymypage_ticket_carts WHERE id = '
                . $cartId . ' FOR UPDATE')->loadObject();
            if (!$cart) {
                $reason = 'cart_changed';
            } elseif ($cart->booking_id === null) {
                $reason = $this->expireCart($cart, $now, $dryRun);
            } else {
                $reason = 'native_cleanup_required';
            }
            if ($dryRun) {
                $this->db->transactionRollback();
            } else {
                $this->db->transactionCommit();
            }
            return $reason;
        } catch (\Throwable $exception) {
            $this->db->transactionRollback();
            throw $exception;
        }
    }

    /** Cart -> events -> seating -> booking/tickets -> inventory, with sorted IDs. */
    private function lockEvents(int $cartId): array
    {
        $rows = $this->db->setQuery('SELECT event_id FROM #__copymypage_ticket_cart_items WHERE cart_id = '
            . $cartId . ' ORDER BY event_id, price_index FOR UPDATE')->loadColumn();
        $eventIds = array_values(array_unique(array_map('intval', $rows)));
        sort($eventIds, SORT_NUMERIC);
        foreach ($eventIds as $id) {
            if ($id < 1 || (int) $this->db->setQuery('SELECT id FROM #__dpcalendar_events WHERE id = '
                . $id . ' FOR UPDATE')->loadResult() !== $id) {
                // Missing vendor data is not proof that the remaining holds are safe to release.
                throw new ReservationCleanupReviewException('event_missing_requires_review', $id);
            }
            if ((int) $this->db->setQuery('SELECT event_id FROM #__copymypage_event_seating WHERE event_id = '
                    . $id . ' AND status = 1 FOR UPDATE')->loadResult() !== $id) {
                throw new ReservationCleanupReviewException('inventory_requires_review', $id);
            }
        }
        return $eventIds;
    }

    private function expireCart(object $cart, string $now, bool $dryRun): string
    {
        $cartId = (int) $cart->id;
        if (!\in_array((int) $cart->status, [0, 2, 3], true) || (string) $cart->expires_at > $now
            || $cart->terms_accepted_at !== null || (int) $cart->native_payment_phase !== NativeReservationService::UNKNOWN
            || trim((string) $cart->payment_provider) !== '' || trim((string) $cart->terms_snapshot) !== ''
            || (int) $this->db->setQuery('SELECT COUNT(*) FROM #__copymypage_payment_attempts WHERE cart_id = ' . $cartId)->loadResult() > 0) {
            throw new ReservationCleanupReviewException('cart_requires_review');
        }
        $eventIds = $this->lockEvents($cartId);
        $seats = $this->db->setQuery('SELECT * FROM #__copymypage_event_seats WHERE cart_id = '
            . $cartId . ' ORDER BY event_id, id FOR UPDATE')->loadObjectList();
        foreach ($seats as $seat) {
            if ((int) $seat->status !== EventSeatInventoryService::SEAT_STATUS_HELD
                || (int) $seat->allocation_type !== EventSeatInventoryService::SEAT_ALLOCATION_STANDARD
                || $seat->ticket_id !== null || !\in_array((int) $seat->event_id, $eventIds, true)) {
                throw new ReservationCleanupReviewException('seat_requires_review', (int) $seat->event_id);
            }
        }
        if ($dryRun) {
            return 'expired_cart';
        }
        $ids = array_map(static fn(object $s): int => (int) $s->id, $seats);
        if ($ids !== []) {
            $this->db->setQuery('UPDATE #__copymypage_event_seats SET status = 0, cart_id = NULL, ticket_id = NULL,'
                . ' price_index = NULL, assignment_order = NULL, modified = UTC_TIMESTAMP(), modified_by = 0'
                . ' WHERE id IN (' . implode(',', $ids) . ') AND cart_id = ' . $cartId
                . ' AND status = 1 AND allocation_type = 0 AND ticket_id IS NULL')->execute();
            $this->assertChanged(\count($ids));
            $released = (int) $this->db->setQuery('SELECT COUNT(*) FROM #__copymypage_event_seats WHERE id IN ('
                . implode(',', $ids) . ') AND status = 0 AND allocation_type = 0 AND cart_id IS NULL'
                . ' AND ticket_id IS NULL AND price_index IS NULL AND assignment_order IS NULL')->loadResult();
            if ($released !== \count($ids)) {
                throw new \RuntimeException('Expired hold postcondition failed.');
            }
        }
        $this->db->setQuery('UPDATE #__copymypage_ticket_carts SET status = 2, revision = revision + 1,'
            . ' modified = UTC_TIMESTAMP() WHERE id = ' . $cartId . ' AND status = ' . (int) $cart->status
            . ' AND revision = ' . (int) $cart->revision . ' AND booking_id IS NULL AND expires_at <= ' . $this->db->quote($now))->execute();
        $this->assertChanged(1);
        $after = $this->db->setQuery('SELECT * FROM #__copymypage_ticket_carts WHERE id = ' . $cartId . ' FOR UPDATE')->loadObject();
        if (!$after || (int) $after->status !== 2 || $after->booking_id !== null
            || (int) $after->revision !== (int) $cart->revision + 1
            || (int) $this->db->setQuery('SELECT COUNT(*) FROM #__copymypage_event_seats WHERE cart_id = ' . $cartId)->loadResult() !== 0) {
            throw new \RuntimeException('Expired cart postcondition failed.');
        }
        $this->advanceInventory($eventIds);
        return 'expired_cart';
    }

    /** Explicit CLI/admin action only. The scheduler never calls the provider or this method. */
    public function releaseExcludedNative(
        int $bookingId,
        string $provider,
        string $orderId,
        string $merchantId,
        User $actor,
        string $caseReference,
        NativePayPalOrderStatus $status,
        bool $dryRun = true
    ): array {
        if ((int) $actor->id < 1 || (int) $actor->block !== 0 || !$actor->authorise('core.admin')
            || !preg_match('/^[A-Za-z0-9._-]{1,80}$/D', $caseReference) || !$this->nativeReservations->hasSchema()) {
            throw new \DomainException('An active administrator and an audit case reference are required.');
        }
        // An audit insert must roll back with the release; never accept nontransactional tables.
        $tables = array_map(fn(string $table): string => $this->db->quote($this->db->getPrefix() . $table),
            ['copymypage_ticket_carts', 'copymypage_ticket_cart_items', 'dpcalendar_events',
                'copymypage_event_seating', 'dpcalendar_bookings', 'dpcalendar_tickets',
                'copymypage_event_seats', 'copymypage_seats', 'action_logs']);
        if ((int) $this->db->setQuery('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME IN (' . implode(',', $tables) . ") AND ENGINE = 'InnoDB'")->loadResult() !== \count($tables)) {
            throw new \DomainException('Transactional reservation and audit tables are required.');
        }
        return $this->nativeReservations->withBookingLock($bookingId, function () use (
            $bookingId, $provider, $orderId, $merchantId, $actor, $caseReference, $status, $dryRun
        ): array {
            $this->db->transactionStart();
            try {
                $carts = $this->db->setQuery('SELECT * FROM #__copymypage_ticket_carts WHERE booking_id = '
                    . $bookingId . ' ORDER BY id FOR UPDATE')->loadObjectList();
                if (\count($carts) !== 1 || (string) $carts[0]->payment_provider !== $provider) {
                    throw new \DomainException('Native reservation cart/provider binding requires review.');
                }
                $cart = $carts[0];
                if ((int) $cart->native_payment_phase === NativeReservationService::RELEASED_AFTER_EXCLUSION) {
                    $booking = $this->db->setQuery('SELECT * FROM #__dpcalendar_bookings WHERE id = '
                        . $bookingId . ' FOR UPDATE')->loadObject();
                    $audit = $this->db->setQuery('SELECT message FROM #__action_logs WHERE extension = '
                        . $this->db->quote('com_copymypage') . ' AND item_id = ' . $bookingId
                        . ' AND message_language_key = ' . $this->db->quote(self::RELEASE_LOG) . ' ORDER BY id DESC', 0, 1)->loadResult();
                    $receipt = json_decode((string) $audit, true);
                    $savedProof = json_decode((string) ($receipt['proof'] ?? ''), true);
                    $ticketIds = $this->db->setQuery('SELECT id FROM #__dpcalendar_tickets WHERE booking_id = '
                        . $bookingId . ' ORDER BY id FOR UPDATE')->loadColumn();
                    if (!$booking || (int) $booking->state !== 6 || (int) $cart->status !== 3
                        || (string) $booking->payment_provider !== $provider || (string) $booking->transaction_id !== $orderId
                        || ($savedProof['orderHash'] ?? '') !== hash('sha256', $orderId)
                        || ($savedProof['merchantHash'] ?? '') !== hash('sha256', $merchantId)
                        || ($savedProof['provider'] ?? '') !== $provider
                        || ($savedProof['status'] ?? '') !== 'VOIDED'
                        || ($receipt['case'] ?? '') !== $caseReference
                        || ($receipt['ticketIds'] ?? '') !== implode(',', array_map('intval', $ticketIds))
                        || (int) ($receipt['ticketCount'] ?? -1) !== \count($ticketIds)
                        || (int) $this->db->setQuery('SELECT COUNT(*) FROM #__copymypage_event_seats WHERE cart_id = '
                            . (int) $cart->id . ' OR ticket_id IN (SELECT id FROM #__dpcalendar_tickets WHERE booking_id = '
                            . $bookingId . ')')->loadResult() !== 0
                        || (int) $this->db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_tickets WHERE booking_id = '
                            . $bookingId . ' AND state <> 6')->loadResult() !== 0) {
                        throw new \DomainException('Released native reservation requires review.');
                    }
                    $this->db->transactionRollback();
                    return ['bookingId' => $bookingId, 'reason' => 'already_released', 'dryRun' => $dryRun];
                }
                if ((int) $cart->status !== 1 || !\in_array((int) $cart->native_payment_phase,
                    [NativeReservationService::STARTED_OR_UNKNOWN, NativeReservationService::CALLBACK_STARTED_OR_UNKNOWN], true)) {
                    throw new \DomainException('Native reservation history requires review.');
                }
                $eventIds = $this->lockEvents((int) $cart->id);
                $state = $this->integrity->inspect($bookingId, true);
                $booking = $state['booking'];
                if (!$state['valid'] || !$booking || (int) $booking->state !== 3 || (float) $booking->price <= 0
                    || (string) $booking->payment_provider !== $provider || (string) $booking->transaction_id !== $orderId
                    || !$this->providers->isAvailable($provider) || $this->nativeReservations->hasLedger($bookingId)) {
                    throw new \DomainException('Native reservation integrity/payment binding requires review.');
                }
                foreach (['payer_id', 'payer_email', 'raw_data', 'txn_type', 'txn_currency'] as $field) {
                    if (trim((string) ($booking->$field ?? '')) !== '') {
                        throw new \DomainException('Local payment evidence requires review.');
                    }
                }
                foreach (['gross_amount', 'net_amount', 'payment_fee'] as $field) {
                    if ((float) ($booking->$field ?? 0) !== 0.0) {
                        throw new \DomainException('Local payment amount requires review.');
                    }
                }
                $proof = $status->verifyExclusion($booking, $orderId, $merchantId);
                if ($dryRun) {
                    $this->db->transactionRollback();
                    return ['bookingId' => $bookingId, 'reason' => 'would_release_excluded_order', 'dryRun' => true, 'proof' => $proof];
                }
                $this->reconciliation->releaseExcludedNativeWithinTransaction($state, $proof);
                $this->advanceInventory($eventIds);
                $audit = (object) [
                    'message_language_key' => self::RELEASE_LOG,
                    'message' => json_encode(['booking_id' => $bookingId, 'cartId' => (int) $cart->id,
                        'case' => $caseReference, 'proof' => json_encode($proof, JSON_THROW_ON_ERROR),
                        'ticketIds' => implode(',', array_map(static fn(object $ticket): int => (int) $ticket->id, $state['tickets'])),
                        'ticketCount' => \count($state['tickets']),
                        'seatCount' => \count($state['seats']), 'fromPhase' => (int) $cart->native_payment_phase,
                        'result' => 'released_after_exclusion'], JSON_THROW_ON_ERROR),
                    'log_date' => gmdate('Y-m-d H:i:s'), 'extension' => 'com_copymypage',
                    'user_id' => (int) $actor->id, 'item_id' => $bookingId, 'ip_address' => 'COM_ACTIONLOGS_DISABLED',
                ];
                $this->db->insertObject('#__action_logs', $audit);
                $this->assertChanged(1);
                $this->db->transactionCommit();
                return ['bookingId' => $bookingId, 'reason' => 'released_excluded_order', 'dryRun' => false];
            } catch (\Throwable $exception) {
                $this->db->transactionRollback();
                throw $exception;
            }
        });
    }

    private function advanceInventory(array $eventIds): void
    {
        foreach ($eventIds as $id) {
            $this->db->setQuery('UPDATE #__copymypage_event_seating SET inventory_version = inventory_version + 1,'
                . ' modified = UTC_TIMESTAMP(), modified_by = 0 WHERE event_id = ' . $id . ' AND status = 1')->execute();
            $this->assertChanged(1);
        }
    }

    /** Model identity only; the web application's visitor identity is never changed. */
    public function getNativeTaskUser(): ?User
    {
        return $this->nativeTaskUser;
    }

    public function withNativeTaskUser(User $user, callable $run): int
    {
        if ($this->nativeTaskUser !== null || (int) $user->id < 1 || (int) $user->block !== 0
            || !$user->authorise('core.delete', 'com_dpcalendar')
            || !$user->authorise('dpcalendar.admin.book', 'com_dpcalendar')) {
            throw new \DomainException('Native cleanup requires an active user with DPCalendar booking and delete permissions.');
        }
        $this->nativeTaskUser = $user;
        try {
            return $run();
        } finally {
            $this->nativeTaskUser = null;
        }
    }

    public function permitsNativeDeletion(int $bookingId): bool
    {
        return isset($this->nativeDeletions[$bookingId])
            && $this->nativeReservations->hasAdministrativeLock($bookingId);
    }

    /** Native events record what was deleted; release follows only after the entire native call. */
    public function recordNativeDeletion(string $context, object $item): void
    {
        $bookingId = (int) ($context === 'com_dpcalendar.booking' ? $item->id : $item->booking_id);
        if (!$this->permitsNativeDeletion($bookingId)) { throw new \DomainException('Native deletion scope is missing.'); }
        if ($context === 'com_dpcalendar.ticket') {
            $id = (int) $item->id;
            if (!isset($this->nativeDeletions[$bookingId]['tickets'][$id])) {
                throw new \DomainException('Native deletion changed its ticket set.');
            }
            $this->nativeDeletions[$bookingId]['deleted'][$id] = $id;
        }
    }

    /**
     * DPCalendar alone decides deletion eligibility. This is NOT a PayPal exclusion proof.
     * The supplied operation is the unchanged parent BookingModel/TicketModel::delete().
     */
    public function deleteNative(string $kind, int $id, callable $nativeDelete, User $actor): bool
    {
        if (!\in_array($kind, ['booking', 'ticket'], true) || $id < 1) { throw new \InvalidArgumentException(); }
        $bookingId = $kind === 'booking' ? $id : (int) $this->db->setQuery(
            'SELECT booking_id FROM #__dpcalendar_tickets WHERE id = ' . $id)->loadResult();
        if ($bookingId < 1 || !$this->nativeReservations->protectsBooking($bookingId)) { return (bool) $nativeDelete(); }
        if ($this->permitsNativeDeletion($bookingId)) { return (bool) $nativeDelete(); }
        return $this->nativeReservations->withBookingLock($bookingId, function () use ($bookingId, $kind, $id, $nativeDelete, $actor): bool {
            if (!$this->nativeReservations->hasSchema()) { throw new \DomainException('Native reservation schema unavailable.'); }
            $tables = array_map(fn(string $t): string => $this->db->quote($this->db->getPrefix() . $t),
                ['dpcalendar_bookings','dpcalendar_tickets','dpcalendar_events','copymypage_ticket_carts',
                    'copymypage_ticket_cart_items','copymypage_event_seats','copymypage_event_seating','action_logs']);
            if ((int) $this->db->setQuery('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()'
                . ' AND TABLE_NAME IN (' . implode(',', $tables) . ") AND ENGINE='InnoDB'")->loadResult() !== \count($tables)) {
                throw new \DomainException('Native cleanup requires transactional tables.');
            }
            $connection = $this->db->getConnection();
            $mysqli = $this->db instanceof \Joomla\Database\Mysqli\MysqliDriver && $connection instanceof \mysqli;
            $pdoMysql = $this->db instanceof \Joomla\Database\Mysql\MysqlDriver
                && $connection instanceof \PDO && $connection->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql';
            if (!$mysqli && !$pdoMysql) {
                throw new \DomainException('Native cleanup requires a supported MySQL connection.');
            }
            $this->db->transactionStart(true);
            try {
                // Keep Joomla's nesting depth and an independent physical-transaction sentinel.
                $this->nativeCleanupSavepoint($connection, $bookingId, false);
                $carts = $this->db->setQuery('SELECT * FROM #__copymypage_ticket_carts WHERE booking_id = '
                    . $bookingId . ' ORDER BY id FOR UPDATE')->loadObjectList();
                if (\count($carts) !== 1) { throw new \DomainException('Native deletion cart binding is ambiguous.'); }
                $cart = $carts[0]; $cartId = (int) $cart->id;
                $eventIds = $this->lockEvents($cartId);
                $booking = $this->db->setQuery('SELECT * FROM #__dpcalendar_bookings WHERE id = ' . $bookingId . ' FOR UPDATE')->loadObject();
                $tickets = $this->db->setQuery('SELECT * FROM #__dpcalendar_tickets WHERE booking_id = '
                    . $bookingId . ' ORDER BY id FOR UPDATE')->loadObjectList('id');
                $seats = $this->db->setQuery('SELECT * FROM #__copymypage_event_seats WHERE cart_id = '
                    . $cartId . ' ORDER BY event_id,id FOR UPDATE')->loadObjectList();
                if (!$booking) {
                    if ($tickets !== [] || $seats !== [] || (int) $cart->status !== 3
                        || (int) $cart->native_payment_phase !== NativeReservationService::NATIVE_TICKETS_DELETED
                        || !(int) $this->db->setQuery('SELECT COUNT(*) FROM #__action_logs WHERE extension = '
                            . $this->db->quote('com_copymypage') . ' AND item_id = ' . $bookingId
                            . ' AND message_language_key = ' . $this->db->quote(self::NATIVE_DELETE_LOG))->loadResult()) {
                        throw new \DomainException('Incomplete native deletion requires review.');
                    }
                    $this->nativeCleanupSavepoint($connection, $bookingId, true);
                    $this->db->transactionCommit(true);
                    return true;
                }
                if ($kind === 'booking' && $tickets === [] && $seats === [] && (int) $cart->status === 3
                    && (int) $cart->native_payment_phase === NativeReservationService::NATIVE_TICKETS_DELETED) {
                    // All tickets may have been deleted individually before DPCalendar removes the empty booking.
                    $this->nativeDeletions[$bookingId] = ['tickets' => [], 'deleted' => []];
                    if (!$nativeDelete() || (int) $this->db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_bookings WHERE id='
                        . $bookingId)->loadResult()) { throw new \DomainException('Empty native booking deletion failed.'); }
                    $this->nativeCleanupSavepoint($connection, $bookingId, true);
                    $this->db->transactionCommit(true);
                    return true;
                }
                if ((int) $cart->status !== 1 || (string) $booking->payment_provider !== (string) $cart->payment_provider) {
                    throw new \DomainException('Native deletion booking binding changed.');
                }
                $seenTickets = [];
                foreach ($seats as $seat) {
                    if (!isset($tickets[(int) $seat->ticket_id]) || (int) $seat->status !== 2
                        || isset($seenTickets[(int) $seat->ticket_id])
                        || (int) $seat->allocation_type !== 0 || !\in_array((int) $seat->event_id, $eventIds, true)) {
                        throw new \DomainException('Native deletion seat binding changed.');
                    }
                    $seenTickets[(int) $seat->ticket_id] = true;
                }
                if (\count($seats) !== \count($tickets)) { throw new \DomainException('Native deletion seat count changed.'); }
                $this->nativeDeletions[$bookingId] = ['tickets' => $tickets, 'deleted' => []];
                if (!$nativeDelete()) { throw new \DomainException('DPCalendar rejected native deletion.'); }
                $deleted = array_values($this->nativeDeletions[$bookingId]['deleted']); sort($deleted, SORT_NUMERIC);
                $expected = $kind === 'booking' ? array_map('intval', array_keys($tickets)) : [$id]; sort($expected, SORT_NUMERIC);
                if ($deleted !== $expected || ($kind === 'booking'
                    && (int) $this->db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_bookings WHERE id = ' . $bookingId)->loadResult())) {
                    throw new \DomainException('Native deletion did not complete its expected events.');
                }
                if ((int) $this->db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_tickets WHERE id IN ('
                    . implode(',', $deleted) . ')')->loadResult() !== 0) { throw new \DomainException('Native tickets still exist.'); }
                $seatIds = []; $changedEvents = [];
                foreach ($seats as $seat) {
                    if (\in_array((int) $seat->ticket_id, $deleted, true)) {
                        $seatIds[] = (int) $seat->id; $changedEvents[(int) $seat->event_id] = (int) $seat->event_id;
                    }
                }
                if (\count($seatIds) !== \count($deleted)) { throw new \DomainException('Native ticket/seat cardinality changed.'); }
                $this->db->setQuery('UPDATE #__copymypage_event_seats SET status=0,cart_id=NULL,ticket_id=NULL,'
                    . 'price_index=NULL,assignment_order=NULL,modified=UTC_TIMESTAMP(),modified_by=' . (int) $actor->id
                    . ' WHERE id IN (' . implode(',', $seatIds) . ') AND cart_id=' . $cartId
                    . ' AND status=2 AND allocation_type=0 AND ticket_id IN (' . implode(',', $deleted) . ')')->execute();
                $this->assertChanged(\count($seatIds));
                $remaining = (int) $this->db->setQuery('SELECT COUNT(*) FROM #__dpcalendar_tickets WHERE booking_id=' . $bookingId)->loadResult();
                $this->db->setQuery('UPDATE #__copymypage_ticket_carts SET status=' . ($remaining === 0 ? 3 : 1)
                    . ',native_payment_phase=' . NativeReservationService::NATIVE_TICKETS_DELETED
                    . ',revision=revision+1,modified=UTC_TIMESTAMP() WHERE id=' . $cartId . ' AND revision=' . (int) $cart->revision)->execute();
                $this->assertChanged(1);
                $this->advanceInventory(array_values($changedEvents));
                $audit = (object) ['message_language_key'=>self::NATIVE_DELETE_LOG,
                    'message'=>json_encode(['booking_id'=>$bookingId,'cartId'=>$cartId,'ticketIds'=>implode(',',$deleted),
                        'seatIds'=>implode(',',$seatIds),'provider'=>$booking->payment_provider,'orderId'=>$booking->transaction_id,
                        'tokenHash'=>hash('sha256',(string)$booking->token),'uid'=>$booking->uid,'bookDate'=>$booking->book_date,
                        'originalState'=>(int)$booking->state,'amount'=>(string)$booking->price,'currency'=>$booking->currency,
                        'decision'=>'dpcalendar_native_delete','paymentExclusionProven'=>false],JSON_THROW_ON_ERROR),
                    'log_date'=>gmdate('Y-m-d H:i:s'),'extension'=>'com_copymypage','user_id'=>(int)$actor->id,
                    'item_id'=>$bookingId,'ip_address'=>'COM_ACTIONLOGS_DISABLED'];
                $this->db->insertObject('#__action_logs',$audit); $this->assertChanged(1);
                if (!$this->nativeReservations->hasAdministrativeLock($bookingId)) {
                    throw new \RuntimeException('Native deletion transaction or lock was lost.');
                }
                $this->nativeCleanupSavepoint($connection, $bookingId, true);
                $this->db->transactionCommit(true);
                return true;
            } catch (\Throwable $error) {
                $this->db->transactionRollback(true); throw $error;
            } finally { unset($this->nativeDeletions[$bookingId]); }
        });
    }

    /** MySQL savepoint statements cannot use MySQLi's prepared-statement protocol. */
    private function nativeCleanupSavepoint(\mysqli|\PDO $connection, int $bookingId, bool $release): void
    {
        if ($this->db->getConnection() !== $connection) {
            throw new \RuntimeException('Native cleanup connection changed.');
        }
        $sql = ($release ? 'RELEASE SAVEPOINT ' : 'SAVEPOINT ')
            . $this->db->quoteName('cmp_native_' . $bookingId);
        if ($connection->query($sql) === false) {
            throw new \RuntimeException('Native cleanup savepoint operation failed.');
        }
    }

    private function assertChanged(int $expected): void
    {
        if ($this->db->getAffectedRows() !== $expected) {
            throw new \RuntimeException('Reservation changed concurrently.');
        }
    }
}
