<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.10
 */

namespace Joomla\Module\CopyMyPage\Dev\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
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
     * Build Open Graph compatible tag data for a dev-backed onepage slot.
     *
     * @param   Registry      $params  The module params.
     * @param   object|null   $module  The published module row.
     * @param   string        $slot    The active system slot.
     *
     * @return  array<string, string>
     */
    public function getOGTags(Registry $params, ?object $module = null, string $slot = ''): array
    {
        return match (strtolower(trim($slot))) {
            'contact' => [
                'slot'        => 'contact',
                'label'       => Text::_('MOD_COPYMYPAGE_DEV_OG_TITLE_CONTACT'),
                'title'       => Text::_('MOD_COPYMYPAGE_DEV_OG_TITLE_CONTACT'),
                'description' => Text::_('MOD_COPYMYPAGE_DEV_OG_DESCRIPTION_CONTACT'),
                'image'       => '',
                'twitterCard' => 'summary',
            ],
            default => [
                'slot'        => 'team',
                'label'       => Text::_('MOD_COPYMYPAGE_DEV_OG_TITLE_TEAM'),
                'title'       => Text::_('MOD_COPYMYPAGE_DEV_OG_TITLE_TEAM'),
                'description' => Text::_('MOD_COPYMYPAGE_DEV_OG_DESCRIPTION_TEAM'),
                'image'       => '',
                'twitterCard' => 'summary',
            ],
        };
    }

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
