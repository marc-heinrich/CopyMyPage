<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.8
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\Module\CopyMyPage\Hero\Site\Helper\HeroHelper;

/** @var array<int, object> $slides */
/** @var array<string, mixed> $cfg */
/** @var \Joomla\Registry\Registry $params */
/** @var object $module */
/** @var string $slideshowOptions */
/** @var string $warning */
/** @var string $hint */

// Normalize dispatcher input so the template works with predictable value types.
$cfg           = \is_array($cfg ?? null) ? $cfg : [];
$layout        = strtolower(trim((string) ($layout ?? '')));
$slides        = \is_array($slides ?? null) ? $slides : [];
$warning       = (string) ($warning ?? '');
$hint          = (string) ($hint ?? '');

// Resolve the layout-specific option bucket for the active hero template.
$layoutConfig = HeroHelper::getLayoutConfig($cfg, $layout);

// Define static template defaults and environment-dependent path values.
$moduleClass   = 'cmp-module cmp-module--hero cmp-module--hero-slideshow';
$variantWidths = [960, 1920];
$siteBaseUrl   = rtrim(Uri::root(), '/');
$siteRootPath  = rtrim(Uri::root(true), '/');

// Toggle optional slideshow controls based on config and available slides.
$hasMultipleSlides = \count($slides) > 1;
$showSlidenav      = HeroHelper::cfgBool($layoutConfig, 'showSlidenav', true) && $hasMultipleSlides;
$showDotnav        = HeroHelper::cfgBool($layoutConfig, 'showDotnav', true) && $hasMultipleSlides;

// Keep path and URL transformations in small local helpers to simplify the slide loop below.
$normalizePublicPath = static function (string $url) use ($siteRootPath): string {
    $path = parse_url($url, PHP_URL_PATH);

    if (!\is_string($path) || $path === '') {
        return '';
    }

    if ($siteRootPath !== '' && str_starts_with($path, $siteRootPath . '/')) {
        $path = substr($path, strlen($siteRootPath));
    }

    return '/' . ltrim($path, '/');
};

$buildPublicUrl = static function (string $publicPath) use ($siteBaseUrl): string {
    return $siteBaseUrl . '/' . ltrim($publicPath, '/');
};

$buildVariantPublicPath = static function (string $publicPath, string $suffix, string $extension): string {
    $variantPath = preg_replace('/\.[^.]+$/', $suffix . '.' . $extension, $publicPath);

    return \is_string($variantPath) ? $variantPath : $publicPath;
};

$buildAbsolutePath = static function (string $publicPath): string {
    return \JPATH_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($publicPath, '/'));
};
?>
<!-- Hero Module Template: Desktop UIkit Framework (https://getuikit.com/docs/slideshow) -->
<div class="<?php echo $moduleClass; ?>">
    <?php if ($warning !== '') : ?>
        <?php echo $warning; ?>
    <?php elseif ($slides !== []) : ?>
        <div class="uk-position-relative uk-visible-toggle uk-light" tabindex="-1"
            uk-slideshow="<?php echo htmlspecialchars($slideshowOptions, ENT_QUOTES, 'UTF-8'); ?>">
            <ul class="uk-slideshow-items">
                <?php foreach ($slides as $slide) : ?>
                    <?php
                    $rawSrc = (string) ($slide->src ?? '');
                    $alt = htmlspecialchars((string) ($slide->alt ?? ''), ENT_QUOTES, 'UTF-8');
                    $isLazy = !empty($slide->isLazy) && $slide->isLazy === true;
                    $width = (int) ($slide->width ?? 0);
                    $height = (int) ($slide->height ?? 0);
                    $fetchPriority = htmlspecialchars((string) ($slide->fetchPriority ?? ($isLazy ? 'low' : 'high')), ENT_QUOTES, 'UTF-8');
                    $publicSrc = $normalizePublicPath($rawSrc);
                    $displaySrc = $publicSrc !== '' ? $buildPublicUrl($publicSrc) : $rawSrc;
                    $defaultDisplaySrc = $displaySrc;
                    $sizes = '100vw';
                    $extension = strtolower((string) pathinfo($publicSrc !== '' ? $publicSrc : $rawSrc, PATHINFO_EXTENSION));
                    $hasIntrinsicJpgVariant = false;

                    $jpgSrcsetEntries = [];
                    $webpSrcsetEntries = [];
                    $avifSrcsetEntries = [];

                    if ($publicSrc !== '' && $extension !== '') {
                        foreach ($variantWidths as $variantWidth) {
                            $jpgVariantPath = $buildVariantPublicPath($publicSrc, '-' . $variantWidth, $extension);
                            $webpVariantPath = $buildVariantPublicPath($publicSrc, '-' . $variantWidth, 'webp');
                            $avifVariantPath = $buildVariantPublicPath($publicSrc, '-' . $variantWidth, 'avif');

                            if (is_file($buildAbsolutePath($jpgVariantPath))) {
                                $jpgSrcsetEntries[] = htmlspecialchars($buildPublicUrl($jpgVariantPath), ENT_QUOTES, 'UTF-8') . ' ' . $variantWidth . 'w';

                                if ($width > 0 && $variantWidth === $width) {
                                    $defaultDisplaySrc = $buildPublicUrl($jpgVariantPath);
                                    $hasIntrinsicJpgVariant = true;
                                }
                            }

                            if (is_file($buildAbsolutePath($webpVariantPath))) {
                                $webpSrcsetEntries[] = htmlspecialchars($buildPublicUrl($webpVariantPath), ENT_QUOTES, 'UTF-8') . ' ' . $variantWidth . 'w';
                            }

                            if (is_file($buildAbsolutePath($avifVariantPath))) {
                                $avifSrcsetEntries[] = htmlspecialchars($buildPublicUrl($avifVariantPath), ENT_QUOTES, 'UTF-8') . ' ' . $variantWidth . 'w';
                            }
                        }
                    }

                    if ($publicSrc !== '' && $width > 0 && !$hasIntrinsicJpgVariant) {
                        $jpgSrcsetEntries[] = htmlspecialchars($buildPublicUrl($publicSrc), ENT_QUOTES, 'UTF-8') . ' ' . $width . 'w';
                    }

                    $jpgSrcset = implode(', ', array_unique($jpgSrcsetEntries));
                    $webpSrcset = implode(', ', array_unique($webpSrcsetEntries));
                    $avifSrcset = implode(', ', array_unique($avifSrcsetEntries));
                    $escapedDisplaySrc = htmlspecialchars($defaultDisplaySrc, ENT_QUOTES, 'UTF-8');
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
                                src="<?php echo $escapedDisplaySrc; ?>"
                                <?php if ($jpgSrcset !== '') : ?>
                                    srcset="<?php echo $jpgSrcset; ?>"
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
                        <?php if (!empty($slide->headline) || !empty($slide->subline)) : ?>
                            <div class="uk-position-center uk-text-center cmp-hero-overlay">
                                <?php if (!empty($slide->headline)) : ?>
                                    <h2 class="uk-heading-medium">
                                        <?php echo htmlspecialchars((string) $slide->headline, ENT_QUOTES, 'UTF-8'); ?>
                                    </h2>
                                <?php endif; ?>

                                <?php if (!empty($slide->subline)) : ?>
                                    <p class="uk-text-lead">
                                        <?php echo htmlspecialchars((string) $slide->subline, ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
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
