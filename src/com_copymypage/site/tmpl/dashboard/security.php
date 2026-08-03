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

/** @var \Joomla\Component\CopyMyPage\Site\View\Dashboard\HtmlView $this */

HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');

$this->getDocument()->getWebAssetManager()
    ->useStyle('joomla.fontawesome')
    ->useScript('keepalive')
    ->useScript('form.validate');
?>
<div class="cmp-dashboard cmp-dashboard--security">
    <header class="cmp-dashboard__page-header">
        <div>
            <h1 class="cmp-dashboard__page-title">
                <?php echo Text::_('COM_COPYMYPAGE_SECURITY_TITLE'); ?>
            </h1>
            <p class="cmp-dashboard__page-lead">
                <?php echo Text::_('COM_COPYMYPAGE_SECURITY_LEAD'); ?>
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
        <?php if ($this->mfaConfigurationUI) : ?>
            <?php
            $mfaStatusClass = $this->mfaActive
                ? 'cmp-security-mfa__status--enabled'
                : 'cmp-security-mfa__status--disabled';
            $mfaStatusIcon = $this->mfaActive ? 'check' : 'ban';
            $mfaStatusText = Text::_($this->mfaActive ? 'JENABLED' : 'JDISABLED');
            ?>
            <div class="cmp-form cmp-profile-form cmp-security-mfa">
                <ul
                    class="uk-accordion-default cmp-security-mfa__accordion"
                    uk-accordion="collapsible: true"
                >
                    <li>
                        <a
                            class="uk-accordion-title cmp-security-mfa__toggle"
                            href="#"
                        >
                            <span class="cmp-security-mfa__summary">
                                <span
                                    class="cmp-security-mfa__status <?php echo $mfaStatusClass; ?>"
                                    uk-icon="icon: <?php echo $mfaStatusIcon; ?>"
                                    aria-hidden="true"
                                ></span>
                                <span class="cmp-security-mfa__copy">
                                    <span
                                        id="cmp-security-mfa-title"
                                        class="cmp-security-mfa__title"
                                    >
                                        <?php echo Text::_('COM_COPYMYPAGE_SECURITY_MFA_TITLE'); ?>
                                        <span class="visually-hidden">
                                            <?php echo ', ' . $mfaStatusText; ?>
                                        </span>
                                    </span>
                                    <span class="cmp-security-mfa__description">
                                        <?php echo Text::_('COM_COPYMYPAGE_SECURITY_MFA_DESCRIPTION'); ?>
                                    </span>
                                </span>
                            </span>
                            <span
                                class="cmp-security-mfa__icon"
                                uk-accordion-icon
                                aria-hidden="true"
                            ></span>
                        </a>
                        <div class="uk-accordion-content cmp-security-mfa__content">
                            <fieldset
                                class="uk-fieldset cmp-profile-form__section com-users-profile__multifactor"
                                aria-labelledby="cmp-security-mfa-title"
                            >
                                <legend class="visually-hidden">
                                    <?php echo Text::_('COM_COPYMYPAGE_SECURITY_MFA_TITLE'); ?>
                                </legend>
                                <?php echo $this->mfaConfigurationUI; ?>
                            </fieldset>
                        </div>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <?php
        echo LayoutHelper::render(
            'copymypage.dashboard.security_form',
            [
                'description' => Text::_('COM_COPYMYPAGE_SECURITY_PASSWORD_DESCRIPTION'),
                'form'        => $this->form,
                'layout'      => 'security',
            ]
        );
        ?>
    </div>
</div>
