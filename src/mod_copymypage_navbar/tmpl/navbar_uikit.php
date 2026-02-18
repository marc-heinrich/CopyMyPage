<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.5
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;

/**
 * Extracted variables
 * -----------------
 * @var bool  $isOnepage
 *
 * @var array<string, mixed> $cfg  Normalized/typed module configuration (from helper).
 *                                Document only the keys used in this layout:
 *                                - logo: string
 *                                - navOffcanvasId: string
 *                                - userOffcanvasId: string
 *                                - basketOffcanvasId: string
 *                                - userDropdownSelectorRoot: string
 *
 * @var array<int, object> $list
 * @var array<int, object> $userItems
 * @var array<int, int>    $path
 * @var object             $active
 * @var int                $active_id
 */

// Read only the config keys used by this layout.
// For type normalization (boolean or integer), use the component helper class CopyMyPage.
$logo                   = (string) ($cfg['logo'] ?? '');
$navOffcanvasId         = (string) ($cfg['navOffcanvasId'] ?? '');
$userOffcanvasId        = (string) ($cfg['userOffcanvasId'] ?? '');
$basketOffcanvasId      = (string) ($cfg['basketOffcanvasId'] ?? '');
$userDropdownRootClass  = CopyMyPageHelper::selectorToToken((string) $cfg['userDropdownSelectorRoot'] ?? '');
?>
<!-- Navbar Module Template: Desktop UIkit Framework (https://getuikit.com/docs/navbar) -->
<div class="cmp-module <?php echo htmlspecialchars($userDropdownRootClass, ENT_QUOTES, 'UTF-8'); ?>">
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
                        <button
                            class="uk-navbar-toggle uk-hidden@m cmp-navbar-toggle"
                            type="button"
                            aria-label="Open menu"
                            data-cmp-mmenulight-open="#<?php echo htmlspecialchars($navOffcanvasId, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <span uk-navbar-toggle-icon></span>
                        </button>

                        <a class="uk-navbar-item uk-logo uk-visible@m cmp-navbar-logo-link" href="<?php echo Uri::root(); ?>">
                            <img
                                class="cmp-navbar-logo"
                                src="<?php echo htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>"
                                alt="CopyMyPage – Your website. Just copy it."
                                width="140"
                                height="32"
                                loading="eager"
                                decoding="async"
                            >
                        </a>
                    </div>

                    <!-- CENTER: Desktop = Nav items, Mobile = Logo -->
                    <div class="uk-navbar-center">
                        <a class="uk-navbar-item uk-logo uk-hidden@m cmp-navbar-logo-link" href="<?php echo Uri::root(); ?>">
                            <img
                                class="cmp-navbar-logo"
                                src="<?php echo htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>"
                                alt="CopyMyPage – Your website. Just copy it."
                                width="140"
                                height="32"
                                loading="eager"
                                decoding="async"
                            >
                        </a>

                        <ul class="uk-navbar-nav uk-visible@m cmp-navbar-nav">
                            <?php
                            $activeId = (int) $active_id;
                            $trailIds = [];
                            $onepageBase = Route::link('site', 'index.php?option=com_copymypage&view=onepage');

                            if (isset($active->tree) && \is_array($active->tree)) {
                                $trailIds = array_map('intval', $active->tree);
                            } else {
                                $trailIds = array_map('intval', $path);
                            }
                            // Escape plain text for safe HTML output.
                            $escape = static function (string $value): string {
                                return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                            };

                            // Convert flat Joomla menu items into a level-based tree.
                            $buildMenuTree = static function (array $items): array {
                                $tree  = [];
                                $stack = [];

                                foreach ($items as $menuItem) {
                                    if (!\is_object($menuItem)) {
                                        continue;
                                    }

                                    $level = max(1, (int) ($menuItem->level ?? 1));
                                    $node  = [
                                        'item'     => $menuItem,
                                        'level'    => $level,
                                        'children' => [],
                                    ];

                                    while (\count($stack) >= $level) {
                                        array_pop($stack);
                                    }

                                    if ($level === 1 || empty($stack)) {
                                        $tree[] = $node;
                                        $rootIndex = array_key_last($tree);
                                        $stack[] =& $tree[$rootIndex];

                                        continue;
                                    }

                                    $parentIndex = \count($stack) - 1;
                                    $parentNode  =& $stack[$parentIndex];
                                    $parentNode['children'][] = $node;

                                    $childIndex = array_key_last($parentNode['children']);
                                    $stack[]    =& $parentNode['children'][$childIndex];

                                    unset($parentNode);
                                }

                                return $tree;
                            };

                            // Resolve each item URL with onepage anchor handling.
                            $resolveItemUrl = static function (object $menuItem, int $level) use ($isOnepage, $onepageBase): string {
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
                            };

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
                            ) use ($buildLinkAttribs, $resolveItemUrl, $escape, $isOnepage): string {
                                $itemType = (string) ($menuItem->type ?? '');
                                $url      = $resolveItemUrl($menuItem, $level);
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
                                $activeId,
                                $trailIds
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

                                    $id       = (int) ($item->id ?? 0);
                                    $isActive = ($id === $activeId) || \in_array($id, $trailIds, true);

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

                            $tree = $buildMenuTree($list);

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

                                $id       = (int) ($item->id ?? 0);
                                $isTrail  = \in_array($id, $trailIds, true);
                                $isActive = ($id === $activeId);
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

                                        $columnItem       = $columnNode['item'];
                                        $columnLevel      = (int) ($columnNode['level'] ?? ($columnItem->level ?? 2));
                                        $columnChildren   = $columnNode['children'] ?? [];
                                        $columnHasChildren = !empty($columnChildren);
                                        $columnType       = (string) ($columnItem->type ?? '');

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

                    <!-- RIGHT: Mobile = User/Basket toggles, Desktop = Icons -->
                    <div class="uk-navbar-right">
                        <!-- Mobile: User offcanvas -->
                        <a
                            class="uk-navbar-item uk-hidden@m cmp-navbar-icon-link"
                            href="#"
                            role="button"
                            aria-label="Open user menu"
                            data-cmp-mmenulight-open="#<?php echo htmlspecialchars($userOffcanvasId, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <span uk-icon="user"></span>
                        </a>

                        <!-- Mobile: Basket offcanvas -->
                        <a
                            class="uk-navbar-item uk-hidden@m cmp-navbar-icon-link"
                            href="#"
                            role="button"
                            aria-label="Open basket"
                            data-cmp-mmenulight-open="#<?php echo htmlspecialchars($basketOffcanvasId, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <span uk-icon="cart"></span>
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
                                        <span uk-icon="icon: user"></span>
                                    </a>

                                    <div class="uk-navbar-dropdown cmp-navbar-user-dropdown" uk-drop="pos: bottom-center">
                                        <ul class="uk-nav uk-navbar-dropdown-nav">
                                            <?php if (empty($userItems)) : ?>
                                                <li class="uk-disabled"><a class="cmp-navbar-link" href="#" onclick="return false;">—</a></li>
                                            <?php else : ?>
                                                <?php foreach ($userItems as $item) : ?>
                                                    <?php
                                                    $type      = (string) ($item->type ?? 'link');
                                                    $isDivider = (bool) ($item->divider ?? false);

                                                    if ($type === 'divider' || $isDivider) :
                                                        ?>
                                                        <li class="uk-nav-divider" role="separator"></li>
                                                        <?php
                                                        continue;
                                                    endif;

                                                    $title   = (string) ($item->title ?? '');
                                                    $href    = (string) ($item->link ?? '#');
                                                    $liClass = trim((string) ($item->class ?? ''));

                                                    if ($title === '') {
                                                        continue;
                                                    }
                                                    ?>
                                                    <li<?php echo $liClass !== '' ? ' class="' . htmlspecialchars($liClass, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                                                        <a class="cmp-navbar-link" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </li>

                                <li>
                                    <a
                                        href="<?php echo Route::link('site', 'index.php?option=com_finder&view=search'); ?>"
                                        class="cmp-navbar-icon"
                                        aria-label="Search"
                                    >
                                        <span uk-icon="icon: search"></span>
                                    </a>
                                </li>

                                <li>
                                    <a
                                        href="<?php echo Route::link('site', 'index.php?option=com_copymypage&view=basket'); ?>"
                                        class="cmp-navbar-icon"
                                        aria-label="Basket"
                                    >
                                        <span uk-icon="icon: cart"></span>
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
