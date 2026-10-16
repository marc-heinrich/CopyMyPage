<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_users
 * @copyright   (C) 2022 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       0.0.17
 * 
 * CopyMyPage dashboard override for Joomla's HMVC MFA methods list.
 */

// Prevent direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Users\Administrator\Helper\Mfa as MfaHelper;
use Joomla\Component\Users\Site\Model\MethodsModel;

/** @var \Joomla\Component\Users\Site\View\Methods\HtmlView $this */

/** @var MethodsModel $model */
$model = $this->getModel();

$this->getDocument()->getWebAssetManager()
     ->useScript('com_users.two-factor-list')
     ->useScript('copymypage.content.drawer');

HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');

$canAddEdit = MfaHelper::canAddEditMethod($this->user);
$canDelete  = MfaHelper::canDeleteMethod($this->user);
$returnUrl  = '';

if (!empty($this->returnURL)) {
    $decodedReturnUrl = base64_decode($this->returnURL, true);

    if ($decodedReturnUrl !== false && Uri::isInternal($decodedReturnUrl)) {
        $returnUrl = $decodedReturnUrl;
    }
}

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$getDrawerAttributes = static function (string $title) use ($escape, $returnUrl): string {
    if ($returnUrl === '') {
        return '';
    }

    $attributes = [
        'data-cmp-content-drawer'       => 'mfa',
        'data-cmp-drawer-return-action' => 'reload-parent',
        'data-cmp-drawer-return-url'    => $returnUrl,
        'data-cmp-drawer-title'         => $title,
        'data-cmp-drawer-transport'     => 'document',
    ];
    $output = [];

    foreach ($attributes as $name => $value) {
        $output[] = $name . '="' . $escape($value) . '"';
    }

    return ' ' . implode(' ', $output);
};

$getActionUrl = function (string $task, string $query): string {
    return Route::_(
        'index.php?option=com_users&task=' . $task . '&' . $query
        . ($this->returnURL ? '&returnurl=' . urlencode($this->returnURL) : '')
        . '&user_id=' . (int) $this->user->id,
        false
    );
};

$renderAction = static function (
    string $url,
    string $title,
    string $description,
    string $hook
) use ($escape, $getDrawerAttributes): void {
    ?>
    <a
        class="cmp-dashboard-link-group__link cmp-dashboard-link-group__link--text <?php echo $escape($hook); ?>"
        href="<?php echo $escape($url); ?>"
        <?php echo $getDrawerAttributes($title); ?>
    >
        <span class="cmp-dashboard-link-group__copy">
            <span class="cmp-dashboard-link-group__label">
                <?php echo $escape($title); ?>
            </span>
            <span class="cmp-dashboard-link-group__description">
                <?php echo $description; ?>
            </span>
        </span>
        <span class="cmp-dashboard-link-group__chevron" aria-hidden="true">
            <span uk-icon="icon: chevron-right"></span>
        </span>
    </a>
    <?php
};
?>
<div id="com-users-methods-list-container" class="cmp-mfa-settings__methods">
    <ul class="cmp-dashboard-link-group__list">
        <?php foreach ($this->methods as $methodName => $method) : ?>
            <?php
            $methodClass = 'com-users-methods-list-method-name-' . $method['name']
                . ($this->defaultMethod === $methodName ? ' com-users-methods-list-method-default' : '');
            ?>
            <?php foreach ($method['active'] as $record) : ?>
                <li class="cmp-dashboard-link-group__item <?php echo $escape($methodClass); ?>">
                    <?php
                    $recordTitle = $methodName === 'backupcodes'
                        ? Text::_('COM_USERS_USER_BACKUPCODES')
                        : (string) $record->title;
                    $descriptionId = 'cmp-mfa-record-description-' . (int) $record->id;
                    ?>
                    <div class="cmp-dashboard-link-group__link cmp-dashboard-link-group__link--text cmp-dashboard-link-group__link--split">
                        <div class="cmp-dashboard-link-group__copy">
                            <div class="cmp-mfa-settings__record-header">
                                <?php if ($canAddEdit) : ?>
                                    <a
                                        class="cmp-dashboard-link-group__label cmp-dashboard-link-group__primary com-users-methods-list-method-record-edit"
                                        href="<?php echo $escape($getActionUrl('method.edit', 'id=' . (int) $record->id)); ?>"
                                        aria-describedby="<?php echo $escape(
                                            $descriptionId
                                            . ($methodName !== 'backupcodes' ? ' cmp-mfa-record-active-' . (int) $record->id : '')
                                            . ($record->default ? ' cmp-mfa-record-default-' . (int) $record->id : '')
                                        ); ?>"
                                        <?php echo $getDrawerAttributes($recordTitle); ?>
                                    ><?php echo $escape($recordTitle); ?></a>
                                <?php else : ?>
                                    <span class="cmp-dashboard-link-group__label"><?php echo $escape($recordTitle); ?></span>
                                <?php endif; ?>
                                <?php if ($methodName !== 'backupcodes' || $record->default) : ?>
                                    <div class="cmp-mfa-settings__record-status">
                                        <?php if ($methodName !== 'backupcodes') : ?>
                                            <span id="cmp-mfa-record-active-<?php echo (int) $record->id; ?>" class="cmp-mfa-settings__active">
                                                <span aria-hidden="true">· </span><?php echo Text::_('COM_COPYMYPAGE_SECURITY_MFA_STATUS_ACTIVE'); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($record->default) : ?>
                                            <span id="cmp-mfa-record-default-<?php echo (int) $record->id; ?>" class="cmp-mfa-settings__default">
                                                <span aria-hidden="true">· </span><?php echo Text::_('COM_USERS_MFA_LIST_DEFAULTTAG'); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($methodName !== 'backupcodes' && $method['canDisable'] && $canDelete) : ?>
                                            <span aria-hidden="true" class="cmp-mfa-settings__separator">·</span>
                                            <a
                                                class="com-users-methods-list-method-record-delete cmp-dashboard-link-group__action cmp-mfa-settings__delete"
                                                href="<?php echo $escape($getActionUrl(
                                                    'method.delete',
                                                    'id=' . (int) $record->id . '&' . Factory::getApplication()->getFormToken() . '=1'
                                                )); ?>"
                                                aria-label="<?php echo $escape(Text::_('JACTION_DELETE') . ' ' . $record->title); ?>"
                                            >
                                                <?php echo Text::_('JACTION_DELETE'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div id="<?php echo $escape($descriptionId); ?>" class="cmp-dashboard-link-group__description cmp-mfa-settings__record-meta">
                                <span><?php echo $method['shortinfo']; ?></span>
                                <span><?php echo Text::sprintf('COM_USERS_MFA_LBL_CREATEDON', $model->formatRelative($record->created_on)); ?></span>
                                <span><?php echo Text::sprintf('COM_USERS_MFA_LBL_LASTUSED', $model->formatRelative($record->last_used)); ?></span>
                            </div>
                        </div>
                        <?php if ($canAddEdit) : ?>
                            <span class="cmp-dashboard-link-group__chevron" aria-hidden="true">
                                <span uk-icon="icon: chevron-right"></span>
                            </span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>

            <?php if ($canAddEdit && (empty($method['active']) || $method['allowMultiple'])) : ?>
                <li class="cmp-dashboard-link-group__item <?php echo $escape($methodClass); ?>">
                    <?php $renderAction(
                        $getActionUrl('method.add', 'method=' . urlencode($method['name'])),
                        Text::sprintf('COM_USERS_MFA_ADD_AUTHENTICATOR_OF_TYPE', $method['display']),
                        (string) $method['shortinfo'],
                        'com-users-methods-list-method-addnew'
                    ); ?>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
</div>
