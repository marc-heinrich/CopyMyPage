<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

namespace Joomla\Module\CopyMyPage\Navbar\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\Registry\Registry;

/**
 * Helper class for the CopyMyPage Navbar module.
 *
 * This helper is intentionally small and acts as the single source of truth
 * for all runtime defaults while the module is in development.
 *
 * Menu rendering logic is delegated to Joomla core (mod_menu MenuHelper via bootModule()).
 */
class NavbarHelper
{
    /**
     * Returns module defaults (development stage).
     *
     * These values are used as the single source of truth during development.
     * Once the module is production-ready, selected values can be migrated into
     * module parameters (DB) without changing the templates.
     *
     * @return array{
     *   logo: string,
     *   sticky: bool,
     *   navOffcanvasId: string,
     *   userOffcanvasId: string,
     *   basketOffcanvasId: string,
     *   mobilemenuTheme: string,
     *   mobilemenuPanelMode: string,
     *   mobilemenuCloseOnClick: bool
     * }
     */
    public function getParams(): array
    {
        return [
            // Logo path and sticky behavior.
            'logo'   => 'media/com_copymypage/images/logo/logo-cmp-1.png',
            'sticky' => true,

            // Offcanvas target IDs (used by navbar toggles and offcanvas markup).
            'navOffcanvasId'    => 'cmp-mobilemenu-nav',
            'userOffcanvasId'   => 'cmp-mobilemenu-user',
            'basketOffcanvasId' => 'cmp-mobilemenu-basket',

            // Mobile menu behavior flags (dev defaults).
            'mobilemenuTheme'        => 'dark',
            'mobilemenuPanelMode'    => 'panels',
            'mobilemenuCloseOnClick' => true,
        ];
    }

    /**
     * Builds a Registry object compatible with Joomla core mod_menu MenuHelper.
     *
     * During development, all menu settings are defined here to avoid relying on DB params.
     *
     * @return Registry  A Registry object for core MenuHelper consumption.
     */
    public function getMenuParams(): Registry
    {
        $menuParams = new Registry();

        $menuParams->set('menutype', 'copymypage');
        $menuParams->set('startLevel', 1);
        $menuParams->set('endLevel', 0);

        // Keep behaviour core-like (same key name as mod_menu expects).
        $menuParams->set('showAllChildren', 1);

        // Dev defaults (can be wired later if needed).
        $menuParams->set('base', 0);
        $menuParams->set('secure', 0);
        $menuParams->set('aliasoptions', []);

        return $menuParams;
    }

    /**
     * Returns a user menu list for mobile layouts (placeholder for now).
     *
     * If you later want real user items, you can either:
     * - build them from com_users routes, or
     * - use a second menutype and delegate to core MenuHelper again.
     *
     * @param  Registry                $params  The module parameters object (kept for future use).
     * @param  CMSApplicationInterface $app     The application instance.
     *
     * @return array<int, object>
     */
    public function getUserItems(Registry $params, CMSApplicationInterface $app): array
    {
        return [];
    }
}
