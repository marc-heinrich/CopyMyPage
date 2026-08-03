<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Joomla\Component\CopyMyPage\Site\View\Avatar\HtmlView $this */

$this->getDocument()->getWebAssetManager()
    ->useScript('keepalive');

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="cmp-avatar-picker">
    <header class="cmp-avatar-picker__header">
        <h1 class="cmp-dashboard__page-title">
            <?php echo Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_PICKER_TITLE'); ?>
        </h1>
        <p class="cmp-dashboard__page-lead">
            <?php echo Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_PICKER_LEAD'); ?>
        </p>
    </header>

    <form
        action="<?php echo $escape($this->uploadUrl); ?>"
        method="post"
        enctype="multipart/form-data"
        class="cmp-form cmp-avatar-upload-form"
    >
        <fieldset class="uk-fieldset cmp-profile-form__section cmp-profile-form__panel">
            <legend><?php echo Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_UPLOAD_TITLE'); ?></legend>
            <p class="cmp-profile-section__description">
                <?php echo Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_UPLOAD_DESCRIPTION'); ?>
            </p>

            <div class="control-group">
                <div class="control-label">
                    <label for="cmp-avatar-file">
                        <?php echo Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_UPLOAD_LABEL'); ?>
                    </label>
                </div>
                <div class="controls">
                    <input
                        id="cmp-avatar-file"
                        class="form-control"
                        type="file"
                        name="avatar_file"
                        accept="image/jpeg,image/png,image/webp,image/avif"
                        required
                    >
                </div>
            </div>

            <div class="cmp-form__actions cmp-avatar-upload-form__actions">
                <button
                    type="submit"
                    class="uk-button uk-button-primary cmp-button cmp-button--primary"
                >
                    <span uk-icon="icon: upload" aria-hidden="true"></span>
                    <?php echo Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_UPLOAD_BUTTON'); ?>
                </button>
            </div>

            <input type="hidden" name="option" value="com_copymypage">
            <input type="hidden" name="task" value="avatar.upload">
            <?php echo HTMLHelper::_('form.token'); ?>
        </fieldset>
    </form>

    <section class="cmp-avatar-picker__library" aria-labelledby="cmp-avatar-picker-library-title">
        <h2 id="cmp-avatar-picker-library-title">
            <?php echo Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_PICKER_LIBRARY_TITLE'); ?>
        </h2>

        <?php if ($this->items === []) : ?>
            <p class="cmp-avatar-picker__empty">
                <?php echo Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_PICKER_EMPTY'); ?>
            </p>
        <?php else : ?>
            <div class="cmp-avatar-picker__items">
                <?php foreach ($this->items as $item) : ?>
                    <button
                        type="button"
                        class="cmp-avatar-picker__item<?php echo !empty($item['pending']) ? ' cmp-avatar-picker__item--pending' : ''; ?>"
                        data-cmp-avatar-media
                        data-cmp-avatar-extension="<?php echo $escape($item['extension'] ?? ''); ?>"
                        data-cmp-avatar-height="<?php echo (int) ($item['height'] ?? 0); ?>"
                        data-cmp-avatar-mime="<?php echo $escape($item['mime'] ?? ''); ?>"
                        data-cmp-avatar-name="<?php echo $escape($item['name'] ?? ''); ?>"
                        data-cmp-avatar-path="<?php echo $escape($item['path'] ?? ''); ?>"
                        data-cmp-avatar-width="<?php echo (int) ($item['width'] ?? 0); ?>"
                        aria-pressed="false"
                    >
                        <img
                            src="<?php echo $escape($item['url'] ?? ''); ?>"
                            alt=""
                            loading="lazy"
                            width="<?php echo (int) ($item['width'] ?? 0); ?>"
                            height="<?php echo (int) ($item['height'] ?? 0); ?>"
                        >
                        <span class="cmp-avatar-picker__item-label">
                            <?php echo Text::_(!empty($item['pending'])
                                ? 'COM_COPYMYPAGE_PROFILE_AVATAR_PICKER_NEW'
                                : 'COM_COPYMYPAGE_PROFILE_AVATAR_PICKER_CURRENT'); ?>
                        </span>
                        <span class="cmp-avatar-picker__item-check" uk-icon="icon: check" aria-hidden="true"></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <p class="cmp-avatar-picker__selection-help">
                <?php echo Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_PICKER_SELECTION_HELP'); ?>
            </p>
        <?php endif; ?>
    </section>
</div>
