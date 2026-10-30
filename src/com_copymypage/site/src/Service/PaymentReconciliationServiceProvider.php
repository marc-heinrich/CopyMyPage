<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

/**
 * Registers the shared payment reconciliation service in a Joomla DI container.
 *
 * @since  0.0.19
 */
final class PaymentReconciliationServiceProvider implements ServiceProviderInterface
{
    /**
     * @since   0.0.19
     */
    public function register(Container $container): void
    {
        if (!$container->has(BookingIntegrityService::class)) {
            $container->share(BookingIntegrityService::class,
                static fn(Container $c): BookingIntegrityService => new BookingIntegrityService($c->get(DatabaseInterface::class)), true);
        }
        if (!$container->has(NativePayPalProviderRegistry::class)) {
            $container->share(NativePayPalProviderRegistry::class,
                static fn(Container $c): NativePayPalProviderRegistry => new NativePayPalProviderRegistry($c->get(DatabaseInterface::class)), true);
        }
        if (!$container->has(NativeReservationService::class)) {
            $container->share(NativeReservationService::class,
                static fn(Container $c): NativeReservationService => new NativeReservationService(
                    $c->get(DatabaseInterface::class), $c->get(NativePayPalProviderRegistry::class),
                    $c->get(BookingIntegrityService::class)), true);
        }
        if (!$container->has(NativePayPalOrderStatus::class)) {
            $container->share(NativePayPalOrderStatus::class,
                static fn(Container $c): NativePayPalOrderStatus => new NativePayPalOrderStatus(
                    $c->get(NativePayPalProviderRegistry::class)), true);
        }
        if (!$container->has(ReservationCleanupService::class)) {
            $container->share(ReservationCleanupService::class,
                static fn(Container $c): ReservationCleanupService => new ReservationCleanupService(
                    $c->get(DatabaseInterface::class), $c->get(BookingIntegrityService::class),
                    $c->get(NativePayPalProviderRegistry::class), $c->get(NativeReservationService::class),
                    $c->get(PaymentReconciliationService::class)), true);
        }
        if ($container->has(PaymentReconciliationService::class)) {
            return;
        }

        $container->share(
            PaymentReconciliationService::class,
            static fn(Container $container): PaymentReconciliationService
                => new PaymentReconciliationService(
                    $container->get(DatabaseInterface::class),
                    $container->get(NativeReservationService::class)
                ),
            true
        );
    }
}
