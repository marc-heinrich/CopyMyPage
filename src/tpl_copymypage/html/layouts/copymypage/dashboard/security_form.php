<?php
/**
 * @package     Joomla.Site
 * @subpackage  Layouts.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$form = $displayData['form'] ?? null;

if (!$form instanceof Form) {
    return;
}

$escape      = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$formLayout  = strtolower(trim((string) ($displayData['layout'] ?? 'security.edit')));
$description = trim((string) ($displayData['description'] ?? ''));

if (!\in_array($formLayout, ['security', 'security.edit'], true)) {
    $formLayout = 'security.edit';
}

$sections = [];

foreach ($form->getFieldsets() as $group => $fieldset) {
    $fields = $form->getFieldset($group);

    if (\count($fields)) {
        $sections[] = [
            'fieldset' => $fieldset,
            'fields'   => $fields,
            'group'    => $group,
        ];
    }
}
?>
<form
    id="member-security"
    action="<?php echo Route::_('index.php'); ?>"
    method="post"
    class="cmp-form cmp-profile-form com-users-profile__edit-form form-validate"
>
    <?php foreach ($sections as $section) : ?>
        <?php
        $fieldset          = $section['fieldset'];
        $fields            = $section['fields'];
        $isPasswordSection = $section['group'] === 'core';
        $legend            = $isPasswordSection
            ? 'COM_COPYMYPAGE_SECURITY_PASSWORD_TITLE'
            : (string) ($fieldset->label ?? '');
        $sectionDescription = $isPasswordSection && $description !== ''
            ? $description
            : (
                isset($fieldset->description) && trim((string) $fieldset->description) !== ''
                    ? Text::_((string) $fieldset->description)
                    : ''
            );
        ?>
        <fieldset class="uk-fieldset cmp-profile-form__section cmp-profile-form__panel">
            <?php if ($legend !== '') : ?>
                <legend><?php echo Text::_($legend); ?></legend>
            <?php endif; ?>
            <?php if ($sectionDescription !== '') : ?>
                <p class="cmp-profile-section__description">
                    <?php echo $escape($sectionDescription); ?>
                </p>
            <?php endif; ?>
            <div class="cmp-profile-form__fields">
                <?php foreach ($fields as $field) : ?>
                    <?php echo $field->renderField(); ?>
                <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endforeach; ?>

    <div class="cmp-form__actions cmp-profile-form__actions com-users-profile__edit-submit">
        <button type="submit" class="uk-button uk-button-primary cmp-button cmp-button--primary validate" name="task" value="security.save">
            <span uk-icon="icon: check" aria-hidden="true"></span>
            <?php echo Text::_('JSAVE'); ?>
        </button>
        <button type="submit" class="uk-button uk-button-default cmp-button cmp-button--secondary" name="task" value="security.cancel" formnovalidate>
            <span uk-icon="icon: close" aria-hidden="true"></span>
            <?php echo Text::_('JCANCEL'); ?>
        </button>
        <input type="hidden" name="option" value="com_copymypage">
        <input type="hidden" name="view" value="dashboard">
        <input type="hidden" name="layout" value="<?php echo $escape($formLayout); ?>">
    </div>

    <?php echo $form->renderControlFields(); ?>
</form>
