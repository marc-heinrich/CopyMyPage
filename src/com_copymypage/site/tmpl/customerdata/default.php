<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Joomla\Component\CopyMyPage\Site\View\Customerdata\HtmlView $this */

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$validAttribute = static function (mixed $value): string {
    $value = strtolower(trim((string) $value));

    return preg_match('/^data-[a-z0-9-]+$/', $value) ? $value : '';
};
$validFieldName = static function (mixed $value): string {
    $value = trim((string) $value);

    return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $value) ? $value : '';
};

$attributes = [];

foreach ((array) ($this->markupAttributes ?? []) as $key => $attribute) {
    $attributes[$key] = $validAttribute($attribute);
}

$fieldNames = \is_array($this->formFieldNames ?? null) ? $this->formFieldNames : [];
$revisionField = $validFieldName($fieldNames['expectedCartRevision'] ?? '')
    ?: 'expectedCartRevision';
$backUrl = Route::_('index.php?option=com_copymypage&view=seatselection');
$formAction = Route::_('index.php?option=com_copymypage&task=customerdata.save');
$loginAction = Route::_('index.php?option=com_copymypage&task=customerdata.login');
$showModeSwitcher = $this->guest && $this->loginForm !== null;
$loginModeActive = $showModeSwitcher && $this->loginModeActive;
$regionsUrl = Route::_(
    'index.php?option=com_copymypage&task=customerdata.regions&format=json&'
        . Session::getFormToken() . '=1',
    false
);
?>
<div
    class="cmp-customer-data"
    <?php if (($attributes['root'] ?? '') !== '') : ?>
        <?php echo $attributes['root']; ?>="1"
    <?php endif; ?>
>
    <div class="uk-container">
        <?php echo LayoutHelper::render(
            'copymypage.tickets.steps',
            [
                'activeStep' => 3,
                'totalSteps' => 5,
            ]
        ); ?>

        <header class="cmp-customer-data__header">
            <h1 id="cmp-customer-data-title" class="cmp-customer-data__title">
                <?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_TITLE')); ?>
            </h1>
            <p id="cmp-customer-data-intro" class="cmp-customer-data__intro">
                <?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_INTRO')); ?>
            </p>

            <?php if ($showModeSwitcher) : ?>
                <nav
                    class="cmp-customer-data__mode-nav"
                    aria-label="<?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_MODE_LABEL')); ?>"
                >
                    <ul
                        class="uk-subnav uk-subnav-pill cmp-customer-data__mode-switcher"
                        uk-switcher="connect: #cmp-customer-data-mode-panels; active: <?php echo $loginModeActive ? 0 : 1; ?>; swiping: false"
                        <?php if (($attributes['modeSwitcher'] ?? '') !== '') : ?>
                            <?php echo $attributes['modeSwitcher']; ?>="1"
                        <?php endif; ?>
                    >
                        <li class="<?php echo $loginModeActive ? 'uk-active' : ''; ?>"
                            <?php if (($attributes['loginMode'] ?? '') !== '') : ?>
                                <?php echo $attributes['loginMode']; ?>="1"
                            <?php endif; ?>
                        >
                            <a href="#">
                                <?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_MODE_ACCOUNT')); ?>
                            </a>
                        </li>
                        <li class="<?php echo $loginModeActive ? '' : 'uk-active'; ?>">
                            <a href="#">
                                <?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_MODE_GUEST')); ?>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </header>

        <?php if ($this->blocked || !$this->form) : ?>
            <section class="cmp-customer-data__blocked" role="alert">
                <h2><?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_BLOCKED_TITLE')); ?></h2>
                <p><?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_BLOCKED_MESSAGE')); ?></p>
                <a
                    class="uk-button uk-button-default cmp-button cmp-button--secondary cmp-button--back cmp-customer-data__back"
                    href="<?php echo $escape($backUrl); ?>"
                >
                    <span uk-icon="icon: chevron-left" aria-hidden="true"></span>
                    <span><?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_BACK')); ?></span>
                </a>
            </section>
        <?php else : ?>
            <?php if ($showModeSwitcher) : ?>
                <ul
                    id="cmp-customer-data-mode-panels"
                    class="uk-switcher cmp-customer-data__mode-panels"
                >
                    <li class="<?php echo $loginModeActive ? 'uk-active' : ''; ?>">
                        <section
                            class="cmp-customer-data-login"
                            aria-labelledby="cmp-customer-data-login-title"
                            aria-describedby="cmp-customer-data-login-intro"
                        >
                            <header class="cmp-customer-data-login__header">
                                <h2 id="cmp-customer-data-login-title">
                                    <?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_LOGIN_TITLE')); ?>
                                </h2>
                                <p id="cmp-customer-data-login-intro">
                                    <?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_LOGIN_INTRO')); ?>
                                </p>
                            </header>

                            <form
                                action="<?php echo $escape($loginAction); ?>"
                                method="post"
                                id="cmp-customer-data-login-form"
                                class="cmp-form cmp-customer-data-login__form form-validate"
                            >
                                <fieldset class="uk-fieldset">
                                    <legend class="visually-hidden">
                                        <?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_LOGIN_TITLE')); ?>
                                    </legend>

                                    <div class="cmp-customer-data__fields">
                                        <?php echo $this->loginForm->renderFieldset('credentials'); ?>
                                    </div>

                                    <?php if (PluginHelper::isEnabled('system', 'remember')) : ?>
                                        <div class="cmp-customer-data-login__remember">
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input"
                                                    id="cmp-customer-data-login-remember"
                                                    type="checkbox"
                                                    name="remember"
                                                    value="yes"
                                                >
                                                <label
                                                    class="form-check-label"
                                                    for="cmp-customer-data-login-remember"
                                                >
                                                    <?php echo $escape(Text::_('COM_USERS_LOGIN_REMEMBER_ME')); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="cmp-form__actions cmp-customer-data-login__actions">
                                        <button
                                            type="submit"
                                            class="uk-button uk-button-primary cmp-button cmp-button--primary"
                                        >
                                            <?php echo $escape(Text::_('JLOGIN')); ?>
                                        </button>
                                    </div>
                                </fieldset>

                                <?php echo HTMLHelper::_('form.token'); ?>
                            </form>

                            <nav
                                class="cmp-customer-data-login__options"
                                aria-label="<?php echo $escape(Text::_('JLOGIN')); ?>"
                            >
                                <a href="<?php echo Route::_('index.php?option=com_users&view=reset'); ?>">
                                    <?php echo $escape(Text::_('COM_USERS_LOGIN_RESET')); ?>
                                </a>
                                <a href="<?php echo Route::_('index.php?option=com_users&view=remind'); ?>">
                                    <?php echo $escape(Text::_('COM_USERS_LOGIN_REMIND')); ?>
                                </a>
                            </nav>
                        </section>
                    </li>
                    <li class="<?php echo $loginModeActive ? '' : 'uk-active'; ?>">
            <?php endif; ?>

            <form
                id="cmp-customer-data-form"
                action="<?php echo $escape($formAction); ?>"
                method="post"
                class="cmp-form cmp-customer-data__form cmp-profile-address-form form-validate"
                aria-labelledby="cmp-customer-data-title"
                aria-describedby="cmp-customer-data-intro"
                <?php if (($attributes['customerForm'] ?? '') !== '') : ?>
                    <?php echo $attributes['customerForm']; ?>="1"
                <?php endif; ?>
                data-regions-url="<?php echo $escape($regionsUrl); ?>"
                data-region-placeholder="<?php echo $escape(
                    Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_SELECT_REGION')
                ); ?>"
                data-region-empty="<?php echo $escape(
                    Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_REGION_EMPTY')
                ); ?>"
                data-region-error="<?php echo $escape(
                    Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_REGION_ERROR')
                ); ?>"
            >
                <fieldset class="uk-fieldset cmp-customer-data__section">
                    <legend><?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_PERSONAL_TITLE')); ?></legend>
                    <p class="cmp-customer-data__section-intro">
                        <?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_PERSONAL_INTRO')); ?>
                    </p>

                    <div class="cmp-customer-data__fields">
                        <div class="uk-grid-small cmp-customer-data__row" uk-grid>
                            <div class="uk-width-1-2@s">
                                <?php echo $this->form->renderField('first_name'); ?>
                            </div>
                            <div class="uk-width-1-2@s">
                                <?php echo $this->form->renderField('last_name'); ?>
                            </div>
                        </div>
                        <?php echo $this->form->renderField('email'); ?>
                    </div>
                </fieldset>

                <fieldset class="uk-fieldset cmp-customer-data__section">
                    <legend><?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ADDRESS_TITLE')); ?></legend>
                    <p class="cmp-customer-data__section-intro">
                        <?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ADDRESS_INTRO')); ?>
                    </p>

                    <div class="cmp-customer-data__fields">
                        <div class="uk-grid-small cmp-customer-data__row" uk-grid>
                            <div class="uk-width-expand@s">
                                <?php echo $this->form->renderField('street'); ?>
                            </div>
                            <div class="uk-width-1-3@s">
                                <?php echo $this->form->renderField('house_number'); ?>
                            </div>
                        </div>
                        <div class="uk-grid-small cmp-customer-data__row" uk-grid>
                            <div class="uk-width-1-2@s">
                                <?php echo $this->form->renderField('postcode'); ?>
                            </div>
                            <div class="uk-width-expand@s">
                                <?php echo $this->form->renderField('city'); ?>
                            </div>
                        </div>
                        <div class="uk-grid-small cmp-customer-data__row" uk-grid>
                            <div class="uk-width-1-2@s">
                                <?php echo $this->form->renderField('country_code'); ?>
                            </div>
                            <div class="uk-width-1-2@s">
                                <?php echo $this->form->renderField('region_code'); ?>
                            </div>
                        </div>
                        <?php echo $this->form->renderField('telephone'); ?>
                    </div>
                </fieldset>

                <?php if ($this->accountCreated) : ?>
                    <section class="cmp-customer-data-account cmp-customer-data-account--created" role="status">
                        <h2><?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ACCOUNT_CREATED_TITLE')); ?></h2>
                        <p><?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ACCOUNT_CREATED_TEXT')); ?></p>
                    </section>
                <?php elseif ($this->showAccountOption && $this->accountForm) : ?>
                    <?php $accountToggle = $this->form->getField('create_account'); ?>
                    <section class="cmp-customer-data-account" aria-labelledby="cmp-customer-data-account-title">
                        <div class="cmp-customer-data-account__switch-row">
                            <div class="cmp-customer-data-account__copy">
                                <h2 id="cmp-customer-data-account-title">
                                    <?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ACCOUNT_TITLE')); ?>
                                </h2>
                                <p><?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ACCOUNT_INTRO')); ?></p>
                            </div>

                            <?php if ($accountToggle !== false) : ?>
                                <label
                                    class="cmp-customer-data-account__switch-control"
                                    for="<?php echo $escape($accountToggle->id); ?>"
                                >
                                    <input
                                        type="checkbox"
                                        name="<?php echo $escape($accountToggle->name); ?>"
                                        id="<?php echo $escape($accountToggle->id); ?>"
                                        class="form-check-input"
                                        value="1"
                                        role="switch"
                                        aria-controls="cmp-customer-data-account-fields"
                                        aria-expanded="<?php echo $this->accountExpanded ? 'true' : 'false'; ?>"
                                        <?php echo $this->accountExpanded ? 'checked' : ''; ?>
                                    >
                                    <span class="visually-hidden">
                                        <?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ACCOUNT_TOGGLE')); ?>
                                    </span>
                                </label>
                            <?php endif; ?>
                        </div>

                        <div
                            id="cmp-customer-data-account-fields"
                            class="cmp-customer-data-account__fields"
                            <?php if (($attributes['accountFields'] ?? '') !== '') : ?>
                                <?php echo $attributes['accountFields']; ?>="1"
                            <?php endif; ?>
                        >
                            <div class="cmp-customer-data__fields">
                                <?php echo $this->accountForm->renderField('username'); ?>
                                <?php echo $this->accountForm->renderField('password1'); ?>
                                <?php echo $this->accountForm->renderField('password2'); ?>
                            </div>

                            <p class="cmp-customer-data-account__activation-note">
                                <?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ACCOUNT_ACTIVATION')); ?>
                            </p>

                            <?php if ($this->accountForm->getFieldset('privacyconsent') !== []) : ?>
                                <div class="cmp-customer-data-account__consent">
                                    <?php echo $this->accountForm->renderFieldset('privacyconsent'); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($this->captchaEnabled && $this->accountForm->getField('captcha')) : ?>
                                <div class="cmp-customer-data-account__captcha">
                                    <?php echo $this->accountForm->renderField('captcha'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <input type="hidden" name="option" value="com_copymypage">
                <input type="hidden" name="task" value="customerdata.save">
                <input
                    type="hidden"
                    name="<?php echo $escape($revisionField); ?>"
                    value="<?php echo (int) $this->cartRevision; ?>"
                >
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>

            <?php if ($showModeSwitcher) : ?>
                    </li>
                </ul>
            <?php endif; ?>

            <nav
                class="cmp-customer-data__navigation"
                aria-label="<?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_NAVIGATION_LABEL')); ?>"
            >
                <a
                    class="uk-button uk-button-default cmp-button cmp-button--secondary cmp-button--back cmp-customer-data__back"
                    href="<?php echo $escape($backUrl); ?>"
                >
                    <span uk-icon="icon: chevron-left" aria-hidden="true"></span>
                    <span><?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_BACK')); ?></span>
                </a>
                <button
                    class="uk-button uk-button-primary cmp-button cmp-button--primary validate cmp-customer-data__continue"
                    type="submit"
                    form="cmp-customer-data-form"
                    <?php if (($attributes['continue'] ?? '') !== '') : ?>
                        <?php echo $attributes['continue']; ?>="1"
                    <?php endif; ?>
                >
                    <span><?php echo $escape(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_CONTINUE')); ?></span>
                    <span uk-icon="icon: chevron-right" aria-hidden="true"></span>
                </button>
            </nav>
        <?php endif; ?>
    </div>
</div>
