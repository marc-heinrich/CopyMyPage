<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 * @see         DPCalendar 10.11.2 components/com_dpcalendar/tmpl/bookingform/default_heading.php
 */

\defined('_JEXEC') or die;

$heading = '';

if ($this->params->get('show_page_heading')) {
    $heading = trim((string) $this->params->get('page_heading'));
} elseif (!empty($this->event->title)) {
    $heading = sprintf(
        $this->translate('COM_DPCALENDAR_VIEW_BOOKINGFORM_BOOK_EVENT'),
        (string) $this->event->title
    );
}

if ($heading === '') {
    return;
}
?>
<header class="com-dpcalendar-bookingform__heading cmp-dpcalendar-bookingform__heading">
    <h1 class="dp-page-heading page-header cmp-dpcalendar-bookingform__title">
        <?php echo $this->escape($heading); ?>
    </h1>
</header>
