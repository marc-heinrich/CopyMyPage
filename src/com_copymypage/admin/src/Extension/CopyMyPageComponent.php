<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.2
 */

namespace Joomla\Component\CopyMyPage\Administrator\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\Database\DatabaseAwareTrait;
use Psr\Container\ContainerInterface;

/**
 * CopyMyPage component class.
 *
 * This component is responsible for the administrator functionality,
 * including routing support and future HTML service registrations.
 */
class CopyMyPageComponent extends MVCComponent implements
    RouterServiceInterface,
    BootableExtensionInterface
{
    use RouterServiceTrait;
    use HTMLRegistryAwareTrait;
    use DatabaseAwareTrait;

    /**
     * Boots the component.
     *
     * This method is called during the application bootstrapping process
     * and can be used to register HTML services or perform other
     * initialization logic.
     *
     * @param   ContainerInterface  $container  The DI container.
     *
     * @return  void
     */
    public function boot(ContainerInterface $container): void
    {
        // Reserved for future HTML service registration and other boot logic.
        // For now, we do not need to register anything here.
    }
}
