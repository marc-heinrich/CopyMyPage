<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Http\Http;
use Joomla\Http\HttpFactory;

/** Official Orders v2 endpoints with per-operation persisted PayPal-Request-Id headers. */
final class PayPalOrdersClient implements PayPalOrdersClientInterface
{
    private readonly Http $http;
    private string $accessToken = '';

    public function __construct(private readonly PaymentProviderConfig $provider, ?Http $http = null)
    {
        $this->http = $http ?? (new HttpFactory())->getHttp([
            'timeout' => 20,
            'follow_location' => false,
            'transport.curl' => [CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_CONNECTTIMEOUT => 10],
        ], ['curl']);
    }

    public function createOrder(array $body, string $requestId): array
    {
        return $this->request('POST', '/v2/checkout/orders', $body, $requestId);
    }

    public function getOrder(string $orderId): array
    {
        self::checkId($orderId);

        return $this->request('GET', '/v2/checkout/orders/' . $orderId);
    }

    public function captureOrder(string $orderId, string $requestId): array
    {
        self::checkId($orderId);

        return $this->request('POST', '/v2/checkout/orders/' . $orderId . '/capture', [], $requestId);
    }

    public function verifyWebhook(array $event, array $headers, string $webhookId): bool
    {
        if ($webhookId === '' || !isset($event['id'], $event['event_type'], $event['resource'])) {
            return false;
        }
        $body = ['webhook_id' => $webhookId, 'webhook_event' => $event];
        foreach (['auth_algo' => 'PAYPAL-AUTH-ALGO', 'cert_url' => 'PAYPAL-CERT-URL', 'transmission_id' => 'PAYPAL-TRANSMISSION-ID', 'transmission_sig' => 'PAYPAL-TRANSMISSION-SIG', 'transmission_time' => 'PAYPAL-TRANSMISSION-TIME'] as $field => $header) {
            $value = $headers[$header] ?? null;
            if (!is_string($value) || $value === '' || strlen($value) > 4096 || strpbrk($value, "\r\n") !== false) {
                return false;
            }
            $body[$field] = $value;
        }
        $certificate = parse_url($body['cert_url']);
        if (($certificate['scheme'] ?? '') !== 'https' || !in_array($certificate['host'] ?? '', ['api.paypal.com', 'api-m.paypal.com', 'api.sandbox.paypal.com', 'api-m.sandbox.paypal.com'], true)) {
            return false;
        }
        $response = $this->request('POST', '/v1/notifications/verify-webhook-signature', $body);

        return ($response['verification_status'] ?? '') === 'SUCCESS';
    }

    public static function checkId(string $id): void
    {
        if (!preg_match('/^[A-Z0-9]{1,36}$/D', $id)) {
            throw new \DomainException('Invalid PayPal resource identifier.');
        }
    }

    private function request(string $method, string $path, ?array $body = null, ?string $requestId = null): array
    {
        $headers = ['Authorization' => 'Bearer ' . $this->token(), 'Content-Type' => 'application/json', 'Accept' => 'application/json', 'Prefer' => 'return=representation'];
        if ($requestId !== null) {
            if (!preg_match('/^[a-f0-9-]{36}$/D', $requestId)) {
                throw new \DomainException('Invalid persisted PayPal request ID.');
            }
            $headers['PayPal-Request-Id'] = $requestId;
        }
        try {
            $response = $method === 'GET'
                ? $this->http->get('https://api-m.sandbox.paypal.com' . $path, $headers, 20)
                : $this->http->post('https://api-m.sandbox.paypal.com' . $path, $body === [] ? '{}' : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $headers, 20);
        } catch (\Throwable) {
            throw new PaymentProviderException('transport_unknown');
        }
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            // Even an error response after Capture requires a server lookup before releasing inventory.
            throw new PaymentProviderException('http_' . $status);
        }
        try {
            $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new \UnexpectedValueException();
            }

            return $decoded;
        } catch (\Throwable) {
            throw new PaymentProviderException('response_unknown');
        }
    }

    private function token(): string
    {
        if ($this->accessToken !== '') {
            return $this->accessToken;
        }
        try {
            $response = $this->http->post('https://api-m.sandbox.paypal.com/v1/oauth2/token', 'grant_type=client_credentials', [
                'Authorization' => 'Basic ' . base64_encode($this->provider->clientId . ':' . $this->provider->secret()),
                'Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json',
            ], 20);
            $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if ($response->getStatusCode() !== 200 || empty($decoded['access_token']) || ($decoded['token_type'] ?? '') !== 'Bearer') {
                throw new \UnexpectedValueException();
            }
            $this->accessToken = (string) $decoded['access_token'];

            return $this->accessToken;
        } catch (\Throwable) {
            throw new PaymentProviderException('authentication_failed', false);
        }
    }

    public function __debugInfo(): array
    {
        return ['provider' => $this->provider->id, 'authentication' => '[redacted]'];
    }
}
