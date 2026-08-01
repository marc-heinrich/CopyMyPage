<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_users
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       0.0.17
 * 
 * CopyMyPage document-drawer override for Joomla's MFA method form.
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;

/** @var \Joomla\Component\Users\Site\View\Method\HtmlView $this */

HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');

$wa = $this->getDocument()->getWebAssetManager();

// com_users renders this override before the outer CopyMyPage template can
// register its assets. Load the registry before requesting the icon style.
$wa->getRegistry()->addExtensionRegistryFile('com_copymypage');
$wa->useStyle('joomla.fontawesome')
   ->useScript('keepalive')
   ->useScript('form.validate');

$cancelUrl = Route::_('index.php?option=com_users&task=methods.display&user_id=' . $this->user->id);

if (!empty($this->returnURL)) {
    $decodedReturnUrl = base64_decode($this->returnURL, true);

    if ($decodedReturnUrl !== false && Uri::isInternal($decodedReturnUrl)) {
        $cancelUrl = $decodedReturnUrl;
    }
}

$recordId     = (int) ($this->record->id ?? 0);
$method       = (string) ($this->record->method ?? $this->getModel()->getState('method'));
$methodTitle  = trim((string) ($this->renderOptions['default_title'] ?? ''));
$userId       = (int) ($this->user->id ?? 0);
$inputType    = (string) $this->renderOptions['input_type'];
$webAuthnUnavailable = $method === 'webauthn'
    && str_contains((string) $this->renderOptions['submit_class'], 'multifactorauth_webauthn_setup')
    && Uri::getInstance()->getScheme() !== 'https';
$hideSubmit = (!$this->renderOptions['show_submit'] && !$this->isEditExisting)
    || $webAuthnUnavailable;
$formAction   = Route::_(
    'index.php?option=com_users&task=method.save&id='
    . $recordId
    . '&method=' . urlencode($method)
    . '&user_id=' . $userId
);

if ($methodTitle === '') {
    $methodTitle = Text::_('COM_USERS_MFA_EDIT_PAGE_HEAD');
}
?>
<div
    class="cmp-mfa-method"
    data-cmp-drawer-document-content
>
    <form
        action="<?php echo $formAction; ?>"
        method="post"
        id="com-users-method-edit"
        class="cmp-form cmp-profile-form cmp-mfa-method__form form-validate"
    >
        <?php echo HTMLHelper::_('form.token'); ?>

        <input
            type="hidden"
            name="title"
            value="<?php echo $this->escape($methodTitle); ?>"
        >

        <?php if (!empty($this->returnURL)) : ?>
            <input
                type="hidden"
                name="returnurl"
                value="<?php echo $this->escape($this->returnURL); ?>"
            >
        <?php endif; ?>

        <?php if (!empty($this->renderOptions['hidden_data'])) : ?>
            <?php foreach ($this->renderOptions['hidden_data'] as $key => $value) : ?>
                <input
                    type="hidden"
                    name="<?php echo $this->escape($key); ?>"
                    value="<?php echo $this->escape($value); ?>"
                >
            <?php endforeach; ?>
        <?php endif; ?>

        <section
            class="cmp-profile-form__section cmp-profile-form__panel cmp-mfa-method__panel"
            aria-labelledby="com-users-method-edit-head"
        >
            <header class="cmp-mfa-method__header">
                <h1
                    id="com-users-method-edit-head"
                    class="cmp-mfa-method__heading cmp-security-mfa__title"
                >
                    <?php echo $this->escape($methodTitle); ?>
                </h1>

                <?php if (!empty($this->renderOptions['help_url'])) : ?>
                    <a
                        href="<?php echo $this->escape($this->renderOptions['help_url']); ?>"
                        class="cmp-mfa-method__help uk-button uk-button-default"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <span class="icon icon-question-sign" aria-hidden="true"></span>
                        <span class="visually-hidden"><?php echo Text::_('JHELP'); ?></span>
                    </a>
                <?php endif; ?>
            </header>

            <div class="cmp-profile-form__fields cmp-mfa-method__fields">
                <div class="control-group">
                    <div class="controls">
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="com-users-is-default-method"
                                name="default"
                                value="1"
                                <?php echo $this->record->default ? 'checked' : ''; ?>
                            >
                            <label
                                class="form-check-label"
                                for="com-users-is-default-method"
                            >
                                <?php echo Text::_('COM_USERS_MFA_EDIT_FIELD_DEFAULT'); ?>
                            </label>
                        </div>
                    </div>
                </div>

                <?php if (!empty($this->renderOptions['pre_message'])) : ?>
                    <div class="com-users-method-edit-pre-message cmp-mfa-method__message">
                        <?php echo $this->renderOptions['pre_message']; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($this->renderOptions['tabular_data'])) : ?>
                    <div class="com-users-method-edit-tabular-container cmp-mfa-method__table-container">
                        <?php if (!empty($this->renderOptions['table_heading'])) : ?>
                            <h2 class="cmp-mfa-method__subheading">
                                <?php echo $this->renderOptions['table_heading']; ?>
                            </h2>
                        <?php endif; ?>
                        <div class="cmp-mfa-method__table-scroll">
                            <table class="cmp-mfa-method__table">
                                <tbody>
                                    <?php foreach ($this->renderOptions['tabular_data'] as $cell1 => $cell2) : ?>
                                        <?php
                                        // The otpauth deep link only works with a registered protocol handler.
                                        if (str_contains((string) $cell2, 'otpauth://')) {
                                            continue;
                                        }
                                        ?>
                                        <tr>
                                            <th scope="row"><?php echo $cell1; ?></th>
                                            <td><?php echo $cell2; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($this->renderOptions['field_type'] === 'custom') : ?>
                    <div class="cmp-mfa-method__custom">
                        <?php echo $this->renderOptions['html']; ?>
                    </div>
                <?php endif; ?>

                <div class="control-group cmp-mfa-method__field<?php echo $inputType === 'hidden' ? ' cmp-mfa-method__field--hidden' : ''; ?>">
                    <?php if ($this->renderOptions['label']) : ?>
                        <div class="control-label">
                            <label for="com-users-method-code">
                                <?php echo $this->renderOptions['label']; ?>
                            </label>
                        </div>
                    <?php endif; ?>
                    <div class="controls">
                        <?php
                        $attributes = array_merge(
                            [
                                'type'             => $inputType,
                                'name'             => 'code',
                                'value'            => $this->escape($this->renderOptions['input_value']),
                                'id'               => 'com-users-method-code',
                                'class'            => 'form-control',
                                'aria-describedby' => 'com-users-method-code-help',
                            ],
                            $this->renderOptions['input_attributes']
                        );

                        if (strpos((string) $attributes['class'], 'form-control') === false) {
                            $attributes['class'] .= ' form-control';
                        }
                        ?>
                        <input <?php echo ArrayHelper::toString($attributes); ?>>

                        <p class="form-text" id="com-users-method-code-help">
                            <?php echo $this->escape($this->renderOptions['placeholder']); ?>
                        </p>
                    </div>
                </div>

                <?php if (!empty($this->renderOptions['post_message'])) : ?>
                    <div class="com-users-method-edit-post-message cmp-mfa-method__message">
                        <?php echo $this->renderOptions['post_message']; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <div class="cmp-form__actions cmp-profile-form__actions cmp-mfa-method__actions">
            <button
                type="submit"
                class="uk-button uk-button-primary cmp-button cmp-button--primary <?php echo $this->escape($this->renderOptions['submit_class']); ?>"
                <?php echo $hideSubmit ? 'hidden' : ''; ?>
                <?php echo $webAuthnUnavailable ? 'disabled' : ''; ?>
            >
                <span
                    class="<?php echo $this->escape($this->renderOptions['submit_icon']); ?>"
                    aria-hidden="true"
                ></span>
                <?php echo Text::_($this->renderOptions['submit_text']); ?>
            </button>

            <a
                href="<?php echo $this->escape($cancelUrl); ?>"
                class="uk-button uk-button-default cmp-button cmp-button--secondary"
            >
                <span uk-icon="icon: close" aria-hidden="true"></span>
                <?php echo Text::_('JCANCEL'); ?>
            </a>
        </div>
    </form>
</div>
