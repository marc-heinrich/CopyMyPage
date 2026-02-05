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
 *
 * @var array<int, object> $list
 * @var array<int, object> $userItems
 * @var array<int, int>    $path
 * @var object             $active
 * @var int                $active_id
 */

// Read only the config keys used by this layout.
// For type normalization (boolean or integer), use the component helper class CopyMyPage.
$logo               = (string) ($cfg['logo'] ?? '');
$navOffcanvasId     = (string) ($cfg['navOffcanvasId'] ?? '');
$userOffcanvasId    = (string) ($cfg['userOffcanvasId'] ?? '');
$basketOffcanvasId  = (string) ($cfg['basketOffcanvasId'] ?? '');
?>
<!-- Navbar Module Template: Desktop UIkit Framework (https://getuikit.com/docs/navbar) -->
<div class="cmp-module cmp-module--navbar">
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

                            if (isset($active->tree) && \is_array($active->tree)) {
                                $trailIds = array_map('intval', $active->tree);
                            } else {
                                $trailIds = array_map('intval', $path);
                            }
                            ?>

                            <?php foreach ($list as $item) : ?>
                                <?php
                                $level = (int) $item->level;
                                $id    = (int) $item->id;

                                $isTrail  = \in_array($id, $trailIds, true);
                                $isActive = ($id === $activeId);

                                $hasDropdown = (bool) $item->deeper;

                                $liClasses = [];

                                if ($isActive || $isTrail) {
                                    $liClasses[] = 'uk-active';
                                }

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

                                $url = $item->flink;

                                if (
                                    $isOnepage
                                    && $item->type === 'url'
                                    && !empty($item->link)
                                    && str_starts_with($item->link, '#')
                                ) {
                                    $url = $item->link;
                                }

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

                                if ($item->type === 'separator') {
                                    echo ($level > 1) ? '<li class="uk-nav-divider"></li>' : '<li class="uk-hidden"></li>';
                                    continue;
                                }

                                if ($item->type === 'heading') {
                                    $url = '#';

                                    if ($hasDropdown && $level === 1) {
                                        $linkText = '<span>' . $item->title . '</span><span uk-navbar-parent-icon></span>';
                                    }

                                    $attribs['role']          = 'button';
                                    $attribs['aria-haspopup'] = 'true';
                                    $attribs['aria-expanded'] = 'false';
                                    $attribs['onclick']       = 'return false;';
                                } elseif ($hasDropdown && $level === 1) {
                                    $linkText = '<span>' . $item->title . '</span><span uk-navbar-parent-icon></span>';
                                    $attribs['aria-haspopup'] = 'true';
                                    $attribs['aria-expanded'] = 'false';
                                }

                                $liClassAttr = $liClasses ? ' class="' . implode(' ', $liClasses) . '"' : '';
                                echo '<li' . $liClassAttr . '>';

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

                                if ($item->deeper) {
                                    if ($level === 1) {
                                        echo '<div class="uk-navbar-dropdown">';
                                        echo '<ul class="uk-nav uk-navbar-dropdown-nav uk-nav-parent-icon">';
                                    } else {
                                        echo '<ul class="uk-nav-sub">';
                                    }
                                } elseif ($item->shallower) {
                                    echo '</li>';

                                    for ($j = 0; $j < (int) $item->level_diff; $j++) {
                                        $closingLevel = $level - $j;

                                        if ($closingLevel === 2) {
                                            echo '</ul></div></li>';
                                        } else {
                                            echo '</ul></li>';
                                        }
                                    }
                                } else {
                                    echo '</li>';
                                }
                                ?>
                            <?php endforeach; ?>
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

                                    <div class="uk-navbar-dropdown cmp-navbar-user-dropdown">
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
