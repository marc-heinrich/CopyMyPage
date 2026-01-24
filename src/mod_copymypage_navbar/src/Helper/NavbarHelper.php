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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
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
     * Returns a user menu list for mobile layouts (dummy for now).
     *
     * @param  Registry                $params  The module parameters object (kept for future use).
     * @param  CMSApplicationInterface $app     The application instance.
     *
     * @return array<int, object>
     */
    public function getUserItems(Registry $params, CMSApplicationInterface $app): array
    {
        $user   = $app->getIdentity();
        $return = rawurlencode(base64_encode(Uri::base()));

        if ($user->guest) {
            return [
                (object) [
                    'title' => Text::_('JLOGIN'),
                    'link'  => Route::link('site', 'index.php?option=com_users&view=login', false),
                ],
            ];
        }

        return [
            (object) [
                'title' => Text::_('COM_USERS_PROFILE'),
                'link'  => Route::link('site', 'index.php?option=com_users&view=profile', false),
            ],
            (object) [
                'title' => Text::_('JLOGOUT'),
                'link'  => Route::link(
                    'site',
                    'index.php?option=com_users&task=user.logout&' . Session::getFormToken() . '=1&return=' . $return,
                    false
                ),
            ],
        ];
    }

    /**
     * Returns a basket menu list for mobile layouts (dummy for now).
     *
     * @param  Registry                $params  The module parameters object (kept for future use).
     * @param  CMSApplicationInterface $app     The application instance.
     *
     * @return array<int, object>
     */
    public function getBasketItems(Registry $params, CMSApplicationInterface $app): array
    {
        return [
            (object) [
                'title' => Text::_('JGLOBAL_CLOSE'),
                'link'  => '#',
            ],
        ];
    }
}
