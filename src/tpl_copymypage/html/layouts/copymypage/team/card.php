<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$item   = \is_object($displayData['item'] ?? null) ? $displayData['item'] : null;

if ($item === null) {
    return;
}

$variant         = strtolower(trim((string) ($displayData['variant'] ?? 'card')));
$variant         = \in_array($variant, ['card', 'profile'], true) ? $variant : 'card';
$headingTag      = strtolower(trim((string) ($displayData['headingTag'] ?? 'h3')));
$headingTag      = \in_array($headingTag, ['h1', 'h2', 'h3', 'h4'], true) ? $headingTag : 'h3';
$headingId       = preg_replace('/[^A-Za-z0-9_:.-]/', '-', trim((string) ($displayData['headingId'] ?? ''))) ?? '';
$cardStyle       = strtolower(trim((string) ($displayData['cardStyle'] ?? 'default')));
$cardStyle       = \in_array($cardStyle, ['default', 'primary', 'secondary'], true) ? $cardStyle : 'default';
$showImage       = (bool) ($displayData['showImage'] ?? true);
$showDescription = (bool) ($displayData['showDescription'] ?? true);
$imageLoading    = strtolower(trim((string) ($displayData['imageLoading'] ?? 'lazy')));
$imageLoading    = \in_array($imageLoading, ['eager', 'lazy'], true) ? $imageLoading : 'lazy';
$fetchPriority   = strtolower(trim((string) ($displayData['fetchPriority'] ?? 'low')));
$fetchPriority   = \in_array($fetchPriority, ['auto', 'high', 'low'], true) ? $fetchPriority : 'low';
$details         = \is_array($displayData['details'] ?? null) ? $displayData['details'] : [];
$links           = \is_array($displayData['links'] ?? null) ? $displayData['links'] : [];
$name            = trim((string) ($item->name ?? ''));
$role            = trim((string) ($item->role ?? ''));
$description     = trim((string) ($item->description ?? ''));
$image           = trim((string) ($item->image ?? ''));
$imageAlt        = trim((string) ($item->imageAlt ?? $name));
$imageWidth      = (int) ($item->imageWidth ?? 0);
$imageHeight     = (int) ($item->imageHeight ?? 0);
$imageSrcset     = trim((string) ($item->imageSrcset ?? ''));
$webpSrcset      = trim((string) ($item->imageWebpSrcset ?? ''));
$avifSrcset      = trim((string) ($item->imageAvifSrcset ?? ''));
$imageSizes      = trim((string) ($displayData['imageSizes'] ?? ($item->imageSizes ?? '')));
$cardClass       = 'cmp-team__card cmp-team__card--' . $variant
    . ' uk-card uk-card-' . $cardStyle . ' uk-card-small';

if ($variant === 'card') {
    $cardClass .= ' uk-card-hover';
}

if ($name === '' && $role === '' && $description === '') {
    return;
}

if ($imageAlt === '') {
    $imageAlt = Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_IMAGE_ALT');
}
?>
<article
    class="<?php echo $escape($cardClass); ?>"
    <?php if ($headingId !== '' && $name !== '') : ?>
        aria-labelledby="<?php echo $escape($headingId); ?>"
    <?php endif; ?>
>
    <?php if ($showImage && $image !== '') : ?>
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
                    loading="<?php echo $escape($imageLoading); ?>"
                    decoding="async"
                    fetchpriority="<?php echo $escape($fetchPriority); ?>"
                >
            </picture>
        </div>
    <?php endif; ?>

    <div class="cmp-team__body uk-card-body">
        <?php if ($name !== '') : ?>
            <<?php echo $headingTag; ?>
                class="cmp-team__name uk-card-title"
                <?php if ($headingId !== '') : ?>id="<?php echo $escape($headingId); ?>"<?php endif; ?>
            >
                <?php echo $escape($name); ?>
            </<?php echo $headingTag; ?>>
        <?php endif; ?>

        <?php if ($role !== '') : ?>
            <p class="cmp-team__role">
                <?php echo $escape($role); ?>
            </p>
        <?php endif; ?>

        <?php if ($showDescription && $description !== '') : ?>
            <p class="cmp-team__description">
                <?php echo $escape($description); ?>
            </p>
        <?php endif; ?>

        <?php if ($details !== []) : ?>
            <dl class="cmp-team__details">
                <?php foreach ($details as $detail) : ?>
                    <?php
                    if (!\is_array($detail)) {
                        continue;
                    }

                    $label = trim((string) ($detail['label'] ?? ''));
                    $value = trim((string) ($detail['value'] ?? ''));

                    if ($label === '' || $value === '') {
                        continue;
                    }
                    ?>
                    <div class="cmp-team__detail">
                        <dt><?php echo $escape($label); ?></dt>
                        <dd><?php echo $escape($value); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>

        <?php if ($links !== []) : ?>
            <ul class="cmp-team__links">
                <?php foreach ($links as $link) : ?>
                    <?php
                    if (!\is_array($link)) {
                        continue;
                    }

                    $url      = trim((string) ($link['url'] ?? ''));
                    $label    = trim((string) ($link['label'] ?? ''));
                    $icon     = strtolower(trim((string) ($link['icon'] ?? 'link')));
                    $icon     = preg_match('/^[a-z0-9-]+$/', $icon) ? $icon : 'link';
                    $external = (bool) ($link['external'] ?? false);

                    if ($url === '' || $label === '') {
                        continue;
                    }
                    ?>
                    <li>
                        <a
                            class="cmp-team__link"
                            href="<?php echo $escape($url); ?>"
                            <?php if ($external) : ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
                        >
                            <span uk-icon="icon: <?php echo $escape($icon); ?>" aria-hidden="true"></span>
                            <span><?php echo $escape($label); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</article>
