<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

/** Reservation evidence only. Payment creation and capture remain with DPCalendar. */
final class NativeReservationService
{
    public const UNKNOWN = 0;
    public const NOT_STARTED = 1;
    public const STARTED_OR_UNKNOWN = 2;
    public const RELEASED_BEFORE_START = 3;
    /** Never expires automatically: the provider may have captured even if PHP failed. */
    public const CALLBACK_STARTED_OR_UNKNOWN = 4;
    public const RELEASED_AFTER_EXCLUSION = 5;
    /** DPCalendar deleted native tickets. This is NOT a financial/provider exclusion proof. */
    public const NATIVE_TICKETS_DELETED = 6;

    /** @var array<int, string> Request-local capabilities, consumed before provider dispatch. */
    private array $startClaims = [];

    /** Named locks retained until PHP request shutdown, beyond vendor HTTP and persistence. */
    private array $callbackLocks = [];

    private array $administrativeLocks = [];

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly NativePayPalProviderRegistry $providers,
        private readonly BookingIntegrityService $integrity
    ) {
    }

    public function hasSchema(): bool
    {
        $column = $this->db->getTableColumns('#__copymypage_ticket_carts', false)['native_payment_phase'] ?? null;
        return $column !== null && preg_match('/^tinyint(?:\([0-9]+\))? unsigned$/iD', $column->Type) === 1
            && $column->Null === 'NO' && (string) $column->Default === '0';
    }

    public function protectsBooking(int $bookingId): bool
    {
        return NativePayPalProviderRegistry::hasManagedBooking($this->db, $bookingId);
    }

    public function isReleased(int $bookingId): bool
    {
        return $this->hasSchema() && (int) $this->db->setQuery('SELECT COUNT(*) FROM #__copymypage_ticket_carts'
            . ' WHERE booking_id = ' . $bookingId . ' AND native_payment_phase IN ('
            . self::RELEASED_BEFORE_START . ', ' . self::RELEASED_AFTER_EXCLUSION . ', '
            . self::NATIVE_TICKETS_DELETED . ')')->loadResult() > 0;
    }

    /** Claims and commits the start before any external request, including direct native routes. */
    public function beginPayment(int $bookingId): ?string
    {
        if (!$this->hasSchema()) {
            throw new \RuntimeException('Native reservation schema is unavailable.');
        }
        $this->db->transactionStart();
        try {
            $state = $this->integrity->inspect($bookingId, true);
            $booking = $state['booking'];
            $cart = $state['cart'];
            if (!$state['valid'] || !$booking || !$cart
                || !\in_array((int) $cart->native_payment_phase, [self::UNKNOWN, self::NOT_STARTED], true)
                || (int) $booking->state !== 3 || (float) $booking->price <= 0
                || trim((string) $booking->token) !== '' || trim((string) $booking->transaction_id) !== ''
                || !$this->providers->isAvailable((string) $booking->payment_provider)
                || $this->hasLedger($bookingId)) {
                $this->db->transactionRollback();
                return null;
            }
            $token = bin2hex(random_bytes(32));
            $this->db->setQuery('UPDATE #__copymypage_ticket_carts SET native_payment_phase = '
                . self::STARTED_OR_UNKNOWN . ', modified = UTC_TIMESTAMP() WHERE id = ' . (int) $cart->id
                . ' AND status = 1 AND native_payment_phase = ' . (int) $cart->native_payment_phase)->execute();
            $this->assertChanged(1);
            $this->db->setQuery('UPDATE #__dpcalendar_bookings SET token = ' . $this->db->quote($token)
                . ' WHERE id = ' . $bookingId . ' AND state = 3'
                . " AND COALESCE(TRIM(token), '') = '' AND COALESCE(TRIM(transaction_id), '') = ''")->execute();
            $this->assertChanged(1);
            $this->db->transactionCommit();
            $this->startClaims[$bookingId] = $token;
            return $token;
        } catch (\Throwable $exception) {
            $this->db->transactionRollback();
            throw $exception;
        }
    }

    public function beforeProviderStart(object $booking): void
    {
        $bookingId = (int) ($booking->id ?? 0);
        if (!$this->protectsBooking($bookingId)) {
            return;
        }
        if (!NativePayPalProviderRegistry::isReference((string) ($booking->payment_provider ?? ''))
            || !$this->providers->isAvailable((string) $booking->payment_provider)) {
            throw new \DomainException('Native payment provider association requires review.');
        }
        $token = $this->startClaims[$bookingId] ?? null;
        if ($token === null) {
            $token = $this->beginPayment($bookingId);
        }
        unset($this->startClaims[$bookingId]);
        if ($token === null) {
            throw new \DomainException('Native payment start requires review.');
        }
        // A direct native template may still contain the pre-claim booking object.
        $booking->token = $token;
    }

    /** All native cancel callbacks hold. A local cancellation is not a payment exclusion proof. */
    public function assertCallback(int $bookingId, bool $cancel, ?object $providerBooking = null): void
    {
        if (!$this->protectsBooking($bookingId)) {
            return;
        }
        if ($cancel || !$this->hasSchema() || $this->hasAdministrativeLock($bookingId)) {
            throw new \DomainException('Native payment cancellation requires manual review.');
        }
        $lock = $this->acquireBookingLock($bookingId);
        $retained = false;
        try {
            $this->db->transactionStart();
            $state = $this->integrity->inspect($bookingId, true);
            $booking = $state['booking'];
            $cart = $state['cart'];
            if (!$state['valid'] || !$booking || !$cart
                || !\in_array((int) $cart->native_payment_phase,
                    [self::UNKNOWN, self::NOT_STARTED, self::STARTED_OR_UNKNOWN], true)
                || !\in_array((int) $booking->state, [3, 4], true)
                || trim((string) $booking->transaction_id) === ''
                || !$this->providers->isAvailable((string) $booking->payment_provider)
                || $this->hasLedger($bookingId)) {
                throw new \DomainException('Native payment callback requires review.');
            }
            // The vendor uses its previously loaded object. Reject stale provider/order bindings.
            if ($providerBooking !== null) {
                foreach (['payment_provider', 'transaction_id', 'token'] as $field) {
                    if ((string) ($providerBooking->$field ?? '') !== (string) ($booking->$field ?? '')) {
                        throw new \DomainException('Native payment callback binding changed.');
                    }
                }
            }
            $this->db->setQuery('UPDATE #__copymypage_ticket_carts SET native_payment_phase = '
                . self::CALLBACK_STARTED_OR_UNKNOWN . ', modified = UTC_TIMESTAMP() WHERE id = ' . (int) $cart->id
                . ' AND status = 1 AND native_payment_phase = ' . (int) $cart->native_payment_phase)->execute();
            $this->assertChanged(1);
            $this->db->transactionCommit();
            $this->callbackLocks[$lock] = ($this->callbackLocks[$lock] ?? 0) + 1;
            $retained = true;
            register_shutdown_function(function () use ($lock): void {
                try {
                    $this->releaseBookingLock($lock);
                } catch (\Throwable) {
                    // Connection loss releases the named lock; persisted phase 4 still holds.
                }
            });
        } catch (\Throwable $exception) {
            $this->db->transactionRollback();
            throw $exception;
        } finally {
            if (!$retained) {
                $this->releaseBookingLock($lock);
            }
        }
    }

    /** Administrative exclusion proof and release share the callback's entire request lock. */
    public function withBookingLock(int $bookingId, callable $operation): mixed
    {
        $lock = $this->bookingLockName($bookingId);
        if (isset($this->callbackLocks[$lock])) {
            throw new \DomainException('Native payment callback is still active in this request.');
        }
        $lock = $this->acquireBookingLock($bookingId);
        $this->administrativeLocks[$lock] = true;
        try {
            return $operation();
        } finally {
            unset($this->administrativeLocks[$lock]);
            $this->releaseBookingLock($lock);
        }
    }

    /** A lost connection must not turn an administrative operation into unlocked writes. */
    public function hasAdministrativeLock(int $bookingId): bool
    {
        $lock = $this->bookingLockName($bookingId);
        return isset($this->administrativeLocks[$lock])
            && (int) $this->db->setQuery('SELECT IS_USED_LOCK(' . $this->db->quote($lock)
                . ') = CONNECTION_ID()')->loadResult() === 1;
    }

    private function bookingLockName(int $bookingId): string
    {
        if ($bookingId < 1) {
            throw new \InvalidArgumentException('Invalid reservation booking.');
        }
        $database = (string) $this->db->setQuery('SELECT DATABASE()')->loadResult();
        return 'cmp.native.' . substr(hash('sha256', $database . "\0" . $this->db->getPrefix() . "\0" . $bookingId), 0, 48);
    }

    private function acquireBookingLock(int $bookingId): string
    {
        $lock = $this->bookingLockName($bookingId);
        if ((int) $this->db->setQuery('SELECT GET_LOCK(' . $this->db->quote($lock) . ', 0)')->loadResult() !== 1) {
            throw new \DomainException('Native reservation payment/release is busy.');
        }
        return $lock;
    }

    private function releaseBookingLock(string $lock): void
    {
        $this->db->setQuery('SELECT RELEASE_LOCK(' . $this->db->quote($lock) . ')')->loadResult();
    }

    public function hasLedger(int $bookingId): bool
    {
        return $this->db->setQuery('SELECT id FROM #__copymypage_payment_attempts'
            . ' WHERE booking_id = ' . $bookingId . ' FOR UPDATE')->loadColumn() !== [];
    }

    private function assertChanged(int $expected): void
    {
        if ($this->db->getAffectedRows() !== $expected) {
            throw new \RuntimeException('Native reservation state changed concurrently.');
        }
    }
}
