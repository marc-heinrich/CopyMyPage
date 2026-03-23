<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.9
 */

\defined('_JEXEC') or die;

$warnings = $displayData['warnings'] ?? [];

if (!is_array($warnings) || $warnings === []) {
    return;
}
?>
<!-- Alert Template: Desktop UIkit Framework (https://getuikit.com/docs/alert) -->
<div class="uk-container cmp-warning-notice">
    <?php foreach ($warnings as $warning) : ?>
        <div class="uk-alert-warning cmp-alert cmp-alert--warning" uk-alert>
            <a class="uk-alert-close" uk-close></a>

            <?php if (!empty($warning['info'])) : ?>
                <h3 class="uk-margin-small-bottom cmp-alert__title">
                    <?php echo $warning['info']; ?>
                </h3>
            <?php endif; ?>

            <?php if (!empty($warning['desc'])) : ?>
                <p class="uk-margin-remove-bottom cmp-alert__body">
                    <?php echo $warning['desc']; ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
