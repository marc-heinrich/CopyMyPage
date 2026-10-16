<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/** Durable financial operations, followed by a separate atomic native finalization. */
final class PayPalPaymentService
{
    private readonly \Closure $providerResolver;
    private readonly \Closure $clientFactory;

    public function __construct(
        private readonly CMSWebApplicationInterface $app,
        private readonly DatabaseInterface $db,
        private readonly BookingIntegrityService $integrity,
        private readonly PaymentAttemptRepository $attempts,
        private readonly PaymentOutboxService $outbox,
        ?callable $providerResolver = null,
        ?callable $clientFactory = null
    ) {
        $this->providerResolver = $providerResolver !== null ? \Closure::fromCallable($providerResolver) : PaymentProviderConfig::load(...);
        $this->clientFactory = $clientFactory !== null ? \Closure::fromCallable($clientFactory) : static fn (PaymentProviderConfig $config): PayPalOrdersClientInterface => new PayPalOrdersClient($config);
    }

    /** Administrator-only caller: recover the first-claim/ledger gap without starting provider I/O. */
    public function recover(int $bookingId): void
    {
        if ($this->attempts->getForBooking($bookingId) === null) {
            $inspection = $this->integrity->inspect($bookingId);
            if (!$inspection['valid'] || !PaymentProviderConfig::isOwned((string) $inspection['booking']->payment_provider)) {
                throw new \DomainException('Invalid managed payment recovery.');
            }
            $config = ($this->providerResolver)((string) $inspection['booking']->payment_provider);
            // The owned adapter always persists a ledger before external I/O. No ledger plus an
            // empty native transaction is recoverable preparation, never a historic payment adoption.
            $this->attempts->prepare($bookingId, $config);
            return;
        }
        $this->reconcile($bookingId);
    }

    /** Caller must already have consumed the existing CSRF/session/booking handoff. */
    public function start(int $bookingId): ?string
    {
        $inspection = $this->integrity->inspect($bookingId);
        if (!$inspection['valid']) {
            throw new \DomainException('Invalid payment booking.');
        }
        $config = ($this->providerResolver)((string) $inspection['booking']->payment_provider);
        $attempt = $this->attempts->prepare($bookingId, $config);
        $attempt = $this->attempts->recoverExpiredLease($attempt);
        if ($attempt === null || !empty($attempt->lease_id)) {
            return null;
        }
        $client = ($this->clientFactory)($config);
        if (PaymentProof::hasDurableCapture($attempt)) {
            $this->finalize($bookingId);
            return null;
        }
        if (!empty($attempt->order_id)) {
            if (!in_array($attempt->state, ['order_created','order_observed','capture_unknown'], true)) {
                return null;
            }
            try {
                $order = $client->getOrder($attempt->order_id);
                PaymentProof::verifyOrder($attempt, $order);
                if (($order['status'] ?? '') === 'COMPLETED') {
                    $this->storeCapture($attempt, $order, 'order_lookup');
                    $this->finalize($bookingId);
                    return null;
                }
                if ($attempt->state === 'capture_unknown') {
                    $this->reconcile($bookingId);
                    return null;
                }
                $attempt = $this->change($attempt, ['state' => 'order_created', 'provider_status' => (string) ($order['status'] ?? '')]);
                $this->storeNativeOrderReference($attempt);
                return $this->approvalUrl($order, $config);
            } catch (PaymentProviderException) {
                return null;
            } catch (\DomainException) {
                $this->manual($attempt, 'order_binding_mismatch');
                return null;
            }
        }
        if (!in_array($attempt->state, ['prepared','create_unknown'], true)) {
            return null;
        }
        if ($attempt->state === 'create_unknown' && (empty($attempt->create_safe_until) || $attempt->create_safe_until <= PaymentAttemptRepository::now())) {
            $this->manual($attempt, 'create_idempotency_window_expired');
            return null;
        }
        $attempt = $this->attempts->claim($attempt, ['prepared','create_unknown'], 'creating');
        if ($attempt === null) {
            return null;
        }
        $body = $this->createBody($attempt, $inspection['booking']);
        $hash = hash('sha256', json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        if (!empty($attempt->create_payload_hash) && !hash_equals($attempt->create_payload_hash, $hash)) {
            $this->manual($attempt, 'create_payload_changed');
            return null;
        }
        $attempt = $this->change($attempt, ['create_payload_hash' => $hash,
            'create_started' => $attempt->create_started ?: PaymentAttemptRepository::now(),
            // Orders Create documents six hours; leave a five-minute margin and never extend on retries.
            'create_safe_until' => $attempt->create_safe_until ?: (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+355 minutes')->format('Y-m-d H:i:s.u')]);
        try {
            $response = $client->createOrder($body, $attempt->create_key);
            $orderId = (string) ($response['id'] ?? '');
            PayPalOrdersClient::checkId($orderId);
            // Commit the real ID before a further network operation can fail.
            $attempt = $this->change($attempt, ['order_id' => $orderId, 'state' => 'order_observed', 'lease_id' => null, 'lease_expires' => null]);
            $order = $client->getOrder($orderId);
            PaymentProof::verifyOrder($attempt, $order);
            if (($order['status'] ?? '') === 'COMPLETED') {
                $this->storeCapture($attempt, $order, 'create_lookup');
                $this->finalize($bookingId);
                return null;
            }
            $attempt = $this->change($attempt, ['state' => 'order_created', 'provider_status' => (string) ($order['status'] ?? '')]);
            $this->storeNativeOrderReference($attempt);
            return $this->approvalUrl($order, $config);
        } catch (PaymentProviderException) {
            if ($attempt->state === 'creating') {
                $this->attempts->transition($attempt, ['state' => 'create_unknown', 'lease_id' => null, 'lease_expires' => null, 'manual_reason' => 'create_response_unknown']);
            }
            return null;
        } catch (\DomainException) {
            $this->manual($attempt, 'order_binding_mismatch');
            return null;
        }
    }

    /** Browser return is only a trigger. The provider token must equal the persisted order, if supplied. */
    public function reconcile(int $bookingId, ?string $browserOrder = null, bool $cancel = false): void
    {
        $attempt = $this->attempts->getForBooking($bookingId);
        if ($attempt === null) {
            throw new \DomainException('Payment attempt missing.');
        }
        if ($browserOrder !== null && ($browserOrder === '' || !hash_equals((string) $attempt->order_id, $browserOrder))) {
            throw new \RuntimeException('Payment return binding mismatch.', 403);
        }
        if ($attempt->state === 'finalized') {
            return;
        }
        $attempt = $this->attempts->recoverExpiredLease($attempt);
        if ($attempt === null || !empty($attempt->lease_id)) {
            return;
        }
        if (PaymentProof::hasDurableCapture($attempt)) {
            $this->finalize($bookingId);
            return;
        }
        $config = ($this->providerResolver)($attempt->provider_id);
        $this->assertAccount($attempt, $config);
        $client = ($this->clientFactory)($config);
        if (empty($attempt->order_id)) {
            // Only a genuinely unstarted attempt can be cancelled without external reconciliation.
            if ($cancel && $attempt->state === 'prepared' && empty($attempt->create_started)) {
                $claimed = $this->attempts->claim($attempt, ['prepared'], 'cancel_check');
                if ($claimed !== null) {
                    $this->cancelLocal($claimed, $config);
                }
            }
            return;
        }
        // Acquire the financial lease before GET as well, so cancel cannot race an approved capture.
        $claimed = $this->attempts->claim($attempt, ['order_created','order_observed','capture_unknown','cancel_unknown','cancelled','manual'], $cancel ? 'cancel_check' : 'capturing');
        if ($claimed === null) {
            return;
        }
        $previousState = $attempt->state;
        $attempt = $claimed;
        try {
            $order = $client->getOrder($attempt->order_id);
            try {
                PaymentProof::verifyOrder($attempt, $order);
            } catch (\DomainException) {
                $this->storeObservedMismatch($attempt, $order, 'order_binding_mismatch');
                return;
            }
            if (($order['status'] ?? '') === 'COMPLETED') {
                $this->storeCapture($attempt, $order, 'order_lookup', $previousState === 'cancelled');
                if ($previousState !== 'cancelled') {
                    $this->finalize($bookingId);
                }
                return;
            }
            if ($previousState === 'cancelled' || $previousState === 'manual') {
                $this->change($attempt, ['state' => $previousState, 'lease_id' => null, 'lease_expires' => null]);
                return;
            }
            $inspection = $this->integrity->inspect($bookingId);
            $this->attempts->assertBinding($attempt, $inspection, $config);
            if ((int) $inspection['booking']->state !== 3) {
                throw new \DomainException('Native pending state changed.');
            }
            $captures = $order['purchase_units'][0]['payments']['captures'] ?? [];
            if ($cancel) {
                if (empty($attempt->capture_started) && $captures === [] && in_array($order['status'] ?? '', ['CREATED','SAVED','PAYER_ACTION_REQUIRED','APPROVED','VOIDED'], true)) {
                    $this->cancelLocal($attempt, $config);
                } else {
                    $this->change($attempt, ['state' => 'cancel_unknown', 'lease_id' => null, 'lease_expires' => null, 'manual_reason' => 'capture_may_be_in_flight']);
                }
                return;
            }
            if (($order['status'] ?? '') !== 'APPROVED' || $captures !== []) {
                $this->change($attempt, ['state' => $captures === [] && empty($attempt->capture_started) ? 'order_created' : 'capture_unknown',
                    'lease_id' => null, 'lease_expires' => null, 'provider_status' => (string) ($order['status'] ?? '')]);
                return;
            }
            if (!empty($attempt->capture_started) && $attempt->capture_started < (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('-55 minutes')->format('Y-m-d H:i:s.u')) {
                $this->manual($attempt, 'capture_retry_window_expired');
                return;
            }
            $attempt = $this->change($attempt, ['capture_started' => $attempt->capture_started ?: PaymentAttemptRepository::now()]);
            PaymentIntegrationContract::assertQualified();
            // Always the same persisted single-CAPTURE order and key; never authorize, patch, or create a replacement.
            $response = $client->captureOrder($attempt->order_id, $attempt->capture_key);
            $this->storeCapture($attempt, $response, 'capture_response');
            $this->finalize($bookingId);
        } catch (PaymentProviderException) {
            // A subsequent invocation starts with GET. No release, activation, or replacement payment.
            $this->attempts->transition($attempt, ['state' => $cancel ? 'cancel_unknown' : 'capture_unknown', 'lease_id' => null, 'lease_expires' => null, 'manual_reason' => 'provider_response_unknown']);
        } catch (\DomainException) {
            $this->manual($attempt, 'local_or_provider_binding_mismatch');
        }
    }

    /** Verify the complete webhook through PayPal before looking at its resource as a reconciliation hint. */
    public function webhook(string $providerId, array $event, array $headers): void
    {
        $config = ($this->providerResolver)($providerId);
        $client = ($this->clientFactory)($config);
        if (!$client->verifyWebhook($event, $headers, $config->webhookId)) {
            throw new \RuntimeException('Unauthenticated payment notification.', 403);
        }
        if (!in_array($event['event_type'] ?? '', ['CHECKOUT.ORDER.APPROVED','PAYMENT.CAPTURE.COMPLETED','PAYMENT.CAPTURE.DENIED','PAYMENT.CAPTURE.PENDING'], true)) {
            return;
        }
        $orderId = ($event['event_type'] ?? '') === 'CHECKOUT.ORDER.APPROVED'
            ? (string) ($event['resource']['id'] ?? '') : (string) ($event['resource']['supplementary_data']['related_ids']['order_id'] ?? '');
        PayPalOrdersClient::checkId($orderId);
        $attempt = $this->attempts->getForOrder($providerId, $orderId);
        if ($attempt === null) {
            return;
        }
        $this->assertAccount($attempt, $config);
        $this->reconcile((int) $attempt->booking_id, $orderId);
    }

    /** No financial I/O. Capture evidence was committed by storeCapture before entering this method. */
    public function finalize(int $bookingId): void
    {
        $attempt = $this->attempts->getForBooking($bookingId);
        if ($attempt === null || $attempt->state === 'finalized') {
            return;
        }
        if (!PaymentProof::hasDurableCapture($attempt)) {
            throw new \DomainException('Native activation requires durable capture proof.');
        }
        $attempt = $this->attempts->claim($attempt, ['captured','finalize_failed'], 'finalizing');
        if ($attempt === null) {
            return;
        }
        $config = ($this->providerResolver)($attempt->provider_id);
        $this->db->transactionStart();
        try {
            $before = $this->integrity->inspect($bookingId, true);
            $locked = $this->attempts->getForBooking($bookingId, true);
            if ($locked === null || $locked->state !== 'finalizing' || $locked->lease_id !== $attempt->lease_id) {
                throw new \DomainException('Finalization lease changed.');
            }
            $this->attempts->assertBinding($locked, $before, $config);
            if (!PaymentProof::hasDurableCapture($locked) || (int) $before['booking']->state !== 3) {
                throw new \DomainException('Invalid native activation state.');
            }
            $scope = $this->saveNative($before, 1, $locked->capture_id, true);
            $after = $this->integrity->inspect($bookingId, true);
            $this->attempts->assertBinding($locked, $after, $config);
            if (!$after['valid'] || (int) $after['booking']->state !== 1 || $after['booking']->transaction_id !== $locked->capture_id) {
                throw new \DomainException('Native activation postconditions failed.');
            }
            $this->outbox->enqueue($after, $scope['messages'], $locked);
            $this->change($locked, ['state' => 'finalized', 'finalized_at' => PaymentAttemptRepository::now(), 'lease_id' => null, 'lease_expires' => null, 'manual_reason' => null]);
            $this->db->transactionCommit();
        } catch (\Throwable $error) {
            $this->db->transactionRollback();
            // CAS cannot overwrite a commit which actually succeeded but whose acknowledgement was lost.
            $this->attempts->transition($attempt, ['state' => 'finalize_failed', 'lease_id' => null, 'lease_expires' => null, 'manual_reason' => 'native_finalize_failed']);
            throw new \RuntimeException('Payment captured; native finalization requires recovery.');
        }
        // Delivery failures never undo or misreport the committed financial/native result.
        try {
            $this->outbox->dispatchBooking($bookingId);
        } catch (\Throwable) {
            // Persisted ready/sending jobs remain available to the authorized worker/reconciliation path.
        }
    }

    private function saveNative(array $inspection, int $state, string $reference, bool $collect): array
    {
        $booking = $inspection['booking'];
        $input = $this->app->getInput();
        $original = [];
        foreach (['b_id','token','dptoken'] as $key) {
            $original[$key] = $input->get($key, null, 'raw');
        }
        try {
            if (!preg_match('/^[a-f0-9]{64}$/D', (string) $booking->token)) {
                throw new \DomainException('Native booking capability missing.');
            }
            $input->set('b_id', (int) $booking->id);
            $input->set('token', $booking->token);
            $input->set('dptoken', $booking->token);
            $factory = $this->app->bootComponent('dpcalendar')->getMVCFactory();
            $model = $factory->createModel('Booking', 'Administrator', ['ignore_request' => true, 'event_after_save' => 'dontusethisevent']);
            // Component webhook/admin recovery routes have another option/menu; keep native booking settings.
            $model->setState('params', clone \Joomla\CMS\Component\ComponentHelper::getParams('com_dpcalendar'));
            $native = $model->getItem(['token' => $booking->token]);
            $ids = array_map(static fn (object $ticket): int => (int) $ticket->id, (array) ($native->tickets ?? []));
            $expected = array_map(static fn (object $ticket): int => (int) $ticket->id, $inspection['tickets']);
            sort($ids, SORT_NUMERIC);
            sort($expected, SORT_NUMERIC);
            if (!$native || (int) $native->id !== (int) $booking->id || $ids !== $expected) {
                throw new \DomainException('Native full ticket context changed.');
            }
            $table = $model->getTable('Booking');
            if (!$table->load($booking->id)) {
                throw new \DomainException('Native booking missing.');
            }
            $data = $table->getData();
            $data['state'] = $state;
            $data['transaction_id'] = $reference;
            if ($state === 6) {
                // Native customer abort authorizes this same pending booking and deletes it.
                // Preserve its audit records instead. SetupForUpdate only permits cancellation of
                // active bookings, so this verified uncharged operation uses the public native tables.
                // This branch never applies to paid/active bookings and never changes event capacity.
                if ((int) $booking->state !== 3 || !$table->save($data)) {
                    throw new \RuntimeException('Native pending cancellation save failed.');
                }
                foreach ($inspection['tickets'] as $ticket) {
                    $ticketTable = $factory->createTable('Ticket', 'Administrator');
                    if (!$ticketTable->load($ticket->id) || (int) $ticketTable->booking_id !== (int) $booking->id
                        || (int) $ticketTable->user_id !== (int) $booking->user_id || (int) $ticketTable->state !== 3) {
                        throw new \DomainException('Native pending cancellation ticket context changed.');
                    }
                    $ticketTable->state = 6;
                    if (!$ticketTable->check() || !$ticketTable->store()) {
                        throw new \RuntimeException('Native pending cancellation ticket save failed.');
                    }
                }
                return ['result' => true, 'messages' => [], 'bookingId' => (int) $booking->id];
            }
            return PaymentMailScope::run((int) $booking->id, (int) $booking->user_id, $collect, static function () use ($model, $data): bool {
                if (!$model->save($data, true)) {
                    throw new \RuntimeException('Native booking save failed.');
                }
                return true;
            });
        } finally {
            foreach ($original as $key => $value) {
                $input->set($key, $value);
            }
        }
    }

    private function cancelLocal(object $attempt, PaymentProviderConfig $config): void
    {
        $this->db->transactionStart();
        try {
            $inspection = $this->integrity->inspect((int) $attempt->booking_id, true);
            $locked = $this->attempts->getForBooking((int) $attempt->booking_id, true);
            if (!$locked || $locked->state !== 'cancel_check' || $locked->lease_id !== $attempt->lease_id || !empty($locked->capture_started) || !empty($locked->capture_id)) {
                throw new \DomainException('Cancellation lost its exclusive uncharged lease.');
            }
            $this->attempts->assertBinding($locked, $inspection, $config);
            if ((int) $inspection['booking']->state !== 3) {
                throw new \DomainException('Native cancellation state changed.');
            }
            $this->saveNative($inspection, 6, (string) ($locked->order_id ?? ''), false);
            $booking = $this->db->setQuery('SELECT state,user_id FROM #__dpcalendar_bookings WHERE id = ' . (int) $locked->booking_id)->loadObject();
            $tickets = $this->db->setQuery('SELECT id,state,user_id FROM #__dpcalendar_tickets WHERE booking_id = ' . (int) $locked->booking_id . ' ORDER BY id')->loadObjectList();
            $ids = array_map(static fn (object $t): int => (int) $t->id, $tickets);
            $expected = array_map(static fn (object $t): int => (int) $t->id, $inspection['tickets']);
            sort($expected, SORT_NUMERIC);
            if (!$booking || (int) $booking->state !== 6 || (int) $booking->user_id !== (int) $locked->owner_id || $ids !== $expected) {
                throw new \DomainException('Cancellation postconditions failed.');
            }
            foreach ($tickets as $ticket) {
                if ((int) $ticket->state !== 6 || (int) $ticket->user_id !== (int) $locked->owner_id) {
                    throw new \DomainException('Cancellation ticket postconditions failed.');
                }
            }
            $cartId = (int) $locked->cart_id;
            $now = PaymentAttemptRepository::now();
            // Do not call the older release helper: its transaction would commit this outer operation.
            $query = $this->db->getQuery(true)->update('#__copymypage_event_seats')
                ->set('status = ' . EventSeatInventoryService::SEAT_STATUS_AVAILABLE)
                ->set('cart_id = NULL')->set('price_index = NULL')->set('assignment_order = NULL')->set('ticket_id = NULL')
                ->set('modified = :now')->where('cart_id = :cart')->where('status = ' . EventSeatInventoryService::SEAT_STATUS_BOOKED)
                ->bind(':now', $now)->bind(':cart', $cartId, ParameterType::INTEGER);
            $this->db->setQuery($query)->execute();
            if ($this->db->getAffectedRows() !== count($inspection['seats'])) {
                throw new \DomainException('Cancellation seat release failed.');
            }
            $query = $this->db->getQuery(true)->update('#__copymypage_ticket_carts')
                ->set('status = ' . TicketCartContextService::STATUS_RELEASED)->set('revision = revision + 1')->set('modified = :now')
                ->where('id = :cart')->where('status = ' . TicketCartContextService::STATUS_CONVERTED)
                ->bind(':now', $now)->bind(':cart', $cartId, ParameterType::INTEGER);
            $this->db->setQuery($query)->execute();
            if ($this->db->getAffectedRows() !== 1) {
                throw new \DomainException('Cancellation cart release failed.');
            }
            $this->change($locked, ['state' => 'cancelled', 'lease_id' => null, 'lease_expires' => null, 'manual_reason' => null]);
            $this->db->transactionCommit();
        } catch (\Throwable $error) {
            $this->db->transactionRollback();
            $this->attempts->transition($attempt, ['state' => 'cancel_unknown', 'lease_id' => null, 'lease_expires' => null, 'manual_reason' => 'native_cancel_failed']);
            throw new \RuntimeException('Cancellation requires recovery.');
        }
    }

    private function storeCapture(object $attempt, array $order, string $source, bool $cancelled = false): void
    {
        try {
            $capture = PaymentProof::verifyCapture($attempt, $order);
        } catch (\DomainException) {
            $this->storeObservedMismatch($attempt, $order, 'capture_proof_mismatch');
            return;
        }
        $this->change($attempt, ['capture_id' => $capture['id'], 'provider_status' => 'COMPLETED',
            'provider_evidence' => PaymentProof::evidence($attempt, $order, $source, true),
            'captured_at' => $attempt->captured_at ?: PaymentAttemptRepository::now(),
            'state' => $cancelled ? 'manual' : 'captured', 'manual_reason' => $cancelled ? 'capture_after_local_cancel' : null,
            'lease_id' => null, 'lease_expires' => null]);
        // Autocommit completes here, before the separate native transaction begins.
    }

    private function storeObservedMismatch(object $attempt, array $order, string $reason): void
    {
        $this->change($attempt, ['state' => 'manual', 'provider_status' => substr((string) ($order['status'] ?? 'UNKNOWN'), 0, 32),
            'provider_evidence' => PaymentProof::evidence($attempt, $order, 'server_lookup_or_capture', false),
            'manual_reason' => $reason, 'lease_id' => null, 'lease_expires' => null]);
    }

    private function change(object $attempt, array $changes): object
    {
        return $this->attempts->transition($attempt, $changes) ?? throw new \RuntimeException('Payment state changed concurrently; reconciliation required.');
    }

    private function manual(object $attempt, string $reason): void
    {
        $this->attempts->transition($attempt, ['state' => 'manual', 'manual_reason' => $reason, 'lease_id' => null, 'lease_expires' => null]);
    }

    private function assertAccount(object $attempt, PaymentProviderConfig $config): void
    {
        if ($attempt->provider_id !== $config->id || !hash_equals($attempt->provider_account_hash, $config->accountHash)
            || $attempt->expected_payee !== $config->merchantId || (bool) $attempt->sandbox !== $config->sandbox) {
            throw new \DomainException('Payment provider account changed.');
        }
    }

    private function createBody(object $attempt, object $booking): array
    {
        $return = $attempt->return_base_uri . 'index.php?' . http_build_query(['option' => 'com_dpcalendar', 'task' => 'booking.pay', 'b_id' => (int) $booking->id, 'dptoken' => $booking->token], '', '&', PHP_QUERY_RFC3986);
        $cancel = $attempt->return_base_uri . 'index.php?' . http_build_query(['option' => 'com_dpcalendar', 'task' => 'booking.paycancel', 'b_id' => (int) $booking->id, 'dptoken' => $booking->token], '', '&', PHP_QUERY_RFC3986);
        $body = ['intent' => 'CAPTURE', 'purchase_units' => [[
            'reference_id' => $attempt->id, 'custom_id' => PaymentProof::reference($attempt), 'invoice_id' => PaymentProof::invoice($attempt),
            'amount' => ['currency_code' => $attempt->expected_currency, 'value' => PaymentAmount::forApi($attempt->expected_amount, $attempt->expected_currency)],
            'payee' => ['merchant_id' => $attempt->expected_payee],
        ]], 'payment_source' => ['paypal' => ['experience_context' => ['return_url' => $return, 'cancel_url' => $cancel, 'user_action' => 'PAY_NOW', 'shipping_preference' => 'NO_SHIPPING']]]];
        // A retry of an already started Create must retain its exact persisted payload.
        if (!empty($attempt->create_payload_hash)
            && hash_equals($attempt->create_payload_hash, hash('sha256', json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)))) {
            return $body;
        }
        $body['payment_source']['paypal']['experience_context']['return_url'] = $this->callbackUrl($attempt->return_base_uri, $booking, 'booking.pay');
        $body['payment_source']['paypal']['experience_context']['cancel_url'] = $this->callbackUrl($attempt->return_base_uri, $booking, 'booking.paycancel');
        return $body;
    }

    private function callbackUrl(string $base, object $booking, string $task): string
    {
        $uri = new Uri($base);
        // These names belong to PayPal's browser return, never the booking capability.
        if ($uri->hasVar('token') || $uri->hasVar('PayerID')) {
            throw new \DomainException('Reserved PayPal callback parameter in return base.');
        }
        $root = rtrim(Uri::root(true), '/') . '/';
        $path = $uri->getPath() ?: '/';
        if (!str_starts_with($path, $root)) {
            throw new \DomainException('Payment return base is outside the Joomla site path.');
        }
        $route = trim(substr($path, strlen($root)), '/');
        $route = preg_replace('~^index\.php(?:/|$)~', '', $route);
        $sef = (bool) $this->app->get('sef', 1);
        if (!$sef && $route !== '') {
            if (!isset(LanguageHelper::getLanguages('sef')[$route])) {
                throw new \DomainException('Non-SEF payment return base requires a site or language root.');
            }
            if (!$uri->hasVar('lang')) {
                $uri->setVar('lang', $route);
            }
            $route = '';
        }
        $uri->setPath($root . ($sef && $this->app->get('sef_rewrite', 0)
            ? ($route !== '' ? $route . '/' : '')
            : 'index.php' . ($route !== '' ? '/' . $route . '/' : '')));
        // A root callback must not depend on a browser language cookie or an extra redirect.
        if ($route === '' && !$uri->hasVar('lang') && Multilanguage::isEnabled()) {
            $language = ComponentHelper::getParams('com_languages')->get('site', 'en-GB');
            $languages = LanguageHelper::getLanguages('lang_code');
            if (isset($languages[$language])) {
                $uri->setVar('lang', $languages[$language]->sef);
            }
        }
        foreach (['option' => 'com_dpcalendar', 'task' => $task, 'b_id' => (int) $booking->id, 'dptoken' => $booking->token] as $key => $value) {
            $uri->setVar($key, $value);
        }
        // Keep the authority of the validated, frozen provider base; never use the request host.
        // Uri's query serializer decodes reserved characters; callback values require RFC 3986.
        return $uri->toString(['scheme', 'host', 'port', 'path']) . '?'
            . http_build_query($uri->getQuery(true), '', '&', PHP_QUERY_RFC3986);
    }

    private function approvalUrl(array $order, PaymentProviderConfig $config): ?string
    {
        if (!in_array($order['status'] ?? '', ['CREATED','SAVED','PAYER_ACTION_REQUIRED','APPROVED'], true)) {
            return null;
        }
        foreach ($order['links'] ?? [] as $link) {
            if (in_array($link['rel'] ?? '', ['payer-action','approve'], true) && ($link['method'] ?? 'GET') === 'GET') {
                $url = (string) ($link['href'] ?? '');
                $parts = parse_url($url);
                if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'www.sandbox.paypal.com'
                    || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])) {
                    throw new \DomainException('Unsafe provider approval URL.');
                }
                parse_str((string) ($parts['query'] ?? ''), $query);
                if (($query['token'] ?? '') !== ($order['id'] ?? '')) {
                    throw new \DomainException('Provider approval URL order mismatch.');
                }
                return $url;
            }
        }
        return null;
    }

    private function storeNativeOrderReference(object $attempt): void
    {
        $order = $attempt->order_id;
        $bookingId = (int) $attempt->booking_id;
        $query = $this->db->getQuery(true)->update('#__dpcalendar_bookings')->set('transaction_id = :order')
            ->where('id = :booking')->where('state = 3')->where("(transaction_id IS NULL OR transaction_id = '' OR transaction_id = :sameOrder)")
            ->bind(':order', $order)->bind(':sameOrder', $order)->bind(':booking', $bookingId, ParameterType::INTEGER);
        $this->db->setQuery($query)->execute();
        $native = $this->db->setQuery('SELECT transaction_id FROM #__dpcalendar_bookings WHERE id = ' . $bookingId)->loadResult();
        if ($native !== $order) {
            throw new \DomainException('Native order reference binding failed.');
        }
    }
}
