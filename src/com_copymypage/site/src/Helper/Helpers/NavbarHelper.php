<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.3
 */

namespace Joomla\Component\CopyMyPage\Site\Helper\Helpers;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Navbar helper for CopyMyPage.
 *
 * Provides methods to build login/logout/profile navbar items.
 */
final class NavbarHelper
{
    /**
     * Create login or profile button in the navbar.
     *
     * @param   string  $logType  The log type ('login' or 'logout').
     *
     * @return  string  The HTML string for the button or user menu.
     */
    public static function getLogItem(string $logType): string
    {
        // Login button.
        if ($logType === 'login') {
            $attribs = ['class' => 'btn btn-primary btn-login d-flex'];
            $url     = Route::link('site', 'index.php?option=com_users&view=' . $logType);
            $title   = Text::_('JLOGIN');

            return HTMLHelper::_(
                'link',
                OutputFilter::ampReplace(htmlspecialchars($url, ENT_COMPAT, 'UTF-8', false)),
                $title,
                $attribs
            );
        }

        // User menu (profile + logout).
        $app = Factory::getApplication();

        return LayoutHelper::render(
            'copymypage.navbar.userMenu',
            [
                'profile' => Route::link(
                    'site',
                    'index.php?option=com_copymypage&view=dashboard&layout=profile'
                ),
                'signout' => Route::link(
                    'site',
                    'index.php?option=com_users&task=user.' . $logType . '&' . Session::getFormToken() . '=1'
                ),
                'user'    => $app->getIdentity(),
            ]
        );
    }
}
