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

$fieldsets = $this->form->getFieldsets();

unset($fieldsets['core'], $fieldsets['params']);

$tmp          = $this->data->jcfields ?? [];
$customFields = [];

foreach ($tmp as $customField) {
    $customFields[$customField->name] = $customField;
}

unset($tmp);
?>
<?php foreach ($fieldsets as $group => $fieldset) : ?>
    <?php $fields = $this->form->getFieldset($group); ?>
    <?php if (\count($fields)) : ?>
        <?php $safeGroup = preg_replace('/[^a-z0-9_-]/i', '', (string) $group) ?: 'custom'; ?>
        <?php $headingId = 'users-profile-custom-' . $safeGroup . '-title'; ?>
        <section
            id="users-profile-custom-<?php echo $this->escape($safeGroup); ?>"
            class="cmp-profile-section com-users-profile__custom users-profile-custom-<?php echo $this->escape($safeGroup); ?>"
            aria-labelledby="<?php echo $this->escape($headingId); ?>"
        >
            <?php if (isset($fieldset->label) && ($legend = trim(Text::_($fieldset->label))) !== '') : ?>
                <h2 id="<?php echo $this->escape($headingId); ?>"><?php echo $legend; ?></h2>
            <?php else : ?>
                <h2 id="<?php echo $this->escape($headingId); ?>" class="visually-hidden">
                    <?php echo Text::_('COM_USERS_PROFILE_CORE_LEGEND'); ?>
                </h2>
            <?php endif; ?>
            <?php if (isset($fieldset->description) && trim($fieldset->description)) : ?>
                <p class="cmp-profile-section__description">
                    <?php echo $this->escape(Text::_($fieldset->description)); ?>
                </p>
            <?php endif; ?>
            <dl class="cmp-profile-details">
                <?php foreach ($fields as $field) : ?>
                    <?php if ($field->type === 'Subform' && $field->fieldname === 'row') : ?>
                        <?php preg_match("/jform\[com_fields]\[(.*)]/", $field->name, $matches); ?>
                        <?php $field->fieldname = $matches[1] ?? $field->fieldname; ?>
                    <?php endif; ?>
                    <?php if (!$field->hidden && $field->type !== 'Spacer') : ?>
                        <div>
                            <dt><?php echo $field->title; ?></dt>
                            <dd>
                                <?php if (array_key_exists($field->fieldname, $customFields)) : ?>
                                    <?php echo \strlen($customFields[$field->fieldname]->value) ? $customFields[$field->fieldname]->value : Text::_('COM_USERS_PROFILE_VALUE_NOT_FOUND'); ?>
                                <?php elseif (HTMLHelper::isRegistered('users.' . $field->id)) : ?>
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
<?php endforeach; ?>
