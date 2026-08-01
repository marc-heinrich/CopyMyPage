<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;

/** @var \Joomla\Component\Users\Site\View\Captive\HtmlView $this */

$app    = Factory::getApplication();
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$model  = $this->getModel();

$this->getDocument()->getWebAssetManager()
     ->useScript('com_users.two-factor-focus');

$method       = (string) ($this->record->method ?? '');
$methodTitle  = !$this->allowEntryBatching
    ? trim((string) ($this->record->title ?? ''))
    : trim((string) $model->translateMethodName($method));
$inputType    = (string) $this->renderOptions['input_type'];
$inputLabel   = trim(strip_tags((string) $this->renderOptions['label']));
$placeholder  = $inputLabel !== ''
    ? $inputLabel
    : trim(strip_tags((string) ($this->renderOptions['placeholder'] ?? '')));
$logoutUrl    = Route::_(
    'index.php?option=com_users&task=user.logout&' . $app->getFormToken() . '=1',
    false
);
$selectUrl    = Route::_('index.php?option=com_users&view=captive&task=select', false);
$validateUrl  = Route::_(
    'index.php?task=captive.validate&record_id=' . (int) $this->record->id,
    false
);
$methodIcons  = [
    'backupcodes' => 'unlock',
    'email'       => 'mail',
    'webauthn'    => 'lock',
    'yubikey'     => 'lock',
];
$methodIcon   = $methodIcons[$method] ?? 'lock';
$selectable   = [];

foreach ($this->records as $record) {
    $recordMethod = (string) ($record->method ?? '');

    if (!isset($this->mfaMethods[$recordMethod]) && $recordMethod !== 'backupcodes') {
        continue;
    }

    $recordAllowsBatching = (bool) ($this->mfaMethods[$recordMethod]['allowEntryBatching'] ?? false);
    $selectableKey        = $recordAllowsBatching
        ? 'method:' . $recordMethod
        : 'record:' . (int) $record->id;

    $selectable[$selectableKey] = true;
}

$hasAlternativeMethod  = \count($selectable) > 1;
$webAuthnUnavailable   = $method === 'webauthn'
    && Uri::getInstance()->getScheme() !== 'https';
$hideSubmit            = (bool) $this->renderOptions['hide_submit']
    || $webAuthnUnavailable;

if ($methodTitle === '') {
    $methodTitle = Text::_('COM_USERS_USER_MULTIFACTOR_AUTH');
}

?>
<section
    class="cmp-auth cmp-captive users-mfa-captive"
    aria-labelledby="users-mfa-title"
>
    <header class="cmp-captive__toolbar">
        <?php if ($hasAlternativeMethod) : ?>
            <a
                href="<?php echo $escape($selectUrl); ?>"
                id="users-mfa-captive-form-choose-another"
                class="cmp-captive__toolbar-action"
                aria-label="<?php echo $escape(Text::_('COM_USERS_MFA_USE_DIFFERENT_METHOD')); ?>"
                title="<?php echo $escape(Text::_('COM_USERS_MFA_USE_DIFFERENT_METHOD')); ?>"
            >
                <span
                    uk-icon="icon: <?php echo $app->getLanguage()->isRtl() ? 'arrow-right' : 'arrow-left'; ?>"
                    aria-hidden="true"
                ></span>
            </a>
        <?php else : ?>
            <span class="cmp-captive__toolbar-spacer" aria-hidden="true"></span>
        <?php endif; ?>

        <a
            href="<?php echo $escape($logoutUrl); ?>"
            class="cmp-captive__toolbar-action"
            aria-label="<?php echo $escape(Text::_('JCANCEL')); ?>"
            title="<?php echo $escape(Text::_('JCANCEL')); ?>"
        >
            <span uk-icon="icon: close" aria-hidden="true"></span>
        </a>
    </header>

    <div class="cmp-captive__body">
        <header class="cmp-auth__header cmp-captive__intro">
            <div class="cmp-captive__title-row">
                <h1 id="users-mfa-title" class="cmp-auth__title cmp-captive__title">
                    <?php echo $escape($methodTitle); ?>
                </h1>

                <?php if (!empty($this->renderOptions['help_url'])) : ?>
                    <a
                        href="<?php echo $escape($this->renderOptions['help_url']); ?>"
                        class="cmp-captive__help"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="<?php echo $escape(Text::_('JHELP')); ?>"
                        title="<?php echo $escape(Text::_('JHELP')); ?>"
                    >
                        <span uk-icon="icon: question" aria-hidden="true"></span>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($this->renderOptions['pre_message']) : ?>
                <div class="cmp-auth__lead cmp-captive__message users-mfa-captive-pre-message">
                    <?php echo $this->renderOptions['pre_message']; ?>
                </div>
            <?php endif; ?>
        </header>

        <form
            action="<?php echo $escape($validateUrl); ?>"
            method="post"
            id="users-mfa-captive-form"
            class="cmp-form cmp-auth__form cmp-captive__form"
            aria-labelledby="users-mfa-title"
        >
            <?php echo HTMLHelper::_('form.token'); ?>

            <div id="users-mfa-captive-form-method-fields" class="cmp-captive__fields">
                <?php if ($this->renderOptions['field_type'] === 'custom') : ?>
                    <div class="cmp-captive__custom">
                        <?php echo $this->renderOptions['html']; ?>
                    </div>
                <?php endif; ?>

                <div class="cmp-captive__field<?php echo $inputType === 'hidden' ? ' cmp-captive__field--hidden' : ''; ?>">
                    <?php if ($inputLabel !== '') : ?>
                        <label for="users-mfa-code" class="visually-hidden">
                            <?php echo $escape($inputLabel); ?>
                        </label>
                    <?php endif; ?>

                    <div class="cmp-captive__input-wrap">
                        <?php if ($inputType !== 'hidden') : ?>
                            <span
                                class="cmp-captive__input-icon"
                                uk-icon="icon: <?php echo $escape($methodIcon); ?>"
                                aria-hidden="true"
                            ></span>
                        <?php endif; ?>

                        <?php
                        $attributes = array_merge(
                            [
                                'type'         => $inputType,
                                'name'         => 'code',
                                'value'        => '',
                                'placeholder'  => $placeholder !== '' ? $placeholder : null,
                                'id'           => 'users-mfa-code',
                                'class'        => 'form-control cmp-captive__input',
                                'autocomplete' => $this->renderOptions['autocomplete'] ?? 'one-time-code',
                            ],
                            $this->renderOptions['input_attributes']
                        );

                        $inputClasses = trim((string) ($attributes['class'] ?? ''));

                        foreach (['form-control', 'cmp-captive__input'] as $requiredClass) {
                            if (!str_contains(' ' . $inputClasses . ' ', ' ' . $requiredClass . ' ')) {
                                $inputClasses .= ' ' . $requiredClass;
                            }
                        }

                        $attributes['class'] = trim($inputClasses);
                        ?>
                        <input <?php echo ArrayHelper::toString($attributes); ?>>
                    </div>
                </div>
            </div>

            <div
                id="users-mfa-captive-form-standard-buttons"
                class="cmp-form__actions cmp-auth__actions cmp-captive__actions"
            >
                <button
                    class="uk-button uk-button-primary cmp-button cmp-button--primary <?php echo $escape($this->renderOptions['submit_class']); ?>"
                    id="users-mfa-captive-button-submit"
                    type="submit"
                    <?php echo $hideSubmit ? 'hidden' : ''; ?>
                    <?php echo $webAuthnUnavailable ? 'disabled' : ''; ?>
                >
                    <span uk-icon="icon: check" aria-hidden="true"></span>
                    <?php echo Text::_($this->renderOptions['submit_text']); ?>
                </button>

                <a
                    href="<?php echo $escape($logoutUrl); ?>"
                    class="cmp-auth__secondary-button cmp-captive__cancel-button uk-button uk-button-default cmp-button cmp-button--secondary"
                    id="users-mfa-captive-button-logout"
                >
                    <span uk-icon="icon: close" aria-hidden="true"></span>
                    <?php echo Text::_('JCANCEL'); ?>
                </a>
            </div>
        </form>

        <?php if ($this->renderOptions['post_message']) : ?>
            <div class="cmp-auth__lead cmp-captive__message cmp-captive__message--post users-mfa-captive-post-message">
                <?php echo $this->renderOptions['post_message']; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
