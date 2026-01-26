<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
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
                    // We create menu items in postflight() so the environment is fully ready.
                    return true;
                }

                /**
                 * Runs on update.
                 */
                public function update(InstallerAdapter $adapter): bool
                {
                    // We create/ensure menu items in postflight() to keep the logic unified.
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

                    // Ensure the component exists so we can safely create the component menu item.
                    $componentId = $this->getExtensionId($db, 'component', 'com_copymypage');

                    if ($componentId === 0) {
                        $app->enqueueMessage('CopyMyPage menu setup skipped: com_copymypage is not installed.', 'warning');

                        return true;
                    }

                    try {
                        // 1) Ensure menu type exists.
                        $this->ensureMenuType($db);

                        // 2) Ensure the hidden component entry exists.
                        $this->ensureHomeMenuItem($db, $componentId);

                        // 3) Ensure the onepage anchor items exist (language-specific).
                        $language = $this->getDefaultSiteLanguage();

                        $this->ensureAnchorMenuItem($db, $language, 'Home', 'hero', '#hero');
                        $this->ensureAnchorMenuItem($db, $language, 'Galerie', 'gallery', '#gallery');
                        $this->ensureAnchorMenuItem($db, $language, 'Team', 'team', '#team');
                        $this->ensureAnchorMenuItem($db, $language, 'Kontakt', 'contact', '#contact');

                        // 4) Optional demo structure: unpublished menu heading + 3 unpublished children.
                        // This is useful for testing dropdown/nesting output later.
                        $headingId = $this->ensureHeadingMenuItem($db, 'Heading', self::DEMO_HEADING_ALIAS, '*');

                        $this->ensureChildUrlMenuItem($db, $headingId, 'Sub-Item 1', 'sub-item-1', '#', '*');
                        $this->ensureChildUrlMenuItem($db, $headingId, 'Sub-Item 2', 'sub-item-2', '#', '*');
                        $this->ensureChildUrlMenuItem($db, $headingId, 'Sub-Item 3', 'sub-item-3', '#', '*');
                    } catch (\Throwable $e) {
                        // Soft warning only: the extension may still be usable without the auto-menu bootstrap.
                        $app->enqueueMessage(
                            Text::sprintf('CopyMyPage menu setup failed: %s', $e->getMessage()),
                            'warning'
                        );
                    }

                    return true;
                }

                /**
                 * Creates the menu type if it does not exist yet.
                 */
                private function ensureMenuType(DatabaseInterface $db): void
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
                        'title'       => 'CopyMyPage Menu (de-DE)',
                        'description' => 'Das CopyMypage Main Menu for the site in language German (Germany)',
                        'client_id'   => 0,
                    ];

                    if (!$table->bind($data) || !$table->check() || !$table->store()) {
                        throw new \RuntimeException('Failed to create menu type: ' . self::MENU_TYPE);
                    }
                }

                /**
                 * Ensures the hidden component entry exists (component router entry).
                 */
                private function ensureHomeMenuItem(DatabaseInterface $db, int $componentId): void
                {
                    $app = Factory::getApplication();

                    $table = $app->bootComponent('com_menus')
                        ->getMVCFactory()
                        ->createTable('Menu', 'Administrator', ['dbo' => $db]);

                    // Idempotent lookup by menutype+alias+language+parent.
                    if ($table->load(['menutype' => self::MENU_TYPE, 'alias' => self::HOME_ALIAS, 'language' => '*', 'parent_id' => 1])) {
                        return;
                    }

                    // Only set as global home if there isn't one already.
                    $shouldBeHome = !$this->hasHomeForLanguage($db, '*');

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
                        'language'     => '*',
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
            }
        );
    }
};
