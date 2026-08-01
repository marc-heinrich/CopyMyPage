<?php
/**
 * @package     Joomla.Site
 * @subpackage  Layouts.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$items  = \is_array($displayData['items'] ?? null) ? $displayData['items'] : [];

$renderItems = static function (array $nodes, int $depth = 0) use (&$renderItems, $escape): string {
    ob_start();

    $listClass = $depth === 0
        ? 'cmp-dashboard-nav__list'
        : 'cmp-dashboard-nav__sublist';
    ?>
    <ul class="<?php echo $listClass; ?>">
        <?php foreach ($nodes as $item) : ?>
            <?php
            if (!\is_array($item)) {
                continue;
            }

            $type = \in_array(($item['type'] ?? ''), ['link', 'heading', 'separator'], true)
                ? (string) $item['type']
                : 'link';

            if ($type === 'separator') {
                echo '<li class="cmp-dashboard-nav__separator" role="separator"></li>';

                continue;
            }

            $label       = trim((string) ($item['label'] ?? ''));
            $key         = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($item['key'] ?? ''))) ?? '';
            $icon        = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($item['icon'] ?? ''))) ?? '';
            $isCurrent   = (bool) ($item['current'] ?? false);
            $activeTrail = (bool) ($item['activeTrail'] ?? false);
            $children    = \is_array($item['children'] ?? null) ? $item['children'] : [];
            $ariaCurrent = \in_array(($item['ariaCurrent'] ?? ''), ['page', 'location'], true)
                ? (string) $item['ariaCurrent']
                : '';
            $classes = [
                'cmp-dashboard-nav__item',
                'cmp-dashboard-nav__item--' . $type,
            ];

            if ($key !== '') {
                $classes[] = 'cmp-dashboard-nav__item--' . $key;
            }

            if ($isCurrent) {
                $classes[] = 'is-current';
            }

            if ($activeTrail) {
                $classes[] = 'is-active-trail';
            }

            if ($children !== []) {
                $classes[] = 'has-children';
            }
            ?>
            <li class="<?php echo $escape(implode(' ', $classes)); ?>">
                <?php if ($type === 'heading') : ?>
                    <span class="cmp-dashboard-nav__heading">
                        <?php if ($icon !== '') : ?>
                            <span class="cmp-dashboard-nav__icon" aria-hidden="true">
                                <span uk-icon="icon: <?php echo $escape($icon); ?>"></span>
                            </span>
                        <?php endif; ?>
                        <span class="cmp-dashboard-nav__label"><?php echo $escape($label); ?></span>
                    </span>
                <?php else : ?>
                    <a
                        class="cmp-dashboard-nav__link"
                        href="<?php echo $escape($item['url'] ?? '#'); ?>"
                        <?php echo $ariaCurrent !== '' ? 'aria-current="' . $ariaCurrent . '"' : ''; ?>
                    >
                        <?php if ($icon !== '') : ?>
                            <span class="cmp-dashboard-nav__icon" aria-hidden="true">
                                <span uk-icon="icon: <?php echo $escape($icon); ?>"></span>
                            </span>
                        <?php endif; ?>
                        <span class="cmp-dashboard-nav__label"><?php echo $escape($label); ?></span>
                    </a>
                <?php endif; ?>

                <?php
                if ($children !== []) {
                    echo $renderItems($children, $depth + 1);
                }
                ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php

    return (string) ob_get_clean();
};
?>
<nav class="cmp-dashboard-nav" aria-label="<?php echo $escape(Text::_('COM_COPYMYPAGE_DASHBOARD_NAV_LABEL')); ?>">
    <?php echo $renderItems($items); ?>
</nav>
