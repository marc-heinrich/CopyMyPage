<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\Module\CopyMyPage\Footer\Site\Helper\FooterHelper;

/**
 * Extracted variables
 * -------------------
 * @var array<int, object> $items
 * @var string             $warning
 * @var FooterHelper|null  $footerHelper
 */

$escape  = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$items   = \is_array($items ?? null) ? $items : [];
$warning = (string) ($warning ?? '');

if (!isset($footerHelper) || !$footerHelper instanceof FooterHelper) {
    return;
}

if ($warning !== '') {
    echo $warning;

    return;
}
?>
<!-- CopyMyPage Footer Module: responsive project and license information. -->
<div class="cmp-module cmp-module--footer cmp-module--footer-default">
    <div class="uk-container">
        <?php foreach ($items as $item) : ?>
            <?php if (\is_object($item) && trim((string) ($item->text ?? '')) !== '') : ?>
                <p class="cmp-footer__item cmp-footer__item--<?php echo $escape($item->key ?? 'info'); ?>">
                    <?php echo $escape($item->text); ?>
                </p>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
