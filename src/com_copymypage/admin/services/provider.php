<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.2
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Component\CopyMyPage\Administrator\Extension\CopyMyPageComponent;
use Joomla\Component\CopyMyPage\Site\Service\EventSeatInventoryService;
use Joomla\Component\CopyMyPage\Site\Service\SeatLayoutService;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface
{
    /**
     * Registers the component services in the DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     */
    public function register(Container $container): void
    {
        // Administrator models and custom fields resolve shared domain services
        // from Joomla's root container, while component providers themselves
        // are registered in an extension container.
        $rootContainer = Factory::getContainer();

        if (!$rootContainer->has(SeatLayoutService::class)) {
            $rootContainer->share(
                SeatLayoutService::class,
                static fn(Container $container): SeatLayoutService => new SeatLayoutService(
                    $container->get(DatabaseInterface::class),
                    JPATH_SITE . '/components/com_copymypage/data/seat-layouts'
                ),
                true
            );
        }

        if (!$rootContainer->has(EventSeatInventoryService::class)) {
            $rootContainer->share(
                EventSeatInventoryService::class,
                static fn(Container $container): EventSeatInventoryService
                    => new EventSeatInventoryService(
                        $container->get(DatabaseInterface::class),
                        $container->get(SeatLayoutService::class)
                    ),
                true
            );
        }

        // Register the dispatcher factory for com_copymypage.
        $container->registerServiceProvider(
            new ComponentDispatcherFactory('\\Joomla\\Component\\CopyMyPage')
        );

        // Register the MVC factory, bound to the CopyMyPage namespace.
        $container->registerServiceProvider(
            new MVCFactory('\\Joomla\\Component\\CopyMyPage')
        );

        // Register the router factory for com_copymypage.
        $container->registerServiceProvider(
            new RouterFactory('\\Joomla\\Component\\CopyMyPage')
        );

        // Register the component itself.
        $container->set(
            ComponentInterface::class,
            static function (Container $container): CopyMyPageComponent {
                $component = new CopyMyPageComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class)
                );

                $component->setMVCFactory(
                    $container->get(MVCFactoryInterface::class)
                );

                $component->setRouterFactory(
                    $container->get(RouterFactoryInterface::class)
                );

                $component->setDatabase(
                    $container->get(DatabaseInterface::class)
                );

                return $component;
            }
        );
    }
};
