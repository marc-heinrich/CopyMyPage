<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_copymypage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.1
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

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
     * @param   Container  $container
     * @return  void
     *
     * @since   0.0.1
     */
    public function register(Container $container): void
    {
        // Register an anonymous installer script into the container.
        $container->set(
            InstallerScriptInterface::class,
            new class () implements InstallerScriptInterface
            {
                /**
                 * Files to be copied or deleted.
                 */
                protected array $files = [];          
                
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
                    $manifest = $adapter->getManifest();

                    // Search for the correct 'files' node containing the file list.
                    if (isset($manifest->files)) {
                        foreach ($manifest->files as $fileGroup) {
                            
                            // Ensure the 'folder' attribute exists.
                            if (isset($fileGroup->attributes()->folder)) {
                                foreach ($fileGroup->filename as $file) {
                                    $fileName    = (string) $file;
                                    $destination = (string) $file->attributes()->destination;

                                    // Ensure destination is set.
                                    if (!empty($destination)) {
                                        $path['src']    = Path::clean($adapter->getParent()->getPath('source') . '/libraries/' . $fileName);
                                        $path['dest']   = Path::clean(JPATH_ROOT . '/' . $destination . '/' . $fileName);
                                        $this->files[]  = $path;
                                    }
                                }
                            }
                        }
                    }

                    // If the operation is not an uninstallation, copy the files.
                    if ($type !== 'uninstall') {
                        return $this->copyFiles();
                    }

                    // Otherwise, delete the files during uninstallation.
                    return $this->deleteFiles();
                }

                /**
                 * Copies the files listed in the manifest to their respective destinations.
                 */
                protected function copyFiles(): bool
                {
                    foreach ($this->files as $file) {
                        $src  = $file['src'];
                        $dest = $file['dest'];

                        if (!file_exists(dirname($dest))) {
                            Folder::create(dirname($dest));
                        }

                        if (!File::copy($src, $dest)) {
                            Log::add(Text::sprintf('JLIB_INSTALLER_ERROR_COPY_FILE', $src, $dest), Log::WARNING, 'jerror');
                            return false;
                        }
                    }

                    return true;
                }

                /**
                 * Deletes the files listed in the manifest during uninstallation.
                 */
                protected function deleteFiles(): bool
                {
                    foreach ($this->files as $file) {
                        $path = $file['dest'];

                        if (is_dir($path)) {
                            Folder::delete($path);
                        } else {
                            File::delete($path);
                        }

                        if (file_exists($path)) {
                            Log::add(Text::sprintf('JLIB_INSTALLER_ERROR_DELETE_FILE', $path), Log::WARNING, 'jerror');
                            return false;
                        }
                    }

                    return true;
                }
            }
        );
    }
};
