<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 * @see         DPCalendar 10.11.2 components/com_dpcalendar/tmpl/bookingform/default.php
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

$this->dpdocument->loadStyleFile('dpcalendar/views/bookingform/default.css');
$this->dpdocument->loadScriptFile('views/bookingform/default.js');
$this->dpdocument->addStyle($this->params->get('booking_form_custom_css', ''));

$this->dpdocument->addScriptOptions(
    'price.url',
    'task=booking.calculateprice&e_id=' .
    (empty($this->event) ? 0 : $this->event->id) . '&b_id=' . (int) $this->bookingId
);
$this->translator->translateJS('COM_DPCALENDAR_OPTIONS');
$this->translator->translateJS('COM_DPCALENDAR_VIEW_BOOKING_ABORT_CONFIRM');
$this->translator->translateJS('COM_DPCALENDAR_VIEW_BOOKINGFORM_EMAILS_NOT_MATCHING_MESSAGE');
?>
<div class="com-dpcalendar-bookingform cmp-dpcalendar-bookingform<?php echo $this->pageclass_sfx ? ' com-dpcalendar-bookingform-' . $this->pageclass_sfx : ''; ?>">
    <div class="uk-container cmp-dpcalendar-bookingform__container">
        <?php echo $this->loadTemplate('heading'); ?>

        <div class="cmp-dpcalendar-bookingform__panel">
            <?php echo $this->layoutHelper->renderLayout('block.timezone', $this->displayData); ?>

            <?php if ($this->needsPayment) : ?>
                <?php echo $this->layoutHelper->renderLayout('block.currency', $this->displayData); ?>
            <?php endif; ?>

            <form
                id="cmp-dpcalendar-bookingform"
                class="com-dpcalendar-bookingform__form cmp-form cmp-dpcalendar-bookingform__form dp-form form-validate"
                method="post"
                name="adminForm"
                action="<?php echo $this->router->route('index.php?option=com_dpcalendar&view=bookingform&b_id=' . (int) $this->bookingId . $this->tmpl); ?>"
            >
                <?php echo $this->layoutHelper->renderLayout('block.loader', $this->displayData); ?>
                <?php echo $this->loadTemplate('steps'); ?>
                <?php echo $this->loadTemplate('existing_booking'); ?>
                <?php echo $this->loadTemplate('info_text'); ?>

                <fieldset class="uk-fieldset cmp-dpcalendar-bookingform__fieldset cmp-dpcalendar-bookingform__fieldset--tickets">
                    <legend class="cmp-dpcalendar-bookingform__legend">
                        <?php echo $this->translate('COM_DPCALENDAR_VIEW_BOOKINGFORM_CHOOSE_TICKETS'); ?>
                    </legend>

                    <?php echo $this->loadTemplate('info_text_events_discount'); ?>
                    <?php echo $this->loadTemplate('info_text_tickets_discount'); ?>
                    <?php echo $this->loadTemplate('series_info'); ?>
                    <?php echo $this->loadTemplate('events'); ?>
                    <?php echo $this->loadTemplate('total_events'); ?>
                    <?php echo $this->loadTemplate('total_coupon'); ?>
                    <?php echo $this->loadTemplate('total_events_discount'); ?>
                    <?php echo $this->loadTemplate('total_tickets_discount'); ?>
                    <?php echo $this->loadTemplate('total_user_group_discount'); ?>
                    <?php echo $this->loadTemplate('total_earlybird_discount'); ?>
                    <?php echo $this->loadTemplate('total_tax'); ?>
                    <?php echo $this->loadTemplate('total'); ?>
                </fieldset>

                <fieldset class="uk-fieldset cmp-dpcalendar-bookingform__fieldset cmp-dpcalendar-bookingform__fieldset--details">
                    <legend class="cmp-dpcalendar-bookingform__legend">
                        <?php echo $this->translate('COM_DPCALENDAR_VIEW_EVENT_BOOKING_INFORMATION'); ?>
                    </legend>

                    <?php echo $this->loadTemplate('fields'); ?>
                </fieldset>

                <input type="hidden" name="task" class="dp-input dp-input-hidden">
                <input type="hidden" name="return" value="<?php echo $this->returnPage; ?>" class="dp-input dp-input-hidden">
                <input type="hidden" name="tmpl" value="<?php echo $this->input->get('tmpl'); ?>" class="dp-input dp-input-hidden">
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>

            <?php echo $this->loadTemplate('actions'); ?>
        </div>
    </div>
</div>
