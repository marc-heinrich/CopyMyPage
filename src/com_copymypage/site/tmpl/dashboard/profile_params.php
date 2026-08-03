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

$fields = $this->form->getFieldset('params');
?>
<?php if (\count($fields)) : ?>
    <section
        id="users-profile-custom"
        class="cmp-profile-section com-users-profile__params"
        aria-labelledby="users-profile-custom-title"
    >
        <h2 id="users-profile-custom-title"><?php echo Text::_('COM_USERS_SETTINGS_FIELDSET_LABEL'); ?></h2>
        <dl class="cmp-profile-details">
            <?php foreach ($fields as $field) : ?>
                <?php if (!$field->hidden) : ?>
                    <div>
                        <dt><?php echo $field->title; ?></dt>
                        <dd>
                            <?php if (HTMLHelper::isRegistered('users.' . $field->id)) : ?>
                                <?php echo HTMLHelper::_('users.' . $field->id, $field->value); ?>
                            <?php elseif (HTMLHelper::isRegistered('users.' . $field->fieldname)) : ?>
                                <?php echo HTMLHelper::_('users.' . $field->fieldname, $field->value); ?>
                            <?php elseif (HTMLHelper::isRegistered('users.' . $field->type)) : ?>
                                <?php echo HTMLHelper::_('users.' . $field->type, $field->value); ?>
                            <?php else : ?>
                                <?php echo HTMLHelper::_('users.value', $field->value); ?>
                            <?php endif; ?>
                        </dd>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </dl>
    </section>
<?php endif; ?>
