<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\DPCalendarPay\CopyMyPagePayPal\Extension\CopyMyPagePayPal;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, static function (Container $container): CopyMyPagePayPal {
            Factory::getApplication()->bootComponent('dpcalendar');
            $plugin = new CopyMyPagePayPal($container->get(DispatcherInterface::class), (array) PluginHelper::getPlugin('dpcalendarpay', 'copymypagepaypal'));
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
