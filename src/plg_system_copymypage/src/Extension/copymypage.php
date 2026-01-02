<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.3
 */

namespace Joomla\Plugin\System\CopyMyPage\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\CopyMyPage\Site\Helper\Registry as CopyMyPageRegistry;
use Joomla\DI\Container;
use Joomla\Event\SubscriberInterface;

/**
 * System plugin for CopyMyPage.
 *
 * Registers the global CopyMyPage helper registry in the root DI container
 * so it can be resolved across extensions (components, modules, templates).
 *
 * @since  0.0.3
 */
final class CopyMyPage extends CMSPlugin implements SubscriberInterface
{
    /**
     * Automatically load the plugin language files (ini + sys.ini).
     *
     * @var bool
     *
     * @since 0.0.4
     */
    protected $autoloadLanguage = true;

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array<string, string>
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
     * Note:
     * We intentionally use Factory::getContainer() here because CMSApplication::getContainer()
     * is not publicly accessible from extensions in Joomla 6.
     *
     * @return  void
     *
     * @since   0.0.4
     */
    public function onAfterInitialise(): void
    {
        $container = Factory::getContainer();

        // Avoid duplicate registration if another bootstrap already added the service.
        if ($container->has(CopyMyPageRegistry::class)) {
            return;
        }

        // Register the registry as a shared (singleton) service under its class name (FQCN).
        $container->share(
            CopyMyPageRegistry::class,
            static function (Container $container): CopyMyPageRegistry {
                // The registry is currently stateless; the container argument is reserved for future extensions.
                return new CopyMyPageRegistry();
            },
            true
        );

        // Provide a stable string alias for convenience and backwards compatibility.
        $container->alias('copymypage.registry', CopyMyPageRegistry::class);
    }
}
