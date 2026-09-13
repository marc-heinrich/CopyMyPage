<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.20
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/** @var \Joomla\Component\CopyMyPage\Site\View\Dashboard\HtmlView $this */

$escape = static fn(mixed $value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
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
                <span
                    class="cmp-dashboard-tickets-empty__icon"
                    uk-icon="icon: tag; ratio: 1.35"
                    aria-hidden="true"
                ></span>
                <div>
                    <h2 id="cmp-dashboard-tickets-empty-title">
                        <?php echo $escape(Text::_('COM_COPYMYPAGE_DASHBOARD_TICKETS_EMPTY_TITLE')); ?>
                    </h2>
                    <p><?php echo $escape(Text::_('COM_COPYMYPAGE_DASHBOARD_TICKETS_EMPTY_TEXT')); ?></p>
                </div>
            </section>
        <?php else : ?>
            <div class="cmp-dashboard-tickets">
                <?php foreach ($this->tickets as $eventIndex => $event) : ?>
                    <?php $eventTitleId = 'cmp-dashboard-ticket-event-' . (int) $eventIndex; ?>
                    <section class="cmp-dashboard-ticket-event" aria-labelledby="<?php echo $eventTitleId; ?>">
                        <header class="cmp-dashboard-ticket-event__header">
                            <span
                                class="cmp-dashboard-ticket-event__icon"
                                uk-icon="icon: calendar"
                                aria-hidden="true"
                            ></span>
                            <div>
                                <h2 id="<?php echo $eventTitleId; ?>">
                                    <?php echo $escape($event['title'] ?? ''); ?>
                                </h2>
                                <?php if ((string) ($event['dateLabel'] ?? '') !== '') : ?>
                                    <time datetime="<?php echo $escape($event['dateIso'] ?? ''); ?>">
                                        <?php echo $escape($event['dateLabel']); ?>
                                    </time>
                                <?php endif; ?>
                            </div>
                        </header>

                        <ul class="cmp-dashboard-ticket-event__tickets">
                            <?php foreach ((array) ($event['tickets'] ?? []) as $ticket) : ?>
                                <li class="cmp-dashboard-ticket-row">
                                    <span class="cmp-dashboard-ticket-row__copy">
                                        <strong><?php echo $escape($ticket['typeLabel'] ?? ''); ?></strong>
                                        <?php if ((string) ($ticket['seatLabel'] ?? '') !== '') : ?>
                                            <span>
                                                <?php echo $escape(Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_SEAT_LABEL')); ?>:
                                                <?php echo $escape($ticket['seatLabel']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                    <a
                                        class="uk-button uk-button-default cmp-button cmp-button--secondary"
                                        href="<?php echo $escape($ticket['downloadUrl'] ?? ''); ?>"
                                    >
                                        <span uk-icon="icon: download" aria-hidden="true"></span>
                                        <?php echo $escape(Text::_('COM_COPYMYPAGE_DASHBOARD_TICKETS_ACTION_DOWNLOAD')); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
