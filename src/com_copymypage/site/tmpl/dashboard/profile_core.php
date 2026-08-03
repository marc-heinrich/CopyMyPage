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

/** @var \Joomla\Component\CopyMyPage\Site\View\Dashboard\HtmlView $this */
?>
<section
    id="users-profile-core"
    class="cmp-profile-section com-users-profile__core"
    aria-labelledby="users-profile-core-title"
>
    <div class="cmp-profile-section__header">
        <h2 id="users-profile-core-title"><?php echo Text::_('COM_USERS_PROFILE_CORE_LEGEND'); ?></h2>
        <a
            class="cmp-dashboard-row-action cmp-profile-section__edit"
            href="<?php echo $this->escape($this->profileEditUrl); ?>"
            aria-label="<?php echo $this->escape(Text::_('COM_USERS_EDIT_PROFILE')); ?>"
        >
            <span class="cmp-profile-section__edit-label"><?php echo Text::_('JACTION_EDIT'); ?></span>
            <span
                class="cmp-profile-section__edit-icon"
                uk-icon="icon: chevron-right"
                aria-hidden="true"
            ></span>
        </a>
    </div>
    <dl class="cmp-profile-details">
        <div>
            <dt><?php echo Text::_('COM_USERS_PROFILE_NAME_LABEL'); ?></dt>
            <dd><?php echo $this->escape($this->data->name); ?></dd>
        </div>
        <div>
            <dt><?php echo Text::_('COM_USERS_PROFILE_USERNAME_LABEL'); ?></dt>
            <dd><?php echo $this->escape($this->data->username); ?></dd>
        </div>
        <div>
            <dt><?php echo Text::_('COM_USERS_PROFILE_EMAIL1_LABEL'); ?></dt>
            <dd><?php echo $this->escape($this->data->email); ?></dd>
        </div>
        <div>
            <dt><?php echo Text::_('COM_USERS_PROFILE_REGISTERED_DATE_LABEL'); ?></dt>
            <dd><?php echo HTMLHelper::_('date', $this->data->registerDate, Text::_('DATE_FORMAT_LC1')); ?></dd>
        </div>
        <div>
            <dt><?php echo Text::_('COM_USERS_PROFILE_LAST_VISITED_DATE_LABEL'); ?></dt>
            <dd>
                <?php if ($this->data->lastvisitDate !== null) : ?>
                    <?php echo HTMLHelper::_('date', $this->data->lastvisitDate, Text::_('DATE_FORMAT_LC1')); ?>
                <?php else : ?>
                    <?php echo Text::_('COM_USERS_PROFILE_NEVER_VISITED'); ?>
                <?php endif; ?>
            </dd>
        </div>
    </dl>
</section>
