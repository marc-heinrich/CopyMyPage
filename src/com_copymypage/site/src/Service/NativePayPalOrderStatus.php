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
    private function readOrder(object $booking, string $orderId): array
    {
        if (!preg_match('/^[A-Z0-9]{1,36}$/D', $orderId)
            || !hash_equals((string) $booking->transaction_id, $orderId)) {
            throw new \DomainException('Explicit PayPal order/merchant binding is required.');
        }
        $instance = $this->providers->getInstance((string) $booking->payment_provider);
        // This local implementation is deliberately limited to authorised Sandbox inspection.
        if (!$instance || (string) ($instance['sandbox'] ?? '') !== '1'
            || !\is_string($instance['rest_client_id'] ?? null) || !\is_string($instance['rest_secret'] ?? null)
            || trim($instance['rest_client_id']) === '' || trim($instance['rest_secret']) === '') {
            throw new \DomainException('Verified native Sandbox configuration is required.');
        }
        $clientId = trim($instance['rest_client_id']);
        $secret = trim($instance['rest_secret']);
        $curlOptions = [CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_CONNECTTIMEOUT => 10];
        if (PHP_OS_FAMILY === 'Windows' && \defined('CURLSSLOPT_NATIVE_CA')) {
            $curlOptions[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }
        $http = $this->http ?? (new HttpFactory())->getHttp([
            'timeout' => 20, 'follow_location' => false,
            'transport.curl' => $curlOptions,
        ], ['curl']);
        $base = 'https://api-m.sandbox.paypal.com';
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
        return [$order, hash('sha256', (string) $booking->payment_provider . "\0sandbox\0" . $clientId),
            hash('sha256', $responseBody)];
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
