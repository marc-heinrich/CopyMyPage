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
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Joomla\Component\CopyMyPage\Site\View\Dashboard\HtmlView $this */

HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');

$this->getDocument()->getWebAssetManager()
    ->useStyle('joomla.fontawesome')
    ->useScript('keepalive')
    ->useScript('form.validate')
    ->useScript('copymypage.avatar.profile');

$escape  = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$heading = $this->params->get('show_page_heading')
    && trim((string) $this->params->get('page_heading', '')) !== ''
    ? (string) $this->params->get('page_heading')
    : Text::_('COM_COPYMYPAGE_PROFILE_EDIT_TITLE');
$avatarPickerTitle = Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_PICKER_TITLE');
$profileSections   = [];

foreach ($this->form->getFieldsets() as $group => $fieldset) {
    $fields = $this->form->getFieldset($group);

    if (\count($fields)) {
        $profileSections[] = [
            'fieldset' => $fieldset,
            'fields'   => $fields,
        ];
    }
}

$avatarField = $this->profileAvatarForm->getField('avatar');

if ($avatarField === false) {
    throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_FORM'), 500);
}

// Render first because Joomla's Media layout registers its default com_media API.
$avatarFieldMarkup = $avatarField->renderField();
$avatarFieldMarkup = str_replace(
    'class="btn btn-success button-select"',
    'class="btn btn-success button-select cmp-button cmp-button--primary-outline"',
    $avatarFieldMarkup
);
$avatarFieldMarkup = str_replace(
    'class="btn btn-danger button-clear"',
    'class="btn btn-danger button-clear cmp-button cmp-button--secondary cmp-button--icon"',
    $avatarFieldMarkup
);

// Keep Joomla's Media field while replacing only its broad com_media metadata endpoint.
$this->getDocument()->addScriptOptions(
    'media-picker-api',
    ['apiBaseUrl' => $this->profileAvatarApiUrl],
    false
);
$this->getDocument()->addScriptOptions(
    'csrf.token',
    Session::getFormToken(),
    false
);
?>
<div class="cmp-dashboard cmp-dashboard--profile-edit com-users-profile__edit profile-edit">
    <header class="cmp-dashboard__page-header">
        <div>
            <h1 class="cmp-dashboard__page-title"><?php echo $escape($heading); ?></h1>
            <p class="cmp-dashboard__page-lead">
                <?php echo Text::_('COM_COPYMYPAGE_PROFILE_EDIT_LEAD'); ?>
            </p>
        </div>
    </header>

    <?php
    echo LayoutHelper::render(
        'copymypage.dashboard.navigation',
        $this->accountMenu
    );
    ?>

    <div class="cmp-dashboard__content">
        <form
            id="member-profile"
            action="<?php echo Route::_('index.php'); ?>"
            method="post"
            class="cmp-form cmp-profile-form cmp-avatar-form com-users-profile__edit-form form-validate"
            enctype="multipart/form-data"
        >
            <?php foreach ($profileSections as $sectionIndex => $section) : ?>
                <?php
                $fieldset      = $section['fieldset'];
                $fields        = $section['fields'];
                $fieldsetLabel = (string) ($fieldset->label ?? '');

                if ((string) ($fieldset->name ?? '') === 'core') {
                    $fieldsetLabel = 'COM_COPYMYPAGE_PROFILE_BASICS_SECTION_TITLE';
                }
                ?>
                <fieldset class="uk-fieldset cmp-profile-form__section cmp-profile-form__panel">
                    <?php if ($fieldsetLabel !== '') : ?>
                        <legend><?php echo Text::_($fieldsetLabel); ?></legend>
                    <?php endif; ?>
                    <?php if (isset($fieldset->description) && trim($fieldset->description)) : ?>
                        <p class="cmp-profile-section__description">
                            <?php echo $this->escape(Text::_($fieldset->description)); ?>
                        </p>
                    <?php endif; ?>
                    <div class="cmp-profile-form__fields">
                        <?php foreach ($fields as $field) : ?>
                            <?php echo $field->renderField(); ?>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <?php if ($sectionIndex === 0) : ?>
                    <fieldset
                        id="cmp-profile-avatar"
                        class="uk-fieldset cmp-profile-form__section cmp-profile-form__panel cmp-profile-avatar-panel"
                        data-cmp-avatar-profile-field
                        data-cmp-avatar-picker-title="<?php echo $escape($avatarPickerTitle); ?>"
                    >
                        <legend>
                            <?php echo Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_SECTION_TITLE'); ?>
                        </legend>
                        <p class="cmp-profile-section__description">
                            <?php echo Text::sprintf(
                                'COM_COPYMYPAGE_PROFILE_AVATAR_SECTION_DESCRIPTION',
                                $escape($this->profileAvatarMaximumUploadSizeLabel)
                            ); ?>
                        </p>

                        <div class="cmp-profile-form__fields">
                            <?php echo $avatarFieldMarkup; ?>
                        </div>
                    </fieldset>
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="cmp-form__actions cmp-profile-form__actions com-users-profile__edit-submit">
                <button type="submit" class="uk-button uk-button-primary cmp-button cmp-button--primary validate" name="task" value="profile.save">
                    <span uk-icon="icon: check" aria-hidden="true"></span>
                    <?php echo Text::_('JSAVE'); ?>
                </button>
                <button type="submit" class="uk-button uk-button-default cmp-button cmp-button--secondary" name="task" value="profile.cancel" formnovalidate>
                    <span uk-icon="icon: close" aria-hidden="true"></span>
                    <?php echo Text::_('JCANCEL'); ?>
                </button>
                <input type="hidden" name="option" value="com_copymypage">
                <input type="hidden" name="view" value="dashboard">
                <input type="hidden" name="layout" value="profile.edit">
            </div>

            <?php echo $this->form->renderControlFields(); ?>
        </form>
    </div>
</div>
