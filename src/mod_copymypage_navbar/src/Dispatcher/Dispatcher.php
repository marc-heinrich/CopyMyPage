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
     * Active slot for the current module instance.
     *
     * The slot token is also the layout prefix.
     *
     * @var string
     */
    protected string $slot = '';

    /**
     * Runs the dispatcher.
     *
     * @return void
     */
    public function dispatch(): void
    {
        $this->loadLanguage();

        $displayData = $this->getBaseLayoutData();

        if ($displayData === false) {
            return;
        }

        if (!$this->hasValidSlotPosition($displayData)) {
            echo $this->renderWarnings();

            return;
        }

        $baseLayout    = $this->resolveBaseLayout();
        $layoutVariant = strtolower(trim((string) ($displayData['rawCfg']['layoutVariant'] ?? $baseLayout)));
        $layout        = $this->resolveLayout($layoutVariant, $baseLayout);

        $this->populateNavbarData($displayData, $layout);

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
             * @var string                                         $slot
             * @var string                                         $warning
             * @var array<string, mixed>                           $navigationState
             * @var \Joomla\Module\CopyMyPage\Navbar\Site\Helper\NavbarHelper $navbarHelper
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
     * Resolves the base layout for the active slot.
     *
     * @return  string
     */
    protected function resolveBaseLayout(): string
    {
        return $this->getHelperFactory()->getHelper('NavbarHelper')->resolveBaseLayout($this->slot);
    }

    /**
     * Resolves the requested layout variant to an existing navbar/mobilemenu layout.
     *
     * @param   string  $layoutVariant  Requested layout variant from module params.
     * @param   string  $baseLayout     Existing fallback layout for this module instance.
     *
     * @return  string
     */
    protected function resolveLayout(string $layoutVariant, string $baseLayout): string
    {
        $layoutPrefix = strtolower(trim($this->slot));

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
     * Check whether the current module instance is published in a supported system slot.
     *
     * @param   array<string, mixed>  $displayData  Prepared display data.
     *
     * @return  bool
     */
    protected function hasValidSlotPosition(array $displayData): bool
    {
        $slot = strtolower(trim((string) ($displayData['module']->position ?? '')));

        if (!$this->getHelperFactory()->getHelper('NavbarHelper')->isSupportedSlot($slot)) {
            $this->queueInvalidLayoutWarning();

            return false;
        }

        $this->slot = $slot;

        return true;
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
     * Prepare the raw display data before slot and layout validation.
     *
     * @return array<string, mixed>|false
     */
    protected function getBaseLayoutData(): array|false
    {
        $data = parent::getLayoutData();

        if ($data === false) {
            return false;
        }

        $data['rawCfg'] = ($data['params'] instanceof \Joomla\Registry\Registry)
            ? $data['params']->toArray()
            : [];
        $data['cfg']             = [];
        $data['slot']            = '';
        $data['navigationState'] = [];
        $data['navbarHelper']    = null;
        $data['base']            = null;
        $data['active']          = null;
        $data['default']         = null;
        $data['active_id']       = 0;
        $data['default_id']      = 0;
        $data['path']            = [];
        $data['showAll']         = 0;
        $data['list']            = [];
        $data['navItems']        = [];
        $data['userItems']       = [];
        $data['basketItems']     = [];
        $data['warning']         = '';

        $option = $data['input']->getCmd('option', '');
        $view   = $data['input']->getCmd('view', '');
        $data['isOnepage'] = \Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper::isOnepage($option, $view);
        $data['activeSlot'] = \Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper::resolveActiveSlot($option, $view);

        return $data;
    }

    /**
     * Populate shared navbar/mobilemenu data after slot and layout validation.
     *
     * @param   array<string, mixed>  $displayData  Prepared display data.
     * @param   string                $layout       Resolved layout key.
     *
     * @return  void
     */
    protected function populateNavbarData(array &$displayData, string $layout): void
    {
        $helper = $this->getHelperFactory()->getHelper('NavbarHelper');
        $menuHelper = $displayData['app']->bootModule('mod_menu', 'site')->getHelper('MenuHelper');
        $menuParams = $helper->getMenuParams();
        $base       = $menuHelper->getBaseItem($menuParams, $displayData['app']);
        $active     = $menuHelper->getActiveItem($displayData['app']);
        $default    = $menuHelper->getDefaultItem($displayData['app']);
        $list       = $menuHelper->getItems($menuParams, $displayData['app']);

        $displayData['slot']         = $this->slot;
        $displayData['navbarHelper'] = $helper;
        $displayData['cfg']          = array_replace(
            $helper->getSharedConfig(),
            $helper->getLayoutConfig($displayData['params'], $layout)
        );
        $displayData['base']       = $base;
        $displayData['active']     = $active;
        $displayData['default']    = $default;
        $displayData['active_id']  = isset($active->id) ? (int) $active->id : 0;
        $displayData['default_id'] = isset($default->id) ? (int) $default->id : 0;
        $displayData['path']       = isset($base->tree) && \is_array($base->tree) ? $base->tree : [];
        $displayData['showAll']    = (int) $menuParams->get('showAllChildren', 1);
        $displayData['list']       = $list;
        $displayData['navItems']   = $list;
        $displayData['userItems']  = $helper->getUserItems($displayData['params'], $displayData['app']);
        $displayData['basketItems']= $helper->getBasketItems($displayData['params'], $displayData['app']);
        $displayData['navigationState'] = $helper->getNavigationState(
            $list,
            $active,
            $displayData['path'],
            (string) ($displayData['activeSlot'] ?? '')
        );
    }
}
