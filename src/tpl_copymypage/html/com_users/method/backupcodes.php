<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_users
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       0.0.17
 * 
 * CopyMyPage document-drawer override for Joomla's MFA backup codes.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \Joomla\Component\Users\Site\View\Method\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();

// com_users renders this override before the outer CopyMyPage template can
// register its assets. Load the registry before requesting the icon style.
$wa->getRegistry()->addExtensionRegistryFile('com_copymypage');
$wa->useStyle('joomla.fontawesome');

$cancelUrl = Route::_('index.php?option=com_users&task=methods.display&user_id=' . $this->user->id);

if (!empty($this->returnURL)) {
    $decodedReturnUrl = base64_decode($this->returnURL, true);

    if ($decodedReturnUrl !== false && Uri::isInternal($decodedReturnUrl)) {
        $cancelUrl = $decodedReturnUrl;
    }
}

if ($this->record->method !== 'backupcodes') {
    throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
}

$isDrawer     = Factory::getApplication()->getInput()->getCmd('cmp_context') === 'drawer';
$headingClass = 'cmp-mfa-backupcodes__heading' . ($isDrawer ? ' visually-hidden' : '');
$resetUrl     = Route::_(
    'index.php?option=com_users&task=method.regenerateBackupCodes&user_id='
    . (int) $this->user->id
    . '&' . Factory::getApplication()->getFormToken() . '=1'
    . (empty($this->returnURL) ? '' : '&returnurl=' . urlencode($this->returnURL))
);
?>
<div
    class="cmp-form cmp-profile-form cmp-mfa-backupcodes"
    data-cmp-drawer-document-content
>
    <section
        class="cmp-profile-form__section cmp-profile-form__panel cmp-mfa-backupcodes__panel"
        aria-labelledby="com-users-method-backupcodes-head"
    >
        <h1
            id="com-users-method-backupcodes-head"
            class="<?php echo $headingClass; ?>"
        >
            <?php echo Text::_('COM_USERS_USER_BACKUPCODES'); ?>
        </h1>

        <div class="cmp-mfa-backupcodes__notice">
            <?php echo Text::_('COM_USERS_USER_BACKUPCODES_DESC'); ?>
        </div>

        <div class="cmp-mfa-method__table-scroll">
            <table class="cmp-mfa-method__table cmp-mfa-backupcodes__table">
                <caption class="visually-hidden">
                    <?php echo Text::_('COM_USERS_USER_BACKUPCODES'); ?>
                </caption>
                <tbody>
                    <?php for ($i = 0; $i < (count($this->backupCodes) / 2); $i++) : ?>
                        <tr>
                            <td>
                                <?php if (!empty($this->backupCodes[2 * $i])) : ?>
                                    <span aria-hidden="true">&#128273;</span>
                                    <code><?php echo $this->escape($this->backupCodes[2 * $i]); ?></code>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($this->backupCodes[1 + 2 * $i])) : ?>
                                    <span aria-hidden="true">&#128273;</span>
                                    <code><?php echo $this->escape($this->backupCodes[1 + 2 * $i]); ?></code>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <p class="cmp-mfa-backupcodes__reset-info">
            <?php echo Text::_('COM_USERS_MFA_BACKUPCODES_RESET_INFO'); ?>
        </p>
    </section>

    <div class="cmp-form__actions cmp-profile-form__actions cmp-mfa-backupcodes__actions">
        <a
            class="uk-button uk-button-danger cmp-button cmp-button--danger"
            href="<?php echo $resetUrl; ?>"
        >
            <span class="icon icon-refresh" aria-hidden="true"></span>
            <?php echo Text::_('COM_USERS_MFA_BACKUPCODES_RESET'); ?>
        </a>

        <a
            href="<?php echo $this->escape($cancelUrl); ?>"
            class="uk-button uk-button-default cmp-button cmp-button--secondary"
        >
            <span uk-icon="icon: close" aria-hidden="true"></span>
            <?php echo Text::_('JCANCEL'); ?>
        </a>
    </div>
</div>
