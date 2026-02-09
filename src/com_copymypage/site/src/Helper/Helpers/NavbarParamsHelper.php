<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.5
 */

namespace Joomla\Component\CopyMyPage\Site\Helper\Helpers;

\defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Registry\Registry;

/**
 * Navbar params helper for CopyMyPage module parameter resolution.
 */
final class NavbarParamsHelper
{
    /**
     * Fetch merged navbar module parameters (DB-backed).
     *
     * We resolve both supported positions ("navbar" + "mobilemenu") and merge
     * them with "mobilemenu" taking precedence.
     *
     * Merge rule: values from module position "mobilemenu" override values from
     * module position "navbar" on key collisions.
     *
     * @return  array<string, mixed>
     */
    public function getModuleParams(): array
    {
        $navbar = $this->getModuleParamsByPosition('navbar');
        $mobile = $this->getModuleParamsByPosition('mobilemenu');

        if ($navbar === [] && $mobile === []) {
            return [];
        }

        // mobilemenu overrides navbar on overlapping keys.
        return array_replace_recursive($navbar, $mobile);
    }

    /**
     * Resolve a mod_copymypage_navbar instance by module position and return its params.
     *
     * @param   string  $position  The module position (e.g. "navbar", "mobilemenu").
     *
     * @return  array<string, mixed>
     */
    private function getModuleParamsByPosition(string $position): array
    {
        $modules = ModuleHelper::getModules($position);

        foreach ($modules as $module) {
            if (($module->module ?? '') !== 'mod_copymypage_navbar') {
                continue;
            }

            $registry = new Registry();
            $registry->loadString((string) ($module->params ?? ''), 'JSON');

            return $registry->toArray();
        }

        return [];
    }
}
