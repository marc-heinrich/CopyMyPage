<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.3
 */

namespace Joomla\Component\CopyMyPage\Site\Helper;

\defined('_JEXEC') or die;

/**
 * Static helper for CopyMyPage view related utilities.
 */
abstract class CopyMyPageHelper
{
    /**
     * Check whether the current request targets the CopyMyPage onepage view.
     *
     * @param   string  $option  The component option (e.g. 'com_copymypage').
     * @param   string  $view    The view name (e.g. 'onepage').
     *
     * @return  bool  True if the request is for com_copymypage & onepage view, otherwise false.
     */
    public static function isOnepage(string $option = '', string $view = ''): bool
    {
        return $option === 'com_copymypage' && $view === 'onepage';
    }
}
