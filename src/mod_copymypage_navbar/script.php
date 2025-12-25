<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
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
                 * @var string
                 */
                private const MENU_TYPE = 'copymypage';

                /**
                 * Hidden "router/home" menu item alias.
                 *
                 * @var string
                 */
                private const HOME_ALIAS = 'placeholder';

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
                    $app = Factory::getApplication();

                    if (!ComponentHelper::isEnabled('com_menus')) {
                        return true;
                    }

                    $db = Factory::getContainer()->get(DatabaseInterface::class);

                    try {
                        $this->cleanupAllMenuItemsByMenuType($db);
                        $this->cleanupMenuType($db);
                    } catch (\Throwable $e) {
                        $app->enqueueMessage(
                            Text::sprintf('CopyMyPage menu cleanup failed: %s', $e->getMessage()),
                            'warning'
                        );
                    }

                    return true;
                }

                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    if (!\in_array($type, ['install', 'update', 'discover_install'], true)) {
                        return true;
                    }

                    $app = Factory::getApplication();

                    if (!ComponentHelper::isEnabled('com_menus')) {
                        $app->enqueueMessage('CopyMyPage menu setup skipped: com_menus is disabled.', 'warning');

                        return true;
                    }

                    $db = Factory::getContainer()->get(DatabaseInterface::class);

                    $componentId = $this->getExtensionId($db, 'component', 'com_copymypage');

                    if ($componentId === 0) {
                        $app->enqueueMessage('CopyMyPage menu setup skipped: com_copymypage is not installed.', 'warning');

                        return true;
                    }

                    try {
                        $this->ensureMenuType($db);
                        $this->ensureHomeMenuItem($db, $componentId);

                        $language = $this->getDefaultSiteLanguage();

                        $this->ensureAnchorMenuItem($db, $language, 'Home', 'hero', '#hero');
                        $this->ensureAnchorMenuItem($db, $language, 'Galerie', 'gallery', '#gallery');
                        $this->ensureAnchorMenuItem($db, $language, 'Team', 'team', '#team');
                        $this->ensureAnchorMenuItem($db, $language, 'Kontakt', 'contact', '#contact');
                    } catch (\Throwable $e) {
                        $app->enqueueMessage(
                            Text::sprintf('CopyMyPage menu setup failed: %s', $e->getMessage()),
                            'warning'
                        );
                    }

                    return true;
                }

                private function ensureMenuType(DatabaseInterface $db): void
                {
                    $app = Factory::getApplication();

                    $table = $app->bootComponent('com_menus')
                        ->getMVCFactory()
                        ->createTable('MenuType', 'Administrator', ['dbo' => $db]);

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

                private function ensureHomeMenuItem(DatabaseInterface $db, int $componentId): void
                {
                    $app = Factory::getApplication();

                    $table = $app->bootComponent('com_menus')
                        ->getMVCFactory()
                        ->createTable('Menu', 'Administrator', ['dbo' => $db]);

                    if ($table->load(['menutype' => self::MENU_TYPE, 'alias' => self::HOME_ALIAS, 'language' => '*', 'parent_id' => 1])) {
                        return;
                    }

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

                    $table->setLocation(1, 'last-child');

                    if (!$table->check() || !$table->store()) {
                        throw new \RuntimeException('Failed to store the hidden Home menu item.');
                    }

                    if (!$table->rebuildPath((int) $table->id)) {
                        throw new \RuntimeException('Failed to rebuild the hidden Home menu item path.');
                    }
                }

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

                    $table->setLocation(1, 'last-child');

                    if (!$table->check() || !$table->store()) {
                        throw new \RuntimeException('Failed to store anchor menu item: ' . $alias);
                    }

                    if (!$table->rebuildPath((int) $table->id)) {
                        throw new \RuntimeException('Failed to rebuild anchor menu item path: ' . $alias);
                    }
                }

                /**
                 * Delete all site menu items having menutype="copymypage".
                 *
                 * Uses JTable deletion to keep the nested set tree consistent.
                 */
                private function cleanupAllMenuItemsByMenuType(DatabaseInterface $db): void
                {
                    $menuType = self::MENU_TYPE;

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

                private function getDefaultSiteLanguage(): string
                {
                    $params = ComponentHelper::getParams('com_languages');

                    return (string) $params->get('site', 'de-DE');
                }

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
