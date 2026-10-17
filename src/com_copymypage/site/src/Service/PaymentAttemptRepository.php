<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/** Unique booking binding plus compare-and-swap leases; no read-then-insert race. */
final class PaymentAttemptRepository
{
    private const MUTABLE = ['state','create_payload_hash','order_id','capture_id','provider_status','provider_evidence','manual_reason','lease_id','lease_expires','captured_at','finalized_at','retry_count'];

    private ?bool $reservationStorage = null;

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly BookingIntegrityService $integrity,
        private readonly ?PaymentReservationPolicy $reservationPolicy = null
    ) {
    }

    /** Source-only deployment must not silently migrate the installed business database. */
    public function supportsReservations(): bool
    {
        if ($this->reservationStorage === null) {
            $columns = $this->db->getTableColumns('#__copymypage_payment_attempts');
            $minutes = array_key_exists('reservation_minutes', $columns);
            $expires = array_key_exists('reservation_expires_at', $columns);
            if ($minutes !== $expires) {
                throw new \DomainException('Incomplete payment reservation schema.');
            }
            $this->reservationStorage = $minutes && $expires;
        }

        return $this->reservationStorage;
    }

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    public static function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }

    public function getForBooking(int $bookingId, bool $lock = false): ?object
    {
        if ($bookingId < 1) {
            return null;
        }
        $query = $this->db->getQuery(true)->select('*')->from($this->db->quoteName('#__copymypage_payment_attempts'))
            ->where($this->db->quoteName('booking_id') . ' = :bookingId')->bind(':bookingId', $bookingId, ParameterType::INTEGER);
        if ($lock) {
            $query->setQuery((string) $query . ' FOR UPDATE');
        }

        return $this->db->setQuery($query)->loadObject() ?: null;
    }

    public function getForOrder(string $providerId, string $orderId): ?object
    {
        $query = $this->db->getQuery(true)->select('*')->from($this->db->quoteName('#__copymypage_payment_attempts'))
            ->where('provider_id = :provider')->where('order_id = :order')
            ->bind(':provider', $providerId)->bind(':order', $orderId);

        return $this->db->setQuery($query)->loadObject() ?: null;
    }

    /** Called after existing native authorization/first-start capability claim, before external I/O. */
    public function prepare(int $bookingId, PaymentProviderConfig $provider): object
    {
        $this->db->transactionStart();
        try {
            $inspection = $this->integrity->inspect($bookingId, true);
            if (!$inspection['valid'] || (int) $inspection['booking']->state !== 3
                || !hash_equals($provider->id, (string) $inspection['booking']->payment_provider)
                || trim((string) $inspection['booking']->token) === '') {
                throw new \DomainException('Invalid managed payment preparation.');
            }
            $existing = $this->getForBooking($bookingId, true);
            if ($existing !== null) {
                $this->assertBinding($existing, $inspection, $provider);
                $this->db->transactionCommit();

                return $existing;
            }
            if (trim((string) $inspection['booking']->transaction_id) !== '') {
                throw new \DomainException('Existing native payment cannot be adopted.');
            }
            $now = self::now();
            $row = (object) [
                'id' => self::uuid(), 'booking_id' => $bookingId,
                'cart_id' => (int) $inspection['cart']->id, 'owner_id' => (int) $inspection['booking']->user_id,
                'provider_id' => $provider->id, 'provider_account_hash' => $provider->accountHash,
                'sandbox' => $provider->sandbox ? 1 : 0,
                'snapshot_hash' => hash('sha256', (string) $inspection['cart']->terms_snapshot),
                'expected_amount' => PaymentAmount::fromBooking($inspection['booking']),
                'expected_currency' => (string) $inspection['booking']->currency,
                'expected_payee' => $provider->merchantId, 'return_base_uri' => $provider->returnBaseUri,
                'create_key' => self::uuid(), 'capture_key' => self::uuid(),
                'state' => 'prepared', 'created' => $now, 'modified' => $now, 'version' => 0, 'retry_count' => 0,
            ];
            if ($this->supportsReservations()) {
                $row->reservation_minutes = ($this->reservationPolicy ?? new PaymentReservationPolicy($this->db))->minutesForNewAttempt();
            }
            // Existing cart/booking row locks serialize this insertion; the UNIQUE booking_id is the final arbiter.
            $this->db->insertObject('#__copymypage_payment_attempts', $row);
            $this->db->transactionCommit();

            return $this->getForBooking($bookingId) ?? throw new \RuntimeException('Payment attempt persistence failed.');
        } catch (\Throwable $error) {
            $this->db->transactionRollback();
            throw $error;
        }
    }

    public function assertBinding(object $attempt, array $inspection, PaymentProviderConfig $provider): void
    {
        $booking = $inspection['booking'] ?? null;
        $cart = $inspection['cart'] ?? null;
        if (empty($inspection['valid']) || !$booking || !$cart
            || (int) $attempt->booking_id !== (int) $booking->id
            || (int) $attempt->cart_id !== (int) $cart->id
            || (int) $attempt->owner_id !== (int) $booking->user_id
            || !hash_equals((string) $attempt->provider_id, $provider->id)
            || !hash_equals((string) $attempt->provider_id, (string) $booking->payment_provider)
            || !hash_equals((string) $attempt->provider_account_hash, $provider->accountHash)
            || (bool) $attempt->sandbox !== $provider->sandbox
            || !hash_equals((string) $attempt->expected_payee, $provider->merchantId)
            || !hash_equals((string) $attempt->snapshot_hash, hash('sha256', (string) $cart->terms_snapshot))
            || !hash_equals((string) $attempt->expected_currency, (string) $booking->currency)
            || !hash_equals((string) $attempt->expected_amount, PaymentAmount::fromBooking($booking))
            || !hash_equals((string) $attempt->return_base_uri, $provider->returnBaseUri)) {
            throw new \DomainException('Managed payment binding changed.');
        }
        $nativeReference = trim((string) $booking->transaction_id);
        if ($nativeReference !== '' && !in_array($nativeReference, [(string) ($attempt->order_id ?? ''), (string) ($attempt->capture_id ?? '')], true)) {
            throw new \DomainException('Managed native payment reference changed.');
        }
    }

    /** A transition succeeds only for the exact observed version, state and lease. */
    public function transition(object $attempt, array $changes): ?object
    {
        foreach (array_keys($changes) as $name) {
            if (!in_array($name, self::MUTABLE, true)) {
                throw new \InvalidArgumentException('Immutable payment-attempt field.');
            }
        }
        $target = $changes['state'] ?? $attempt->state;
        if (PaymentReservationPolicy::isClosed($attempt)
            && ($target !== $attempt->state && $target !== 'manual'
                || !empty($changes['lease_id'])
                || ($attempt->state === 'finalized' && $target !== 'finalized'))) {
            return null;
        }

        return $this->write($attempt, $changes);
    }

    /** Only dedicated financial authorization may write first-operation timestamps. */
    private function write(object $attempt, array $changes, array $conditions = []): ?object
    {
        $query = $this->db->getQuery(true)->update($this->db->quoteName('#__copymypage_payment_attempts'));
        foreach ($changes as $name => $value) {
            if ($value === null) {
                $query->set($this->db->quoteName($name) . ' = NULL');
            } else {
                $query->set($this->db->quoteName($name) . ' = :value_' . $name);
                $query->bind(':value_' . $name, $changes[$name], is_int($value) ? ParameterType::INTEGER : ParameterType::STRING);
            }
        }
        $now = self::now();
        $id = (string) $attempt->id;
        $version = (int) $attempt->version;
        $state = (string) $attempt->state;
        $query->set('version = version + 1')->set('modified = :modified')
            ->where('id = :id')->where('version = :version')->where('state = :state')
            ->bind(':modified', $now)->bind(':id', $id)->bind(':version', $version, ParameterType::INTEGER)->bind(':state', $state);
        if (!empty($attempt->lease_id)) {
            $lease = (string) $attempt->lease_id;
            $query->where('lease_id = :lease')->bind(':lease', $lease);
        } else {
            $query->where('lease_id IS NULL');
        }
        foreach ($conditions as $condition) {
            $query->where($condition);
        }
        $this->db->setQuery($query)->execute();
        if ($this->db->getAffectedRows() !== 1) {
            return null;
        }

        return $this->getForBooking((int) $attempt->booking_id);
    }

    public function claim(object $attempt, array $states, string $target): ?object
    {
        if (PaymentReservationPolicy::isClosed($attempt)
            || !in_array((string) $attempt->state, $states, true) || !empty($attempt->lease_id)) {
            return null;
        }
        return $this->transition($attempt, [
            'state' => $target, 'lease_id' => self::uuid(),
            'lease_expires' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+120 seconds')->format('Y-m-d H:i:s.u'),
            'retry_count' => (int) $attempt->retry_count + 1,
        ]);
    }

    /** A crashed financial request is never reset to "not started" when its lease expires. */
    public function recoverExpiredLease(object $attempt): ?object
    {
        if (empty($attempt->lease_id) || (string) $attempt->lease_expires >= self::now()) {
            return $attempt;
        }
        $target = match ($attempt->state) {
            'creating' => 'create_unknown', 'capturing' => 'capture_unknown',
            'finalizing' => 'finalize_failed', 'cancel_check' => 'cancel_unknown',
            default => PaymentReservationPolicy::isClosed($attempt) ? $attempt->state : 'manual',
        };

        return $this->transition($attempt, ['state' => $target, 'lease_id' => null, 'lease_expires' => null, 'manual_reason' => 'interrupted_operation']);
    }

    /** Persist the first real Create start and its frozen deadline in one leased CAS. */
    public function authorizeCreate(object $attempt, string $payloadHash): ?object
    {
        if ($attempt->state !== 'creating' || PaymentReservationPolicy::isClosed($attempt)
            || empty($attempt->lease_id)
            || !in_array(PaymentReservationPolicy::state($attempt), ['legacy', 'unstarted', 'active'], true)
            || (!empty($attempt->create_payload_hash) && !hash_equals($attempt->create_payload_hash, $payloadHash))) {
            return null;
        }
        $now = (string) $this->db->setQuery('SELECT UTC_TIMESTAMP(6)')->loadResult();
        $changes = ['create_payload_hash' => $payloadHash];
        if (empty($attempt->create_started)) {
            $changes['create_started'] = $now;
            $changes['create_safe_until'] = (new \DateTimeImmutable($now, new \DateTimeZone('UTC')))->modify('+355 minutes')->format('Y-m-d H:i:s.u');
            if (($attempt->reservation_minutes ?? null) !== null) {
                $changes['reservation_expires_at'] = PaymentReservationPolicy::expiresAt($now, (int) $attempt->reservation_minutes);
            }
        }

        return $this->write($attempt, $changes, $this->financialConditions());
    }

    /** The same order/key may be retried only while its reservation remains payable. */
    public function authorizeCapture(object $attempt): ?object
    {
        if ($attempt->state !== 'capturing' || PaymentReservationPolicy::isClosed($attempt)
            || empty($attempt->lease_id)
            || !in_array(PaymentReservationPolicy::state($attempt), ['legacy', 'active'], true)) {
            return null;
        }
        $now = (string) $this->db->setQuery('SELECT UTC_TIMESTAMP(6)')->loadResult();

        return $this->write($attempt, ['capture_started' => $attempt->capture_started ?: $now], $this->financialConditions());
    }

    private function financialConditions(): array
    {
        $conditions = ['lease_expires > UTC_TIMESTAMP(6)'];
        if ($this->supportsReservations()) {
            $conditions[] = '(reservation_expires_at IS NULL OR reservation_expires_at > UTC_TIMESTAMP(6))';
        }

        return $conditions;
    }

    public function isFinalized(int $bookingId): bool
    {
        $row = $this->getForBooking($bookingId);

        return $row !== null && $row->state === 'finalized' && !empty($row->finalized_at)
            && PaymentProof::hasDurableCapture($row);
    }
}
