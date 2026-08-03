<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_copymypage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.1
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

/**
 * CopyMyPage Service Provider + Installer Script
 *
 * Registers the installer callbacks, copies shared library files and
 * provisions the component-owned account menu without overwriting edits.
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

                    // If the operation is not an uninstallation, copy the files
                    // and ensure the component-owned account navigation.
                    if ($type !== 'uninstall') {
                        if (!$this->copyFiles()) {
                            return false;
                        }

                        if (\in_array($type, ['install', 'update', 'discover_install'], true)) {
                            try {
                                $this->ensureAccountMenu($adapter);
                            } catch (\Throwable $exception) {
                                Factory::getApplication()->enqueueMessage(
                                    'CopyMyPage account menu setup failed: ' . $exception->getMessage(),
                                    'warning'
                                );
                            } finally {
                                $this->clearAccountMenuCaches();
                            }

                            try {
                                $this->ensureAvatarField();
                            } catch (\Throwable $exception) {
                                Factory::getApplication()->enqueueMessage(
                                    'CopyMyPage avatar field setup failed: ' . $exception->getMessage(),
                                    'warning'
                                );
                            }
                        }

                        return true;
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

                /**
                 * Provision the system-managed Joomla Custom User Field for avatars.
                 *
                 * @since  0.0.17
                 */
                private function ensureAvatarField(): void
                {
                    $db      = Factory::getContainer()->get(DatabaseInterface::class);
                    $context = 'com_users.user';
                    $name    = 'copymypage-avatar';
                    $query   = $db->getQuery(true)
                        ->select($db->quoteName(['id', 'asset_id', 'type']))
                        ->from($db->quoteName('#__fields'))
                        ->where($db->quoteName('context') . ' = :context')
                        ->where($db->quoteName('name') . ' = :name')
                        ->bind(':context', $context)
                        ->bind(':name', $name);
                    $field = $db->setQuery($query)->loadObject();

                    if ($field) {
                        if ((string) $field->type !== 'media') {
                            throw new \RuntimeException(
                                'The existing copymypage-avatar field is not a Media field.'
                            );
                        }

                        $this->ensureAvatarFieldPermission($db, (int) $field->asset_id);

                        return;
                    }

                    $query = $db->getQuery(true)
                        ->select($db->quoteName('id'))
                        ->from($db->quoteName('#__fields'))
                        ->where($db->quoteName('name') . ' = :name')
                        ->bind(':name', $name);

                    if ((int) $db->setQuery($query)->loadResult() > 0) {
                        throw new \RuntimeException(
                            'The stable copymypage-avatar field name is already used in another context.'
                        );
                    }

                    $registeredGroupId = $this->getRegisteredUserGroupId($db);
                    $table             = Factory::getApplication()
                        ->bootComponent('com_fields')
                        ->getMVCFactory()
                        ->createTable('Field', 'Administrator', ['dbo' => $db]);
                    $data = [
                        'access'              => 1,
                        'context'             => $context,
                        'default_value'       => '',
                        'description'         => 'System-managed profile image used by the CopyMyPage Dashboard.',
                        'fieldparams'         => [
                            'directory'   => 'copymypage/avatars',
                            'image_class' => '',
                            'preview'     => 'true',
                            'types'       => 'images',
                        ],
                        'group_id'            => 0,
                        'label'               => 'CopyMyPage profile image',
                        'language'            => '*',
                        'name'                => $name,
                        'note'                => 'copymypage.system.avatar',
                        'only_use_in_subform' => 0,
                        'ordering'            => 0,
                        'params'              => [
                            'class'              => '',
                            'display'            => '0',
                            'display_readonly'   => '0',
                            'hint'               => '',
                            'label_class'        => '',
                            'label_render_class' => '',
                            'layout'             => '',
                            'prefix'             => '',
                            'render_class'       => '',
                            'searchindex'        => '0',
                            'show_on'            => '2',
                            'showlabel'          => '1',
                            'suffix'             => '',
                        ],
                        'required'             => 0,
                        'rules'                => [
                            'core.edit.value' => [(string) $registeredGroupId => true],
                        ],
                        'state'                => 1,
                        'title'                => 'CopyMyPage profile image',
                        'type'                 => 'media',
                    ];

                    if (!$table || !$table->bind($data) || !$table->check() || !$table->store()) {
                        $message = $table && method_exists($table, 'getError')
                            ? (string) $table->getError()
                            : '';

                        throw new \RuntimeException(
                            $message !== '' ? $message : 'The CopyMyPage avatar field could not be stored.'
                        );
                    }

                    $this->ensureAvatarFieldPermission($db, (int) $table->asset_id);
                }

                /**
                 * Ensure ordinary authenticated users may update this field value only.
                 *
                 * @since  0.0.17
                 */
                private function ensureAvatarFieldPermission(
                    DatabaseInterface $db,
                    int $assetId
                ): void {
                    if ($assetId <= 0) {
                        throw new \RuntimeException('The CopyMyPage avatar field has no asset record.');
                    }

                    $query = $db->getQuery(true)
                        ->select($db->quoteName('rules'))
                        ->from($db->quoteName('#__assets'))
                        ->where($db->quoteName('id') . ' = :assetId')
                        ->bind(':assetId', $assetId, ParameterType::INTEGER);
                    $rules = json_decode((string) $db->setQuery($query)->loadResult(), true);
                    $rules = \is_array($rules) ? $rules : [];
                    $rules['core.edit.value'] = \is_array($rules['core.edit.value'] ?? null)
                        ? $rules['core.edit.value']
                        : [];
                    $rules['core.edit.value'][(string) $this->getRegisteredUserGroupId($db)] = 1;
                    $asset        = new \stdClass();
                    $asset->id    = $assetId;
                    $asset->rules = json_encode($rules, JSON_UNESCAPED_SLASHES);

                    if (!\is_string($asset->rules)) {
                        throw new \RuntimeException('The CopyMyPage avatar field rules could not be encoded.');
                    }

                    $db->updateObject('#__assets', $asset, 'id');
                }

                /**
                 * Resolve Joomla's standard Registered group without hard-coding a field id.
                 *
                 * @since  0.0.17
                 */
                private function getRegisteredUserGroupId(DatabaseInterface $db): int
                {
                    $title = 'Registered';
                    $query = $db->getQuery(true)
                        ->select($db->quoteName('id'))
                        ->from($db->quoteName('#__usergroups'))
                        ->where($db->quoteName('title') . ' = :title')
                        ->bind(':title', $title);

                    return (int) ($db->setQuery($query)->loadResult() ?: 2);
                }

                /**
                 * Ensure the dedicated account menu and manifest-defined defaults.
                 *
                 * @since  0.0.17
                 */
                private function ensureAccountMenu(InstallerAdapter $adapter): void
                {
                    $manifest = $adapter->getManifest();

                    if (!isset($manifest->accountMenuBootstrap)) {
                        return;
                    }

                    $db          = Factory::getContainer()->get(DatabaseInterface::class);
                    $componentId = $this->getComponentId($db);

                    if ($componentId === 0) {
                        throw new \RuntimeException('com_copymypage extension id was not found.');
                    }

                    $this->ensureAccountMenuType($db);

                    foreach ($manifest->accountMenuBootstrap->item as $item) {
                        $this->ensureAccountMenuItem($db, $componentId, $item);
                    }
                }

                /**
                 * Clear menu caches even when a multi-item bootstrap stopped partway through.
                 *
                 * @since  0.0.17
                 */
                private function clearAccountMenuCaches(): void
                {
                    try {
                        $cacheFactory = Factory::getContainer()->get(CacheControllerFactoryInterface::class);

                        foreach (['com_menus', 'com_modules', 'mod_menu'] as $group) {
                            $cacheFactory
                                ->createCacheController('', ['defaultgroup' => $group])
                                ->clean();
                        }
                    } catch (\Throwable $exception) {
                        Log::add(
                            'CopyMyPage account menu cache cleanup failed: ' . $exception->getMessage(),
                            Log::WARNING,
                            'jerror'
                        );
                    }
                }

                /**
                 * Create the account menu type when it does not exist.
                 *
                 * @since  0.0.17
                 */
                private function ensureAccountMenuType(DatabaseInterface $db): void
                {
                    $table = Factory::getApplication()
                        ->bootComponent('com_menus')
                        ->getMVCFactory()
                        ->createTable('MenuType', 'Administrator', ['dbo' => $db]);

                    if ($table->load(['menutype' => 'copymypage-account'])) {
                        return;
                    }

                    $data = [
                        'id'          => 0,
                        'menutype'    => 'copymypage-account',
                        'title'       => 'CopyMyPage Account',
                        'description' => 'Navigation for the CopyMyPage personal account area',
                        'client_id'   => 0,
                    ];

                    if (!$table->bind($data) || !$table->check() || !$table->store()) {
                        throw new \RuntimeException('The CopyMyPage account menu type could not be created.');
                    }
                }

                /**
                 * Create one missing account destination without overwriting user changes.
                 *
                 * @since  0.0.17
                 */
                private function ensureAccountMenuItem(
                    DatabaseInterface $db,
                    int $componentId,
                    \SimpleXMLElement $item
                ): void {
                    $key   = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $item['key'])) ?? '';
                    $title = trim((string) $item['title']);
                    $alias = trim((string) $item['alias']);
                    $icon  = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $item['icon'])) ?? '';
                    $link  = html_entity_decode(
                        trim((string) $item['link']),
                        ENT_QUOTES | ENT_XML1,
                        'UTF-8'
                    );

                    if ($key === '' || $title === '' || $alias === '' || $link === '') {
                        throw new \RuntimeException('An account menu manifest item is incomplete.');
                    }

                    $note = 'copymypage.account.' . $key;

                    if ($this->accountMenuItemExists($db, $note, $link, $alias)) {
                        return;
                    }

                    $table = Factory::getApplication()
                        ->bootComponent('com_menus')
                        ->getMVCFactory()
                        ->createTable('Menu', 'Administrator', ['dbo' => $db]);
                    $data = [
                        'id'           => 0,
                        'menutype'     => 'copymypage-account',
                        'title'        => $title,
                        'alias'        => $alias,
                        'note'         => $note,
                        'type'         => 'component',
                        'link'         => $link,
                        'component_id' => $componentId,
                        'published'    => 1,
                        'parent_id'    => 1,
                        'level'        => 1,
                        'access'       => $this->getRegisteredViewLevelId($db),
                        'client_id'    => 0,
                        'home'         => 0,
                        'language'     => '*',
                        'params'       => json_encode(
                            [
                                'copymypage_account_icon' => $icon,
                                'copymypage_account_key'  => $key,
                                'menu_show'               => 1,
                            ],
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        ),
                    ];

                    if (!$table->bind($data)) {
                        throw new \RuntimeException('An account menu item could not be bound.');
                    }

                    $table->setLocation(1, 'last-child');

                    if (!$table->check() || !$table->store() || !$table->rebuildPath((int) $table->id)) {
                        throw new \RuntimeException('An account menu item could not be stored.');
                    }
                }

                /**
                 * Check stable note first, then canonical link and alias fallbacks.
                 *
                 * @since  0.0.17
                 */
                private function accountMenuItemExists(
                    DatabaseInterface $db,
                    string $note,
                    string $link,
                    string $alias
                ): bool {
                    $menuType = 'copymypage-account';
                    $query    = $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($db->quoteName('#__menu'))
                        ->where($db->quoteName('menutype') . ' = :menutype')
                        ->where(
                            '('
                            . $db->quoteName('note') . ' = :note'
                            . ' OR ' . $db->quoteName('link') . ' = :link'
                            . ' OR ' . $db->quoteName('alias') . ' = :alias'
                            . ')'
                        )
                        ->bind(':menutype', $menuType)
                        ->bind(':note', $note)
                        ->bind(':link', $link)
                        ->bind(':alias', $alias);

                    return (int) $db->setQuery($query)->loadResult() > 0;
                }

                /**
                 * Resolve the installed component extension id.
                 *
                 * @since  0.0.17
                 */
                private function getComponentId(DatabaseInterface $db): int
                {
                    $element  = 'com_copymypage';
                    $type     = 'component';
                    $clientId = 1;
                    $query    = $db->getQuery(true)
                        ->select($db->quoteName('extension_id'))
                        ->from($db->quoteName('#__extensions'))
                        ->where($db->quoteName('element') . ' = :element')
                        ->where($db->quoteName('type') . ' = :type')
                        ->where($db->quoteName('client_id') . ' = :clientId')
                        ->bind(':element', $element)
                        ->bind(':type', $type)
                        ->bind(':clientId', $clientId, ParameterType::INTEGER);

                    return (int) $db->setQuery($query)->loadResult();
                }

                /**
                 * Resolve the standard Registered view level with a safe fallback.
                 *
                 * @since  0.0.17
                 */
                private function getRegisteredViewLevelId(DatabaseInterface $db): int
                {
                    $title = 'Registered';
                    $query = $db->getQuery(true)
                        ->select($db->quoteName('id'))
                        ->from($db->quoteName('#__viewlevels'))
                        ->where($db->quoteName('title') . ' = :title')
                        ->bind(':title', $title);

                    return (int) ($db->setQuery($query)->loadResult() ?: 2);
                }
            }
        );
    }
};
