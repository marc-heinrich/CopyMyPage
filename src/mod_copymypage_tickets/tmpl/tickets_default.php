<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\Module\CopyMyPage\Tickets\Site\Helper\TicketsHelper;

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$validAttribute = static function (mixed $value): string {
    $value = strtolower(trim((string) $value));

    return preg_match('/^data-[a-z0-9-]+$/', $value) ? $value : '';
};
$validClass = static function (mixed $value): string {
    $value = trim((string) $value);

    return preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $value) ? $value : '';
};

$eyebrow          = trim((string) ($eyebrow ?? ''));
$headline         = trim((string) ($headline ?? ''));
$lead             = trim((string) ($lead ?? ''));
$items            = \is_array($items ?? null) ? $items : [];
$warning          = (string) ($warning ?? '');
$hint             = (string) ($hint ?? '');
$markupAttributes = \is_array($markupAttributes ?? null) ? $markupAttributes : [];
$markupClasses    = \is_array($markupClasses ?? null) ? $markupClasses : [];

if (!isset($ticketsHelper) || !$ticketsHelper instanceof TicketsHelper) {
    return;
}

if ($warning !== '') {
    echo $warning;

    return;
}

if ($headline === '') {
    $headline = Text::_('MOD_COPYMYPAGE_TICKETS_DEFAULT_HEADLINE');
}

$rootAttribute            = $validAttribute($markupAttributes['root'] ?? '');
$nextAttribute            = $validAttribute($markupAttributes['next'] ?? '');
$previousAttribute        = $validAttribute($markupAttributes['previous'] ?? '');
$paginationAttribute      = $validAttribute($markupAttributes['pagination'] ?? '');
$paginationLabelAttribute = $validAttribute($markupAttributes['paginationLabel'] ?? '');
$eventIdAttribute         = $validAttribute($markupAttributes['eventId'] ?? '');
$statusAttribute          = $validAttribute($markupAttributes['status'] ?? '');
$actionAttribute          = $validAttribute($markupAttributes['action'] ?? '');
$actionLabelAttribute     = $validAttribute($markupAttributes['actionLabel'] ?? '');
$actionUrlAttribute       = $validAttribute($markupAttributes['actionUrl'] ?? '');
$progressAttribute        = $validAttribute($markupAttributes['progress'] ?? '');
$progressLabelAttribute   = $validAttribute($markupAttributes['progressLabel'] ?? '');
$swiperClass              = $validClass($markupClasses['root'] ?? '');
$slideClass               = $validClass($markupClasses['slide'] ?? '');
$swiperSlideClass         = $validClass($markupClasses['swiperSlide'] ?? '');
$swiperWrapperClass       = $validClass($markupClasses['swiperWrapper'] ?? '');

if (
    $items !== []
    && $rootAttribute !== ''
    && $swiperClass !== ''
    && $slideClass !== ''
    && $swiperSlideClass !== ''
    && $swiperWrapperClass !== ''
    && isset($app)
    && $app instanceof CMSApplicationInterface
) {
    $wa = $app->getDocument()->getWebAssetManager();
    $wa->getRegistry()->addExtensionRegistryFile('com_copymypage');
    $wa->usePreset('copymypage.tickets');
}

$moduleId = max(0, (int) ($module->id ?? 0));
$headingId = 'cmp-tickets-heading-' . $moduleId;
?>
<div class="cmp-module cmp-module--tickets cmp-module--tickets-default">
    <div class="uk-container">
        <?php if ($eyebrow !== '' || $headline !== '' || $lead !== '') : ?>
            <header class="cmp-tickets__header cmp-section-header">
                <?php if ($eyebrow !== '') : ?>
                    <p class="cmp-tickets__eyebrow cmp-section-header__eyebrow">
                        <?php echo $escape($eyebrow); ?>
                    </p>
                <?php endif; ?>
                <?php if ($headline !== '') : ?>
                    <h2 id="<?php echo $escape($headingId); ?>" class="cmp-tickets__headline cmp-section-header__headline">
                        <?php echo $escape($headline); ?>
                    </h2>
                <?php endif; ?>
                <?php if ($lead !== '') : ?>
                    <p class="cmp-tickets__lead cmp-section-header__lead">
                        <?php echo $escape($lead); ?>
                    </p>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <?php if (
            $items !== []
            && $rootAttribute !== ''
            && $swiperClass !== ''
            && $slideClass !== ''
            && $swiperSlideClass !== ''
            && $swiperWrapperClass !== ''
        ) : ?>
            <div
                class="cmp-tickets__swiper <?php echo $escape($swiperClass); ?>"
                <?php echo $rootAttribute; ?>="<?php echo $moduleId; ?>"
                aria-labelledby="<?php echo $escape($headingId); ?>"
            >
                <div class="cmp-tickets__track <?php echo $escape($swiperWrapperClass); ?>">
                    <?php foreach ($items as $index => $item) : ?>
                        <?php
                        if (!\is_object($item)) {
                            continue;
                        }

                        $title          = trim((string) ($item->title ?? ''));
                        $bookingUrl     = trim((string) ($item->bookingUrl ?? ''));
                        $bookable       = (bool) ($item->bookable ?? false);
                        $eventId        = max(0, (int) ($item->eventId ?? 0));
                        $status = preg_replace(
                            '/[^a-z-]/',
                            '',
                            strtolower((string) ($item->status ?? 'unavailable'))
                        ) ?? 'unavailable';
                        $statusLabel        = trim((string) ($item->statusLabel ?? ''));
                        $lastPurchaseLabel  = trim((string) ($item->lastPurchaseLabel ?? ''));
                        $progressLabel      = trim((string) ($item->progressLabel ?? ''));
                        $image          = trim((string) ($item->image ?? ''));
                        $imageAlt       = trim((string) ($item->imageAlt ?? $title));
                        $imageSrcset    = trim((string) ($item->imageSrcset ?? ''));
                        $webpSrcset     = trim((string) ($item->imageWebpSrcset ?? ''));
                        $avifSrcset     = trim((string) ($item->imageAvifSrcset ?? ''));
                        $imageSizes     = trim((string) ($item->imageSizes ?? ''));
                        $imageWidth     = max(0, (int) ($item->imageWidth ?? 0));
                        $imageHeight    = max(0, (int) ($item->imageHeight ?? 0));
                        $progress       = $item->progress === null ? null : min(100, max(0, (int) $item->progress));
                        $cardHeadingId  = 'cmp-ticket-' . $moduleId . '-' . $escape($item->id ?? $index);
                        $dateAriaLabel   = trim((string) ($item->dateLabel ?? ''))
                            . ', ' . trim((string) ($item->timeLabel ?? ''));
                        $paginationLabel = trim((string) ($item->paginationLabel ?? ''));
                        ?>
                        <div
                            class="<?php echo $escape($slideClass . ' ' . $swiperSlideClass); ?>"
                            <?php if ($paginationLabelAttribute !== '' && $paginationLabel !== '') : ?>
                                <?php echo $paginationLabelAttribute; ?>="<?php echo $escape($paginationLabel); ?>"
                            <?php endif; ?>
                        >
                            <article
                                class="cmp-ticket-card cmp-ticket-card--<?php echo $escape($status); ?> uk-card uk-card-default"
                                aria-labelledby="<?php echo $escape($cardHeadingId); ?>"
                                <?php if ($eventIdAttribute !== '' && $eventId > 0) : ?>
                                    <?php echo $eventIdAttribute; ?>="<?php echo $eventId; ?>"
                                <?php endif; ?>
                            >
                                <header class="cmp-ticket-card__date uk-card-header">
                                    <time
                                        class="cmp-ticket-card__date-time"
                                        datetime="<?php echo $escape($item->dateTime ?? ''); ?>"
                                        aria-label="<?php echo $escape($dateAriaLabel); ?>"
                                    >
                                        <span class="cmp-ticket-card__weekday">
                                            <?php echo $escape($item->dateWeekday ?? ''); ?>
                                        </span>
                                        <span class="cmp-ticket-card__calendar-date" aria-hidden="true">
                                            <strong class="cmp-ticket-card__day"><?php echo $escape($item->dateDay ?? ''); ?></strong>
                                            <span class="cmp-ticket-card__month-year">
                                                <span><?php echo $escape($item->dateMonth ?? ''); ?></span>
                                                <span><?php echo $escape($item->dateYear ?? ''); ?></span>
                                            </span>
                                        </span>
                                        <span class="cmp-ticket-card__time">
                                            <span uk-icon="icon: clock; ratio: 0.82" aria-hidden="true"></span>
                                            <?php echo $escape($item->timeLabel ?? ''); ?>
                                        </span>
                                    </time>
                                </header>

                                <div class="cmp-ticket-card__visual">
                                    <?php if ($image !== '') : ?>
                                        <div class="cmp-ticket-card__media uk-card-media-top">
                                            <picture class="cmp-ticket-card__picture">
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
                                                    <?php if ($imageWidth > 0) : ?>width="<?php echo $imageWidth; ?>"<?php endif; ?>
                                                    <?php if ($imageHeight > 0) : ?>height="<?php echo $imageHeight; ?>"<?php endif; ?>
                                                    loading="lazy"
                                                    decoding="async"
                                                    fetchpriority="low"
                                                >
                                            </picture>
                                        </div>
                                    <?php endif; ?>

                                    <div class="cmp-ticket-card__body uk-card-body">
                                        <h3 id="<?php echo $escape($cardHeadingId); ?>" class="cmp-ticket-card__title uk-card-title">
                                            <?php echo $escape($title); ?>
                                        </h3>

                                        <div class="cmp-ticket-card__action-wrap">
                                            <?php if ($bookingUrl !== '') : ?>
                                                <a
                                                    class="cmp-ticket-card__action uk-button uk-button-primary uk-width-1-1
                                                        cmp-button cmp-button--primary<?php echo $bookable ? '' : ' disabled'; ?>"
                                                    <?php if ($bookable) : ?>
                                                        href="<?php echo $escape($bookingUrl); ?>"
                                                    <?php else : ?>
                                                        aria-disabled="true"
                                                        tabindex="-1"
                                                    <?php endif; ?>
                                                    aria-label="<?php echo $escape($item->actionAriaLabel ?? $item->actionLabel ?? ''); ?>"
                                                    <?php if ($actionAttribute !== '') : ?>
                                                        <?php echo $actionAttribute; ?>
                                                    <?php endif; ?>
                                                    <?php if ($actionLabelAttribute !== '') : ?>
                                                        <?php echo $actionLabelAttribute; ?>="<?php echo $escape($item->actionLabel ?? ''); ?>"
                                                    <?php endif; ?>
                                                    <?php if ($actionUrlAttribute !== '') : ?>
                                                        <?php echo $actionUrlAttribute; ?>="<?php echo $escape($bookingUrl); ?>"
                                                    <?php endif; ?>
                                                >
                                                    <?php echo $escape(
                                                        $bookable
                                                            ? ($item->actionLabel ?? '')
                                                            : $statusLabel
                                                    ); ?>
                                                </a>
                                            <?php else : ?>
                                                <span
                                                    class="cmp-ticket-card__action uk-button uk-button-default uk-width-1-1
                                                        cmp-button cmp-button--secondary disabled"
                                                    aria-disabled="true"
                                                >
                                                    <?php echo $escape($statusLabel); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <footer class="cmp-ticket-card__footer uk-card-footer">
                                    <div class="cmp-ticket-card__availability">
                                        <span class="cmp-ticket-card__status-dot" aria-hidden="true"></span>
                                        <span>
                                            <strong
                                                <?php if ($statusAttribute !== '') : ?>
                                                    <?php echo $statusAttribute; ?>
                                                <?php endif; ?>
                                            ><?php echo $escape($statusLabel); ?></strong>
                                            <?php if ($lastPurchaseLabel !== '') : ?>
                                                <small><?php echo $escape($lastPurchaseLabel); ?></small>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if ($progress !== null) : ?>
                                        <label
                                            class="uk-hidden-visually"
                                            for="cmp-ticket-progress-<?php echo $moduleId . '-' . $index; ?>"
                                            <?php if ($progressLabelAttribute !== '') : ?>
                                                <?php echo $progressLabelAttribute; ?>
                                            <?php endif; ?>
                                        >
                                            <?php echo $escape($progressLabel); ?>
                                        </label>
                                        <progress
                                            id="cmp-ticket-progress-<?php echo $moduleId . '-' . $index; ?>"
                                            class="cmp-ticket-card__progress"
                                            max="100"
                                            value="<?php echo $progress; ?>"
                                            <?php if ($progressAttribute !== '') : ?>
                                                <?php echo $progressAttribute; ?>
                                            <?php endif; ?>
                                        ><?php echo $progress; ?>%</progress>
                                    <?php endif; ?>
                                </footer>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($previousAttribute !== '' && $nextAttribute !== '') : ?>
                    <button
                        class="cmp-tickets__nav cmp-tickets__nav--previous"
                        type="button"
                        <?php echo $previousAttribute; ?>
                        aria-label="<?php echo $escape(Text::_('MOD_COPYMYPAGE_TICKETS_SWIPER_PREVIOUS')); ?>"
                    >
                        <span uk-icon="icon: chevron-left; ratio: 1.15" aria-hidden="true"></span>
                    </button>
                    <button
                        class="cmp-tickets__nav cmp-tickets__nav--next"
                        type="button"
                        <?php echo $nextAttribute; ?>
                        aria-label="<?php echo $escape(Text::_('MOD_COPYMYPAGE_TICKETS_SWIPER_NEXT')); ?>"
                    >
                        <span uk-icon="icon: chevron-right; ratio: 1.15" aria-hidden="true"></span>
                    </button>
                <?php endif; ?>

                <?php if ($paginationAttribute !== '') : ?>
                    <div class="cmp-tickets__pagination" <?php echo $paginationAttribute; ?>></div>
                <?php endif; ?>
            </div>
        <?php elseif ($hint !== '') : ?>
            <?php echo $hint; ?>
        <?php endif; ?>
    </div>
</div>
