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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \Joomla\Component\Users\Site\View\Captive\HtmlView $this */

$app          = Factory::getApplication();
$escape       = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$model        = $this->getModel();
$shownMethods = [];
$logoutUrl    = Route::_(
    'index.php?option=com_users&task=user.logout&' . $app->getFormToken() . '=1',
    false
);
$returnUrl = !empty($this->record)
    ? Route::_('index.php?option=com_users&view=captive&record_id=' . (int) $this->record->id, false)
    : '';
?>
<section
    id="com-users-select"
    class="cmp-auth cmp-captive cmp-captive--select"
    aria-labelledby="com-users-select-heading"
>
    <header class="cmp-captive__toolbar">
        <?php if ($returnUrl !== '') : ?>
            <a
                href="<?php echo $escape($returnUrl); ?>"
                class="cmp-captive__toolbar-action"
                aria-label="<?php echo $escape(Text::_('JPREVIOUS')); ?>"
                title="<?php echo $escape(Text::_('JPREVIOUS')); ?>"
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
            <h1 id="com-users-select-heading" class="cmp-auth__title cmp-captive__title">
                <?php echo Text::_('COM_USERS_MFA_USE_DIFFERENT_METHOD'); ?>
            </h1>

            <p id="com-users-select-information" class="cmp-auth__lead cmp-captive__message">
                <?php echo Text::_('COM_USERS_LBL_SELECT_INSTRUCTIONS'); ?>
            </p>
        </header>

        <div class="cmp-captive__methods com-users-select-methods">
            <?php foreach ($this->records as $record) : ?>
                <?php
                $method = (string) $record->method;

                if (!isset($this->mfaMethods[$method]) && $method !== 'backupcodes') {
                    continue;
                }

                $allowEntryBatching = (bool) ($this->mfaMethods[$method]['allowEntryBatching'] ?? false);

                if ($allowEntryBatching && isset($shownMethods[$method])) {
                    continue;
                }

                if ($allowEntryBatching) {
                    $shownMethods[$method] = true;
                }

                $methodTitle = $allowEntryBatching
                    ? $model->translateMethodName($method)
                    : (string) $record->title;
                $methodUrl   = Route::_(
                    'index.php?option=com_users&view=captive&record_id=' . (int) $record->id,
                    false
                );
                $imageUrl    = Uri::root() . $model->getMethodImage($method);
                ?>
                <a
                    class="cmp-captive__method com-users-method"
                    href="<?php echo $escape($methodUrl); ?>"
                >
                    <span class="cmp-captive__method-icon" aria-hidden="true">
                        <img
                            src="<?php echo $escape($imageUrl); ?>"
                            alt=""
                            class="com-users-method-image"
                            loading="eager"
                            decoding="async"
                        >
                    </span>
                    <span class="cmp-captive__method-title com-users-method-title">
                        <?php echo $escape($methodTitle); ?>
                    </span>
                    <span
                        class="cmp-captive__method-arrow"
                        uk-icon="icon: <?php echo $app->getLanguage()->isRtl() ? 'chevron-left' : 'chevron-right'; ?>"
                        aria-hidden="true"
                    ></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="cmp-form__actions cmp-auth__actions cmp-captive__actions">
            <a
                href="<?php echo $escape($logoutUrl); ?>"
                class="cmp-auth__secondary-button cmp-captive__cancel-button uk-button uk-button-default cmp-button cmp-button--secondary"
                id="users-mfa-captive-button-logout"
            >
                <span uk-icon="icon: close" aria-hidden="true"></span>
                <?php echo Text::_('JCANCEL'); ?>
            </a>
        </div>
    </div>
</section>
