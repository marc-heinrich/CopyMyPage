<?php
namespace Joomla\Plugin\DPCalendarPay\CopyMyPagePayPal\Extension;

\defined('_JEXEC') or die;

use DigitalPeak\Component\DPCalendar\Administrator\Plugin\PaymentPlugin;
use Joomla\CMS\Factory;
use Joomla\Component\CopyMyPage\Site\Service\PayPalPaymentService;
use Joomla\Component\CopyMyPage\Site\Service\PaymentAttemptRepository;
use Joomla\Component\CopyMyPage\Site\Service\PaymentProviderConfig;

/** Separate native provider identity; protected CMP start/callback own the lifecycle. */
final class CopyMyPagePayPal extends PaymentPlugin
{
    public function onDPPaymentProviders(): array
    {
        $providers = [];
        $seen = [];
        foreach (parent::onDPPaymentProviders() as $provider) {
            if (isset($seen[$provider->id])) {
                throw new \DomainException('Duplicate CopyMyPage payment provider identity.');
            }
            $seen[$provider->id] = true;
            // Invalid/unconfigured/live providers must not become selectable.
            try {
                new PaymentProviderConfig(
                    $provider->id, trim((string) ($provider->rest_client_id ?? '')),
                    (string) ($provider->rest_secret ?? ''), trim((string) ($provider->merchant_id ?? '')),
                    (bool) ($provider->sandbox ?? false), rtrim(trim((string) ($provider->return_base_uri ?? '')), '/') . '/',
                    trim((string) ($provider->webhook_id ?? ''))
                );
                $providers[] = $provider;
            } catch (\DomainException) {
                // Standard Joomla plugin configuration can be saved before credentials are complete.
            }
        }
        return $providers;
    }

    public function onDPPaymentNew(\stdClass $booking): false|string
    {
        // Starting a provider from the passive vendor template would bypass CMP's one-use handoff.
        // CMP's pay override calls the shared adapter only after authorization and exclusive first-start claim.
        return PaymentProviderConfig::isOwned((string) $booking->payment_provider) ? '' : false;
    }

    public function onDPPaymentCallBack(\stdClass $booking, array $data): bool
    {
        if (!PaymentProviderConfig::isOwned((string) $booking->payment_provider)) {
            return false;
        }
        // The system guard supplies full booking context. Never use the native base callback's unchecked save.
        $input = $this->getApplication()->getInput();
        $capability = $input->getString('dptoken', '');
        if ($capability === '' || empty($booking->token) || !hash_equals((string) $booking->token, $capability)) {
            throw new \RuntimeException('Owned payment callback authorization missing.', 403);
        }
        Factory::getContainer()->get(PayPalPaymentService::class)->reconcile((int) $booking->id, isset($data['token']) ? (string) $data['token'] : null);
        return Factory::getContainer()->get(PaymentAttemptRepository::class)->isFinalized((int) $booking->id);
    }

    protected function finishTransaction(array $data, \stdClass $booking, \stdClass $paymentProvider): array
    {
        throw new \LogicException('Owned payment never uses unchecked native finalization.');
    }
}
