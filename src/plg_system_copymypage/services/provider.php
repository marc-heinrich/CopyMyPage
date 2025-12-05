<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.3
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Plugin\System\CopyMyPage\Extension\CopyMyPage;

/**
 * Service provider for the CopyMyPage system plugin.
 *
 * Wires the CopyMyPage plugin into Joomla's DI container so that it can be
 * instantiated and its subscribed events can be dispatched.
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

                // Instantiate the plugin class with its configuration.
                $plugin = new CopyMyPage($config);

                // Inject the current application instance.
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
