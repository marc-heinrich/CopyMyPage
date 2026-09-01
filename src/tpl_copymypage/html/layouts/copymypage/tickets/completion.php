<?php
/**
 * @package     Joomla.Site
 * @subpackage  Layouts.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

$escape = static fn(mixed $value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$state          = \is_array($displayData['state'] ?? null) ? $displayData['state'] : [];
$bookingUid     = trim((string) ($state['bookingUid'] ?? ''));
$completed      = !empty($state['completed']);
$events         = \is_array($state['events'] ?? null) ? $state['events'] : [];
$icon           = (string) ($state['icon'] ?? 'warning');
$integrityOk    = !empty($state['integrityOk']);
$managed        = !empty($state['managed']);
$paymentAction  = trim((string) ($displayData['paymentAction'] ?? ''));
$paymentHandoff = trim((string) ($displayData['paymentHandoff'] ?? ''));
$showBack       = !empty($displayData['showBack']);
$showRefresh    = !empty($displayData['showRefresh']);
$showResume     = !empty($displayData['showResume'])
    && $paymentAction !== ''
    && preg_match('/^[a-f0-9]{64}$/D', $paymentHandoff) === 1;
$showSteps      = !empty($displayData['showSteps']);
$tone           = (string) ($state['tone'] ?? 'danger');

if (!\in_array($tone, ['danger', 'info', 'success', 'warning'], true)) {
    $tone = 'danger';
}

if (!\in_array($icon, ['check', 'clock', 'close', 'refresh', 'reply', 'warning'], true)) {
    $icon = 'warning';
}
?>
<div class="cmp-booking-completion">
    <div class="uk-container cmp-booking-completion__container">
        <?php if ($showSteps) : ?>
            <?php echo LayoutHelper::render(
                'copymypage.tickets.steps',
                [
                    'activeStep' => 5,
                    'totalSteps' => 5,
                ]
            ); ?>
        <?php endif; ?>

        <section class="cmp-booking-completion__panel" aria-labelledby="cmp-booking-completion-title">
            <header class="cmp-booking-completion__status cmp-booking-completion__status--<?php echo $escape($tone); ?>">
                <span
                    class="cmp-booking-completion__status-icon"
                    uk-icon="icon: <?php echo $escape($icon); ?>; ratio: 1.4"
                    aria-hidden="true"
                ></span>
                <div>
                    <p class="cmp-booking-completion__eyebrow">
                        <?php echo $escape(Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_LABEL')); ?>
                    </p>
                    <h1 id="cmp-booking-completion-title">
                        <?php echo $escape(Text::_((string) ($state['titleKey'] ?? 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_UNKNOWN_TITLE'))); ?>
                    </h1>
                    <p><?php echo $escape(Text::_((string) ($state['introKey'] ?? 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_UNKNOWN_INTRO'))); ?></p>
                </div>
            </header>

            <?php if ($bookingUid !== '') : ?>
                <dl class="cmp-booking-completion__booking-data">
                    <div>
                        <dt><?php echo $escape(Text::_('COM_DPCALENDAR_BOOKING_FIELD_ID_LABEL')); ?></dt>
                        <dd><?php echo $escape($bookingUid); ?></dd>
                    </div>
                </dl>
            <?php endif; ?>

            <?php if ($managed && !$integrityOk) : ?>
                <div class="cmp-booking-completion__integrity-warning" role="alert">
                    <strong><?php echo $escape(Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_DATA_ERROR_TITLE')); ?></strong>
                    <p><?php echo $escape(Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_DATA_ERROR')); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($events !== []) : ?>
                <section class="cmp-booking-completion__tickets" aria-labelledby="cmp-booking-completion-tickets-title">
                    <h2 id="cmp-booking-completion-tickets-title">
                        <?php echo $escape(Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_TICKETS_TITLE')); ?>
                    </h2>
                    <ul class="cmp-booking-completion__events">
                        <?php foreach ($events as $event) : ?>
                            <li class="cmp-booking-completion__event">
                                <h3><?php echo $escape($event['title'] ?? ''); ?></h3>
                                <ul class="cmp-booking-completion__ticket-list">
                                    <?php foreach ((array) ($event['tickets'] ?? []) as $ticket) : ?>
                                        <li class="cmp-booking-completion__ticket">
                                            <span class="cmp-booking-completion__ticket-copy">
                                                <strong><?php echo $escape($ticket['typeLabel'] ?? ''); ?></strong>
                                                <?php if ((string) ($ticket['seatLabel'] ?? '') !== '') : ?>
                                                    <span>
                                                        <?php echo $escape(Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_SEAT_LABEL')); ?>:
                                                        <?php echo $escape($ticket['seatLabel']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </span>

                                            <?php if ($completed && (string) ($ticket['url'] ?? '') !== '') : ?>
                                                <a
                                                    class="uk-button uk-button-default cmp-button cmp-button--secondary"
                                                    href="<?php echo $escape($ticket['url']); ?>"
                                                >
                                                    <?php echo $escape(Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_ACTION_TICKET')); ?>
                                                </a>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if ($showResume) : ?>
                <form
                    class="cmp-form cmp-booking-completion__resume-form"
                    action="<?php echo $escape($paymentAction); ?>"
                    method="post"
                >
                    <input
                        type="hidden"
                        name="cmp_payment_handoff"
                        value="<?php echo $escape($paymentHandoff); ?>"
                    >
                    <div class="cmp-form__actions cmp-booking-completion__actions">
                        <button
                            class="uk-button uk-button-primary cmp-button cmp-button--primary"
                            type="submit"
                        >
                            <?php echo $escape(Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_ACTION_RESUME')); ?>
                        </button>
                    </div>
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>
            <?php endif; ?>

            <div class="cmp-booking-completion__actions">
                <?php if ($completed && (string) ($displayData['invoiceUrl'] ?? '') !== '') : ?>
                    <a
                        class="uk-button uk-button-default cmp-button cmp-button--secondary"
                        href="<?php echo $escape($displayData['invoiceUrl']); ?>"
                    >
                        <?php echo $escape(Text::_('COM_DPCALENDAR_INVOICE')); ?>
                    </a>
                <?php endif; ?>

                <?php if ($completed && (string) ($displayData['receiptUrl'] ?? '') !== '') : ?>
                    <a
                        class="uk-button uk-button-default cmp-button cmp-button--secondary"
                        href="<?php echo $escape($displayData['receiptUrl']); ?>"
                    >
                        <?php echo $escape(Text::_('COM_DPCALENDAR_RECEIPT')); ?>
                    </a>
                <?php endif; ?>

                <?php if ($showRefresh && (string) ($displayData['refreshUrl'] ?? '') !== '') : ?>
                    <a
                        class="uk-button uk-button-default cmp-button cmp-button--secondary"
                        href="<?php echo $escape($displayData['refreshUrl']); ?>"
                    >
                        <?php echo $escape(Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_ACTION_REFRESH')); ?>
                    </a>
                <?php endif; ?>

                <?php if ($showBack && (string) ($displayData['selectionUrl'] ?? '') !== '') : ?>
                    <a
                        class="uk-button uk-button-default cmp-button cmp-button--secondary cmp-button--back"
                        href="<?php echo $escape($displayData['selectionUrl']); ?>"
                    >
                        <span uk-icon="icon: chevron-left" aria-hidden="true"></span>
                        <?php echo $escape(Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_ACTION_BACK')); ?>
                    </a>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
