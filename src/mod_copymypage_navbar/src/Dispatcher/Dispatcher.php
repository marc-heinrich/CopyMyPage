<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

namespace Joomla\Module\CopyMyPage\Navbar\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Helper\ModuleHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

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
    public function dispatch()
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

        // Read a single variant value (e.g. "navbar_uikit", "mobilemenu_mmenu", "default").
        $layoutVariant = strtolower(trim((string) $displayData['params']->get('layout_variant', 'default')));

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

            /**
             * Extracted variables
             * -----------------
             * @var \stdClass                               $module
             * @var \Joomla\Registry\Registry               $params
             * @var \Joomla\CMS\Application\SiteApplication $app
             * @var \Joomla\CMS\Input\Input                 $input
             *
             * @var bool   $isOnepage
             * @var string $logo
             * @var bool   $sticky
             *
             * @var array<int, object> $navItems
             * @var array<int, object> $userItems
             * @var string             $navOffcanvasId
             * @var string             $userOffcanvasId
             * @var string             $basketOffcanvasId
             * @var string             $mobilemenuTheme
             * @var string             $mobilemenuPanelMode
             * @var bool               $mobilemenuCloseOnClick
             *
             * @var array<int, object> $list
             * @var array<int, int>    $path
             * @var object             $base
             * @var object             $active
             * @var object             $default
             * @var int                $active_id
             * @var int                $default_id
             * @var int                $showAll
             */

            $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_navbar', $layout);

            if (!is_file($layoutPath)) {
                $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_navbar', $fallbackLayout);
            }

            require $layoutPath;
        };

        $fallbackLayout = ($baseLayout === 'default') ? 'default' : $baseLayout;

        $loader($displayData, $layout, $fallbackLayout);
    }

    /**
     * Returns the layout data.
     *
     * While the module is in development, all runtime defaults come from the helper.
     * The only request-context logic here is the onepage detection and the core menu lookup.
     *
     * Note: Keep using $data['input'] as requested.
     *
     * @return array|false
     */
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();

        // Prepare helper instances.
        $helper     = $this->getHelperFactory()->getHelper('NavbarHelper');
        $menuHelper = $data['app']->bootModule('mod_menu', 'site')->getHelper('MenuHelper');

        // Development defaults (single source of truth).
        $defaults = $helper->getParams();

        // Determine if we are in a CopyMyPage onepage view.
        $option = $data['input']->getCmd('option', '');
        $view   = $data['input']->getCmd('view', '');
        $data['isOnepage'] = \Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper::isOnepage($option, $view);

        // Provide module-specific defaults (dev stage).
        $data['logo']   = (string) $defaults['logo'];
        $data['sticky'] = (bool) $defaults['sticky'];

        // Offcanvas target IDs.
        $data['navOffcanvasId']    = (string) $defaults['navOffcanvasId'];
        $data['userOffcanvasId']   = (string) $defaults['userOffcanvasId'];
        $data['basketOffcanvasId'] = (string) $defaults['basketOffcanvasId'];

        // Mobile menu behaviour flags.
        $data['mobilemenuTheme']        = (string) $defaults['mobilemenuTheme'];
        $data['mobilemenuPanelMode']    = (string) $defaults['mobilemenuPanelMode'];
        $data['mobilemenuCloseOnClick'] = (bool) $defaults['mobilemenuCloseOnClick'];

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
