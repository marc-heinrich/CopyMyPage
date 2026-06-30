<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.16
 */

\defined('_JEXEC') or die;

use Joomla\Module\CopyMyPage\Counts\Site\Helper\CountsHelper;

/**
 * Extracted variables
 * -----------------
 * @var \Joomla\CMS\Application\CMSApplicationInterface $app
 * @var array<int, object>|null                         $items
 * @var string                                          $warning
 * @var string                                          $hint
 * @var \Joomla\Module\CopyMyPage\Counts\Site\Helper\CountsHelper|null $countsHelper
 */

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$items   = \is_array($items ?? null) ? $items : [];
$warning = (string) ($warning ?? '');
$hint    = (string) ($hint ?? '');

if (!isset($countsHelper) || !$countsHelper instanceof CountsHelper) {
    return;
}

if (isset($app) && $app instanceof \Joomla\CMS\Application\CMSApplicationInterface) {
    /** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
    $wa = $app->getDocument()->getWebAssetManager();
    $wa->useScript('purecounter');
}

if ($warning !== '') {
    echo $warning;

    return;
}

$moduleClass = 'cmp-module cmp-module--counts cmp-module--counts-default';
?>
<!-- Counts Module Template: UIkit Framework (https://getuikit.com/docs/card) and PureCounter (https://github.com/srexi/purecounterjs) -->
<div class="<?php echo $escape($moduleClass); ?>">
    <div class="uk-container">
        <?php if ($items !== []) : ?>
            <div
                class="cmp-counts__grid uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-grid-column-small uk-grid-row-small uk-grid-match"
                uk-grid
                uk-scrollspy="target: > .cmp-counts__item; cls: uk-animation-fade; delay: 120; repeat: false"
            >
                <?php foreach ($items as $item) : ?>
                    <?php
                    if (!\is_object($item)) {
                        continue;
                    }

                    $key      = preg_replace('/[^a-z0-9_-]/i', '', (string) ($item->key ?? '')) ?: 'counter';
                    $label    = trim((string) ($item->label ?? ''));
                    $value    = max(0, (int) ($item->value ?? 0));
                    $start    = max(0, (int) ($item->start ?? 0));
                    $duration = max(0, min(30, (int) ($item->duration ?? 1)));
                    $icon     = trim((string) ($item->icon ?? 'star'));

                    if ($label === '') {
                        continue;
                    }
                    ?>
                    <div class="cmp-counts__item cmp-counts__item--<?php echo $escape($key); ?>">
                        <article
                            class="cmp-counts__card uk-card uk-card-default uk-card-body uk-card-hover uk-flex uk-flex-column uk-flex-middle uk-text-center"
                            aria-label="<?php echo $escape($label . ': ' . $value); ?>"
                        >
                            <span class="cmp-counts__icon" uk-icon="icon: <?php echo $escape($icon); ?>; ratio: 1.35" aria-hidden="true"></span>
                            <span
                                class="cmp-counts__value purecounter"
                                data-purecounter-start="<?php echo $start; ?>"
                                data-purecounter-end="<?php echo $value; ?>"
                                data-purecounter-duration="<?php echo $duration; ?>"
                            ><?php echo $value; ?></span>
                            <span class="cmp-counts__label">
                                <?php echo $escape($label); ?>
                            </span>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($hint !== '') : ?>
            <?php echo $hint; ?>
        <?php endif; ?>
    </div>
</div>
