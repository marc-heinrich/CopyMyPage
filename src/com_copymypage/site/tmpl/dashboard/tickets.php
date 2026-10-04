<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.20
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/** @var \Joomla\Component\CopyMyPage\Site\View\Dashboard\HtmlView $this */

$escape = static fn(mixed $value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$activeBookings = [];
$pastBookings   = [];

foreach ($this->tickets as $booking) {
    if ($booking['isPast'] === true) {
        $pastBookings[] = $booking;
    } else {
        $activeBookings[] = $booking;
    }
}

$bookingGroups = [$activeBookings, $pastBookings];
?>
<div class="cmp-dashboard cmp-dashboard--tickets">
    <header class="cmp-dashboard__page-header">
        <div>
            <h1 class="cmp-dashboard__page-title">
                <?php echo $escape(Text::_('COM_COPYMYPAGE_DASHBOARD_TICKETS_TITLE')); ?>
            </h1>
            <p class="cmp-dashboard__page-lead">
                <?php echo $escape(Text::_('COM_COPYMYPAGE_DASHBOARD_TICKETS_LEAD')); ?>
            </p>
        </div>
    </header>

    <?php echo LayoutHelper::render('copymypage.dashboard.navigation', $this->accountMenu); ?>

    <div class="cmp-dashboard__content">
        <?php if ($this->tickets === []) : ?>
            <section class="cmp-dashboard-tickets-empty" aria-labelledby="cmp-dashboard-tickets-empty-title">
                <div>
                    <h2 id="cmp-dashboard-tickets-empty-title">
                        <?php echo $escape(Text::_('COM_COPYMYPAGE_DASHBOARD_TICKETS_EMPTY_TITLE')); ?>
                    </h2>
                    <p><?php echo $escape(Text::_('COM_COPYMYPAGE_DASHBOARD_TICKETS_EMPTY_TEXT')); ?></p>
                </div>
            </section>
        <?php else : ?>
            <div class="cmp-dashboard-bookings">
                <ul
                    class="uk-tab cmp-dashboard-bookings__tabs"
                    uk-tab="connect: #cmp-dashboard-bookings-panes; swiping: false"
                >
                    <li class="uk-active">
                        <a href="#"><?php echo $escape(Text::_('COM_COPYMYPAGE_DASHBOARD_TICKETS_ACTIVE')); ?></a>
                    </li>
                    <li>
                        <a href="#"><?php echo $escape(Text::_('COM_COPYMYPAGE_DASHBOARD_TICKETS_PAST')); ?></a>
                    </li>
                </ul>

                <ul id="cmp-dashboard-bookings-panes" class="uk-switcher cmp-dashboard-bookings__panes">
                    <?php foreach ($bookingGroups as $groupIndex => $bookings) : ?>
                        <li class="<?php echo $groupIndex === 0 ? 'uk-active' : ''; ?>">
                            <?php if ($bookings === []) : ?>
                                <p class="cmp-dashboard-bookings__pane-empty">
                                    <?php echo $escape(Text::_($groupIndex === 0
                                        ? 'COM_COPYMYPAGE_DASHBOARD_TICKETS_ACTIVE_EMPTY'
                                        : 'COM_COPYMYPAGE_DASHBOARD_TICKETS_PAST_EMPTY')); ?>
                                </p>
                            <?php else : ?>
                                <ul
                                    class="uk-accordion-default cmp-dashboard-bookings__accordion"
                                    uk-accordion="collapsible: true; multiple: true"
                                >
                                    <?php foreach ($bookings as $booking) : ?>
                                        <?php
                                        $events      = array_values((array) ($booking['events'] ?? []));
                                        $eventCount  = count($events);
                                        $bookedAt    = (string) ($booking['bookedAt'] ?? '');
                                        $total       = (string) ($booking['total'] ?? '');
                                        $currency    = (string) ($booking['currency'] ?? '');
                                        $hasTotal    = $total !== '' && $currency !== '';
                                        $summaryDate = '';

                                        if ($eventCount === 1) {
                                            $summary = trim((string) ($events[0]['title'] ?? ''));
                                            $summary = $summary !== ''
                                                ? $summary
                                                : Text::_('COM_COPYMYPAGE_DASHBOARD_TICKETS_EVENT_UNAVAILABLE');

                                            if (
                                                ($events[0]['dateKnown'] ?? false) === true
                                                && !empty($events[0]['startsAt'])
                                                && !empty($events[0]['endsAt'])
                                            ) {
                                                $summaryDate = (string) $events[0]['startsAt'];
                                            }
                                        } elseif ($eventCount > 1) {
                                            $summary = Text::sprintf(
                                                'COM_COPYMYPAGE_DASHBOARD_TICKETS_EVENTS_SUMMARY',
                                                $eventCount
                                            );
                                        } else {
                                            $summary = Text::_('COM_COPYMYPAGE_DASHBOARD_TICKETS_EVENT_UNAVAILABLE');
                                        }
                                        ?>
                                        <li class="cmp-dashboard-booking">
                                            <a class="uk-accordion-title cmp-dashboard-booking__toggle" href="#">
                                                <div class="cmp-dashboard-booking__summary">
                                                    <h2 class="cmp-dashboard-booking__title">
                                                        <?php echo $escape($summary); ?>
                                                    </h2>
                                                    <?php if ($summaryDate !== '' || $hasTotal) : ?>
                                                        <span class="cmp-dashboard-booking__meta">
                                                            <?php if ($summaryDate !== '') : ?>
                                                                <time datetime="<?php echo $escape($summaryDate); ?>">
                                                                    <?php echo $escape(HTMLHelper::_(
                                                                        'date',
                                                                        $summaryDate,
                                                                        Text::_('DATE_FORMAT_LC1'),
                                                                        null
                                                                    )); ?>
                                                                </time>
                                                            <?php endif; ?>
                                                            <?php if ($hasTotal) : ?>
                                                                <span><?php echo $escape($total . ' ' . $currency); ?></span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <span
                                                    class="cmp-dashboard-booking__chevron"
                                                    uk-icon="icon: chevron-down"
                                                    aria-hidden="true"
                                                ></span>
                                            </a>

                                            <div class="uk-accordion-content cmp-dashboard-booking__content">
                                                <div class="cmp-dashboard-booking__body">
                                                    <?php if ($bookedAt !== '' || $hasTotal) : ?>
                                                        <dl class="cmp-dashboard-booking__details">
                                                            <?php if ($bookedAt !== '') : ?>
                                                                <div>
                                                                    <dt><?php echo $escape(Text::_('COM_COPYMYPAGE_DASHBOARD_TICKETS_BOOKED_ON')); ?></dt>
                                                                    <dd>
                                                                        <time datetime="<?php echo $escape($bookedAt); ?>">
                                                                            <?php echo $escape(HTMLHelper::_(
                                                                                'date',
                                                                                $bookedAt,
                                                                                Text::_('DATE_FORMAT_LC1'),
                                                                                null
                                                                            )); ?>
                                                                        </time>
                                                                    </dd>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if ($hasTotal) : ?>
                                                                <div>
                                                                    <dt><?php echo $escape(Text::_('COM_COPYMYPAGE_DASHBOARD_TICKETS_TOTAL')); ?></dt>
                                                                    <dd><?php echo $escape($total . ' ' . $currency); ?></dd>
                                                                </div>
                                                            <?php endif; ?>
                                                        </dl>
                                                    <?php endif; ?>

                                                    <?php if ($events === []) : ?>
                                                        <p class="cmp-dashboard-booking__unavailable">
                                                            <?php echo $escape(Text::_('COM_COPYMYPAGE_DASHBOARD_TICKETS_EVENT_UNAVAILABLE')); ?>
                                                        </p>
                                                    <?php else : ?>
                                                        <?php foreach ($events as $event) : ?>
                                                            <?php
                                                            $eventDate = (string) ($event['startsAt'] ?? '');
                                                            $dateKnown = ($event['dateKnown'] ?? false) === true
                                                                && $eventDate !== ''
                                                                && (string) ($event['endsAt'] ?? '') !== '';
                                                            $eventTitle = trim((string) ($event['title'] ?? ''));
                                                            $eventTitle = $eventTitle !== ''
                                                                ? $eventTitle
                                                                : Text::_('COM_COPYMYPAGE_DASHBOARD_TICKETS_EVENT_UNAVAILABLE');
                                                            ?>
                                                            <section class="cmp-dashboard-booking__event">
                                                                <h3><?php echo $escape($eventTitle); ?></h3>
                                                                <?php if ($dateKnown) : ?>
                                                                    <p class="cmp-dashboard-booking__event-date">
                                                                        <time datetime="<?php echo $escape($eventDate); ?>">
                                                                            <?php echo $escape(HTMLHelper::_(
                                                                                'date',
                                                                                $eventDate,
                                                                                Text::_('DATE_FORMAT_LC' . (!empty($event['allDay']) ? '1' : '2')),
                                                                                null
                                                                            )); ?>
                                                                        </time>
                                                                    </p>
                                                                <?php else : ?>
                                                                    <p class="cmp-dashboard-booking__event-date">
                                                                        <?php echo $escape(Text::_('COM_COPYMYPAGE_DASHBOARD_TICKETS_DATE_UNAVAILABLE')); ?>
                                                                    </p>
                                                                <?php endif; ?>

                                                                <ul class="cmp-dashboard-booking__tickets">
                                                                    <?php foreach ((array) ($event['tickets'] ?? []) as $ticketIndex => $ticket) : ?>
                                                                        <li class="cmp-dashboard-booking__ticket">
                                                                            <span class="cmp-dashboard-booking__ticket-copy">
                                                                                <strong><?php echo $escape($ticket['typeLabel'] ?? ''); ?></strong>
                                                                                <?php if (trim((string) ($ticket['seatLabel'] ?? '')) !== '') : ?>
                                                                                    <span>
                                                                                        <?php echo $escape(Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_SEAT_LABEL')); ?>:
                                                                                        <?php echo $escape($ticket['seatLabel']); ?>
                                                                                    </span>
                                                                                <?php endif; ?>
                                                                            </span>
                                                                            <?php if ((string) ($ticket['downloadUrl'] ?? '') !== '') : ?>
                                                                                <a
                                                                                    class="uk-button uk-button-default cmp-button cmp-button--secondary"
                                                                                    href="<?php echo $escape($ticket['downloadUrl']); ?>"
                                                                                    aria-label="<?php echo $escape(Text::sprintf(
                                                                                        'COM_COPYMYPAGE_DASHBOARD_TICKETS_DOWNLOAD_ARIA',
                                                                                        (int) $ticketIndex + 1,
                                                                                        $eventTitle
                                                                                    )); ?>"
                                                                                >
                                                                                    <span uk-icon="icon: download" aria-hidden="true"></span>
                                                                                    <?php echo $escape(Text::_('COM_COPYMYPAGE_DASHBOARD_TICKETS_ACTION_DOWNLOAD')); ?>
                                                                                </a>
                                                                            <?php endif; ?>
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            </section>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
