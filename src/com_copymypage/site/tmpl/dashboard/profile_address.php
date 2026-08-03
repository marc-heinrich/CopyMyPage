<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var \Joomla\Component\CopyMyPage\Site\View\Dashboard\HtmlView $this */

$address    = $this->profileAddress;
$hasAddress = (bool) ($address['exists'] ?? false);
$actionText = $hasAddress
    ? Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_ACTION_EDIT')
    : Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_ACTION_ADD');
$fields = [
    'street'       => 'COM_COPYMYPAGE_PROFILE_ADDRESS_FIELD_STREET',
    'house_number' => 'COM_COPYMYPAGE_PROFILE_ADDRESS_FIELD_HOUSE_NUMBER',
    'postcode'     => 'COM_CONTACT_FIELD_INFORMATION_POSTCODE_LABEL',
    'city'         => 'COM_CONTACT_FIELD_INFORMATION_SUBURB_LABEL',
    'country'      => 'COM_COPYMYPAGE_PROFILE_ADDRESS_FIELD_COUNTRY',
    'region'       => 'COM_COPYMYPAGE_PROFILE_ADDRESS_FIELD_REGION',
];
?>
<section
    id="cmp-profile-address"
    class="cmp-profile-section cmp-profile-address"
    aria-labelledby="cmp-profile-address-title"
>
    <div class="cmp-profile-section__header">
        <h2 id="cmp-profile-address-title">
            <?php echo Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_SECTION_TITLE'); ?>
        </h2>
        <a
            class="cmp-dashboard-row-action cmp-profile-section__edit"
            href="<?php echo $this->escape($this->profileAddressUrl); ?>"
            aria-label="<?php echo $this->escape($actionText); ?>"
        >
            <span class="cmp-profile-section__edit-label"><?php echo $actionText; ?></span>
            <span
                class="cmp-profile-section__edit-icon"
                uk-icon="icon: chevron-right"
                aria-hidden="true"
            ></span>
        </a>
    </div>

    <?php if (!$hasAddress) : ?>
        <p class="cmp-profile-section__description">
            <?php echo Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_EMPTY'); ?>
        </p>
    <?php else : ?>
        <dl class="cmp-profile-details">
            <?php foreach ($fields as $fieldName => $label) : ?>
                <?php if (trim((string) ($address[$fieldName] ?? '')) === '') : ?>
                    <?php continue; ?>
                <?php endif; ?>
                <div>
                    <dt><?php echo Text::_($label); ?></dt>
                    <dd><?php echo $this->escape($address[$fieldName] ?? ''); ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>
</section>
