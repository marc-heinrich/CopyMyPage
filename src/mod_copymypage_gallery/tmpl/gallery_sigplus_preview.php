<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.14
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Module\CopyMyPage\Gallery\Site\Helper\GalleryHelper;

/**
 * Extracted variables
 * -----------------
 * @var \Joomla\CMS\Application\CMSApplicationInterface $app
 * @var array<string, mixed>                            $cfg
 * @var array<int, object>                              $list
 * @var array<int, string>                              $filters
 * @var string                                          $warning
 * @var string                                          $hint
 * @var \Joomla\Module\CopyMyPage\Gallery\Site\Helper\GalleryHelper|null $galleryHelper
 */

// Closure for escaping output.
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

// Normalize the raw inputs received from the dispatcher.
$cfg     = \is_array($cfg ?? null) ? $cfg : [];
$layout  = strtolower(trim((string) ($layout ?? '')));
$list    = \is_array($list ?? null) ? $list : [];
$filters = \is_array($filters ?? null) ? $filters : [];
$warning = (string) ($warning ?? '');
$hint    = (string) ($hint ?? '');

if (!isset($galleryHelper) || !$galleryHelper instanceof GalleryHelper) {
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

// Extract the layout-specific parameter subset for the active template.
$layoutConfig = GalleryHelper::getLayoutConfig($cfg, $layout);

// Keep template defaults together so fallback behavior stays easy to scan.
$moduleClass     = 'cmp-module cmp-module--gallery cmp-module--gallery-sigplus-preview';
$defaultHeadline = Text::_('MOD_COPYMYPAGE_GALLERY_PREVIEW_TITLE');
$defaultLead     = Text::_('MOD_COPYMYPAGE_GALLERY_PREVIEW_DESC');

// Apply layout overrides on top of the template defaults.
$headline    = trim(GalleryHelper::cfgString($layoutConfig, 'headline', $defaultHeadline));
$lead        = trim(GalleryHelper::cfgString($layoutConfig, 'lead', $defaultLead));
$showFilters = GalleryHelper::cfgBool($layoutConfig, 'showFilters', true) && \count($filters) > 1;

// Collect the static UI labels used by the rendered markup.
$filterAllLabel = Text::_('MOD_COPYMYPAGE_GALLERY_FILTER_ALL');
$imagesLabel    = Text::_('MOD_COPYMYPAGE_GALLERY_IMAGES');
$galleryLabel   = Text::_('MOD_COPYMYPAGE_GALLERY_TO_GALLERY');

if ($headline === '') {
    $headline = $defaultHeadline;
}

if ($lead === '') {
    $lead = $defaultLead;
}
?>
<div class="<?php echo $escape($moduleClass); ?>">
    <?php if ($list !== []) : ?>
        <div class="uk-container">
            <div class="cmp-gallery-preview__header cmp-section-header">
                <h2 class="cmp-gallery-preview__headline cmp-section-header__headline">
                    <?php echo $escape($headline); ?>
                </h2>
                <p class="cmp-gallery-preview__lead cmp-section-header__lead">
                    <?php echo $escape($lead); ?>
                </p>
            </div>

            <div class="cmp-gallery-preview__browser" uk-filter="target: .cmp-gallery-preview__grid; duration: 350" animation="slide">
                <?php if ($showFilters) : ?>
                    <ul class="cmp-gallery-preview__filters uk-subnav uk-flex-center uk-margin-medium-bottom">
                        <li class="uk-active" uk-filter-control>
                            <a href="#">
                                <?php echo $escape($filterAllLabel); ?>
                            </a>
                        </li>
                        <?php foreach ($filters as $filter) : ?>
                            <?php
                            $filterLabel = trim((string) $filter);
                            $filterClass = trim((string) GalleryHelper::getFilterClass($filterLabel));

                            if ($filterLabel === '' || $filterClass === 'filter-') {
                                continue;
                            }
                            ?>
                            <li uk-filter-control=".<?php echo $escape($filterClass); ?>">
                                <a href="#">
                                    <?php echo $escape($filterLabel); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div
                    class="cmp-gallery-preview__grid uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-3@l"
                    uk-grid
                    uk-scrollspy="target: > .cmp-gallery-preview__item; cls: uk-animation-scale-up; delay: 160; repeat: false"
                >
                    <?php foreach ($list as $item) : ?>
                        <?php
                        if (!\is_object($item)) {
                            continue;
                        }

                        // Build normalized gallery data for the current item.
                        $title         = trim((string) ($item->title ?? ''));
                        $source        = trim((string) ($item->gallery_source ?? ''));
                        $filterLabel   = trim((string) ($item->filter_label ?? ''));
                        $filterClass   = trim((string) ($item->filter_class ?? ''));
                        $imageCount    = (int) ($item->image_count ?? 0);
                        $previewImage  = trim((string) ($item->gallery_start_image ?? $item->gallery_image ?? ''));
                        $previewSrc    = '';
                        $galleryLink   = '';
                        $cardTitle     = $title !== '' ? $title : ($filterLabel !== '' ? $filterLabel : $source);
                        $cardItemClass = trim('cmp-gallery-preview__item ' . $filterClass);

                        if ($previewImage !== '') {
                            if (preg_match('#^(?:https?:)?//#i', $previewImage) || str_starts_with($previewImage, 'data:')) {
                                $previewSrc = $previewImage;
                            } else {
                                $imageRelativePath = ltrim($previewImage, '/');
                                $imageAbsolutePath = JPATH_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $imageRelativePath);

                                if (is_file($imageAbsolutePath)) {
                                    $previewSrc = Uri::root() . $imageRelativePath;
                                }
                            }
                        }

                        if ($previewSrc === '') {
                            continue;
                        }

                        if ((int) ($item->id ?? 0) > 0) {
                            $galleryLink = Route::link(
                                'site',
                                'index.php?option=com_copymypage&view=gallery&id=' . (int) $item->id . '&imageCount=' . $imageCount
                            );
                        }
                        ?>
                        <div class="<?php echo $escape($cardItemClass); ?>">
                            <div
                                class="cmp-gallery-preview__card"
                                <?php if ($galleryLink !== '') : ?>
                                    tabindex="0"
                                <?php endif; ?>
                            >
                                <img
                                    class="cmp-gallery-preview__image"
                                    src="<?php echo $escape($previewSrc); ?>"
                                    alt="<?php echo $escape($cardTitle); ?>"
                                    loading="lazy"
                                    decoding="async"
                                >

                                <div class="cmp-gallery-preview__info">
                                    <h3 class="cmp-gallery-preview__title">
                                        <?php echo $escape($cardTitle); ?>
                                    </h3>
                                    <p class="cmp-gallery-preview__meta">
                                        <span class="cmp-gallery-preview__meta-icon" uk-icon="icon: album" aria-hidden="true"></span>
                                        <span>
                                            <?php echo $imageCount; ?>
                                            <?php echo $escape($imagesLabel); ?>
                                        </span>
                                    </p>

                                    <?php if ($galleryLink !== '') : ?>
                                        <a
                                            class="cmp-gallery-preview__action"
                                            href="<?php echo $escape($galleryLink); ?>"
                                            aria-label="<?php echo $escape($galleryLabel); ?>"
                                            title="<?php echo $escape($galleryLabel); ?>"
                                        >
                                            <span uk-icon="icon: search"></span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php elseif ($hint !== '') : ?>
        <?php echo $hint; ?>
    <?php endif; ?>
</div>
