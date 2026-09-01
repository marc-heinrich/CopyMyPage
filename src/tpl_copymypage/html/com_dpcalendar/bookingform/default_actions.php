<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 * @see         DPCalendar 10.11.2 components/com_dpcalendar/tmpl/bookingform/default_actions.php
 */

\defined('_JEXEC') or die;

use DigitalPeak\Component\DPCalendar\Administrator\HTML\Block\Icon;

$this->translator->translateJS('COM_DPCALENDAR_VIEW_BOOKINGFORM_CONFIRM_BUTTON');
$this->translator->translateJS('COM_DPCALENDAR_VIEW_BOOKINGFORM_GO_REVIEW_BUTTON');
$this->translator->translateJS('COM_DPCALENDAR_VIEW_BOOKINGFORM_GO_CONFIRM_BUTTON');
$this->translator->translateJS('COM_DPCALENDAR_VIEW_BOOKINGFORM_GO_CONFIRM_PAYMENT_BUTTON');

$buttonText = $this->bookingId ? 'JSAVE' : 'COM_DPCALENDAR_VIEW_BOOKINGFORM_GO_REVIEW_BUTTON';

if (
    $this->event->capacity !== null
    && $this->event->capacity_used >= $this->event->capacity
    && $this->event->booking_waiting_list
    && (is_countable($this->events) ? count($this->events) : 0 === 1 || $this->event->booking_series != 2)
    && !$this->bookingId
) {
    $buttonText = 'COM_DPCALENDAR_VIEW_BOOKINGFORM_WAITING_BUTTON';
}

// Ensure that tickets already assigned to the waiting list keep the waiting action.
if ((int) $this->event->waiting_list_count > 0) {
    $buttonText = 'COM_DPCALENDAR_VIEW_BOOKINGFORM_WAITING_BUTTON';
}
?>
<div class="com-dpcalendar-bookingform__actions cmp-form__actions cmp-dpcalendar-bookingform__actions dp-button-bar">
    <button
        type="button"
        class="dp-button dp-button-action dp-button-save uk-button uk-button-primary cmp-button cmp-button--primary"
        data-task="save"
        data-waiting="<?php echo $buttonText === 'COM_DPCALENDAR_VIEW_BOOKINGFORM_WAITING_BUTTON' ? 1 : 0; ?>"
        data-review="<?php echo $this->bookingId ? '' : $this->params->get('booking_review_step', 2); ?>"
        data-confirm="<?php echo $this->bookingId ? '' : $this->params->get('booking_confirm_step', 1); ?>"
        aria-controls="cmp-dpcalendar-bookingform"
    >
        <span aria-hidden="true">
            <?php echo $this->layoutHelper->renderLayout('block.icon', ['icon' => Icon::NEXT]); ?>
        </span>
        <span class="dp-button-save__text"><?php echo $this->translate($buttonText); ?></span>
    </button>

    <button
        type="button"
        class="dp-button dp-button-action dp-button-cancel uk-button uk-button-default cmp-button cmp-button--secondary"
        data-task="cancel"
        aria-controls="cmp-dpcalendar-bookingform"
    >
        <span aria-hidden="true">
            <?php echo $this->layoutHelper->renderLayout('block.icon', ['icon' => Icon::CANCEL]); ?>
        </span>
        <?php echo $this->translate('COM_DPCALENDAR_VIEW_BOOKINGFORM_' . ($this->bookingId ? 'CANCEL' : 'ABORT') . '_BUTTON'); ?>
    </button>
</div>
