<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.3
 */

namespace Joomla\Plugin\System\CopyMyPage\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\CopyMyPage\Site\Helper\Registry as CopyMyPageRegistry;
use Joomla\DI\Container;
use Joomla\Event\SubscriberInterface;

/**
 * System plugin for CopyMyPage.
 *
 * Registers the global CopyMyPage helper registry in the application
 * DI container so it can be used by modules, components and templates.
 *
 * @since  0.0.3
 */
final class CopyMyPage extends CMSPlugin implements SubscriberInterface
{
    /**
     * The application object.
     *
     * @var CMSApplicationInterface
     */
    protected $app;

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   0.0.3
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterInitialise' => 'onAfterInitialise',
        ];
    }

    /**
     * Handler for the onAfterInitialise event.
     *
     * Registers the CopyMyPage helper registry in the main DI container
     * if it has not been registered yet.
     *
     * @return  void
     *
     * @since   0.0.3
     */
    public function onAfterInitialise(): void
    {
        $container = Factory::getContainer();

        if (! $container->has(CopyMyPageRegistry::class)) {
            $container
                ->alias(CopyMyPageRegistry::class, 'copymypage.registry')
                ->share(
                    'copymypage.registry',
                    static function (Container $c) {
                        return new CopyMyPageRegistry();
                    },
                    true
                );
        }
    }
}
