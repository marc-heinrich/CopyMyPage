<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Module\CopyMyPage\Footer\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * Prepares language-based footer information for presentation layouts.
 */
final class FooterHelper
{
    /**
     * Get the neutral footer entries for the active layout.
     *
     * @return array<int, object>
     */
    public function getItems(): array
    {
        return [
            (object) [
                'key'  => 'copymypage',
                'text' => Text::_('MOD_COPYMYPAGE_FOOTER_COPYMYPAGE_LICENSE'),
            ],
            (object) [
                'key'  => 'joomla',
                'text' => Text::_('MOD_COPYMYPAGE_FOOTER_JOOMLA_LICENSE'),
            ],
        ];
    }
}
