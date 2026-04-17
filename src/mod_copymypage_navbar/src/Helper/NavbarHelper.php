<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.10
 */

namespace Joomla\Module\CopyMyPage\Navbar\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;
use Joomla\Registry\Registry;

/**
 * Helper class for the CopyMyPage Navbar module.
 *
 * This helper is intentionally small and acts as the single source of truth
 * for all runtime defaults while the module is in development.
 *
 * Menu rendering logic is delegated to Joomla core (mod_menu MenuHelper via bootModule()).
 */
class NavbarHelper
{
    /**
     * Base layouts per supported system slot.
     *
     * The slot token is also the layout prefix.
     *
     * @var array<string, string>
     */
    private const SLOT_BASE_LAYOUTS = [
        'navbar'     => 'uikit',
        'mobilemenu' => 'mmenulight',
    ];

    /**
     * Build the merged client-side payload for all navbar-related slots.
     *
     * @return array{
     *     shared: array<string, mixed>,
     *     slots: array<string, array<string, mixed>>
     * }
     */
    public function getClientConfig(): array
    {
        $shared = [];
        $slots  = [];

        foreach ($this->getSupportedSlots() as $slot) {
            $slotConfig   = $this->getSlotRuntimeConfig($slot);
            $slots[$slot] = $slotConfig;
            $shared       = array_replace($shared, $this->withoutMeta($slotConfig));
        }

        return [
            'shared' => $shared,
            'slots'  => $slots,
        ];
    }

    /**
     * Build shared runtime config across all supported navbar-related slots.
     *
     * @return array<string, mixed>
     */
    public function getSharedConfig(): array
    {
        return $this->getClientConfig()['shared'];
    }

    /**
     * Return the fixed system slots supported by this module.
     *
     * @return  array<int, string>
     */
    public function getSupportedSlots(): array
    {
        return array_keys(self::SLOT_BASE_LAYOUTS);
    }

    /**
     * Check whether a slot is supported by this module.
     *
     * @param   string  $slot  The fixed system slot.
     *
     * @return  bool
     */
    public function isSupportedSlot(string $slot): bool
    {
        return isset(self::SLOT_BASE_LAYOUTS[strtolower(trim($slot))]);
    }

    /**
     * Build the runtime config for a supported slot.
     *
     * @param   string  $slot  The fixed system slot.
     *
     * @return  array<string, mixed>
     */
    public function getSlotRuntimeConfig(string $slot): array
    {
        $slot         = strtolower(trim($slot));
        $baseLayout   = $this->resolveBaseLayout($slot);
        $rawParams    = $this->getModuleParamsBySlot($slot);
        $layout       = $this->resolveStoredLayoutVariant($rawParams, $slot);
        $slotConfig   = $this->getSlotDefaults($slot, $baseLayout);
        $layoutConfig = $this->getLayoutConfig($rawParams, $layout);

        return array_replace($slotConfig, $layoutConfig);
    }

    /**
     * Resolve the supported base layout name for a slot.
     *
     * @param   string  $slot  The fixed system slot.
     *
     * @return  string
     */
    public function resolveBaseLayout(string $slot): string
    {
        $slot       = strtolower(trim($slot));
        $baseLayout = strtolower(trim(self::SLOT_BASE_LAYOUTS[$slot] ?? ''));

        if ($slot === '') {
            return '';
        }

        if ($baseLayout === '') {
            return $slot;
        }

        if (str_starts_with($baseLayout, $slot . '_')) {
            return $baseLayout;
        }

        return $slot . '_' . $baseLayout;
    }

    /**
     * Resolve the stored layout variant for a slot with safe fallback rules.
     *
     * @param   array<string, mixed>  $params  Raw module parameters.
     * @param   string                $slot    The fixed system slot.
     *
     * @return  string
     */
    public function resolveStoredLayoutVariant(array $params, string $slot): string
    {
        $slot    = strtolower(trim($slot));
        $variant = strtolower(trim((string) ($params['layoutVariant'] ?? 'default')));
        $default = $this->resolveBaseLayout($slot);

        if ($slot === '' || $variant === '' || $variant === 'default') {
            return $default;
        }

        if (!str_starts_with($variant, $slot . '_')) {
            return $default;
        }

        return $variant;
    }

    /**
     * Normalize the layout config for runtime/template/client consumption.
     *
     * Stored params may be layout-prefixed in XML, but the returned runtime payload stays
     * JS- and template-friendly via stable canonical keys.
     *
     * @param   Registry|array<string, mixed>  $params   Raw module params.
     * @param   string                         $layout  Resolved layout key.
     *
     * @return  array<string, mixed>
     */
    public function getLayoutConfig(Registry|array $params, string $layout): array
    {
        $rawParams = $params instanceof Registry ? $params->toArray() : $params;
        $layout    = strtolower(trim($layout));
        $slot      = $this->extractSlotFromLayout($layout);

        if ($layout === '' || $slot === '') {
            return [];
        }

        $config = [
            'slot'         => $slot,
            'layoutPrefix' => $slot,
            'layoutVariant'=> $layout,
        ];

        return match ($layout) {
            'navbar_uikit' => array_replace($config, $this->getNavbarUIKitConfig($rawParams)),
            'mobilemenu_mmenulight' => array_replace($config, $this->getMobilemenuMmenuLightConfig($rawParams)),
            default => $config,
        };
    }

    /**
     * Builds a Registry object compatible with Joomla core mod_menu MenuHelper.
     *
     * During development, all menu settings are defined here to avoid relying on DB params.
     *
     * @return Registry  A Registry object for core MenuHelper consumption.
     */
    public function getMenuParams(): Registry
    {
        $menuParams = new Registry();

        $menuParams->set('menutype', 'copymypage');
        $menuParams->set('startLevel', 1);
        $menuParams->set('endLevel', 0);

        // Keep behaviour core-like (same key name as mod_menu expects).
        $menuParams->set('showAllChildren', 1);

        // Dev defaults (can be wired later if needed).
        $menuParams->set('base', 0);
        $menuParams->set('secure', 0);
        $menuParams->set('aliasoptions', []);

        return $menuParams;
    }

    /**
     * Returns a user menu list for mobile layouts (dummy for now).
     *
     * @param  Registry                $params  The module parameters object (kept for future use).
     * @param  CMSApplicationInterface $app     The application instance.
     *
     * @return array<int, object>
     */
    public function getUserItems(Registry $params, CMSApplicationInterface $app): array
    {
        $user   = $app->getIdentity();
        $return = rawurlencode(base64_encode(Uri::base()));

        if ($user->guest) {
            return [
                (object) [
                    'title' => Text::_('JLOGIN'),
                    'link'  => Route::link('site', 'index.php?option=com_users&view=login', false),
                ],
            ];
        }

        return [
            (object) [
                'title' => Text::_('COM_USERS_PROFILE'),
                'link'  => Route::link('site', 'index.php?option=com_users&view=profile', false),
            ],
            (object) [
                'title' => Text::_('JLOGOUT'),
                'link'  => Route::link(
                    'site',
                    'index.php?option=com_users&task=user.logout&' . Session::getFormToken() . '=1&return=' . $return,
                    false
                ),
            ],
        ];
    }

    /**
     * Returns a basket menu list for mobile layouts (dummy for now).
     *
     * @param  Registry                $params  The module parameters object (kept for future use).
     * @param  CMSApplicationInterface $app     The application instance.
     *
     * @return array<int, object>
     */
    public function getBasketItems(Registry $params, CMSApplicationInterface $app): array
    {
        return [
            (object) [
                'title' => Text::_('JGLOBAL_CLOSE'),
                'link'  => '#',
            ],
        ];
    }

    /**
     * Build common navigation state used by desktop and mobile layouts.
     *
     * @param   array<int, object>  $list        Menu items.
     * @param   object|null         $active      Active menu item.
     * @param   array<int, int>     $path        Active path fallback.
     * @param   string              $activeSlot  Active CopyMyPage slot token.
     *
     * @return  array<string, mixed>
     */
    public function getNavigationState(array $list, ?object $active, array $path, string $activeSlot): array
    {
        $trailIds = [];

        if (isset($active->tree) && is_array($active->tree)) {
            $trailIds = array_map('intval', $active->tree);
        } else {
            $trailIds = array_map('intval', $path);
        }

        return [
            'activeId'           => isset($active->id) ? (int) $active->id : 0,
            'activeSlot'         => strtolower(trim($activeSlot)),
            'trailIds'           => $trailIds,
            'hasForcedActiveSlot'=> $this->hasForcedActiveSlot($list, $activeSlot),
        ];
    }

    /**
     * Check whether a menu item targets the active CopyMyPage slot hash.
     *
     * @param   object  $menuItem    Menu item object.
     * @param   string  $activeSlot  Active CopyMyPage slot token.
     *
     * @return  bool
     */
    public function matchesActiveSlot(object $menuItem, string $activeSlot): bool
    {
        $activeSlot = strtolower(trim($activeSlot));

        if ($activeSlot === '' || (string) ($menuItem->type ?? '') !== 'url') {
            return false;
        }

        $candidates = [
            (string) ($menuItem->link ?? ''),
            (string) ($menuItem->flink ?? ''),
        ];

        foreach ($candidates as $candidate) {
            if (CopyMyPageHelper::extractHashToken($candidate) === $activeSlot) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether the current menu set contains a forced slot-active item.
     *
     * @param   array<int, object>  $list        Menu items.
     * @param   string              $activeSlot  Active CopyMyPage slot token.
     *
     * @return  bool
     */
    public function hasForcedActiveSlot(array $list, string $activeSlot): bool
    {
        $activeSlot = strtolower(trim($activeSlot));

        if ($activeSlot === '') {
            return false;
        }

        foreach ($list as $menuItem) {
            if (is_object($menuItem) && $this->matchesActiveSlot($menuItem, $activeSlot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve whether a menu item should be treated as current.
     *
     * @param   object               $menuItem       Menu item object.
     * @param   array<string, mixed> $navigationState Shared navigation state.
     * @param   bool                 $includeTrail   Include trail matches.
     *
     * @return  bool
     */
    public function isMenuItemCurrent(object $menuItem, array $navigationState, bool $includeTrail = false): bool
    {
        $activeSlot = (string) ($navigationState['activeSlot'] ?? '');

        if (!empty($navigationState['hasForcedActiveSlot'])) {
            return $this->matchesActiveSlot($menuItem, $activeSlot);
        }

        $itemId   = (int) ($menuItem->id ?? 0);
        $activeId = (int) ($navigationState['activeId'] ?? 0);

        if ($itemId === $activeId) {
            return true;
        }

        return $includeTrail
            && in_array($itemId, (array) ($navigationState['trailIds'] ?? []), true);
    }

    /**
     * Resolve the frontend URL for a menu item with onepage anchor handling.
     *
     * @param   object  $menuItem     Menu item object.
     * @param   bool    $isOnepage    Whether the current request is the onepage view.
     * @param   string  $onepageBase  Routed onepage base URL.
     *
     * @return  string
     */
    public function resolveMenuItemUrl(object $menuItem, bool $isOnepage, string $onepageBase): string
    {
        $level    = max(1, (int) ($menuItem->level ?? 1));
        $url      = (string) ($menuItem->flink ?? '');
        $itemType = (string) ($menuItem->type ?? '');
        $itemLink = (string) ($menuItem->link ?? '');

        if ($isOnepage && $itemType === 'url' && $itemLink !== '' && str_starts_with($itemLink, '#')) {
            return $itemLink;
        }

        if (!$isOnepage && $level === 1 && $itemType === 'url' && $itemLink !== '' && str_starts_with($itemLink, '#')) {
            return $onepageBase . $itemLink;
        }

        return $url;
    }

    /**
     * Convert flat Joomla menu items into a level-based tree.
     *
     * @param   array<int, object>  $items  Flat menu items.
     *
     * @return  array<int, array<string, mixed>>
     */
    public function buildMenuTree(array $items): array
    {
        $tree  = [];
        $stack = [];

        foreach ($items as $menuItem) {
            if (!is_object($menuItem)) {
                continue;
            }

            $level = max(1, (int) ($menuItem->level ?? 1));
            $node  = [
                'item'     => $menuItem,
                'level'    => $level,
                'children' => [],
            ];

            while (count($stack) >= $level) {
                array_pop($stack);
            }

            if ($level === 1 || $stack === []) {
                $tree[] = $node;
                $rootIndex = array_key_last($tree);
                $stack[] =& $tree[$rootIndex];

                continue;
            }

            $parentIndex = count($stack) - 1;
            $parentNode  =& $stack[$parentIndex];
            $parentNode['children'][] = $node;

            $childIndex = array_key_last($parentNode['children']);
            $stack[]    =& $parentNode['children'][$childIndex];

            unset($parentNode);
        }

        return $tree;
    }

    /**
     * Resolve raw module params for a supported slot.
     *
     * @param   string  $slot  Supported system slot.
     *
     * @return  array<string, mixed>
     */
    private function getModuleParamsBySlot(string $slot): array
    {
        $modules = ModuleHelper::getModules($slot);

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

    /**
     * Extract the slot token from a layout key.
     *
     * @param   string  $layout  Resolved layout key.
     *
     * @return  string
     */
    private function extractSlotFromLayout(string $layout): string
    {
        foreach ($this->getSupportedSlots() as $slot) {
            if ($layout === $slot || str_starts_with($layout, $slot . '_')) {
                return $slot;
            }
        }

        return '';
    }

    /**
     * Provide normalized defaults for a supported slot.
     *
     * @param   string  $slot        Supported system slot.
     * @param   string  $baseLayout  Resolved slot base layout.
     *
     * @return  array<string, mixed>
     */
    private function getSlotDefaults(string $slot, string $baseLayout): array
    {
        return match ($slot) {
            'navbar' => [
                'slot'                              => 'navbar',
                'layoutPrefix'                      => 'navbar',
                'layoutVariant'                     => $baseLayout,
                'logo'                              => 'media/com_copymypage/images/logo/logo-cmp.png',
                'userDropdownHoldOpenEnabled'       => true,
                'userDropdownCloseDelay'            => 180,
                'userDropdownCloseOnNavClick'       => true,
                'userDropdownSelectorRoot'          => '.cmp-module--navbar',
                'userDropdownSelectorUser'          => '.cmp-navbar-user',
                'userDropdownSelectorToggle'        => 'a.cmp-navbar-icon',
                'userDropdownSelectorDropdown'      => '.cmp-navbar-user .uk-navbar-dropdown',
                'userDropdownSelectorNavbarDropdown'=> '.cmp-navbar .uk-navbar-dropdown',
            ],
            'mobilemenu' => [
                'slot'                        => 'mobilemenu',
                'layoutPrefix'                => 'mobilemenu',
                'layoutVariant'               => $baseLayout,
                'navOffcanvasId'              => 'cmp-mobilemenu-nav',
                'userOffcanvasId'             => 'cmp-mobilemenu-user',
                'basketOffcanvasId'           => 'cmp-mobilemenu-basket',
                'mmenuLightMediaQuery'        => 'all',
                'mmenuLightSelectedClass'     => 'Selected',
                'mmenuLightSlidingSubmenus'   => true,
                'mmenuLightTheme'             => 'dark',
                'mmenuLightCloseOnClick'      => true,
                'mmenuLightItemHeight'        => 50,
                'mmenuLightOcdWidth'          => 80,
                'mmenuLightOcdMinWidth'       => 200,
                'mmenuLightOcdMaxWidth'       => 440,
                'mmenuLightNavTitle'          => 'Menu',
                'mmenuLightNavPosition'       => 'left',
                'mmenuLightUserTitle'         => 'User',
                'mmenuLightUserPosition'      => 'right',
                'mmenuLightBasketTitle'       => 'Basket',
                'mmenuLightBasketPosition'    => 'right',
            ],
            default => [
                'slot'         => $slot,
                'layoutPrefix' => $slot,
                'layoutVariant'=> $baseLayout,
            ],
        };
    }

    /**
     * Normalize the navbar UIkit layout config to canonical runtime keys.
     *
     * @param   array<string, mixed>  $params  Raw module params.
     *
     * @return  array<string, mixed>
     */
    private function getNavbarUIKitConfig(array $params): array
    {
        return [
            'logo'                              => $this->readString($params, ['navbar_uikit_logo', 'logo'], 'media/com_copymypage/images/logo/logo-cmp.png'),
            'userDropdownHoldOpenEnabled'       => $this->readBool($params, ['navbar_uikit_userDropdownHoldOpenEnabled', 'userDropdownHoldOpenEnabled'], true),
            'userDropdownCloseDelay'            => $this->readInt($params, ['navbar_uikit_userDropdownCloseDelay', 'userDropdownCloseDelay'], 180, 0),
            'userDropdownCloseOnNavClick'       => $this->readBool($params, ['navbar_uikit_userDropdownCloseOnNavClick', 'userDropdownCloseOnNavClick'], true),
            'userDropdownSelectorRoot'          => $this->readString($params, ['navbar_uikit_userDropdownSelectorRoot', 'userDropdownSelectorRoot'], '.cmp-module--navbar'),
            'userDropdownSelectorUser'          => $this->readString($params, ['navbar_uikit_userDropdownSelectorUser', 'userDropdownSelectorUser'], '.cmp-navbar-user'),
            'userDropdownSelectorToggle'        => $this->readString($params, ['navbar_uikit_userDropdownSelectorToggle', 'userDropdownSelectorToggle'], 'a.cmp-navbar-icon'),
            'userDropdownSelectorDropdown'      => $this->readString($params, ['navbar_uikit_userDropdownSelectorDropdown', 'userDropdownSelectorDropdown'], '.cmp-navbar-user .uk-navbar-dropdown'),
            'userDropdownSelectorNavbarDropdown'=> $this->readString($params, ['navbar_uikit_userDropdownSelectorNavbarDropdown', 'userDropdownSelectorNavbarDropdown'], '.cmp-navbar .uk-navbar-dropdown'),
        ];
    }

    /**
     * Normalize the mobilemenu Mmenu Light layout config to canonical runtime keys.
     *
     * @param   array<string, mixed>  $params  Raw module params.
     *
     * @return  array<string, mixed>
     */
    private function getMobilemenuMmenuLightConfig(array $params): array
    {
        return [
            'navOffcanvasId'            => $this->readString($params, ['mobilemenu_mmenulight_navOffcanvasId', 'navOffcanvasId'], 'cmp-mobilemenu-nav'),
            'userOffcanvasId'           => $this->readString($params, ['mobilemenu_mmenulight_userOffcanvasId', 'userOffcanvasId'], 'cmp-mobilemenu-user'),
            'basketOffcanvasId'         => $this->readString($params, ['mobilemenu_mmenulight_basketOffcanvasId', 'basketOffcanvasId'], 'cmp-mobilemenu-basket'),
            'mmenuLightMediaQuery'      => $this->readString($params, ['mobilemenu_mmenulight_mediaQuery', 'mmenuLightMediaQuery'], 'all'),
            'mmenuLightSelectedClass'   => $this->readString($params, ['mobilemenu_mmenulight_selectedClass', 'mmenuLightSelectedClass'], 'Selected'),
            'mmenuLightSlidingSubmenus' => $this->readBool($params, ['mobilemenu_mmenulight_slidingSubmenus', 'mmenuLightSlidingSubmenus'], true),
            'mmenuLightTheme'           => $this->readString($params, ['mobilemenu_mmenulight_theme', 'mmenuLightTheme'], 'dark'),
            'mmenuLightCloseOnClick'    => $this->readBool($params, ['mobilemenu_mmenulight_closeOnClick', 'mmenuLightCloseOnClick'], true),
            'mmenuLightItemHeight'      => $this->readInt($params, ['mobilemenu_mmenulight_itemHeight', 'mmenuLightItemHeight'], 50, 0),
            'mmenuLightOcdWidth'        => $this->readInt($params, ['mobilemenu_mmenulight_ocdWidth', 'mmenuLightOcdWidth'], 80, 0),
            'mmenuLightOcdMinWidth'     => $this->readInt($params, ['mobilemenu_mmenulight_ocdMinWidth', 'mmenuLightOcdMinWidth'], 200, 0),
            'mmenuLightOcdMaxWidth'     => $this->readInt($params, ['mobilemenu_mmenulight_ocdMaxWidth', 'mmenuLightOcdMaxWidth'], 440, 0),
            'mmenuLightNavTitle'        => $this->readString($params, ['mobilemenu_mmenulight_navTitle', 'mmenuLightNavTitle'], 'Menu'),
            'mmenuLightNavPosition'     => $this->readString($params, ['mobilemenu_mmenulight_navPosition', 'mmenuLightNavPosition'], 'left'),
            'mmenuLightUserTitle'       => $this->readString($params, ['mobilemenu_mmenulight_userTitle', 'mmenuLightUserTitle'], 'User'),
            'mmenuLightUserPosition'    => $this->readString($params, ['mobilemenu_mmenulight_userPosition', 'mmenuLightUserPosition'], 'right'),
            'mmenuLightBasketTitle'     => $this->readString($params, ['mobilemenu_mmenulight_basketTitle', 'mmenuLightBasketTitle'], 'Basket'),
            'mmenuLightBasketPosition'  => $this->readString($params, ['mobilemenu_mmenulight_basketPosition', 'mmenuLightBasketPosition'], 'right'),
        ];
    }

    /**
     * Read the first available string value from a param key list.
     *
     * @param   array<string, mixed>  $params   Raw params.
     * @param   array<int, string>    $keys     Preferred param keys.
     * @param   string                $default  Safe fallback.
     *
     * @return  string
     */
    private function readString(array $params, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $params)) {
                continue;
            }

            return CopyMyPageHelper::toString($params[$key], $default);
        }

        return $default;
    }

    /**
     * Read the first available boolean value from a param key list.
     *
     * @param   array<string, mixed>  $params   Raw params.
     * @param   array<int, string>    $keys     Preferred param keys.
     * @param   bool                  $default  Safe fallback.
     *
     * @return  bool
     */
    private function readBool(array $params, array $keys, bool $default = false): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $params)) {
                continue;
            }

            return CopyMyPageHelper::toBool($params[$key], $default);
        }

        return $default;
    }

    /**
     * Read the first available integer value from a param key list.
     *
     * @param   array<string, mixed>  $params   Raw params.
     * @param   array<int, string>    $keys     Preferred param keys.
     * @param   int                   $default  Safe fallback.
     * @param   int|null              $min      Optional minimum.
     * @param   int|null              $max      Optional maximum.
     *
     * @return  int
     */
    private function readInt(array $params, array $keys, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $params)) {
                continue;
            }

            return CopyMyPageHelper::toInt($params[$key], $default, $min, $max);
        }

        return $default;
    }

    /**
     * Remove slot meta keys from a config payload before shared merging.
     *
     * @param   array<string, mixed>  $config  Slot runtime config.
     *
     * @return  array<string, mixed>
     */
    private function withoutMeta(array $config): array
    {
        unset(
            $config['slot'],
            $config['layoutPrefix'],
            $config['layoutVariant']
        );

        return $config;
    }
}
