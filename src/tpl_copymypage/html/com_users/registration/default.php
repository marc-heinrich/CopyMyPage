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
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\Users\Site\View\Registration\HtmlView $this */

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$escape      = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$pageHeading = trim((string) $this->params->get('page_heading', ''));
$heading     = $this->params->get('show_page_heading') && $pageHeading !== ''
    ? $pageHeading
    : Text::_('COM_USERS_REGISTRATION');
?>
<div class="cmp-auth cmp-auth--registration com-users-registration registration">
    <header class="cmp-auth__header">
        <h1 id="cmp-auth-title" class="cmp-auth__title">
            <?php echo $escape($heading); ?>
        </h1>
    </header>

    <form
        id="member-registration"
        action="<?php echo Route::_('index.php?task=registration.register'); ?>"
        method="post"
        class="cmp-form cmp-auth__form com-users-registration__form form-validate"
        enctype="multipart/form-data"
        aria-labelledby="cmp-auth-title"
    >
        <?php foreach ($this->form->getFieldsets() as $fieldset) : ?>
            <?php if ($fieldset->name === 'captcha' && $this->captchaEnabled) : ?>
                <?php continue; ?>
            <?php endif; ?>

            <?php $fields = $this->form->getFieldset($fieldset->name); ?>
            <?php if (\count($fields)) : ?>
                <fieldset class="cmp-auth__fieldset">
                    <?php if (isset($fieldset->label) && $fieldset->name !== 'default') : ?>
                        <legend class="cmp-auth__legend"><?php echo Text::_($fieldset->label); ?></legend>
                    <?php endif; ?>
                    <?php echo $this->form->renderFieldset($fieldset->name); ?>
                </fieldset>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($this->captchaEnabled) : ?>
            <?php echo $this->form->renderFieldset('captcha'); ?>
        <?php endif; ?>

        <div class="cmp-form__actions cmp-auth__actions com-users-registration__submit control-group">
            <div class="controls">
                <button type="submit" class="uk-button uk-button-primary validate">
                    <?php echo Text::_('JREGISTER'); ?>
                </button>
                <input type="hidden" name="option" value="com_users">
                <input type="hidden" name="task" value="registration.register">
            </div>
        </div>

        <?php echo $this->form->renderControlFields(); ?>
    </form>
</div>
