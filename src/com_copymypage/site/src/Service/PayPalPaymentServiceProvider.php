<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

final class PayPalPaymentServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        if (!$container->has(PaymentAttemptRepository::class)) {
            $container->share(PaymentAttemptRepository::class, static fn (Container $c): PaymentAttemptRepository => new PaymentAttemptRepository($c->get(DatabaseInterface::class), $c->get(BookingIntegrityService::class)), true);
        }
        if (!$container->has(PaymentOutboxService::class)) {
            $container->share(PaymentOutboxService::class, static fn (Container $c): PaymentOutboxService => new PaymentOutboxService($c->get(DatabaseInterface::class), Factory::getApplication(), $c->get(BookingIntegrityService::class), $c->get(PaymentAttemptRepository::class), $c->get(MailerFactoryInterface::class)), true);
        }
        if (!$container->has(PayPalPaymentService::class)) {
            $container->share(PayPalPaymentService::class, static fn (Container $c): PayPalPaymentService => new PayPalPaymentService(Factory::getApplication(), $c->get(DatabaseInterface::class), $c->get(BookingIntegrityService::class), $c->get(PaymentAttemptRepository::class), $c->get(PaymentOutboxService::class)), true);
        }
    }
}
