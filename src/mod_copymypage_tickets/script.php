<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Log\Log;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

return new class () implements ServiceProviderInterface
{
    /**
     * Register the installer hooks and remove module-local legacy assets.
     */
    public function register(Container $container): void
    {
        $container->set(
            InstallerScriptInterface::class,
            new class () implements InstallerScriptInterface
            {
                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function install(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    $this->removeLegacyAssets();

                    return true;
                }

                /**
                 * Remove assets now owned by com_copymypage.
                 *
                 * @since  0.0.19
                 */
                private function removeLegacyAssets(): void
                {
                    $mediaDirectory = Path::clean(JPATH_ROOT . '/media/mod_copymypage_tickets');

                    if (is_dir($mediaDirectory) && !Folder::delete($mediaDirectory)) {
                        Log::add(
                            'The legacy CopyMyPage Tickets media directory could not be removed: '
                            . $mediaDirectory,
                            Log::WARNING,
                            'jerror'
                        );
                    }

                    $assetItem = Path::clean(
                        JPATH_SITE . '/modules/mod_copymypage_tickets/src/WebAsset/AssetItem/TicketsAssetItem.php'
                    );

                    if (is_file($assetItem)) {
                        $contents = file_get_contents($assetItem);

                        if (
                            \is_string($contents)
                            && str_contains($contents, 'MOD_COPYMYPAGE_TICKETS_JS_RUNTIME_MISSING')
                            && !File::delete($assetItem)
                        ) {
                            Log::add(
                                'The legacy module WebAssetItem could not be removed: ' . $assetItem,
                                Log::WARNING,
                                'jerror'
                            );
                        }
                    }

                    foreach ([dirname($assetItem), dirname($assetItem, 2)] as $directory) {
                        if (
                            is_dir($directory)
                            && (new \FilesystemIterator($directory))->valid() === false
                        ) {
                            Folder::delete($directory);
                        }
                    }
                }
            }
        );
    }
};
