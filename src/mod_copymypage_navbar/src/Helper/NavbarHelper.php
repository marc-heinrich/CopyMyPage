<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

namespace Joomla\Module\CopyMyPage\Navbar\Site\Helper;

\defined('_JEXEC') or die;

/**
 * Helper class to prepare data for the CopyMyPage Navbar module.
 *
 * Later, this class will read the configuration from the module parameters
 * and/or the database.
 */
class NavbarHelper
{
    /**
     * Get the parameters for the Navbar module as an associative array.
     *
     * This method will later read the parameters from the database or configuration.
     *
     * @return array  The parameters as an associative array.
     */
    public static function getParams(): array
    {
        return [
            'logo'              => 'media/com_copymypage/images/logo/logo-cmp-1.png', // Default logo
            'sticky'            => true,  // Default sticky option (true = sticky)
            'moduleclass_sfx'   => '',    // Default module class suffix
        ];
    }
}
