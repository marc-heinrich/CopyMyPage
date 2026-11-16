<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Http\Http;
use Joomla\Http\HttpFactory;

/** Read-only Orders v2 proof. No create/capture/void/refund methods or provider callbacks. */
final class NativePayPalOrderStatus
{
    public function __construct(private readonly NativePayPalProviderRegistry $providers, private readonly ?Http $http = null)
    {
    }

    /**
     * Not a terminal provider exclusion. The caller must hold the native booking
     * lock, exclude every prior capture attempt and atomically close the local
     * capture entry points by completing native deletion before releasing it.
     */
    public function verifyUncapturedWalletOrder(object $booking): array
    {
        $orderId = (string) $booking->transaction_id;
        [$order, $accountHash, $responseHash, $environment] = $this->readOrder($booking, $orderId, false, 3);
        $unit = $this->boundUnit($booking, $order, $orderId);
        if (!\in_array($order['status'] ?? null, ['CREATED', 'PAYER_ACTION_REQUIRED', 'VOIDED'], true)
            || !\is_array($order['payment_source'] ?? null) || array_keys($order['payment_source']) !== ['paypal']
            || !\is_array($order['payment_source']['paypal'])
            || !\is_string($unit['payee']['merchant_id'] ?? null)
            || !preg_match('/^[A-Z0-9]{8,64}$/D', $unit['payee']['merchant_id'])
            || (isset($order['processing_instruction']) && $order['processing_instruction'] !== 'NO_INSTRUCTION')
            || (array_key_exists('payments', $unit) && (!\is_array($unit['payments']) || $unit['payments'] !== []))) {
            throw new \DomainException('PayPal wallet order is not verified uncaptured.');
        }
        return ['status' => $order['status'], 'environment' => $environment,
            'orderHash' => hash('sha256', $orderId), 'accountHash' => $accountHash,
            'responseHash' => $responseHash, 'checkedAt' => gmdate('Y-m-d H:i:s')];
    }

    public function verifyExclusion(object $booking, string $orderId, string $merchantId): array
    {
        if (!preg_match('/^[A-Z0-9]{8,64}$/D', $merchantId)) {
            throw new \DomainException('Explicit PayPal order/merchant binding is required.');
        }
        [$order, $accountHash, $responseHash] = $this->readOrder($booking, $orderId);
        $unit = $this->boundUnit($booking, $order, $orderId);
        if (($order['status'] ?? null) !== 'VOIDED') {
            throw new \DomainException('PayPal payment is not proven terminal and excluded.');
        }
        // Any payment resource requires review, including failed captures and voided authorizations.
        if (($unit['payee']['merchant_id'] ?? null) !== $merchantId
            || (array_key_exists('payments', $unit) && (!\is_array($unit['payments']) || $unit['payments'] !== []))) {
            throw new \DomainException('PayPal terminal proof has payment resources or a binding mismatch.');
        }
        return ['provider' => (string) $booking->payment_provider, 'environment' => 'sandbox', 'status' => 'VOIDED',
            'orderHash' => hash('sha256', $orderId), 'merchantHash' => hash('sha256', $merchantId),
            'accountHash' => $accountHash, 'responseHash' => $responseHash, 'checkedAt' => gmdate('Y-m-d H:i:s')];
    }

    /** Read-only review information, never a release proof. No payer data, credentials or raw response. */
    public function inspect(object $booking, string $orderId): array
    {
        [$order, $accountHash, $responseHash] = $this->readOrder($booking, $orderId);
        $unit = $this->boundUnit($booking, $order, $orderId);
        if (!\is_string($order['status'] ?? null)
            || !\is_string($unit['payee']['merchant_id'] ?? null)
            || !preg_match('/^[A-Z0-9]{8,64}$/D', $unit['payee']['merchant_id'])
            || (array_key_exists('payments', $unit) && !\is_array($unit['payments']))) {
            throw new \DomainException('PayPal inspection response is incomplete; reservation retained.');
        }
        $resources = [];
        foreach (($unit['payments'] ?? []) as $kind => $payments) {
            if (!\is_array($payments) || !array_is_list($payments)) {
                throw new \DomainException('PayPal inspection resources require review.');
            }
            $resources[$kind] = array_map(static fn(array $payment): array => [
                'id' => $payment['id'] ?? null, 'status' => $payment['status'] ?? null,
                'amount' => $payment['amount'] ?? null,
            ], $payments);
        }
        return ['bookingId' => (int) $booking->id, 'provider' => (string) $booking->payment_provider,
            'environment' => 'sandbox', 'orderId' => $orderId, 'status' => $order['status'], 'intent' => 'CAPTURE',
            'merchantId' => $unit['payee']['merchant_id'], 'amount' => $unit['amount'], 'payments' => $resources,
            'createdAt' => $order['create_time'] ?? null, 'updatedAt' => $order['update_time'] ?? null,
            'accountHash' => $accountHash, 'responseHash' => $responseHash, 'checkedAt' => gmdate('Y-m-d H:i:s')];
    }

    /** Internal response only; callers must validate the booking/purchase-unit association. */
    private function readOrder(object $booking, string $orderId, bool $sandboxOnly = true, int $timeout = 20): array
    {
        if (!preg_match('/^[A-Z0-9]{1,36}$/D', $orderId)
            || !hash_equals((string) $booking->transaction_id, $orderId)) {
            throw new \DomainException('Explicit PayPal order/merchant binding is required.');
        }
        $instance = $this->providers->getInstance((string) $booking->payment_provider);
        // Administrative inspection/exclusion stays Sandbox-only. Automatic
        // cleanup uses the exact installed native instance, including live mode.
        if (!$instance || ($sandboxOnly && (string) ($instance['sandbox'] ?? '') !== '1')
            || !\in_array($instance['sandbox'] ?? null, [false, true, 0, 1, '0', '1'], true)
            || !\is_string($instance['rest_client_id'] ?? null) || !\is_string($instance['rest_secret'] ?? null)
            || trim($instance['rest_client_id']) === '' || trim($instance['rest_secret']) === '') {
            throw new \DomainException('Verified native PayPal configuration is required.');
        }
        $clientId = trim($instance['rest_client_id']);
        $secret = trim($instance['rest_secret']);
        $environment = (bool) $instance['sandbox'] ? 'sandbox' : 'live';
        $curlOptions = [CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_CONNECTTIMEOUT => min(10, $timeout)];
        if (PHP_OS_FAMILY === 'Windows' && \defined('CURLSSLOPT_NATIVE_CA')) {
            $curlOptions[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }
        $http = $this->http ?? (new HttpFactory())->getHttp([
            'timeout' => $timeout, 'follow_location' => false,
            'transport.curl' => $curlOptions,
        ], ['curl']);
        $base = $environment === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        try {
            $tokenResponse = $http->post($base . '/v1/oauth2/token', 'grant_type=client_credentials', [
                'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]);
            $token = json_decode((string) $tokenResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if ($tokenResponse->getStatusCode() !== 200 || !\is_string($token['access_token'] ?? null)
                || $token['access_token'] === '') {
                throw new \RuntimeException();
            }
            $response = $http->get($base . '/v2/checkout/orders/' . $orderId,
                ['Authorization' => 'Bearer ' . $token['access_token']]);
            $responseBody = (string) $response->getBody();
            $order = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            if ($response->getStatusCode() !== 200 || !\is_array($order)) {
                throw new \RuntimeException();
            }
        } catch (\Throwable) {
            // Transport exceptions may contain request headers. Never retain them as previous exceptions.
            throw new \DomainException('PayPal status could not be verified; reservation retained.');
        }
        return [$order, hash('sha256', (string) $booking->payment_provider . "\0" . $environment . "\0" . $clientId),
            hash('sha256', $responseBody), $environment];
    }

    private function boundUnit(object $booking, array $order, string $orderId): array
    {
        $units = $order['purchase_units'] ?? null;
        if (($order['id'] ?? null) !== $orderId || ($order['intent'] ?? null) !== 'CAPTURE'
            || !\is_array($units) || !array_is_list($units) || \count($units) !== 1) {
            throw new \DomainException('PayPal order binding could not be verified.');
        }
        $unit = reset($units);
        if (!\is_array($unit) || !\is_string($unit['description'] ?? null)
            || !str_starts_with($unit['description'], '[' . (int) $booking->id . '] ')
            || ($unit['amount']['currency_code'] ?? null) !== (string) $booking->currency
            || !self::sameAmount($unit['amount']['value'] ?? null, $booking->price)) {
            throw new \DomainException('PayPal booking, amount or currency binding mismatch.');
        }
        return $unit;
    }

    private static function sameAmount(mixed $actual, mixed $expected): bool
    {
        $normalise = static function (mixed $value): ?string {
            if (!\is_scalar($value) || !preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', (string) $value)) {
                return null;
            }
            [$whole, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');
            return (ltrim($whole, '0') ?: '0') . '.' . rtrim($fraction, '0');
        };
        return $normalise($actual) !== null && $normalise($actual) === $normalise($expected);
    }
}
