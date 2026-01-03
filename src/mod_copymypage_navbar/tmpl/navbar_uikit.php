<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;

$moduleClass = 'cmp-module cmp-module--navbar';

if (!empty($moduleclass_sfx)) {
    $moduleClass .= ' ' . htmlspecialchars($moduleclass_sfx, ENT_QUOTES, 'UTF-8');
}

$mobileMenuTarget = 'cmp-mobilemenu';

$input     = $app->getInput();
$option    = $input->getCmd('option', '');
$view      = $input->getCmd('view', '');
$isOnepage = CopyMyPageHelper::isOnepage($option, $view);
?>

<div class="<?php echo $moduleClass; ?>">

    <div
        uk-sticky="start: 1; end: false; sel-target: .uk-navbar-container;
        cls-active: cmp-navbar--scrolled;
        cls-inactive: cmp-navbar--top uk-navbar-transparent uk-light"
    >
        <div class="uk-navbar-container cmp-navbar-container">
            <div class="uk-container">
                <div class="uk-navbar" uk-navbar="mode: hover; delay-show: 0; delay-hide: 200">

                    <!-- LEFT: Desktop = Logo, Mobile = Menu toggle -->
                    <div class="uk-navbar-left">
                        <!-- Mobile toggle -->
                        <button
                            class="uk-navbar-toggle uk-hidden@m cmp-navbar-toggle"
                            type="button"
                            aria-label="Open menu"
                            data-cmp-mobilemenu-toggle="#<?php echo $mobileMenuTarget; ?>"
                        >
                            <span uk-navbar-toggle-icon></span>
                        </button>

                        <!-- Desktop logo -->
                        <a
                            class="uk-navbar-item uk-logo uk-visible@m cmp-navbar-logo-link"
                            href="<?php echo Uri::root(); ?>"
                        >
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
                        <!-- Mobile centered logo -->
                        <a
                            class="uk-navbar-item uk-logo uk-hidden@m cmp-navbar-logo-link"
                            href="<?php echo Uri::root(); ?>"
                        >
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

                        <!-- Desktop navigation -->
                        <?php if (!empty($list) && \is_array($list)) : ?>
                            <ul class="uk-navbar-nav uk-visible@m cmp-navbar-nav">
                                <?php
                                $activeId = (int) ($active_id ?? 0);

                                // Prefer the active item's tree for "trail"; fall back to $path if needed.
                                $trailIds = [];

                                if (isset($active) && isset($active->tree) && \is_array($active->tree)) {
                                    $trailIds = array_map('intval', $active->tree);
                                } elseif (\is_array($path)) {
                                    $trailIds = array_map('intval', $path);
                                }
                                ?>

                                <?php foreach ($list as $item) : ?>
                                    <?php
                                    $level = (int) $item->level;
                                    $id    = (int) $item->id;

                                    $isTrail  = \in_array($id, $trailIds, true);
                                    $isActive = ($id === $activeId);

                                    // "Has dropdown" means: next item is deeper (core flag semantics).
                                    $hasDropdown = (bool) $item->deeper;

                                    $liClasses = [];

                                    // Mark trail AND active with uk-active so parent items get highlighted.
                                    if ($isActive || $isTrail) {
                                        $liClasses[] = 'uk-active';
                                    }

                                    // For nested menus inside dropdown: UIkit expects uk-parent on items that have children.
                                    if ($hasDropdown && $level >= 2) {
                                        $liClasses[] = 'uk-parent';
                                    }

                                    $attribs = [
                                        'id'    => $id,
                                        'class' => 'cmp-navbar-link',
                                    ];

                                    if ($isActive) {
                                        $attribs['aria-current'] = 'page';
                                    }

                                    if (!empty($item->anchor_css)) {
                                        $attribs['class'] .= ' ' . $item->anchor_css;
                                    }

                                    if (!empty($item->anchor_title)) {
                                        $attribs['title'] = $item->anchor_title;
                                    }

                                    if (!empty($item->anchor_rel)) {
                                        $attribs['rel'] = $item->anchor_rel;
                                    }

                                    // Build URL.
                                    $url = $item->flink;

                                    // If we are on the onepage view and the original menu link is a hash, keep it a pure hash.
                                    // This is important for UIkit uk-scroll to apply correctly.
                                    if (
                                        $isOnepage
                                        && $item->type === 'url'
                                        && !empty($item->link)
                                        && str_starts_with($item->link, '#')
                                    ) {
                                        $url = $item->link;
                                    }

                                    // If we are NOT on the onepage view, route top-level hash items to the onepage view + anchor.
                                    if (
                                        !$isOnepage
                                        && $level === 1
                                        && $item->type === 'url'
                                        && !empty($item->link)
                                        && str_starts_with($item->link, '#')
                                    ) {
                                        $url = Route::link('site', 'index.php?option=com_copymypage&view=onepage') . $item->link;
                                    }

                                    $linkText = $item->title;

                                    // Separators: only useful inside dropdowns.
                                    if ($item->type === 'separator') {
                                        echo ($level > 1) ? '<li class="uk-nav-divider"></li>' : '<li class="uk-hidden"></li>';
                                        continue;
                                    }

                                    // Headings: behave like dropdown toggles (no jump).
                                    if ($item->type === 'heading') {
                                        $url = '#';

                                        if ($hasDropdown && $level === 1) {
                                            $linkText = '<span>' . $item->title . '</span>' . '<span uk-navbar-parent-icon></span>';
                                        }

                                        $attribs['role']          = 'button';
                                        $attribs['aria-haspopup'] = 'true';
                                        $attribs['aria-expanded'] = 'false';
                                        $attribs['onclick']       = 'return false;';
                                    } elseif ($hasDropdown && $level === 1) {
                                        // Parent item: use UIkit's navbar parent icon (rotates on open/hover).
                                        $linkText = '<span>' . $item->title . '</span>' . '<span uk-navbar-parent-icon></span>';

                                        // Do NOT prevent navigation here; dropdown is hover-based on desktop.
                                        $attribs['aria-haspopup'] = 'true';
                                        $attribs['aria-expanded'] = 'false';
                                    }

                                    $liClassAttr = $liClasses ? ' class="' . implode(' ', $liClasses) . '"' : '';
                                    echo '<li' . $liClassAttr . '>';

                                    // Mark same-page anchors so JS can attach UIkit scroll with the correct offset.
                                    $isScrollAnchor = $isOnepage
                                        && $item->type === 'url'
                                        && \is_string($url)
                                        && $url !== '#'
                                        && str_starts_with($url, '#');

                                    if ($isScrollAnchor) {
                                        $attribs['data-cmp-scroll'] = '1';
                                    }

                                    echo HTMLHelper::_(
                                        'link',
                                        OutputFilter::ampReplace(htmlspecialchars($url, ENT_COMPAT, 'UTF-8', false)),
                                        $linkText,
                                        $attribs
                                    );

                                    // Depth handling.
                                    if ($item->deeper) {
                                        if ($level === 1) {
                                            // Dropdown container (level 1 -> level 2).
                                            echo '<div class="uk-navbar-dropdown">';
                                            echo '<ul class="uk-nav uk-navbar-dropdown-nav uk-nav-parent-icon">';
                                        } else {
                                            // Nested inside dropdown.
                                            echo '<ul class="uk-nav-sub">';
                                        }
                                    } elseif ($item->shallower) {
                                        echo '</li>';

                                        for ($j = 0; $j < (int) $item->level_diff; $j++) {
                                            $closingLevel = $level - $j;

                                            // Closing from level 2 back to level 1 => close dropdown wrapper + parent <li>.
                                            if ($closingLevel === 2) {
                                                echo '</ul></div></li>';
                                            } else {
                                                // Closing nested sub levels.
                                                echo '</ul></li>';
                                            }
                                        }
                                    } else {
                                        echo '</li>';
                                    }
                                    ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- RIGHT: Desktop = Icons, Mobile = User icon -->
                    <div class="uk-navbar-right">
                        <!-- Mobile: only user icon -->
                        <a class="uk-navbar-item uk-hidden@m cmp-navbar-icon-link" href="#" aria-label="User">
                            <span uk-icon="user"></span>
                        </a>

                        <!-- Desktop: icon group -->
                        <div class="uk-navbar-item">
                            <ul class="uk-iconnav uk-visible@m cmp-navbar-icons">
                                <li><a class="uk-icon" href="#" aria-label="User" uk-icon="icon: user"></a></li>
                                <li>
                                    <a
                                        class="uk-icon"
                                        href="<?php echo Route::link('site', 'index.php?option=com_finder&view=search'); ?>"
                                        aria-label="Search"
                                        uk-icon="icon: search"
                                    ></a>
                                </li>
                                <li><a class="uk-icon" href="#" aria-label="Basket" uk-icon="icon: cart"></a></li>
                            </ul>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>
