<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\PluginHelper;

/** A separate provider identity. No interpretation or copying of historic paypal-0 payments. */
final class PaymentProviderConfig
{
    public readonly string $accountHash;

    public function __construct(
        public readonly string $id,
        public readonly string $clientId,
        private readonly string $clientSecret,
        public readonly string $merchantId,
        public readonly bool $sandbox,
        public readonly string $returnBaseUri,
        public readonly string $webhookId = ''
    ) {
        $uri = parse_url($returnBaseUri);
        if (!preg_match('/^copymypagepaypal-[0-9]+$/D', $id)
            || $clientId === '' || $clientSecret === ''
            || !preg_match('/^[A-Z0-9]{8,64}$/D', $merchantId)
            || !is_array($uri) || !in_array($uri['scheme'] ?? '', ['https', 'http'], true)
            || empty($uri['host']) || isset($uri['user']) || isset($uri['pass']) || isset($uri['query']) || isset($uri['fragment'])
            || (($uri['scheme'] ?? '') === 'http' && !in_array($uri['host'], ['localhost','127.0.0.1','[::1]'], true))) {
            throw new \DomainException('Incomplete or invalid CopyMyPage PayPal configuration.');
        }
        // This development authorization includes Sandbox transactions only.
        if (!$sandbox) {
            throw new \DomainException('CopyMyPage PayPal development adapter requires Sandbox.');
        }
        $this->accountHash = hash('sha256', $id . "\0" . $clientId . "\0" . ($sandbox ? 'sandbox' : 'live'));
    }

    public static function isOwned(string $provider): bool
    {
        return preg_match('/^copymypagepaypal-[0-9]+$/D', $provider) === 1;
    }

    public static function load(string $providerId): self
    {
        $plugin = PluginHelper::getPlugin('dpcalendarpay', 'copymypagepaypal');
        $parameters = json_decode((string) ($plugin->params ?? ''), true, 512, JSON_THROW_ON_ERROR);
        foreach ((array) ($parameters['providers'] ?? []) as $provider) {
            if ('copymypagepaypal-' . (int) ($provider['id'] ?? -1) === $providerId && (int) ($provider['state'] ?? 0) === 1) {
                return new self($providerId, trim((string) ($provider['rest_client_id'] ?? '')), (string) ($provider['rest_secret'] ?? ''), trim((string) ($provider['merchant_id'] ?? '')), (bool) ($provider['sandbox'] ?? true), rtrim(trim((string) ($provider['return_base_uri'] ?? '')), '/') . '/', trim((string) ($provider['webhook_id'] ?? '')));
            }
        }
        throw new \DomainException('CopyMyPage payment provider is unavailable.');
    }

    public function secret(): string
    {
        return $this->clientSecret;
    }

    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'sandbox' => $this->sandbox, 'credentials' => '[redacted]'];
    }
}
