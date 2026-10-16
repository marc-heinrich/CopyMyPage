<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/** @var \Joomla\Component\CopyMyPage\Site\View\Dashboard\HtmlView $this */
?>
<div class="cmp-dashboard cmp-dashboard--security-mfa">
    <header class="cmp-dashboard__page-header">
        <div>
            <h1 class="cmp-dashboard__page-title">
                <?php echo Text::_('COM_COPYMYPAGE_SECURITY_MFA_TITLE'); ?>
            </h1>
            <p class="cmp-dashboard__page-lead">
                <?php echo Text::_('COM_COPYMYPAGE_SECURITY_MFA_DESCRIPTION'); ?>
            </p>
        </div>
    </header>

    <?php echo LayoutHelper::render('copymypage.dashboard.navigation', $this->accountMenu); ?>

    <div class="cmp-dashboard__content">
        <?php if ($this->mfaConfigurationUI) : ?>
            <?php echo $this->mfaConfigurationUI; ?>
        <?php else : ?>
            <div class="cmp-mfa-settings__toolbar">
                <a
                    class="uk-button uk-button-default cmp-button cmp-button--secondary cmp-button--back"
                    href="<?php echo $this->escape($this->securityUrl); ?>"
                >
                    <span uk-icon="icon: chevron-left" aria-hidden="true"></span>
                    <?php echo Text::_('COM_COPYMYPAGE_PROFILE_TITLE'); ?>
                </a>
            </div>
            <p role="status">
                <span
                    class="cmp-security-mfa__status cmp-security-mfa__status--<?php echo $this->mfaActive ? 'enabled' : 'disabled'; ?>"
                    uk-icon="icon: <?php echo $this->mfaActive ? 'check' : 'ban'; ?>"
                    aria-hidden="true"
                ></span>
                <?php echo Text::_('COM_COPYMYPAGE_SECURITY_MFA_STATUS_' . ($this->mfaActive ? 'ACTIVE' : 'INACTIVE')); ?>
            </p>
            <p><?php echo Text::_('COM_COPYMYPAGE_SECURITY_MFA_UNAVAILABLE'); ?></p>
        <?php endif; ?>
    </div>
</div>
