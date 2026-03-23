<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.1
 */

namespace Joomla\Module\CopyMyPage\Gallery\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

/**
 * Helper class for the CopyMyPage Gallery module.
 */
final class GalleryHelper
{
    /**
     * Returns the placeholder message for the initial scaffold.
     *
     * @param  object    $module  The module object.
     * @param  Registry  $params  The module parameters.
     *
     * @return string
     */
    public function getHelloMessage(object $module, Registry $params): string
    {
        return Text::_('MOD_COPYMYPAGE_GALLERY_HELLO_WORLD');
    }
}
