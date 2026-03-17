<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.7
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * CopyMyPage Service Provider + Installer Script
 *
 * Registers an InstallerScriptInterface instance in the DI container.
 * The installer then hooks into Joomla's lifecycle (pre/postflight, uninstall, ...).
 *
 * @since 0.0.4
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
                 * Menu type used for CopyMyPage.
                 *
                 * We use a dedicated menutype so we can:
                 * - create items deterministically during install/update
                 * - remove everything safely on uninstall
                 *
                 * @var string
                 */
                private const MENU_TYPE = 'copymypage';

                /**
                 * Hidden "router/home" menu item alias.
                 *
                 * This is the component entry used as the "main" route for the site.
                 *
                 * @var string
                 */
                private const HOME_ALIAS = 'placeholder';

                /**
                 * Unpublished demo heading alias.
                 *
                 * @var string
                 */
                private const DEMO_HEADING_ALIAS = 'heading';

                /**
                 * Runs before install or update starts.
                 */
                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    // Intentionally no-op. Keep the installer resilient.
                    return true;
                }

                /**
                 * Runs on fresh installation.
                 */
                public function install(InstallerAdapter $adapter): bool
                {
                    // We create the initial menu structure in postflight() once the environment is fully ready.
                    return true;
                }

                /**
                 * Runs on update.
                 */
                public function update(InstallerAdapter $adapter): bool
                {
                    // Intentionally no-op: updates must not rebuild or alter user-managed menu structures.
                    return true;
                }

                /**
                 * Runs after install or update.
                 */
                public function uninstall(InstallerAdapter $adapter): bool
                {
                    // During uninstall we remove all items that belong to our dedicated menutype.
                    $app = Factory::getApplication();

                    if (!ComponentHelper::isEnabled('com_menus')) {
                        return true;
                    }

                    $db = Factory::getContainer()->get(DatabaseInterface::class);

                    try {
                        // Delete children first (Table API keeps nested sets consistent).
                        $this->cleanupAllMenuItemsByMenuType($db);

                        // Then remove the menu type itself.
                        $this->cleanupMenuType($db);
                    } catch (\Throwable $e) {
                        // Soft warning only: uninstall should still finish.
                        $app->enqueueMessage(
                            Text::sprintf('CopyMyPage menu cleanup failed: %s', $e->getMessage()),
                            'warning'
                        );
                    }

                    return true;
                }

                /**
                 * Runs after install or update has finished.
                 */
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    // Only run our setup after install/update-like operations.
                    if (!\in_array($type, ['install', 'update', 'discover_install'], true)) {
                        return true;
                    }

                    $app = Factory::getApplication();

                    // If Menus are disabled, we can’t bootstrap navigation.
                    if (!ComponentHelper::isEnabled('com_menus')) {
                        $app->enqueueMessage('CopyMyPage menu setup skipped: com_menus is disabled.', 'warning');

                        return true;
                    }

                    $db = Factory::getContainer()->get(DatabaseInterface::class);

                    if ($type === 'install') {
                        // Initial menu bootstrap belongs to first install only.
                        // Updates must never interfere with user-managed menu structures.
                        $language = $this->getDefaultSiteLanguage();

                        if ($this->menuTypeExists($db)) {
                            $app->enqueueMessage(
                                'CopyMyPage menu setup skipped: menu type "copymypage" already exists.',
                                'message'
                            );
                        } else {
                            // Ensure the component exists so we can safely create the component menu item.
                            $componentId = $this->getExtensionId($db, 'component', 'com_copymypage');

                            if ($componentId === 0) {
                                $app->enqueueMessage('CopyMyPage menu setup skipped: com_copymypage is not installed.', 'warning');

                                return true;
                            }

                            try {
                                // 1) Ensure menu type exists.
                                $this->ensureMenuType($db, $language);

                                // 2) Ensure the hidden component entry exists.
                                $this->ensureHomeMenuItem($db, $componentId, $language);

                                // 3) Ensure the onepage anchor items exist (language-specific).
                                $this->ensureAnchorMenuItem($db, $language, 'Menuitem 1', 'hero', '#hero');
                                $this->ensureAnchorMenuItem($db, $language, 'Menuitem 2', 'gallery', '#gallery');
                                $this->ensureAnchorMenuItem($db, $language, 'Menuitem 3', 'team', '#team');
                                $this->ensureAnchorMenuItem($db, $language, 'Menuitem 4', 'contact', '#contact');

                                // 4) Optional demo structure: unpublished menu heading + 3 unpublished children.
                                // This is useful for testing dropdown/nesting output later.
                                $headingId = $this->ensureHeadingMenuItem($db, 'Heading', self::DEMO_HEADING_ALIAS, $language);

                                $this->ensureChildUrlMenuItem($db, $headingId, 'Submenuitem 1', 'sub-item-1', '#', $language);
                                $this->ensureChildUrlMenuItem($db, $headingId, 'Submenuitem 2', 'sub-item-2', '#', $language);
                                $this->ensureChildUrlMenuItem($db, $headingId, 'Submenuitem 3', 'sub-item-3', '#', $language);
                            } catch (\Throwable $e) {
                                // Soft warning only: the extension may still be usable without the auto-menu bootstrap.
                                $app->enqueueMessage(
                                    Text::sprintf('CopyMyPage menu setup failed: %s', $e->getMessage()),
                                    'warning'
                                );
                            }
                        }
                    }

                    try {
                        $this->normalizeNavbarModuleParams($db);
                    } catch (\Throwable $e) {
                        $app->enqueueMessage(
                            Text::sprintf('CopyMyPage navbar param normalization failed: %s', $e->getMessage()),
                            'warning'
                        );
                    }

                    return true;
                }

                /**
                 * Creates the menu type if it does not exist yet.
                 */
                private function ensureMenuType(DatabaseInterface $db, string $language): void
                {
                    $app = Factory::getApplication();

                    $table = $app->bootComponent('com_menus')
                        ->getMVCFactory()
                        ->createTable('MenuType', 'Administrator', ['dbo' => $db]);

                    // Idempotent: if it exists, do nothing.
                    if ($table->load(['menutype' => self::MENU_TYPE])) {
                        return;
                    }

                    $data = [
                        'id'          => 0,
                        'menutype'    => self::MENU_TYPE,
                        'title'       => 'CopyMyPage Menu (' . $language . ')',
                        'description' => 'CopyMyPage main menu for the site in language ' . $language,
                        'client_id'   => 0,
                    ];

                    if (!$table->bind($data) || !$table->check() || !$table->store()) {
                        throw new \RuntimeException('Failed to create menu type: ' . self::MENU_TYPE);
                    }
                }

                /**
                 * Checks whether the dedicated CopyMyPage menu type already exists.
                 */
                private function menuTypeExists(DatabaseInterface $db): bool
                {
                    $menuType = self::MENU_TYPE;

                    $query = $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($db->quoteName('#__menu_types'))
                        ->where($db->quoteName('menutype') . ' = :menutype')
                        ->bind(':menutype', $menuType, ParameterType::STRING);

                    $db->setQuery($query);

                    return (int) $db->loadResult() > 0;
                }

                /**
                 * Ensures the hidden component entry exists (component router entry).
                 */
                private function ensureHomeMenuItem(DatabaseInterface $db, int $componentId, string $language): void
                {
                    $app = Factory::getApplication();

                    $table = $app->bootComponent('com_menus')
                        ->getMVCFactory()
                        ->createTable('Menu', 'Administrator', ['dbo' => $db]);

                    // Idempotent lookup by menutype+alias+language+parent.
                    if ($table->load(['menutype' => self::MENU_TYPE, 'alias' => self::HOME_ALIAS, 'language' => $language, 'parent_id' => 1])) {
                        return;
                    }

                    // Only set as home if there isn't one already for the default site language.
                    $shouldBeHome = !$this->hasHomeForLanguage($db, $language);

                    $params = [
                        'menu-anchor_title'     => '',
                        'menu-anchor_css'       => '',
                        'menu_icon_css'         => '',
                        'menu_image'            => '',
                        'menu_image_css'        => '',
                        'menu_text'             => 0,
                        'menu_show'             => 0,
                        'page_title'            => '',
                        'show_page_heading'     => '',
                        'page_heading'          => '',
                        'pageclass_sfx'         => '',
                        'menu-meta_description' => '',
                        'robots'                => '',
                    ];

                    $data = [
                        'id'           => 0,
                        'menutype'     => self::MENU_TYPE,
                        'title'        => 'CopyMyPage',
                        'alias'        => self::HOME_ALIAS,
                        'type'         => 'component',
                        'link'         => 'index.php?option=com_copymypage&view=onepage',
                        'component_id' => $componentId,
                        'published'    => 1,
                        'parent_id'    => 1,
                        'level'        => 1,
                        'access'       => 1,
                        'client_id'    => 0,
                        'home'         => $shouldBeHome ? 1 : 0,
                        'language'     => $language,
                        'params'       => json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ];

                    if (!$table->bind($data)) {
                        throw new \RuntimeException('Failed to bind the hidden Home menu item.');
                    }

                    // Proper nested set placement.
                    $table->setLocation(1, 'last-child');

                    if (!$table->check() || !$table->store()) {
                        throw new \RuntimeException('Failed to store the hidden Home menu item.');
                    }

                    if (!$table->rebuildPath((int) $table->id)) {
                        throw new \RuntimeException('Failed to rebuild the hidden Home menu item path.');
                    }
                }

                /**
                 * Ensures a top-level anchor item exists (onepage section link).
                 */
                private function ensureAnchorMenuItem(
                    DatabaseInterface $db,
                    string $language,
                    string $title,
                    string $alias,
                    string $link
                ): void {
                    $app = Factory::getApplication();

                    $table = $app->bootComponent('com_menus')
                        ->getMVCFactory()
                        ->createTable('Menu', 'Administrator', ['dbo' => $db]);

                    // Idempotent lookup by menutype+alias+language+parent.
                    if ($table->load(['menutype' => self::MENU_TYPE, 'alias' => $alias, 'language' => $language, 'parent_id' => 1])) {
                        return;
                    }

                    $params = [
                        'menu-anchor_title' => '',
                        'menu-anchor_css'   => '',
                        'menu_icon_css'     => '',
                        'menu-anchor_rel'   => '',
                        'menu_image'        => '',
                        'menu_image_css'    => '',
                        'menu_text'         => 1,
                        'menu_show'         => 1,
                    ];

                    $data = [
                        'id'           => 0,
                        'menutype'     => self::MENU_TYPE,
                        'title'        => $title,
                        'alias'        => $alias,
                        'type'         => 'url',
                        'link'         => $link,
                        'component_id' => 0,
                        'published'    => 1,
                        'parent_id'    => 1,
                        'level'        => 1,
                        'access'       => 1,
                        'client_id'    => 0,
                        'home'         => 0,
                        'language'     => $language,
                        'params'       => json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ];

                    if (!$table->bind($data)) {
                        throw new \RuntimeException('Failed to bind anchor menu item: ' . $alias);
                    }

                    // Proper nested set placement.
                    $table->setLocation(1, 'last-child');

                    if (!$table->check() || !$table->store()) {
                        throw new \RuntimeException('Failed to store anchor menu item: ' . $alias);
                    }

                    if (!$table->rebuildPath((int) $table->id)) {
                        throw new \RuntimeException('Failed to rebuild anchor menu item path: ' . $alias);
                    }
                }

                /**
                 * Ensure an unpublished "Menu Heading" item exists and return its ID.
                 *
                 * Menu headings are useful as non-clickable dropdown containers.
                 */
                private function ensureHeadingMenuItem(
                    DatabaseInterface $db,
                    string $title,
                    string $alias,
                    string $language
                ): int {
                    $app = Factory::getApplication();

                    $table = $app->bootComponent('com_menus')
                        ->getMVCFactory()
                        ->createTable('Menu', 'Administrator', ['dbo' => $db]);

                    if ($table->load(['menutype' => self::MENU_TYPE, 'alias' => $alias, 'language' => $language, 'parent_id' => 1])) {
                        return (int) $table->id;
                    }

                    $params = [
                        'menu-anchor_title' => '',
                        'menu-anchor_css'   => '',
                        'menu_icon_css'     => '',
                        'menu_image'        => '',
                        'menu_image_css'    => '',
                        'menu_text'         => 1,
                        'menu_show'         => 1,
                    ];

                    $data = [
                        'id'           => 0,
                        'menutype'     => self::MENU_TYPE,
                        'title'        => $title,
                        'alias'        => $alias,
                        'type'         => 'heading',
                        'link'         => '',
                        'component_id' => 0,
                        'published'    => 0,
                        'parent_id'    => 1,
                        'level'        => 1,
                        'access'       => 1,
                        'client_id'    => 0,
                        'home'         => 0,
                        'language'     => $language,
                        'params'       => json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ];

                    if (!$table->bind($data)) {
                        throw new \RuntimeException('Failed to bind menu heading item: ' . $alias);
                    }

                    $table->setLocation(1, 'last-child');

                    if (!$table->check() || !$table->store()) {
                        throw new \RuntimeException('Failed to store menu heading item: ' . $alias);
                    }

                    if (!$table->rebuildPath((int) $table->id)) {
                        throw new \RuntimeException('Failed to rebuild menu heading item path: ' . $alias);
                    }

                    return (int) $table->id;
                }

                /**
                 * Ensure an unpublished URL child item exists under a given parent.
                 *
                 * This demonstrates real nesting (level 2) for later dropdown/mobile menu output.
                 */
                private function ensureChildUrlMenuItem(
                    DatabaseInterface $db,
                    int $parentId,
                    string $title,
                    string $alias,
                    string $link,
                    string $language
                ): void {
                    $app = Factory::getApplication();

                    $table = $app->bootComponent('com_menus')
                        ->getMVCFactory()
                        ->createTable('Menu', 'Administrator', ['dbo' => $db]);

                    // Idempotent lookup by menutype+alias+language+parent.
                    if ($table->load(['menutype' => self::MENU_TYPE, 'alias' => $alias, 'language' => $language, 'parent_id' => $parentId])) {
                        return;
                    }

                    $params = [
                        'menu-anchor_title' => '',
                        'menu-anchor_css'   => '',
                        'menu_icon_css'     => '',
                        'menu-anchor_rel'   => '',
                        'menu_image'        => '',
                        'menu_image_css'    => '',
                        'menu_text'         => 1,
                        'menu_show'         => 1,
                    ];

                    $data = [
                        'id'           => 0,
                        'menutype'     => self::MENU_TYPE,
                        'title'        => $title,
                        'alias'        => $alias,
                        'type'         => 'url',
                        'link'         => $link,
                        'component_id' => 0,
                        'published'    => 0,
                        'parent_id'    => $parentId,
                        'level'        => 2,
                        'access'       => 1,
                        'client_id'    => 0,
                        'home'         => 0,
                        'language'     => $language,
                        'params'       => json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ];

                    if (!$table->bind($data)) {
                        throw new \RuntimeException('Failed to bind child menu item: ' . $alias);
                    }

                    // Proper nested set placement (attach below the parent).
                    $table->setLocation($parentId, 'last-child');

                    if (!$table->check() || !$table->store()) {
                        throw new \RuntimeException('Failed to store child menu item: ' . $alias);
                    }

                    if (!$table->rebuildPath((int) $table->id)) {
                        throw new \RuntimeException('Failed to rebuild child menu item path: ' . $alias);
                    }
                }

                /**
                 * Delete all site menu items having menutype="copymypage".
                 *
                 * Uses Table deletion to keep the nested set tree consistent.
                 */
                private function cleanupAllMenuItemsByMenuType(DatabaseInterface $db): void
                {
                    $menuType = self::MENU_TYPE;

                    // Note: DatabaseQuery::bind() expects by-reference values; therefore we bind variables.
                    $query = $db->getQuery(true)
                        ->select($db->quoteName('id'))
                        ->from($db->quoteName('#__menu'))
                        ->where($db->quoteName('client_id') . ' = 0')
                        ->where($db->quoteName('menutype') . ' = :menutype')
                        ->bind(':menutype', $menuType, ParameterType::STRING);

                    $db->setQuery($query);

                    $ids = $db->loadColumn();

                    if (empty($ids)) {
                        return;
                    }

                    $app = Factory::getApplication();

                    $table = $app->bootComponent('com_menus')
                        ->getMVCFactory()
                        ->createTable('Menu', 'Administrator', ['dbo' => $db]);

                    // Deleting in reverse order is a safe default for nested structures.
                    $ids = array_map('intval', $ids);
                    rsort($ids);

                    foreach ($ids as $id) {
                        if ($id <= 0) {
                            continue;
                        }

                        if (!$table->delete($id)) {
                            throw new \RuntimeException('Failed to delete menu item id: ' . $id);
                        }
                    }
                }

                /**
                 * Delete the menu type row for menutype="copymypage".
                 */
                private function cleanupMenuType(DatabaseInterface $db): void
                {
                    $app = Factory::getApplication();

                    $table = $app->bootComponent('com_menus')
                        ->getMVCFactory()
                        ->createTable('MenuType', 'Administrator', ['dbo' => $db]);

                    if (!$table->load(['menutype' => self::MENU_TYPE])) {
                        return;
                    }

                    if (!$table->delete((int) $table->id)) {
                        throw new \RuntimeException('Failed to delete menu type: ' . self::MENU_TYPE);
                    }
                }

                /**
                 * Reads the default site language from com_languages config.
                 */
                private function getDefaultSiteLanguage(): string
                {
                    $params = ComponentHelper::getParams('com_languages');

                    return (string) $params->get('site', 'de-DE');
                }

                /**
                 * Checks if there is already a home item for the given language.
                 */
                private function hasHomeForLanguage(DatabaseInterface $db, string $language): bool
                {
                    $lang = $language;

                    $query = $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($db->quoteName('#__menu'))
                        ->where($db->quoteName('client_id') . ' = 0')
                        ->where($db->quoteName('home') . ' = 1')
                        ->where($db->quoteName('language') . ' = :language')
                        ->bind(':language', $lang, ParameterType::STRING);

                    $db->setQuery($query);

                    return (int) $db->loadResult() > 0;
                }

                /**
                 * Resolves the extension_id for a given type/element.
                 */
                private function getExtensionId(DatabaseInterface $db, string $type, string $element): int
                {
                    $typeValue    = $type;
                    $elementValue = $element;

                    $query = $db->getQuery(true)
                        ->select($db->quoteName('extension_id'))
                        ->from($db->quoteName('#__extensions'))
                        ->where($db->quoteName('type') . ' = :type')
                        ->where($db->quoteName('element') . ' = :element')
                        ->bind(':type', $typeValue, ParameterType::STRING)
                        ->bind(':element', $elementValue, ParameterType::STRING);

                    $db->setQuery($query);

                    return (int) $db->loadResult();
                }

                /**
                 * Normalize stored navbar module params so obsolete variants and numeric fields are cleaned up.
                 */
                private function normalizeNavbarModuleParams(DatabaseInterface $db): void
                {
                    $module = 'mod_copymypage_navbar';

                    $query = $db->getQuery(true)
                        ->select([$db->quoteName('id'), $db->quoteName('params')])
                        ->from($db->quoteName('#__modules'))
                        ->where($db->quoteName('module') . ' = :module')
                        ->bind(':module', $module, ParameterType::STRING);

                    $db->setQuery($query);
                    $rows = (array) $db->loadObjectList();

                    foreach ($rows as $row) {
                        $params = json_decode((string) ($row->params ?? ''), true);

                        if (!\is_array($params)) {
                            $params = [];
                        }

                        $normalized = $params;
                        $normalized['layoutVariant']          = $this->normalizeLayoutVariant($params['layoutVariant'] ?? null);
                        $normalized['mmenuLightItemHeight']   = $this->extractNumericParam($params['mmenuLightItemHeight'] ?? null, 50);
                        $normalized['mmenuLightOcdWidth']     = $this->extractNumericParam($params['mmenuLightOcdWidth'] ?? null, 80);
                        $normalized['mmenuLightOcdMinWidth']  = $this->extractNumericParam($params['mmenuLightOcdMinWidth'] ?? null, 200);
                        $normalized['mmenuLightOcdMaxWidth']  = $this->extractNumericParam($params['mmenuLightOcdMaxWidth'] ?? null, 440);
                        $normalized['userDropdownCloseDelay'] = $this->extractNumericParam($params['userDropdownCloseDelay'] ?? null, 180);

                        if ($normalized === $params) {
                            continue;
                        }

                        $encodedParams = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                        if (!\is_string($encodedParams)) {
                            throw new \RuntimeException('Failed to encode normalized navbar module params.');
                        }

                        $moduleId = (int) ($row->id ?? 0);

                        $update = $db->getQuery(true)
                            ->update($db->quoteName('#__modules'))
                            ->set($db->quoteName('params') . ' = :params')
                            ->where($db->quoteName('id') . ' = :id')
                            ->bind(':params', $encodedParams, ParameterType::STRING)
                            ->bind(':id', $moduleId, ParameterType::INTEGER);

                        $db->setQuery($update)->execute();
                    }
                }

                /**
                 * Normalize deprecated or missing layout variants to supported values.
                 */
                private function normalizeLayoutVariant(mixed $value): string
                {
                    $variant = \is_string($value) ? strtolower(trim($value)) : '';

                    return match ($variant) {
                        'navbar_bootstrap' => 'navbar_uikit',
                        'navbar_uikit',
                        'mobilemenu_mmenulight',
                        'mobilemenu_uikit',
                        'default' => $variant,
                        default => 'default',
                    };
                }

                /**
                 * Extract the numeric portion from an integer-like setting, tolerating legacy unit suffixes.
                 */
                private function extractNumericParam(mixed $value, int $default, int $min = 0, ?int $max = null): int
                {
                    if (\is_int($value)) {
                        $number = $value;
                    } elseif (\is_numeric($value)) {
                        $number = (int) $value;
                    } elseif (\is_string($value) && preg_match('/^-?\d+(?:\.\d+)?/', trim($value), $matches) === 1) {
                        $number = (int) $matches[0];
                    } else {
                        $number = $default;
                    }

                    if ($number < $min) {
                        $number = $min;
                    }

                    if ($max !== null && $number > $max) {
                        $number = $max;
                    }

                    return $number;
                }
            }
        );
    }
};
