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
<div class="cmp-dashboard cmp-dashboard--security-edit com-users-profile__edit profile-edit">
    <header class="cmp-dashboard__page-header">
        <div>
            <h1 class="cmp-dashboard__page-title">
                <?php echo Text::_('COM_COPYMYPAGE_SECURITY_EDIT_TITLE'); ?>
            </h1>
            <p class="cmp-dashboard__page-lead">
                <?php echo Text::_('COM_COPYMYPAGE_SECURITY_EDIT_LEAD'); ?>
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
        <?php
        echo LayoutHelper::render(
            'copymypage.dashboard.security_form',
            [
                'description' => Text::_('COM_COPYMYPAGE_SECURITY_PASSWORD_DESCRIPTION'),
                'form'        => $this->form,
                'layout'      => 'security.edit',
            ]
        );
        ?>
    </div>
</div>
