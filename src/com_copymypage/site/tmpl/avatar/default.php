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
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\CopyMyPage\Site\View\Avatar\HtmlView $this */

HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');

$this->getDocument()->getWebAssetManager()
    ->useStyle('joomla.fontawesome')
    ->useScript('keepalive')
    ->useScript('form.validate');

$field = $this->form->getField('avatar');

if ($field === false) {
    throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_FORM'), 500);
}

// Render first because Joomla's Media layout registers its default com_media API.
$fieldMarkup = $field->renderField();
$fieldMarkup = str_replace(
    'class="btn btn-success button-select"',
    'class="btn btn-success button-select cmp-button cmp-button--primary-outline"',
    $fieldMarkup
);
$fieldMarkup = str_replace(
    'class="btn btn-danger button-clear"',
    'class="btn btn-danger button-clear cmp-button cmp-button--secondary cmp-button--icon"',
    $fieldMarkup
);

// Keep Joomla's Media field while replacing only its broad com_media metadata endpoint.
$this->getDocument()->addScriptOptions(
    'media-picker-api',
    ['apiBaseUrl' => $this->apiUrl],
    false
);
?>
<div class="cmp-avatar-editor">
    <header class="cmp-avatar-editor__header">
        <h1 class="cmp-dashboard__page-title">
            <?php echo Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_EDIT_TITLE'); ?>
        </h1>
        <p class="cmp-dashboard__page-lead">
            <?php echo Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_EDIT_LEAD'); ?>
        </p>
    </header>

    <form
        id="cmp-avatar-form"
        action="<?php echo Route::_('index.php'); ?>"
        method="post"
        class="cmp-form cmp-profile-form cmp-avatar-form form-validate"
    >
        <fieldset class="uk-fieldset cmp-profile-form__section cmp-profile-form__panel">
            <legend><?php echo Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_SECTION_TITLE'); ?></legend>
            <p class="cmp-profile-section__description">
                <?php echo Text::sprintf(
                    'COM_COPYMYPAGE_PROFILE_AVATAR_SECTION_DESCRIPTION',
                    $this->escape($this->maximumUploadSizeLabel)
                ); ?>
            </p>

            <div class="cmp-profile-form__fields">
                <?php echo $fieldMarkup; ?>
            </div>
        </fieldset>

        <div class="cmp-form__actions cmp-profile-form__actions">
            <button
                type="submit"
                class="uk-button uk-button-primary cmp-button cmp-button--primary validate"
                name="task"
                value="avatar.save"
            >
                <span uk-icon="icon: check" aria-hidden="true"></span>
                <?php echo Text::_('JSAVE'); ?>
            </button>
            <button
                type="submit"
                class="uk-button uk-button-default cmp-button cmp-button--secondary"
                name="task"
                value="avatar.cancel"
                formnovalidate
            >
                <span uk-icon="icon: close" aria-hidden="true"></span>
                <?php echo Text::_('JCANCEL'); ?>
            </button>
            <input type="hidden" name="option" value="com_copymypage">
            <input type="hidden" name="view" value="avatar">
        </div>

        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
