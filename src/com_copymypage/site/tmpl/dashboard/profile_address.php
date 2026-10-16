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

$address = $this->profileAddress;
$street  = trim(implode(' ', [
    trim((string) ($address['street'] ?? '')),
    trim((string) ($address['house_number'] ?? '')),
]));
$city = trim(implode(' ', [
    trim((string) ($address['postcode'] ?? '')),
    trim((string) ($address['city'] ?? '')),
]));
$addressSummary = implode(' · ', array_filter(
    [$street, $city],
    static fn(string $value): bool => $value !== ''
));

if (empty($address['exists']) || $addressSummary === '') {
    $addressSummary = Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_EMPTY');
}
?>
<li class="cmp-dashboard-link-group__item">
    <a
        class="cmp-dashboard-link-group__link"
        href="<?php echo $this->escape($this->profileAddressUrl); ?>"
    >
        <span class="cmp-dashboard-link-group__icon" aria-hidden="true">
            <span uk-icon="icon: location"></span>
        </span>
        <span class="cmp-dashboard-link-group__copy">
            <span class="cmp-dashboard-link-group__label">
                <?php echo Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_SECTION_TITLE'); ?>
            </span>
            <span class="cmp-dashboard-link-group__description">
                <?php echo $this->escape($addressSummary); ?>
            </span>
        </span>
        <span class="cmp-dashboard-link-group__chevron" aria-hidden="true">
            <span uk-icon="icon: chevron-right"></span>
        </span>
    </a>
</li>
