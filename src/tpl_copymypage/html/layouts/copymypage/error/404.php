<?php

/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @license     GNU General Public License version 3 or later
 */

\defined('_JEXEC') or die;

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<article class="cmp-error-page__panel" aria-labelledby="cmp-error-title">
    <div class="cmp-error-page__icon" aria-hidden="true">
        <span uk-icon="icon: <?php echo $escape($displayData['icon']); ?>"></span>
    </div>
    <?php if ($displayData['showStatus']) : ?>
        <p class="cmp-error-page__code"><?php echo $escape($displayData['status']); ?></p>
    <?php endif; ?>
    <h1 class="cmp-error-page__title" id="cmp-error-title"><?php echo $escape($displayData['heading']); ?></h1>
    <p class="cmp-error-page__description"><?php echo $escape($displayData['description']); ?></p>
    <?php if (trim((string) ($displayData['detail'] ?? '')) !== '') : ?>
        <p class="cmp-error-page__detail"><?php echo $escape($displayData['detail']); ?></p>
    <?php endif; ?>
    <div class="cmp-error-page__actions">
        <a class="cmp-error-page__home" href="<?php echo $escape($displayData['homeUrl']); ?>">
            <span uk-icon="icon: home" aria-hidden="true"></span>
            <span><?php echo $escape($displayData['homeLabel']); ?></span>
        </a>
    </div>
</article>
