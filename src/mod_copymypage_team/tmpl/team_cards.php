<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.15
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Module\CopyMyPage\Team\Site\Helper\TeamHelper;

/**
 * Extracted variables
 * -----------------
 * @var \Joomla\CMS\Application\CMSApplicationInterface $app
 * @var array<string, mixed>                            $cfg
 * @var string                                          $eyebrow
 * @var string                                          $headline
 * @var string                                          $lead
 * @var array<int, object>                              $items
 * @var string                                          $warning
 * @var string                                          $hint
 * @var \Joomla\Module\CopyMyPage\Team\Site\Helper\TeamHelper|null $teamHelper
 */

// Closure for escaping output.
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$cfg      = \is_array($cfg ?? null) ? $cfg : [];
$layout   = strtolower(trim((string) ($layout ?? '')));
$eyebrow  = trim((string) ($eyebrow ?? ''));
$headline = trim((string) ($headline ?? ''));
$lead     = trim((string) ($lead ?? ''));
$items    = \is_array($items ?? null) ? $items : [];
$warning  = (string) ($warning ?? '');
$hint     = (string) ($hint ?? '');

if (!isset($teamHelper) || !$teamHelper instanceof TeamHelper) {
    return;
}

if (isset($app) && $app instanceof \Joomla\CMS\Application\CMSApplicationInterface) {
    /** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
    $wa = $app->getDocument()->getWebAssetManager();

    // Activate template-specific assets here when the active layout needs them.
}

if ($warning !== '') {
    echo $warning;

    return;
}

$layoutConfig     = TeamHelper::getLayoutConfig($cfg, $layout);
$showImages       = TeamHelper::cfgBool($layoutConfig, 'showImages', true);
$showDescriptions = TeamHelper::cfgBool($layoutConfig, 'showDescriptions', true);
$cardStyle        = strtolower(trim(TeamHelper::cfgString($layoutConfig, 'cardStyle', 'default')));
$cardStyle        = \in_array($cardStyle, ['default', 'primary', 'secondary'], true) ? $cardStyle : 'default';
$moduleClass      = 'cmp-module cmp-module--team cmp-module--team-cards';
$cardClass        = 'cmp-team__card uk-card uk-card-' . $cardStyle . ' uk-card-small uk-card-hover';

if ($headline === '') {
    $headline = Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_HEADLINE');
}
?>
<!-- Team Module Template: UIkit Framework (https://getuikit.com/docs/card) -->
<div class="<?php echo $escape($moduleClass); ?>">
    <div class="uk-container">
        <?php if ($eyebrow !== '' || $headline !== '' || $lead !== '') : ?>
            <header class="cmp-team__header cmp-section-header">
                <?php if ($eyebrow !== '') : ?>
                    <p class="cmp-team__eyebrow cmp-section-header__eyebrow">
                        <?php echo $escape($eyebrow); ?>
                    </p>
                <?php endif; ?>
                <?php if ($headline !== '') : ?>
                    <h2 class="cmp-team__headline cmp-section-header__headline">
                        <?php echo $escape($headline); ?>
                    </h2>
                <?php endif; ?>
                <?php if ($lead !== '') : ?>
                    <p class="cmp-team__lead cmp-section-header__lead">
                        <?php echo $escape($lead); ?>
                    </p>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <?php if ($items !== []) : ?>
            <div
                class="cmp-team__grid uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-grid-column-small uk-grid-row-small uk-grid-match"
                uk-grid
                uk-scrollspy="target: > .cmp-team__item; cls: uk-animation-fade; delay: 120; repeat: false"
            >
                <?php foreach ($items as $item) : ?>
                    <?php
                    if (!\is_object($item)) {
                        continue;
                    }

                    $name        = trim((string) ($item->name ?? ''));
                    $role        = trim((string) ($item->role ?? ''));
                    $description = trim((string) ($item->description ?? ''));
                    $image       = trim((string) ($item->image ?? ''));
                    $imageAlt    = trim((string) ($item->imageAlt ?? $name));
                    $imageWidth  = (int) ($item->imageWidth ?? 0);
                    $imageHeight = (int) ($item->imageHeight ?? 0);
                    $imageSrcset = trim((string) ($item->imageSrcset ?? ''));
                    $webpSrcset  = trim((string) ($item->imageWebpSrcset ?? ''));
                    $avifSrcset  = trim((string) ($item->imageAvifSrcset ?? ''));
                    $imageSizes  = trim((string) ($item->imageSizes ?? ''));

                    if ($name === '' && $description === '') {
                        continue;
                    }

                    if ($imageAlt === '') {
                        $imageAlt = Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_IMAGE_ALT');
                    }
                    ?>
                    <div class="cmp-team__item">
                        <article class="<?php echo $escape($cardClass); ?>">
                            <?php if ($showImages && $image !== '') : ?>
                                <div class="cmp-team__media uk-card-media-top">
                                    <picture class="cmp-team__picture">
                                        <?php if ($avifSrcset !== '') : ?>
                                            <source
                                                type="image/avif"
                                                srcset="<?php echo $escape($avifSrcset); ?>"
                                                <?php if ($imageSizes !== '') : ?>
                                                    sizes="<?php echo $escape($imageSizes); ?>"
                                                <?php endif; ?>
                                            >
                                        <?php endif; ?>
                                        <?php if ($webpSrcset !== '') : ?>
                                            <source
                                                type="image/webp"
                                                srcset="<?php echo $escape($webpSrcset); ?>"
                                                <?php if ($imageSizes !== '') : ?>
                                                    sizes="<?php echo $escape($imageSizes); ?>"
                                                <?php endif; ?>
                                            >
                                        <?php endif; ?>
                                        <img
                                            src="<?php echo $escape($image); ?>"
                                            <?php if ($imageSrcset !== '') : ?>
                                                srcset="<?php echo $escape($imageSrcset); ?>"
                                                <?php if ($imageSizes !== '') : ?>
                                                    sizes="<?php echo $escape($imageSizes); ?>"
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            alt="<?php echo $escape($imageAlt); ?>"
                                            <?php if ($imageWidth > 0) : ?>
                                                width="<?php echo $imageWidth; ?>"
                                            <?php endif; ?>
                                            <?php if ($imageHeight > 0) : ?>
                                                height="<?php echo $imageHeight; ?>"
                                            <?php endif; ?>
                                            loading="lazy"
                                            decoding="async"
                                            fetchpriority="low"
                                        >
                                    </picture>
                                </div>
                            <?php endif; ?>

                            <div class="cmp-team__body uk-card-body">
                                <?php if ($name !== '') : ?>
                                    <h3 class="cmp-team__name uk-card-title">
                                        <?php echo $escape($name); ?>
                                    </h3>
                                <?php endif; ?>

                                <?php if ($role !== '') : ?>
                                    <p class="cmp-team__role">
                                        <?php echo $escape($role); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if ($showDescriptions && $description !== '') : ?>
                                    <p class="cmp-team__description">
                                        <?php echo $escape($description); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($hint !== '') : ?>
            <?php echo $hint; ?>
        <?php endif; ?>
    </div>
</div>
