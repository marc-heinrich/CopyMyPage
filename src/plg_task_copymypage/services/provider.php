<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Task.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\CopyMyPage\Site\Service\PaymentReconciliationService;
use Joomla\Component\CopyMyPage\Site\Service\PaymentOutboxService;
use Joomla\Component\CopyMyPage\Site\Service\PaymentReconciliationServiceProvider;
use Joomla\Component\CopyMyPage\Site\Service\BookingIntegrityService;
use Joomla\Component\CopyMyPage\Site\Service\PayPalPaymentService;
use Joomla\Component\CopyMyPage\Site\Service\PayPalPaymentServiceProvider;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Plugin\Task\CopyMyPage\Extension\CopyMyPage;

/**
 * Service provider for the CopyMyPage task plugin.
 *
 * @since  0.0.19
 */
return new class () implements ServiceProviderInterface {
    /**
     * @since   0.0.19
     */
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new PaymentReconciliationServiceProvider());
        if (!$container->has(BookingIntegrityService::class)) {
            $container->share(BookingIntegrityService::class,
                static fn (Container $c): BookingIntegrityService => new BookingIntegrityService($c->get(DatabaseInterface::class)), true);
        }
        $container->registerServiceProvider(new PayPalPaymentServiceProvider());

        $container->set(
            PluginInterface::class,
            $container->lazy(
                CopyMyPage::class,
                static function (Container $container): PluginInterface {
                    $plugin = new CopyMyPage(
                        (array) PluginHelper::getPlugin('task', 'copymypage'),
                        $container->get(PaymentReconciliationService::class),
                        $container->get(PayPalPaymentService::class),
                        $container->get(PaymentOutboxService::class)
                    );
                    $plugin->setApplication(Factory::getApplication());

                    return $plugin;
                }
            )
        );
    }
};
