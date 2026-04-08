<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.10
 */

namespace Joomla\Module\CopyMyPage\Navbar\Site\Dispatcher;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;

/**
 * Dispatcher class for mod_copymypage_navbar.
 *
 * Selects the layout automatically based on the module position and the selected layout variant.
 * While the module is in development, all runtime defaults come from the module helper.
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Collected warning messages for the current module render cycle.
     *
     * @var array<int, array<string, string>>
     */
    protected array $warnings = [];

    /**
     * Supported slot-to-layout mappings for this module.
     *
     * @var array<string, array{layoutPrefix: string, baseLayout: string}>
     */
    protected array $slotLayouts = [
        'navbar' => [
            'layoutPrefix' => 'navbar',
            'baseLayout'   => 'navbar_uikit',
        ],
        'mobilemenu' => [
            'layoutPrefix' => 'mobilemenu',
            'baseLayout'   => 'mobilemenu_mmenulight',
        ],
    ];

    /**
     * Runs the dispatcher.
     *
     * @return void
     */
    public function dispatch(): void
    {
        $this->loadLanguage();

        $displayData = $this->getLayoutData();

        if ($displayData === false) {
            return;
        }

        $slotLayout = $this->resolveSlotLayout($displayData);

        if ($slotLayout === null) {
            echo $this->renderWarnings();

            return;
        }

        $layoutVariant = strtolower(trim((string) ($displayData['cfg']['layoutVariant'] ?? $slotLayout['baseLayout'])));
        $layout = $this->resolveLayout(
            $layoutVariant,
            $slotLayout['layoutPrefix'],
            $slotLayout['baseLayout']
        );
        $displayData['warning'] = $this->renderWarnings();

        $loader = static function (array $displayData, string $layout): void {
            if (!\array_key_exists('displayData', $displayData)) {
                extract($displayData);
                unset($displayData);
            } else {
                extract($displayData);
            }

            /**
             * Extracted variables
             * -----------------
             * @var \stdClass                                      $module
             * @var \Joomla\Registry\Registry                      $params
             * @var \Joomla\CMS\Application\CMSApplicationInterface $app
             * @var array<string, mixed>                           $cfg
             * @var object|null                                    $base
             * @var object|null                                    $active
             * @var object|null                                    $default
             * @var int                                            $active_id
             * @var int                                            $default_id
             * @var array<int, int>                                $path
             * @var int                                            $showAll
             * @var array<int, object>                             $list
             * @var array<int, object>                             $navItems
             * @var array<int, object>                             $userItems
             * @var array<int, object>                             $basketItems
             * @var bool                                           $isOnepage
             * @var string                                         $activeSlot
             * @var string                                         $warning
             */
            require ModuleHelper::getLayoutPath('mod_copymypage_navbar', $layout);
        };

        $loader($displayData, $layout);
    }

    /**
     * Load module and shared CopyMyPage UI languages.
     *
     * @return  void
     */
    protected function loadLanguage(): void
    {
        parent::loadLanguage();

        CopyMyPageHelper::loadSharedUiLanguages($this->app->getLanguage());
    }

    /**
     * Resolves the slot layout context for the current module position.
     *
     * @param   array<string, mixed>  $displayData  Prepared display data.
     *
     * @return  array{layoutPrefix: string, baseLayout: string}|null
     */
    protected function resolveSlotLayout(array $displayData): ?array
    {
        $slot = strtolower(trim((string) ($displayData['module']->position ?? '')));

        if (!isset($this->slotLayouts[$slot])) {
            $this->queueInvalidLayoutWarning();

            return null;
        }

        return $this->slotLayouts[$slot];
    }

    /**
     * Resolves the requested layout variant to an existing navbar/mobilemenu layout.
     *
     * @param   string  $layoutVariant  Requested layout variant from module params.
     * @param   string  $layoutPrefix   Layout prefix for the current system slot.
     * @param   string  $baseLayout     Existing fallback layout for this module instance.
     *
     * @return  string
     */
    protected function resolveLayout(string $layoutVariant, string $layoutPrefix, string $baseLayout): string
    {
        $layoutPrefix = strtolower(trim($layoutPrefix));

        if ($layoutVariant === '' || $layoutVariant === 'default') {
            return $baseLayout;
        }

        if ($layoutPrefix !== '' && !str_starts_with($layoutVariant, $layoutPrefix . '_')) {
            $this->queueInvalidLayoutWarning();

            return $baseLayout;
        }

        $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_navbar', $layoutVariant);

        if (!is_file($layoutPath) || basename($layoutPath, '.php') !== $layoutVariant) {
            $this->queueInvalidLayoutWarning();

            return $baseLayout;
        }

        return $layoutVariant;
    }

    /**
     * Renders collected warnings via the shared system layout.
     *
     * @return  string
     */
    protected function renderWarnings(): string
    {
        if ($this->warnings === []) {
            return '';
        }

        return LayoutHelper::render(
            'copymypage.system.warning',
            ['messages' => $this->warnings]
        );
    }

    /**
     * Adds the navbar layout warning once per render cycle.
     *
     * @return  void
     */
    protected function queueInvalidLayoutWarning(): void
    {
        if ($this->warnings !== []) {
            return;
        }

        $modulesUrl = Route::link('administrator', 'index.php?option=com_modules&view=modules');

        $this->warnings[] = [
            'info' => Text::_('MOD_COPYMYPAGE_NAVBAR'),
            'desc' => Text::sprintf('MOD_COPYMYPAGE_NAVBAR_ALERT_INVALID_POSITION', $modulesUrl),
        ];
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
        $data['activeSlot'] = \Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper::resolveActiveSlot($option, $view);

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

        // Provide explicit names for the three mobile menus.
        $data['navItems']    = $data['list'];
        $data['userItems']   = $helper->getUserItems($data['params'], $data['app']);
        $data['basketItems'] = $helper->getBasketItems($data['params'], $data['app']);
        $data['warning']     = '';

        return $data;
    }
}
