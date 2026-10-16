<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_users
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * CopyMyPage dashboard override for Joomla's HMVC MFA configuration.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Component\CopyMyPage\Site\Service\AccountMenuProvider;

/** @var \Joomla\Component\Users\Site\View\Methods\HtmlView $this */

$this->getDocument()->getWebAssetManager()->useStyle('joomla.fontawesome');
$profileUrl = Factory::getContainer()->get(AccountMenuProvider::class)
    ->getDashboardUrl(Factory::getApplication(), 'profile') . '#cmp-profile-security';
?>
<div id="com-users-methods-list" class="cmp-mfa-settings">
    <div class="cmp-mfa-settings__toolbar">
        <a
            class="uk-button uk-button-default cmp-button cmp-button--secondary cmp-button--back"
            href="<?php echo $this->escape($profileUrl); ?>"
        >
            <span uk-icon="icon: chevron-left" aria-hidden="true"></span>
            <?php echo Text::_('COM_COPYMYPAGE_PROFILE_TITLE'); ?>
        </a>
        <?php if ($this->mfaActive) : ?>
            <div id="com-users-methods-reset-container" class="cmp-mfa-settings__status">
                <p id="com-users-methods-reset-message" role="status">
                    <span uk-icon="icon: check" aria-hidden="true"></span>
                    <?php echo Text::_('COM_COPYMYPAGE_SECURITY_MFA_STATUS_ENABLED'); ?>
                </p>
                <a
                    class="uk-button uk-button-danger cmp-button cmp-button--danger"
                    href="<?php echo $this->escape(Route::_(
                        'index.php?option=com_users&task=methods.disable&'
                        . Factory::getApplication()->getFormToken() . '=1'
                        . ($this->returnURL ? '&returnurl=' . urlencode($this->returnURL) : '')
                        . '&user_id=' . (int) $this->user->id,
                        false
                    )); ?>"
                >
                    <?php echo Text::_('COM_USERS_MFA_LIST_REMOVEALL'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!\count($this->methods)) : ?>
        <p><?php echo Text::_('COM_USERS_MFA_LIST_INSTRUCTIONS'); ?></p>
    <?php elseif ($this->isMandatoryMFASetup) : ?>
        <div class="alert alert-info">
            <h2><?php echo Text::_('COM_USERS_MFA_MANDATORY_NOTICE_HEAD'); ?></h2>
            <p><?php echo Text::_('COM_USERS_MFA_MANDATORY_NOTICE_BODY'); ?></p>
        </div>
    <?php endif; ?>

    <?php
    $this->setLayout('list');
    echo $this->loadTemplate();
    ?>
</div>
