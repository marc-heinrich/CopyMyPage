<?php
/**
 * @package     Joomla.Site
 * @subpackage  Plugins.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * CopyMyPage Installer Script (System Plugin)
 *
 * Automatically enables the system plugin after install/update so the
 * global registry bootstrap is active without manual backend interaction.
 *
 * @since  0.0.4
 */
return new class () implements ServiceProviderInterface
{
    /**
     * Registers services to the Joomla DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   0.0.4
     */
    public function register(Container $container): void
    {
        $container->set(
            InstallerScriptInterface::class,
            new class () implements InstallerScriptInterface
            {
                /**
                 * Plugin folder (group).
                 *
                 * @var string
                 */
                private const PLUGIN_FOLDER = 'system';

                /**
                 * Plugin element (name).
                 *
                 * @var string
                 */
                private const PLUGIN_ELEMENT = 'copymypage';

                /**
                 * Runs before install/update starts.
                 *
                 * @since  0.0.4
                 */
                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Runs on fresh installation.
                 *
                 * @since  0.0.4
                 */
                public function install(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Runs on update.
                 *
                 * @since  0.0.4
                 */
                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Runs on uninstall.
                 *
                 * @since  0.0.4
                 */
                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Runs after install/update/discover_install has finished.
                 *
                 * @since  0.0.4
                 */
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    if (!\in_array($type, ['install', 'update', 'discover_install'], true)) {
                        return true;
                    }

                    $app = Factory::getApplication();
                    $db  = Factory::getContainer()->get(DatabaseInterface::class);

                    try {
                        $this->enableSystemPlugin($db);

                        // Keep messaging intentionally minimal; the site/admin must not fail if this step is skipped.
                        // If you prefer, you can enqueue a success message here.
                    } catch (\Throwable $e) {
                        $app->enqueueMessage(
                            Text::sprintf('CopyMyPage plugin auto-enable failed: %s', $e->getMessage()),
                            'warning'
                        );
                    }

                    return true;
                }

                /**
                 * Enables the CopyMyPage system plugin (idempotent).
                 *
                 * @param   DatabaseInterface  $db  The database connection.
                 *
                 * @return  void
                 *
                 * @since   0.0.4
                 */
                private function enableSystemPlugin(DatabaseInterface $db): void
                {
                    $type   = 'plugin';
                    $folder = self::PLUGIN_FOLDER;
                    $elem   = self::PLUGIN_ELEMENT;

                    // 1) Read current state (so we can stay idempotent and avoid pointless writes).
                    $query = $db->getQuery(true)
                        ->select([
                            $db->quoteName('extension_id'),
                            $db->quoteName('enabled'),
                        ])
                        ->from($db->quoteName('#__extensions'))
                        ->where($db->quoteName('type') . ' = :type')
                        ->where($db->quoteName('folder') . ' = :folder')
                        ->where($db->quoteName('element') . ' = :element')
                        ->bind(':type', $type, ParameterType::STRING)
                        ->bind(':folder', $folder, ParameterType::STRING)
                        ->bind(':element', $elem, ParameterType::STRING);

                    $db->setQuery($query);
                    $row = $db->loadAssoc();

                    if (empty($row) || empty($row['extension_id'])) {
                        // Plugin not found in extensions table (should not happen, but must be resilient).
                        return;
                    }

                    if (!empty($row['enabled'])) {
                        // Already enabled.
                        return;
                    }

                    // 2) Enable the plugin.
                    $extensionId = (int) $row['extension_id'];

                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__extensions'))
                        ->set($db->quoteName('enabled') . ' = 1')
                        ->where($db->quoteName('extension_id') . ' = :id')
                        ->bind(':id', $extensionId, ParameterType::INTEGER);

                    $db->setQuery($query);
                    $db->execute();
                }
            }
        );
    }
};
