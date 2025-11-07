<?php
/**
 * @package     Joomla.Site
 * @subpackage  Package.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.1
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

/**
 * CopyMyPage Service Provider + Installer Script
 *
 * Registers an InstallerScriptInterface instance in the DI container.
 * All lifecycle methods are intentionally no-ops and return true.
 *
 * @since 0.0.1
 */
return new class () implements ServiceProviderInterface
{
    /**
     * Registers services to the Joomla DI container.
     *
     * @param  Container  $container
     * @return void
     *
     * @since 0.0.1
     */
    public function register(Container $container): void
    {
        // Register an anonymous installer script into the container.
        $container->set(
            InstallerScriptInterface::class,
            new class () implements InstallerScriptInterface
            {
                /**
                 * Runs before install/update/discover_install.
                 */
                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Install callback.
                 */
                public function install(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Update callback.
                 */
                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Uninstall callback.
                 */
                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Runs after install/update/discover_install.
                 */
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    return true;
                }
            }
        );
    }
};
