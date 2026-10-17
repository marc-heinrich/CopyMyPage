<?php
namespace Joomla\Component\CopyMyPage\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Component\CopyMyPage\Site\Service\PayPalPaymentService;
use Joomla\Component\CopyMyPage\Site\Service\PaymentOutboxService;
use Joomla\Component\CopyMyPage\Site\Service\PaymentProviderConfig;

/** Provider-authenticated notification and explicitly authorized synchronous recovery/dispatch. */
final class PaymentController extends BaseController
{
    public function webhook(): void
    {
        $this->requirePost();
        $provider = $this->input->getString('provider', '');
        if (!PaymentProviderConfig::isOwned($provider)) {
            throw new \RuntimeException('Invalid payment provider.', 400);
        }
        $stream = fopen('php://input', 'rb');
        $body = $stream !== false ? stream_get_contents($stream, 262145) : false;
        if (is_resource($stream)) {
            fclose($stream);
        }
        if (!is_string($body) || $body === '' || strlen($body) > 262144) {
            throw new \RuntimeException('Invalid payment notification.', 400);
        }
        try {
            $event = json_decode($body, false, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException('Invalid payment notification.', 400);
        }
        if (!$event instanceof \stdClass) {
            throw new \RuntimeException('Invalid payment notification.', 400);
        }
        $headers = [];
        foreach (['PAYPAL-AUTH-ALGO','PAYPAL-CERT-URL','PAYPAL-TRANSMISSION-ID','PAYPAL-TRANSMISSION-SIG','PAYPAL-TRANSMISSION-TIME'] as $header) {
            $headers[$header] = $this->input->server->getString('HTTP_' . str_replace('-', '_', $header), '');
        }
        Factory::getContainer()->get(PayPalPaymentService::class)->webhook($provider, $body, $headers);
        $this->app->setHeader('Cache-Control', 'no-store', true);
        $this->app->setHeader('Content-Type', 'application/json', true);
        echo '{"accepted":true}';
        $this->app->close();
    }

    public function recover(): void
    {
        $this->requireAdministrator();
        $bookingId = $this->input->post->getInt('booking_id', 0);
        if ($bookingId < 1) {
            throw new \RuntimeException('Invalid payment recovery booking.', 400);
        }
        Factory::getContainer()->get(PayPalPaymentService::class)->recover($bookingId);
        $this->respond();
    }

    public function dispatch(): void
    {
        $this->requireAdministrator();
        $bookingId = $this->input->post->getInt('booking_id', 0);
        if ($bookingId < 1) {
            throw new \RuntimeException('Invalid mail dispatch booking.', 400);
        }
        Factory::getContainer()->get(PaymentOutboxService::class)->dispatchBooking($bookingId);
        $this->respond();
    }

    private function requirePost(): void
    {
        if (strtoupper($this->input->getMethod()) !== 'POST') {
            throw new \RuntimeException('POST required.', 405);
        }
    }

    private function requireAdministrator(): void
    {
        $this->requirePost();
        $token = \Joomla\CMS\Session\Session::getFormToken();
        // Core checkToken redirects a new session and can return true afterwards. This API
        // requires an actually submitted capability before invoking the unchanged core check.
        if ((!$this->input->post->get($token, '', 'alnum')
                && !hash_equals($token, $this->input->server->getString('HTTP_X_CSRF_TOKEN', '')))
            || !$this->checkToken('post', false)) {
            throw new \RuntimeException('Invalid payment recovery token.', 403);
        }
        $user = $this->app->getIdentity();
        if (!$user || $user->guest || !$user->authorise('core.admin', 'com_copymypage')) {
            throw new \RuntimeException('Payment recovery authorization required.', 403);
        }
    }

    private function respond(): void
    {
        $this->app->setHeader('Cache-Control', 'no-store', true);
        $this->app->setHeader('Content-Type', 'application/json', true);
        echo '{"processed":true}';
        $this->app->close();
    }
}
