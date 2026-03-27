<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.9
 */

\defined('_JEXEC') or die;

$messages = $displayData['messages'] ?? [];

if (!is_array($messages) || $messages === []) {
    return;
}
?>
<!-- Alert Template: UIkit Framework @see https://getuikit.com/docs/alert -->
<div class="uk-container cmp-warning-notice">
    <?php foreach ($messages as $message) : ?>
        <div class="uk-alert-warning cmp-alert cmp-alert--warning" uk-alert>
            <a class="uk-alert-close" uk-close></a>

            <?php if (!empty($message['info'])) : ?>
                <h3 class="uk-margin-small-bottom cmp-alert__title">
                    <?php echo $message['info']; ?>
                </h3>
            <?php endif; ?>

            <?php if (!empty($message['desc'])) : ?>
                <p class="uk-margin-remove-bottom cmp-alert__body">
                    <?php echo $message['desc']; ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
