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
        if ($container->has(PaymentReconciliationService::class)) {
            return;
        }

        $container->share(
            PaymentReconciliationService::class,
            static fn(Container $container): PaymentReconciliationService
                => new PaymentReconciliationService(
                    $container->get(DatabaseInterface::class)
                ),
            true
        );
    }
}
