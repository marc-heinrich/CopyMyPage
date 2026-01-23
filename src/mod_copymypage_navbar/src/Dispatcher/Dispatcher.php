<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

namespace Joomla\Module\CopyMyPage\Navbar\Site\Dispatcher;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Registry\Registry;

/**
 * Dispatcher class for mod_copymypage_navbar.
 *
 * Selects the layout automatically based on the module position and a single variant field.
 * While the module is in development, all runtime defaults come from the module helper.
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Runs the dispatcher.
     *
     * Uses a core-like loader pattern and resolves the layout based on:
     * - module position (navbar/mobilemenu)
     * - a single variant field
     *
     * @return void
     */
    public function dispatch(): void
    {
        // Load the module language.
        $this->loadLanguage();

        $displayData = $this->getLayoutData();

        // Bail out if no data is available.
        if ($displayData === false) {
            return;
        }

        // Get the module position for layout resolution.
        $position = strtolower((string) ($displayData['module']->position ?? ''));

        // Resolve the base layout by module position.
        $baseLayout = match ($position) {
            'navbar'     => 'navbar',
            'mobilemenu' => 'mobilemenu',
            default      => 'default',
        };

        // Read a single variant value (e.g. "navbar_uikit", "mobilemenu_mmenulight", "default").
        $layoutVariant = strtolower(trim((string) ($displayData['cfg']['layoutVariant'] ?? 'default')));

        // Build the final layout name.
        if ($baseLayout === 'default') {
            $layout = 'default';
        } else {
            $expectedPrefix = $baseLayout . '_';

            if (
                $layoutVariant !== ''
                && $layoutVariant !== 'default'
                && str_starts_with($layoutVariant, $expectedPrefix)
            ) {
                $layout = $layoutVariant;
            } else {
                $layout = $baseLayout;
            }
        }

        // Execute the layout without the module context (core pattern).
        $loader = static function (array $displayData, string $layout, string $fallbackLayout): void {
            if (!\array_key_exists('displayData', $displayData)) {
                extract($displayData);
                unset($displayData);
            } else {
                extract($displayData);
            }

            // Resolve the layout path.
            $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_navbar', $layout);

            // Fallback to base layout if the specific variant does not exist.
            if (!is_file($layoutPath)) {
                $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_navbar', $fallbackLayout);
            }

            require $layoutPath;
        };

        // Determine the fallback layout.
        $fallbackLayout = ($baseLayout === 'default') ? 'default' : $baseLayout;

        // Run the loader.
        $loader($displayData, $layout, $fallbackLayout);
    }

    /**
     * Build the layout payload for this module instance.
     *
     * Exposes raw, DB-backed module params as `$cfg` (layouts cast what they need) and
     * computes `$isOnepage` from the current request to adjust anchor/link output.
     * Reuses Joomla core `mod_menu` MenuHelper to populate active/base/default items,
     * path/showAll, and the final `$list` (with `$navItems` alias) plus `$userItems`.
     *
     * @return array|false  Layout data array, or false to skip rendering.
     */
    protected function getLayoutData(): array|false
    {
        $data = parent::getLayoutData();

        // Prepare helper instances.
        $helper     = $this->getHelperFactory()->getHelper('NavbarHelper');
        $menuHelper = $data['app']->bootModule('mod_menu', 'site')->getHelper('MenuHelper');

        // Expose raw, DB-backed module params to the layout (no helper bridge).
        $data['cfg'] = ($data['params'] instanceof \Joomla\Registry\Registry)
            ? $data['params']->toArray()
            : [];

        // Determine if we are in a CopyMyPage onepage view (used by layouts to adjust output).
        $option = $data['input']->getCmd('option', '');
        $view   = $data['input']->getCmd('view', '');
        $data['isOnepage'] = \Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper::isOnepage($option, $view);

        // Prepare menu parameters for mod_menu MenuHelper (dev defaults from helper).
        $menuParams = $helper->getMenuParams();

        // Get menu items and related data via Core MenuHelper.
        $base    = $menuHelper->getBaseItem($menuParams, $data['app']);
        $active  = $menuHelper->getActiveItem($data['app']);
        $default = $menuHelper->getDefaultItem($data['app']);

        // Populate data array with menu info.
        $data['base']       = $base;
        $data['active']     = $active;
        $data['default']    = $default;
        $data['active_id']  = isset($active->id) ? (int) $active->id : 0;
        $data['default_id'] = isset($default->id) ? (int) $default->id : 0;
        $data['path']       = isset($base->tree) && \is_array($base->tree) ? $base->tree : [];
        $data['showAll']    = (int) $menuParams->get('showAllChildren', 1);
        $data['list']       = $menuHelper->getItems($menuParams, $data['app']);

        // Provide explicit names for the two mobile menus.
        $data['navItems']  = $data['list'];
        $data['userItems'] = $helper->getUserItems($data['params'], $data['app']);

        return $data;
    }
}
