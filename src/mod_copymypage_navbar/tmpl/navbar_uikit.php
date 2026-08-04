<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;

/**
 * Extracted variables
 * -----------------
 * @var bool  $isOnepage
 *
 * @var array<string, mixed> $cfg Normalized/typed module configuration (from helper).
 *                                Document only the keys used in this layout:
 *                                - logoLayout: string
 *                                - navOffcanvasId: string
 *                                - userOffcanvasId: string
 *                                - userDropdownSelectorRoot: string
 *
 * @var array<int, object> $list
 * @var array<string, mixed> $accountMenu
 * @var array<int, int>    $path
 * @var object             $active
 * @var int                $active_id
 * @var string             $activeSlot
 * @var string             $warning
 * @var array<string, mixed> $navigationState
 * @var \Joomla\Module\CopyMyPage\Navbar\Site\Helper\NavbarHelper $navbarHelper
 */

// Closure for escaping output.
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

// Read only the config keys used by this layout.
// For type normalization (boolean or integer), use the component helper class CopyMyPage.
$logoLayout             = strtolower(trim((string) ($cfg['logoLayout'] ?? 'image')));
$logoLayout             = \in_array($logoLayout, ['image', 'image_title'], true) ? $logoLayout : 'image';
$navOffcanvasId         = (string) ($cfg['navOffcanvasId'] ?? '');
$userOffcanvasId        = (string) ($cfg['userOffcanvasId'] ?? '');
$userDropdownRootClass  = CopyMyPageHelper::selectorToToken((string) $cfg['userDropdownSelectorRoot'] ?? '');
$moduleClass            = trim('cmp-module ' . $userDropdownRootClass);
$onepageBase            = Route::link('site', 'index.php?option=com_copymypage&view=onepage');
$basketUrl              = Route::link('site', 'index.php?option=com_copymypage&view=basket');
$basketLabel            = Text::_('MOD_COPYMYPAGE_NAVBAR_BASKET_OPEN');
$basketTitle            = Text::_('MOD_COPYMYPAGE_NAVBAR_BASKET_TITLE');
$finderUrl              = Route::link('site', 'index.php?option=com_finder&view=search');
$finderLabel            = Text::_('JSEARCH_FILTER_SUBMIT');
$logoHref               = $isOnepage ? '#top' : $onepageBase;
$navigationState        = is_array($navigationState ?? null) ? $navigationState : [];
$accountMenu            = is_array($accountMenu ?? null) ? $accountMenu : [];
$accountItems           = is_array($accountMenu['items'] ?? null) ? $accountMenu['items'] : [];
$isAuthenticated        = !(bool) ($accountMenu['guest'] ?? true);
$accountAction          = $isAuthenticated
    ? ($accountMenu['logout'] ?? null)
    : ($accountMenu['login'] ?? null);
$accountAction          = is_array($accountAction) ? $accountAction : null;
$userIconClass          = 'cmp-navbar-user-icon'
    . ($isAuthenticated ? ' cmp-navbar-user-icon--authenticated' : '');
$renderLogo             = static fn(string $context): string => LayoutHelper::render(
    'copymypage.navbar.logo.' . $logoLayout,
    ['context' => $context]
);
$renderAccountItems = static function (array $nodes) use (&$renderAccountItems, $escape): string {
    ob_start();

    foreach ($nodes as $item) {
        if (!\is_array($item)) {
            continue;
        }

        $type = \in_array(($item['type'] ?? ''), ['link', 'heading', 'separator'], true)
            ? (string) $item['type']
            : 'link';

        if ($type === 'separator') {
            echo '<li class="uk-nav-divider" role="separator"></li>';

            continue;
        }

        $title       = trim((string) ($item['label'] ?? ''));
        $children    = \is_array($item['children'] ?? null) ? $item['children'] : [];
        $isCurrent   = (bool) ($item['current'] ?? false);
        $activeTrail = (bool) ($item['activeTrail'] ?? false);
        $ariaCurrent = \in_array(($item['ariaCurrent'] ?? ''), ['page', 'location'], true)
            ? (string) $item['ariaCurrent']
            : '';

        if ($title === '') {
            continue;
        }

        $classes = [];

        if ($isCurrent || $activeTrail) {
            $classes[] = 'uk-active';
        }

        if ($children !== []) {
            $classes[] = 'uk-parent';
        }

        if ($type === 'heading') {
            $classes[] = 'cmp-navbar-account-heading';
        }

        $classAttribute = $classes !== []
            ? ' class="' . $escape(implode(' ', $classes)) . '"'
            : '';

        echo '<li' . $classAttribute . '>';

        if ($type === 'heading') {
            echo '<span class="uk-nav-header">' . $escape($title) . '</span>';
        } else {
            echo '<a class="cmp-navbar-link" href="' . $escape($item['url'] ?? '#') . '"';

            if ($ariaCurrent !== '') {
                echo ' aria-current="' . $ariaCurrent . '"';
            }

            echo '>' . $escape($title) . '</a>';
        }

        if ($children !== []) {
            echo '<ul class="uk-nav-sub">';
            echo $renderAccountItems($children);
            echo '</ul>';
        }

        echo '</li>';
    }

    return (string) ob_get_clean();
};

if (!isset($navbarHelper) || !$navbarHelper instanceof \Joomla\Module\CopyMyPage\Navbar\Site\Helper\NavbarHelper) {
    return;
}

if (isset($app) && $app instanceof \Joomla\CMS\Application\CMSApplicationInterface) {
    /** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
    $wa = $app->getDocument()->getWebAssetManager();
    $wa->useScript('mburger');
    $wa->useScript('copymypage.dropdown');
}

if (!empty($warning)) {
    echo $warning;
}
?>
<!-- Navbar Module Template: Desktop UIkit Framework (https://getuikit.com/docs/navbar) -->
<div class="<?php echo $escape($moduleClass); ?>">
    <div
        uk-sticky="start: 1; end: false; sel-target: .uk-navbar-container;
        cls-active: cmp-navbar--scrolled;
        cls-inactive: cmp-navbar--top uk-navbar-transparent uk-light"
    >
        <div class="uk-navbar-container cmp-navbar-container">
            <div class="uk-container">
                <div class="uk-navbar" uk-navbar="mode: hover; delay-show: 0; delay-hide: 200">

                    <!-- LEFT: Desktop = Logo, Mobile = Nav toggle -->
                    <div class="uk-navbar-left">
                        
                        <!-- Animated hamburger (https://mmenujs.com/mburger/) -->
                        <mm-burger
                            class="uk-hidden@m cmp-navbar-toggle"
                            fx="collapse"
                            ease="ease"
                            role="button"
                            tabindex="0"
                            aria-label="Open menu"
                            aria-expanded="false"
                            title="Open menu"
                            data-cmp-mmenulight-open="#<?php echo $escape($navOffcanvasId); ?>"
                        ></mm-burger>

                        <a class="uk-navbar-item uk-logo uk-visible@m cmp-navbar-logo-link" href="<?php echo $escape($logoHref); ?>">
                            <?php echo $renderLogo('desktop'); ?>
                        </a>
                    </div>

                    <!-- CENTER: Desktop = Nav items, Mobile = Logo -->
                    <div class="uk-navbar-center">
                        <a class="uk-navbar-item uk-logo uk-hidden@m cmp-navbar-logo-link" href="<?php echo $escape($logoHref); ?>">
                            <?php echo $renderLogo('mobile'); ?>
                        </a>

                        <ul class="uk-navbar-nav uk-visible@m cmp-navbar-nav">
                            <?php
                            // Build shared link attributes for navbar items.
                            $buildLinkAttribs = static function (object $menuItem, bool $isActive): array {
                                $attribs = [
                                    'id'    => (int) ($menuItem->id ?? 0),
                                    'class' => 'cmp-navbar-link',
                                ];

                                if ($isActive) {
                                    $attribs['aria-current'] = 'page';
                                }

                                if (!empty($menuItem->anchor_css)) {
                                    $attribs['class'] .= ' ' . $menuItem->anchor_css;
                                }

                                if (!empty($menuItem->anchor_title)) {
                                    $attribs['title'] = $menuItem->anchor_title;
                                }

                                if (!empty($menuItem->anchor_rel)) {
                                    $attribs['rel'] = $menuItem->anchor_rel;
                                }

                                return $attribs;
                            };

                            // Render one navbar link with UIkit icon and accessibility states.
                            $renderNavbarLink = static function (
                                object $menuItem,
                                int $level,
                                bool $isActive,
                                bool $hasChildren,
                                bool $isTopLevel
                            ) use ($buildLinkAttribs, $escape, $isOnepage, $onepageBase, $navbarHelper): string {
                                $itemType = (string) ($menuItem->type ?? '');
                                $url      = $navbarHelper->resolveMenuItemUrl($menuItem, $isOnepage, $onepageBase);
                                $attribs  = $buildLinkAttribs($menuItem, $isActive);
                                $linkText = $escape((string) ($menuItem->title ?? ''));

                                if ($itemType === 'heading') {
                                    $url = '#';
                                    $attribs['role']          = 'button';
                                    $attribs['aria-haspopup'] = 'true';
                                    $attribs['aria-expanded'] = 'false';
                                    $attribs['onclick']       = 'return false;';
                                } elseif ($hasChildren && $isTopLevel) {
                                    $attribs['aria-haspopup'] = 'true';
                                    $attribs['aria-expanded'] = 'false';
                                }

                                if ($hasChildren && $isTopLevel) {
                                    $linkText = '<span>' . $linkText . '</span><span uk-navbar-parent-icon></span>';
                                }

                                $isScrollAnchor = $isOnepage
                                    && $itemType === 'url'
                                    && $url !== '#'
                                    && str_starts_with($url, '#');

                                if ($isScrollAnchor) {
                                    $attribs['data-cmp-scroll'] = '1';
                                }

                                return HTMLHelper::_(
                                    'link',
                                    OutputFilter::ampReplace(htmlspecialchars($url, ENT_COMPAT, 'UTF-8', false)),
                                    $linkText,
                                    $attribs
                                );
                            };

                            // Render dropdown list nodes recursively for nested submenus.
                            $renderDropdownNodes = null;

                            $renderDropdownNodes = static function (array $nodes) use (
                                &$renderDropdownNodes,
                                $renderNavbarLink,
                                $escape,
                                $navbarHelper,
                                $navigationState
                            ): string {
                                $html = '';

                                foreach ($nodes as $node) {
                                    if (!isset($node['item']) || !\is_object($node['item'])) {
                                        continue;
                                    }

                                    $item       = $node['item'];
                                    $level      = (int) ($node['level'] ?? ($item->level ?? 1));
                                    $children   = $node['children'] ?? [];
                                    $hasChildren = !empty($children);
                                    $itemType   = (string) ($item->type ?? '');

                                    if ($itemType === 'separator') {
                                        $html .= '<li class="uk-nav-divider"></li>';

                                        continue;
                                    }

                                    if ($itemType === 'heading') {
                                        $html .= '<li class="uk-nav-header">' . $escape((string) ($item->title ?? '')) . '</li>';

                                        if ($hasChildren) {
                                            $html .= $renderDropdownNodes($children);
                                        }

                                        continue;
                                    }

                                    $isActive = $navbarHelper->isMenuItemCurrent($item, $navigationState, true);

                                    $liClasses = [];

                                    if ($isActive) {
                                        $liClasses[] = 'uk-active';
                                    }

                                    if ($hasChildren) {
                                        $liClasses[] = 'uk-parent';
                                    }

                                    $liClassAttr = $liClasses ? ' class="' . implode(' ', $liClasses) . '"' : '';
                                    $html .= '<li' . $liClassAttr . '>';
                                    $html .= $renderNavbarLink($item, $level, $isActive, false, false);

                                    if ($hasChildren) {
                                        $html .= '<ul class="uk-nav-sub">';
                                        $html .= $renderDropdownNodes($children);
                                        $html .= '</ul>';
                                    }

                                    $html .= '</li>';
                                }

                                return $html;
                            };

                            $tree = $navbarHelper->buildMenuTree($list);

                            foreach ($tree as $node) :
                                if (!isset($node['item']) || !\is_object($node['item'])) {
                                    continue;
                                }

                                $item     = $node['item'];
                                $level    = (int) ($node['level'] ?? ($item->level ?? 1));
                                $itemType = (string) ($item->type ?? '');

                                if ($level !== 1) {
                                    continue;
                                }

                                if ($itemType === 'separator') {
                                    echo '<li class="uk-hidden"></li>';

                                    continue;
                                }

                                $isActive = $navbarHelper->isMenuItemCurrent($item, $navigationState, false);
                                $isTrail  = $navbarHelper->isMenuItemCurrent($item, $navigationState, true) && !$isActive;
                                // Keep only real child columns and skip separator pseudo items.
                                $children = array_values(array_filter(
                                    $node['children'] ?? [],
                                    static function (array $childNode): bool {
                                        return isset($childNode['item'])
                                            && \is_object($childNode['item'])
                                            && (string) ($childNode['item']->type ?? '') !== 'separator';
                                    }
                                ));
                                $hasDropdown = !empty($children);

                                $liClasses = [];

                                if ($isActive || $isTrail) {
                                    $liClasses[] = 'uk-active';
                                }

                                if ($hasDropdown) {
                                    $liClasses[] = 'uk-parent';
                                }

                                $liClassAttr = $liClasses ? ' class="' . implode(' ', $liClasses) . '"' : '';
                                echo '<li' . $liClassAttr . '>';
                                echo $renderNavbarLink($item, $level, $isActive, $hasDropdown, true);

                                if ($hasDropdown) {
                                    // Limit mega dropdown columns to the five-column UIkit variant.
                                    $columnCount = max(1, min(5, \count($children)));
                                    $dropdownClasses = ['uk-navbar-dropdown', 'cmp-navbar-mega-dropdown'];

                                    if ($columnCount >= 2) {
                                        $dropdownClasses[] = 'uk-navbar-dropdown-width-' . $columnCount;
                                    }

                                    echo '<div class="' . implode(' ', $dropdownClasses) . '">';
                                    echo '<div class="uk-navbar-dropdown-grid uk-child-width-1-' . $columnCount . '" uk-grid>';

                                    foreach ($children as $columnNode) {
                                        if (!isset($columnNode['item']) || !\is_object($columnNode['item'])) {
                                            continue;
                                        }

                                        $columnItem        = $columnNode['item'];
                                        $columnLevel       = (int) ($columnNode['level'] ?? ($columnItem->level ?? 2));
                                        $columnChildren    = $columnNode['children'] ?? [];
                                        $columnHasChildren = !empty($columnChildren);
                                        $columnType        = (string) ($columnItem->type ?? '');

                                        if ($columnType === 'separator') {
                                            continue;
                                        }

                                        echo '<div><ul class="uk-nav uk-navbar-dropdown-nav">';

                                        if ($columnHasChildren) {
                                            if ($columnType === 'heading') {
                                                echo '<li class="uk-nav-header">' . $escape((string) ($columnItem->title ?? '')) . '</li>';
                                            } else {
                                                echo '<li class="uk-nav-header">';
                                                echo $renderNavbarLink($columnItem, $columnLevel, false, false, false);
                                                echo '</li>';
                                            }

                                            echo $renderDropdownNodes($columnChildren);
                                        } else {
                                            if ($columnType === 'heading') {
                                                echo '<li class="uk-nav-header">' . $escape((string) ($columnItem->title ?? '')) . '</li>';
                                            } else {
                                                echo '<li>';
                                                echo $renderNavbarLink($columnItem, $columnLevel, false, false, false);
                                                echo '</li>';
                                            }
                                        }

                                        echo '</ul></div>';
                                    }

                                    echo '</div></div>';
                                }

                                echo '</li>';
                            endforeach;
                            ?>
                        </ul>
                    </div>

                    <!-- RIGHT: Mobile = User/Search/Basket icons, Desktop = Icons -->
                    <div class="uk-navbar-right">
                        <!-- Mobile: User offcanvas -->
                        <a
                            class="uk-navbar-item uk-hidden@m cmp-navbar-icon-link"
                            href="#"
                            role="button"
                            aria-label="Open user menu"
                            data-cmp-mmenulight-open="#<?php echo $escape($userOffcanvasId); ?>"
                        >
                            <span class="<?php echo $escape($userIconClass); ?>" uk-icon="user"></span>
                        </a>

                        <!-- Mobile: Finder search -->
                        <a
                            class="uk-navbar-item uk-hidden@m cmp-navbar-icon-link"
                            href="<?php echo $escape($finderUrl); ?>"
                            aria-label="<?php echo $escape($finderLabel); ?>"
                            title="<?php echo $escape($finderLabel); ?>"
                        >
                            <span uk-icon="search" aria-hidden="true"></span>
                        </a>

                        <!-- Mobile: Basket content drawer -->
                        <a
                            class="uk-navbar-item uk-hidden@m cmp-navbar-icon-link"
                            href="<?php echo $escape($basketUrl); ?>"
                            aria-label="<?php echo $escape($basketLabel); ?>"
                            title="<?php echo $escape($basketLabel); ?>"
                            aria-haspopup="dialog"
                            data-cmp-content-drawer="basket"
                            data-cmp-drawer-title="<?php echo $escape($basketTitle); ?>"
                            data-cmp-drawer-transport="document"
                        >
                            <span uk-icon="cart" aria-hidden="true"></span>
                        </a>

                        <!-- Desktop: icon nav (use uk-navbar-nav so dropdown uses navbar positioning) -->
                        <div class="uk-navbar-item uk-visible@m">
                            <ul class="uk-navbar-nav cmp-navbar-icons-nav">

                                <li class="uk-parent cmp-navbar-user">
                                    <a
                                        href="#"
                                        class="cmp-navbar-icon"
                                        aria-label="User"
                                        aria-haspopup="true"
                                        aria-expanded="false"
                                        onclick="return false;"
                                    >
                                        <span class="<?php echo $escape($userIconClass); ?>" uk-icon="icon: user"></span>
                                    </a>

                                    <div class="uk-navbar-dropdown cmp-navbar-user-dropdown" uk-drop="pos: bottom-center">
                                        <ul class="uk-nav uk-navbar-dropdown-nav">
                                            <?php if ($accountItems === [] && $accountAction === null) : ?>
                                                <li class="uk-disabled"><a class="cmp-navbar-link" href="#" onclick="return false;">&mdash;</a></li>
                                            <?php else : ?>
                                                <?php echo $renderAccountItems($accountItems); ?>
                                                <?php if ($accountItems !== [] && $accountAction !== null) : ?>
                                                    <li class="uk-nav-divider" role="separator"></li>
                                                <?php endif; ?>
                                                <?php if ($accountAction !== null) : ?>
                                                    <li>
                                                        <a
                                                            class="cmp-navbar-link"
                                                            href="<?php echo $escape($accountAction['url'] ?? '#'); ?>"
                                                        >
                                                            <?php echo $escape($accountAction['label'] ?? ''); ?>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </li>

                                <li>
                                    <a
                                        href="<?php echo $escape($finderUrl); ?>"
                                        class="cmp-navbar-icon"
                                        aria-label="<?php echo $escape($finderLabel); ?>"
                                        title="<?php echo $escape($finderLabel); ?>"
                                    >
                                        <span uk-icon="icon: search" aria-hidden="true"></span>
                                    </a>
                                </li>

                                <li>
                                    <a
                                        href="<?php echo $escape($basketUrl); ?>"
                                        class="cmp-navbar-icon"
                                        aria-label="<?php echo $escape($basketLabel); ?>"
                                        title="<?php echo $escape($basketLabel); ?>"
                                        aria-haspopup="dialog"
                                        data-cmp-content-drawer="basket"
                                        data-cmp-drawer-title="<?php echo $escape($basketTitle); ?>"
                                        data-cmp-drawer-transport="document"
                                    >
                                        <span uk-icon="icon: cart" aria-hidden="true"></span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
