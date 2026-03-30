<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.9
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;

// Read layout-specific configuration keys.
$navOffcanvasId    = (string) ($cfg['navOffcanvasId'] ?? '');
$userOffcanvasId   = (string) ($cfg['userOffcanvasId'] ?? '');
$basketOffcanvasId = (string) ($cfg['basketOffcanvasId'] ?? '');

// Mmenu-light expects a "selected" class on the <li>.
$selectedClass = (string) ($cfg['mmenuLightSelectedClass'] ?? 'current');

// Cannot render the mmenu root without an offcanvas id.
if ($navOffcanvasId === '') {
    return;
}

// Register mmenu-light sizing CSS variables.
$itemHeight  = CopyMyPageHelper::cfgInt($cfg, 'mmenuLightItemHeight', 50, 0) . 'px';
$ocdWidth    = CopyMyPageHelper::cfgInt($cfg, 'mmenuLightOcdWidth', 80, 0) . '%';
$ocdMinWidth = CopyMyPageHelper::cfgInt($cfg, 'mmenuLightOcdMinWidth', 200, 0) . 'px';
$ocdMaxWidth = CopyMyPageHelper::cfgInt($cfg, 'mmenuLightOcdMaxWidth', 440, 0) . 'px';

/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $app->getDocument()->getWebAssetManager();
$wa->addInlineStyle(
    ":root {\n"
    . "    /* mmenu-light tokens */\n"
    . "    --mm-spn-item-height: {$itemHeight};\n"
    . "    --mm-ocd-width: {$ocdWidth};\n"
    . "    --mm-ocd-min-width: {$ocdMinWidth};\n"
    . "    --mm-ocd-max-width: {$ocdMaxWidth};\n"
    . "}",
    ['name' => 'mod-copymypage-navbar-mmenu-light-widths-' . (int) ($module->id ?? 0)]
);

$activeId = (int) ($active_id ?? 0);

// Prefer active item tree, fallback to $path.
$trailIds = [];
$activeSlot = strtolower(trim((string) ($activeSlot ?? '')));

if (isset($active) && isset($active->tree) && \is_array($active->tree)) {
    $trailIds = array_map('intval', $active->tree);
} elseif (isset($path) && \is_array($path)) {
    $trailIds = array_map('intval', $path);
}

$matchesActiveSlot = static function (object $item) use ($activeSlot): bool {
    if ($activeSlot === '' || (string) ($item->type ?? '') !== 'url') {
        return false;
    }

    $candidates = [
        (string) ($item->link ?? ''),
        (string) ($item->flink ?? ''),
    ];

    foreach ($candidates as $candidate) {
        if (CopyMyPageHelper::extractHashToken($candidate) === $activeSlot) {
            return true;
        }
    }

    return false;
};

$hasForcedActiveSlot = $activeSlot !== ''
    && !empty(array_filter(
        $list,
        static function ($menuItem) use ($matchesActiveSlot): bool {
            return \is_object($menuItem) && $matchesActiveSlot($menuItem);
        }
    ));

// Build links aligned with desktop navbar behavior.
$buildUrl = static function ($item) use ($isOnepage): string {
    $url = (string) ($item->flink ?? '');

    // Keep hash links unchanged on onepage view.
    if (
        $isOnepage
        && ($item->type ?? '') === 'url'
        && !empty($item->link)
        && \is_string($item->link)
        && str_starts_with($item->link, '#')
    ) {
        return (string) $item->link;
    }

    // On non-onepage views, route top-level hash links to onepage + anchor.
    if (
        !$isOnepage
        && (int) ($item->level ?? 0) === 1
        && ($item->type ?? '') === 'url'
        && !empty($item->link)
        && \is_string($item->link)
        && str_starts_with($item->link, '#')
    ) {
        return Route::link('site', 'index.php?option=com_copymypage&view=onepage') . (string) $item->link;
    }

    return $url;
};
?>

<!-- Navbar Module Template: Mmenu Light JS-Plugin (https://mmenujs.com/mmenu-light) -->

<!-- Navigation offcanvas menu (mobile only) -->
<nav id="<?php echo htmlspecialchars($navOffcanvasId, ENT_QUOTES, 'UTF-8'); ?>" class="uk-hidden@m">
    <?php if (!empty($list) && \is_array($list)) : ?>
        <ul>
            <?php foreach ($list as $item) : ?>
                <?php
                $level = (int) ($item->level ?? 1);
                $id    = (int) ($item->id ?? 0);

                $slotActive = $matchesActiveSlot($item);
                $isTrail    = !$hasForcedActiveSlot && \in_array($id, $trailIds, true);
                $isActive   = $hasForcedActiveSlot ? $slotActive : ($id === $activeId);

                // Mmenu-light expects the selected class on <li>.
                $liClasses = [];

                if ($isActive || $isTrail) {
                    $liClasses[] = $selectedClass;
                }

                // Skip separators (mmenu-light has no native divider rendering).
                if (($item->type ?? '') === 'separator') {
                    // Still need to respect depth flags if your data contains them on separators (rare).
                    continue;
                }

                $url = $buildUrl($item);
                $title = (string) ($item->title ?? '');

                // Headings: prefer <span> like the mmenu-light demo, so they open submenus without navigation.
                $isHeading = (($item->type ?? '') === 'heading');

                $liAttr = $liClasses ? ' class="' . htmlspecialchars(implode(' ', $liClasses), ENT_QUOTES, 'UTF-8') . '"' : '';
                echo '<li' . $liAttr . '>';

                if ($isHeading) {
                    echo '<span>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span>';
                } else {
                    echo '<a href="' . htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8') . '">'
                        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
                        . '</a>';
                }

                // Depth handling (core flags semantics).
                if (!empty($item->deeper)) {
                    echo '<ul>';
                } elseif (!empty($item->shallower)) {
                    echo '</li>';

                    for ($j = 0; $j < (int) ($item->level_diff ?? 0); $j++) {
                        echo '</ul></li>';
                    }
                } else {
                    echo '</li>';
                }
                ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</nav>

<!-- User offcanvas menu (mobile only) -->
<?php if ($userOffcanvasId !== '') : ?>
    <nav id="<?php echo htmlspecialchars($userOffcanvasId, ENT_QUOTES, 'UTF-8'); ?>" class="uk-hidden@m">
        <ul>
            <?php if (!empty($userItems) && \is_array($userItems)) : ?>
                <?php foreach ($userItems as $item) : ?>
                    <?php
                    $title = (string) ($item->title ?? '');
                    $href  = (string) ($item->link ?? '#');
                    ?>
                    <li>
                        <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php else : ?>
                <li>
                    <a href="<?php echo htmlspecialchars(Route::link('site', 'index.php?option=com_users&view=login'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars(Text::_('JLOGIN'), ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>

<!-- Basket offcanvas menu (mobile only) -->
<?php if ($basketOffcanvasId !== '') : ?>
    <nav id="<?php echo htmlspecialchars($basketOffcanvasId, ENT_QUOTES, 'UTF-8'); ?>" class="uk-hidden@m">
        <ul>
            <li>
                <a href="#"><?php echo htmlspecialchars(Text::_('MOD_COPYMYPAGE_NAVBAR_BASKET_EMPTY'), ENT_QUOTES, 'UTF-8'); ?></a>
            </li>
        </ul>
    </nav>
<?php endif; ?>
