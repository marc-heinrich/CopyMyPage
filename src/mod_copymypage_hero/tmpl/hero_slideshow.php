<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\Module\CopyMyPage\Hero\Site\Helper\HeroHelper;

/**
 * Extracted variables
 * -----------------
 * @var \Joomla\CMS\Application\CMSApplicationInterface $app
 * @var array<string, mixed>                            $cfg
 * @var array<int, object>                              $slides
 * @var \Joomla\Registry\Registry                       $params
 * @var object                                          $module
 * @var string                                          $slideshowOptions
 * @var string                                          $warning
 * @var string                                          $hint
 * @var \Joomla\Module\CopyMyPage\Hero\Site\Helper\HeroHelper|null $heroHelper
 */

// Closure for escaping output.
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

// Normalize dispatcher input so the template works with predictable value types.
$cfg        = \is_array($cfg ?? null) ? $cfg : [];
$layout     = strtolower(trim((string) ($layout ?? '')));
$slides     = \is_array($slides ?? null) ? $slides : [];
$warning    = (string) ($warning ?? '');
$hint       = (string) ($hint ?? '');

if (!isset($heroHelper) || !$heroHelper instanceof HeroHelper) {
    return;
}

if ($warning !== '') {
    echo $warning;

    return;
}

// Prioritize the display font only when this layout renders a hero headline.
$hasHeadline = false;

foreach ($slides as $slide) {
    if (trim((string) ($slide->headline ?? '')) !== '') {
        $hasHeadline = true;

        break;
    }
}

if ($hasHeadline && isset($app) && $app instanceof \Joomla\CMS\Application\CMSApplicationInterface) {
    $fontUri = rtrim(Uri::root(true), '/')
        . '/media/com_copymypage/css/fonts-local/Finger_Paint/FingerPaint-Regular.woff2';

    $app->getDocument()->getPreloadManager()->preload(
        $fontUri,
        [
            'as'          => 'font',
            'type'        => 'font/woff2',
            'crossorigin' => 'anonymous',
        ]
    );
}

// Resolve the layout-specific option bucket for the active hero template.
$layoutConfig = HeroHelper::getLayoutConfig($cfg, $layout);

// Define the static wrapper classes used by this layout.
$moduleClass = 'cmp-module cmp-module--hero cmp-module--hero-slideshow';

// Toggle optional slideshow controls based on config and available slides.
$hasMultipleSlides = \count($slides) > 1;
$showSlidenav      = HeroHelper::cfgBool($layoutConfig, 'showSlidenav', true) && $hasMultipleSlides;
$showDotnav        = HeroHelper::cfgBool($layoutConfig, 'showDotnav', true) && $hasMultipleSlides;
?>
<!-- Hero Module Template: UIkit Framework (https://getuikit.com/docs/slideshow) -->
<div class="<?php echo $escape($moduleClass); ?>">
    <?php if ($slides !== []) : ?>
        <div class="uk-position-relative uk-visible-toggle uk-light" tabindex="-1"
            uk-slideshow="<?php echo $escape($slideshowOptions); ?>">
            <ul class="uk-slideshow-items">
                <?php foreach ($slides as $slide) : ?>
                    <?php
                    $displaySrc   = $escape($slide->displaySrc ?? '');
                    $srcset       = $escape($slide->srcset ?? '');
                    $webpSrcset   = $escape($slide->webpSrcset ?? '');
                    $avifSrcset   = $escape($slide->avifSrcset ?? '');
                    $sizes        = $escape($slide->sizes ?? '100vw');
                    $alt          = $escape($slide->alt ?? '');
                    $isLazy       = !empty($slide->isLazy) && $slide->isLazy === true;
                    $width        = (int) ($slide->width ?? 0);
                    $height       = (int) ($slide->height ?? 0);
                    $fetchPriority = $escape($slide->fetchPriority ?? ($isLazy ? 'low' : 'high'));
                    $headline     = trim((string) ($slide->headline ?? ''));
                    $subline      = trim((string) ($slide->subline ?? ''));
                    ?>
                    <li>
                        <picture>
                            <?php if ($avifSrcset !== '') : ?>
                                <source
                                    type="image/avif"
                                    srcset="<?php echo $avifSrcset; ?>"
                                    sizes="<?php echo $sizes; ?>"
                                >
                            <?php endif; ?>
                            <?php if ($webpSrcset !== '') : ?>
                                <source
                                    type="image/webp"
                                    srcset="<?php echo $webpSrcset; ?>"
                                    sizes="<?php echo $sizes; ?>"
                                >
                            <?php endif; ?>
                            <img
                                src="<?php echo $displaySrc; ?>"
                                <?php if ($srcset !== '') : ?>
                                    srcset="<?php echo $srcset; ?>"
                                    sizes="<?php echo $sizes; ?>"
                                <?php endif; ?>
                                loading="<?php echo $isLazy ? 'lazy' : 'eager'; ?>"
                                decoding="async"
                                fetchpriority="<?php echo $fetchPriority; ?>"
                                <?php if ($width > 0) : ?>
                                    width="<?php echo $width; ?>"
                                <?php endif; ?>
                                <?php if ($height > 0) : ?>
                                    height="<?php echo $height; ?>"
                                <?php endif; ?>
                                alt="<?php echo $alt; ?>"
                                uk-cover
                            >
                        </picture>
                        <?php if ($headline !== '' || $subline !== '') : ?>
                            <div class="uk-position-center uk-text-center cmp-hero-overlay">
                                <?php if ($headline !== '') : ?>
                                    <div class="uk-heading-medium cmp-hero-overlay__headline">
                                        <?php echo $headline; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($subline !== '') : ?>
                                    <div class="uk-text-lead cmp-hero-overlay__subline">
                                        <?php echo $subline; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($showSlidenav) : ?>
                <a class="uk-position-center-left uk-position-small" href="#"
                    uk-slidenav-previous uk-slideshow-item="previous"></a>
                <a class="uk-position-center-right uk-position-small" href="#"
                    uk-slidenav-next uk-slideshow-item="next"></a>
            <?php endif; ?>

            <?php if ($showDotnav) : ?>
                <ul class="uk-slideshow-nav uk-dotnav uk-flex-center"></ul>
            <?php endif; ?>
        </div>
    <?php elseif ($hint !== '') : ?>
        <?php echo $hint; ?>
    <?php endif; ?>
</div>
