<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\Service\Provider\HelperFactory;
use Joomla\CMS\Extension\Service\Provider\Module;
use Joomla\CMS\Extension\Service\Provider\ModuleDispatcherFactory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the services for the CopyMyPage Slideshow module.
     *
     * @param  Container  $container  The DI container.
     *
     * @return void
     */
    public function register(Container $container): void
    {
        // Registering the Module Dispatcher for the Slideshow module.
        $container->registerServiceProvider(
            new ModuleDispatcherFactory('\\Joomla\\Module\\CopyMyPage\\Slideshow')
        );

        // Registering the Helper for the Slideshow module.
        $container->registerServiceProvider(
            new HelperFactory('\\Joomla\\Module\\CopyMyPage\\Slideshow\\Site\\Helper')
        );

        // Registering the core Module service.
        $container->registerServiceProvider(new Module());
    }
};
