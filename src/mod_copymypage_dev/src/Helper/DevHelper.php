<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.2
 */

namespace Joomla\Module\CopyMyPageDev\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Menu\SiteMenu;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use stdClass;

/**
 * Helper class for the CopyMyPage Dev module.
 */
class DevHelper
{
    /**
     * Collects debug data for the current request context.
     *
     * @param   stdClass  $module  The module object.
     * @param   Registry  $params  The module parameters.
     *
     * @return  array<string, mixed>
     */
    public static function getDebugData(stdClass $module, Registry $params): array
    {
        /** @var SiteApplication $app */
        $app = Factory::getApplication();

        /** @var SiteMenu $menu */
        $menu = $app->getMenu();
        $item = $menu->getActive();

        $language = $app->getLanguage();
        $uri      = Uri::getInstance();

        return [
            'module' => [
                'id'          => $module->id ?? null,
                'title'       => $module->title ?? null,
                'position'    => $module->position ?? null,
                'showtitle'   => $module->showtitle ?? null,
                'class_sfx'   => (string) $params->get('moduleclass_sfx'),
            ],
            'menu' => [
                'id'          => $item ? $item->id : null,
                'title'       => $item ? $item->title : null,
                'link'        => $item ? $item->link : null,
                'language'    => $item ? $item->language : null,
            ],
            'context' => [
                'uri'         => (string) $uri,
                'language'    => $language->getTag(),
                'client'      => $app->getClientId(),
            ],
        ];
    }
}
