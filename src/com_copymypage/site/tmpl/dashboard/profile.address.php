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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Joomla\Component\CopyMyPage\Site\View\Dashboard\HtmlView $this */

HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');

$this->getDocument()->getWebAssetManager()
    ->useScript('keepalive')
    ->useScript('form.validate')
    ->useScript('copymypage.profile.address');

$escape  = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$heading = $this->params->get('show_page_heading')
    && trim((string) $this->params->get('page_heading', '')) !== ''
    ? (string) $this->params->get('page_heading')
    : Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_EDIT_TITLE');
$form        = $this->profileAddressForm;
$street      = $form->getField('street');
$houseNumber = $form->getField('house_number');
$postcode    = $form->getField('postcode');
$city        = $form->getField('city');
$country     = $form->getField('country_code');
$region      = $form->getField('region_code');
$regionsUrl  = Route::_(
    'index.php?option=com_copymypage&task=profile.regions&format=json&'
    . Session::getFormToken()
    . '=1',
    false
);
?>
<div class="cmp-dashboard cmp-dashboard--profile-address com-users-profile__edit profile-edit">
    <header class="cmp-dashboard__page-header">
        <div>
            <h1 class="cmp-dashboard__page-title"><?php echo $escape($heading); ?></h1>
            <p class="cmp-dashboard__page-lead">
                <?php echo Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_EDIT_LEAD'); ?>
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
        <form
            id="member-profile-address"
            action="<?php echo Route::_('index.php'); ?>"
            method="post"
            class="cmp-form cmp-profile-form cmp-profile-address-form form-validate"
            data-regions-url="<?php echo $escape($regionsUrl); ?>"
            data-region-placeholder="<?php echo $escape(Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_SELECT_REGION')); ?>"
            data-region-empty="<?php echo $escape(Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_REGION_EMPTY')); ?>"
            data-region-error="<?php echo $escape(Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_REGION_ERROR')); ?>"
        >
            <fieldset class="uk-fieldset cmp-profile-form__section cmp-profile-form__panel">
                <legend><?php echo Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_SECTION_TITLE'); ?></legend>
                <p class="cmp-profile-section__description">
                    <?php echo Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_SECTION_DESCRIPTION'); ?>
                </p>

                <div class="cmp-profile-form__fields">
                    <div class="uk-grid-small cmp-profile-address-form__row" uk-grid>
                        <div class="uk-width-1-1 uk-width-expand@s">
                            <?php echo $street->renderField(); ?>
                        </div>
                        <div class="uk-width-1-1 uk-width-1-4@s">
                            <?php echo $houseNumber->renderField(); ?>
                        </div>
                    </div>

                    <div class="uk-grid-small uk-child-width-1-2@s cmp-profile-address-form__row" uk-grid>
                        <div><?php echo $postcode->renderField(); ?></div>
                        <div><?php echo $city->renderField(); ?></div>
                    </div>

                    <div class="uk-grid-small uk-child-width-1-2@s cmp-profile-address-form__row" uk-grid>
                        <div><?php echo $country->renderField(); ?></div>
                        <div><?php echo $region->renderField(); ?></div>
                    </div>
                </div>
            </fieldset>

            <div class="cmp-form__actions cmp-profile-form__actions">
                <button
                    type="submit"
                    class="uk-button uk-button-primary cmp-button cmp-button--primary validate"
                    name="task"
                    value="profile.saveAddress"
                >
                    <span uk-icon="icon: check" aria-hidden="true"></span>
                    <?php echo Text::_('JSAVE'); ?>
                </button>
                <button
                    type="submit"
                    class="uk-button uk-button-default cmp-button cmp-button--secondary"
                    name="task"
                    value="profile.cancelAddress"
                    formnovalidate
                >
                    <span uk-icon="icon: close" aria-hidden="true"></span>
                    <?php echo Text::_('JCANCEL'); ?>
                </button>
                <input type="hidden" name="option" value="com_copymypage">
                <input type="hidden" name="view" value="dashboard">
                <input type="hidden" name="layout" value="profile.address">
            </div>

            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    </div>
</div>
