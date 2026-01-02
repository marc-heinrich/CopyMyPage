<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.3
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\System\CopyMyPage\Extension\CopyMyPage;

/**
 * Service provider for the CopyMyPage system plugin.
 *
 * Registers the plugin in Joomla's DI container so it can be instantiated
 * and subscribed events can be dispatched.
 *
 * @since  0.0.3
 */
return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with the DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   0.0.3
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            static function (Container $container): PluginInterface {
                // Load the plugin configuration from the extensions table.
                $config = (array) PluginHelper::getPlugin('system', 'copymypage');

                // The dispatcher is the "subject" passed to CMSPlugin-based plugins.
                $subject = $container->get(DispatcherInterface::class);

                // Instantiate the plugin with dispatcher + config.
                $plugin = new CopyMyPage($subject, $config);

                // Inject the current application instance (standard Joomla pattern).
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
