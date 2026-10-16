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

$personalDetails = implode(' · ', array_filter(
    [
        trim((string) $this->data->name),
        trim((string) $this->data->email),
        Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_SECTION_TITLE'),
    ],
    static fn(string $value): bool => $value !== ''
));
?>
<li class="cmp-dashboard-link-group__item">
    <a
        class="cmp-dashboard-link-group__link"
        href="<?php echo $this->escape($this->profileEditUrl); ?>"
    >
        <span class="cmp-dashboard-link-group__icon" aria-hidden="true">
            <span uk-icon="icon: user"></span>
        </span>
        <span class="cmp-dashboard-link-group__copy">
            <span class="cmp-dashboard-link-group__label">
                <?php echo Text::_('COM_COPYMYPAGE_DASHBOARD_OVERVIEW_PERSONAL_DETAILS'); ?>
            </span>
            <span class="cmp-dashboard-link-group__description">
                <?php echo $this->escape($personalDetails); ?>
            </span>
        </span>
        <span class="cmp-dashboard-link-group__chevron" aria-hidden="true">
            <span uk-icon="icon: chevron-right"></span>
        </span>
    </a>
</li>
