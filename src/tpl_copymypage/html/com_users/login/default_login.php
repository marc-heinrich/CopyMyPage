<?php

/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\Users\Site\View\Login\HtmlView $this */

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$usersConfig = ComponentHelper::getParams('com_users');
$escape      = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$pageHeading = trim((string) $this->params->get('page_heading', ''));
$heading     = $this->params->get('show_page_heading') && $pageHeading !== ''
    ? $pageHeading
    : Text::_('JLOGIN');
$hasDescription = (
    (int) $this->params->get('logindescription_show', 0) === 1
    && trim((string) $this->params->get('login_description', '')) !== ''
) || (string) $this->params->get('login_image', '') !== '';

// Always return a successful login to the dedicated CopyMyPage dashboard route.
$this->form->addControlField(
    'return',
    base64_encode('index.php?option=com_copymypage&view=dashboard&layout=default')
);
?>
<div class="cmp-auth cmp-auth--login com-users-login login">
    <header class="cmp-auth__header">
        <h1 id="cmp-auth-title" class="cmp-auth__title">
            <?php echo $escape($heading); ?>
        </h1>

        <?php if ($hasDescription) : ?>
            <div class="cmp-auth__lead com-users-login__description login-description">
                <?php if ((int) $this->params->get('logindescription_show', 0) === 1) : ?>
                    <?php echo $this->params->get('login_description'); ?>
                <?php endif; ?>

                <?php if ((string) $this->params->get('login_image', '') !== '') : ?>
                    <?php
                    echo HTMLHelper::_(
                        'image',
                        $this->params->get('login_image'),
                        empty($this->params->get('login_image_alt'))
                            && empty($this->params->get('login_image_alt_empty'))
                            ? false
                            : $this->params->get('login_image_alt'),
                        ['class' => 'cmp-auth__image com-users-login__image login-image']
                    );
                    ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </header>

    <form
        action="<?php echo Route::_('index.php?task=user.login'); ?>"
        method="post"
        id="com-users-login__form"
        class="cmp-form cmp-auth__form com-users-login__form form-validate"
        aria-labelledby="cmp-auth-title"
    >
        <fieldset class="cmp-auth__fieldset">
            <?php echo $this->form->renderFieldset('credentials', ['class' => 'com-users-login__input']); ?>

            <?php if (PluginHelper::isEnabled('system', 'remember')) : ?>
                <div class="cmp-auth__remember com-users-login__remember">
                    <div class="form-check">
                        <input class="form-check-input" id="remember" type="checkbox" name="remember" value="yes">
                        <label class="form-check-label" for="remember">
                            <?php echo Text::_('COM_USERS_LOGIN_REMEMBER_ME'); ?>
                        </label>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($this->extraButtons as $button) : ?>
                <?php
                $dataAttributeKeys = array_filter(
                    array_keys($button),
                    static fn(mixed $key): bool => str_starts_with((string) $key, 'data-')
                );
                ?>
                <div class="cmp-auth__extra-button com-users-login__submit control-group">
                    <div class="controls">
                        <button
                            type="button"
                            class="cmp-auth__secondary-button btn btn-secondary w-100 <?php echo $escape($button['class'] ?? ''); ?>"
                            <?php foreach ($dataAttributeKeys as $key) : ?>
                                <?php $dataKey = preg_replace('/[^a-z0-9:_-]/i', '', (string) $key); ?>
                                <?php if ($dataKey !== '') : ?>
                                    <?php echo $dataKey; ?>="<?php echo $escape($button[$key] ?? ''); ?>"
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if (!empty($button['onclick'])) : ?>
                                onclick="<?php echo $escape($button['onclick']); ?>"
                            <?php endif; ?>
                            title="<?php echo $escape(Text::_((string) ($button['label'] ?? ''))); ?>"
                            id="<?php echo $escape($button['id'] ?? ''); ?>"
                        >
                            <?php if (!empty($button['icon'])) : ?>
                                <span class="<?php echo $escape($button['icon']); ?>"></span>
                            <?php elseif (!empty($button['image'])) : ?>
                                <?php
                                echo HTMLHelper::_(
                                    'image',
                                    $button['image'],
                                    Text::_((string) ($button['tooltip'] ?? '')),
                                    ['class' => 'icon'],
                                    true
                                );
                                ?>
                            <?php elseif (!empty($button['svg'])) : ?>
                                <?php echo $button['svg']; ?>
                            <?php endif; ?>
                            <?php echo $escape(Text::_((string) ($button['label'] ?? ''))); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="cmp-form__actions cmp-auth__actions com-users-login__submit control-group">
                <div class="controls">
                    <button type="submit" class="uk-button uk-button-primary">
                        <?php echo Text::_('JLOGIN'); ?>
                    </button>
                </div>
            </div>

            <?php echo $this->form->renderControlFields(); ?>
        </fieldset>
    </form>

    <nav class="cmp-auth__options com-users-login__options" aria-label="<?php echo $escape(Text::_('JLOGIN')); ?>">
        <a class="com-users-login__reset" href="<?php echo Route::_('index.php?option=com_users&view=reset'); ?>">
            <?php echo Text::_('COM_USERS_LOGIN_RESET'); ?>
        </a>
        <a class="com-users-login__remind" href="<?php echo Route::_('index.php?option=com_users&view=remind'); ?>">
            <?php echo Text::_('COM_USERS_LOGIN_REMIND'); ?>
        </a>
        <?php if ($usersConfig->get('allowUserRegistration')) : ?>
            <?php
            $regLinkMenuId = (int) $this->params->get('customRegLinkMenu', 0);
            $regLink       = 'index.php?option=com_users&view=registration';

            if ($regLinkMenuId > 0) {
                $item = Factory::getApplication()->getMenu()->getItem($regLinkMenuId);

                if ($item) {
                    $regLink = 'index.php?Itemid=' . $regLinkMenuId;
                }
            }
            ?>
            <a class="com-users-login__register" href="<?php echo Route::_($regLink); ?>">
                <?php echo Text::_('COM_USERS_LOGIN_REGISTER'); ?>
            </a>
        <?php endif; ?>
    </nav>
</div>
