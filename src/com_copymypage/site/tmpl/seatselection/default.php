<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\CopyMyPage\Site\View\Seatselection\HtmlView $this */

$escape         = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$validAttribute = static function (mixed $value): string {
    $value = strtolower(trim((string) $value));

    return preg_match('/^data-[a-z0-9-]+$/', $value) ? $value : '';
};
$validFieldName = static function (mixed $value): string {
    $value = trim((string) $value);

    return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $value) ? $value : '';
};
$finiteNumber = static function (mixed $value, float $minimum, float $maximum): float {
    if (!\is_numeric($value)) {
        return $minimum;
    }

    $number = (float) $value;

    if (!\is_finite($number)) {
        return $minimum;
    }

    return min($maximum, max($minimum, $number));
};
$formatNumber = static function (float $value): string {
    $formatted = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');

    return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
};
$cssLength = static fn(mixed $value): string => $formatNumber($finiteNumber($value, 0, 10000)) . 'px';
$cssAngle  = static fn(mixed $value): string => $formatNumber($finiteNumber($value, -360, 360)) . 'deg';
$safeId    = static function (mixed $value, string $fallback): string {
    $value = preg_replace('/[^A-Za-z0-9_-]/', '-', trim((string) $value)) ?: '';

    return $value === '' ? $fallback : $value;
};

$attributes = [];

foreach ((array) ($this->markupAttributes ?? []) as $key => $attribute) {
    $attributes[$key] = $validAttribute($attribute);
}

$fieldNames   = \is_array($this->formFieldNames ?? null) ? $this->formFieldNames : [];
$seatIdsField = $validFieldName($fieldNames['seatIds'] ?? '') ?: 'seat_ids';
$revisionField = $validFieldName($fieldNames['expectedCartRevision'] ?? '')
    ?: 'expectedCartRevision';
$events       = \is_array($this->events ?? null) ? $this->events : [];
$cart         = \is_array($this->cart ?? null) ? $this->cart : [];
$cartRevision = max(0, (int) ($cart['cartRevision'] ?? 0));

$firstIncompleteEventId = 0;

foreach ($events as $event) {
    if (!\is_array($event)) {
        continue;
    }

    $candidateId = max(0, (int) ($event['id'] ?? 0));

    if ($candidateId > 0 && empty($event['complete'])) {
        $firstIncompleteEventId = $candidateId;

        break;
    }
}

$formAction  = Route::_('index.php?option=com_copymypage&view=seatselection');
$backUrl     = Route::_('index.php?option=com_copymypage&view=ticketselection');
$continueUrl = !empty($this->allComplete) ? trim((string) ($this->continueUrl ?? '')) : '';
?>
<div
    class="cmp-seat-selection"
    <?php if (($attributes['root'] ?? '') !== '') : ?>
        <?php echo $attributes['root']; ?>="1"
    <?php endif; ?>
>
    <div class="uk-container">
        <?php echo LayoutHelper::render(
            'copymypage.tickets.steps',
            [
                'activeStep' => 2,
                'totalSteps' => 5,
            ]
        ); ?>

        <header class="cmp-seat-selection__header">
            <h1 class="cmp-seat-selection__title">
                <?php echo $escape(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_TITLE')); ?>
            </h1>
            <p class="cmp-seat-selection__intro">
                <?php echo $escape(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_INTRO')); ?>
            </p>
        </header>

        <div
            class="cmp-seat-selection__live visually-hidden"
            role="status"
            aria-live="polite"
            aria-atomic="true"
            <?php if (($attributes['globalStatus'] ?? '') !== '') : ?>
                <?php echo $attributes['globalStatus']; ?>="1"
            <?php endif; ?>
        ></div>

        <?php if ($events === []) : ?>
            <div class="cmp-seat-selection__notice" role="status">
                <p><?php echo $escape(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_EMPTY')); ?></p>
            </div>
        <?php else : ?>
            <section
                class="cmp-seat-selection__events"
                aria-labelledby="cmp-seat-selection-events-title"
            >
                <h2 id="cmp-seat-selection-events-title" class="visually-hidden">
                    <?php echo $escape(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_EVENTS_TITLE')); ?>
                </h2>

                <ul
                    class="uk-accordion-default cmp-ticket-selection__accordion cmp-seat-selection__accordion"
                    uk-accordion="targets: > .cmp-seat-selection-event; collapsible: true; multiple: false"
                >
                    <?php foreach ($events as $event) : ?>
                        <?php
                        if (!\is_array($event)) {
                            continue;
                        }

                        $eventId       = max(0, (int) ($event['id'] ?? 0));
                        $eventTitle    = trim((string) ($event['title'] ?? ''));
                        $requiredCount = min(200, max(0, (int) ($event['requiredCount'] ?? 0)));
                        $selectedCount = min(200, max(0, (int) ($event['selectedCount'] ?? 0)));
                        $isComplete    = (bool) ($event['complete'] ?? false);
                        $isReady       = (bool) ($event['ready'] ?? false);
                        $isOpen        = $eventId > 0 && $eventId === $firstIncompleteEventId;
                        $eventMessage  = trim((string) ($event['message'] ?? ''));
                        $layout        = \is_array($event['layout'] ?? null) ? $event['layout'] : [];
                        $layoutTitle   = trim((string) ($layout['title'] ?? ''));
                        $layoutWidth   = $finiteNumber($layout['width'] ?? 0, 0, 10000);
                        $layoutHeight  = $finiteNumber($layout['height'] ?? 0, 0, 10000);
                        $areas         = \is_array($layout['areas'] ?? null) ? $layout['areas'] : [];
                        $tables        = \is_array($layout['tables'] ?? null) ? $layout['tables'] : [];

                        usort($tables, static function (mixed $left, mixed $right): int {
                            $leftOrder  = \is_array($left) ? (int) ($left['sortOrder'] ?? 0) : 0;
                            $rightOrder = \is_array($right) ? (int) ($right['sortOrder'] ?? 0) : 0;

                            return $leftOrder <=> $rightOrder;
                        });

                        $seatRecords    = [];
                        $seatLabelsById = [];

                        foreach ($tables as $tableIndex => $table) {
                            if (!\is_array($table)) {
                                continue;
                            }

                            $tableSeats = \is_array($table['seats'] ?? null) ? $table['seats'] : [];

                            usort($tableSeats, static function (mixed $left, mixed $right): int {
                                $leftOrder  = \is_array($left) ? (int) ($left['sortOrder'] ?? 0) : 0;
                                $rightOrder = \is_array($right) ? (int) ($right['sortOrder'] ?? 0) : 0;

                                return $leftOrder <=> $rightOrder;
                            });

                            foreach ($tableSeats as $seat) {
                                if (!\is_array($seat)) {
                                    continue;
                                }

                                $seatId = max(0, (int) ($seat['id'] ?? 0));

                                if ($seatId === 0) {
                                    continue;
                                }

                                $seatRecords[]           = ['seat' => $seat, 'tableIndex' => $tableIndex];
                                $seatLabelsById[$seatId] = trim((string) ($seat['label'] ?? ''));
                            }
                        }

                        $selectedSeatItems = [];
                        $selectedSeats     = \is_array($event['selectedSeats'] ?? null)
                            ? $event['selectedSeats']
                            : [];

                        foreach ($selectedSeats as $selectedSeat) {
                            if (!\is_array($selectedSeat)) {
                                continue;
                            }

                            $selectedSeatId = max(0, (int) ($selectedSeat['id'] ?? 0));

                            if ($selectedSeatId === 0 || isset($selectedSeatItems[$selectedSeatId])) {
                                continue;
                            }

                            $selectedSeatLabel = trim((string) ($selectedSeat['label'] ?? ''));

                            if ($selectedSeatLabel === '') {
                                $selectedSeatLabel = $seatLabelsById[$selectedSeatId] ?? '';
                            }

                            $selectedSeatItems[$selectedSeatId] = $selectedSeatLabel;
                        }

                        if ($selectedSeatItems === []) {
                            foreach ($seatRecords as $record) {
                                $seat   = $record['seat'];
                                $status = strtolower(trim((string) ($seat['status'] ?? 'unavailable')));

                                if ($status !== 'selected') {
                                    continue;
                                }

                                $selectedSeatId = max(0, (int) ($seat['id'] ?? 0));

                                if ($selectedSeatId > 0) {
                                    $selectedSeatItems[$selectedSeatId] = trim((string) ($seat['label'] ?? ''));
                                }
                            }
                        }

                        $statusKey = !$isReady
                            ? 'COM_COPYMYPAGE_SEAT_SELECTION_EVENT_UNAVAILABLE'
                            : ($isComplete
                                ? 'COM_COPYMYPAGE_SEAT_SELECTION_EVENT_COMPLETE'
                                : 'COM_COPYMYPAGE_SEAT_SELECTION_EVENT_INCOMPLETE');
                        $statusClass = !$isReady ? 'unavailable' : ($isComplete ? 'complete' : 'incomplete');
                        $titleId     = 'cmp-seat-selection-event-title-' . $eventId;
                        $contentId   = 'cmp-seat-selection-event-content-' . $eventId;
                        $formId      = 'cmp-seat-selection-form-' . $eventId;
                        $messageId   = 'cmp-seat-selection-event-message-' . $eventId;
                        $instructionId = 'cmp-seat-selection-instruction-' . $eventId;
                        $hasMap      = $layoutWidth > 0 && $layoutHeight > 0 && $tables !== [];
                        ?>
                        <li
                            class="cmp-ticket-selection-event cmp-seat-selection-event
                                cmp-seat-selection-event--<?php echo $escape($statusClass); ?><?php echo $isOpen ? ' uk-open' : ''; ?>"
                            <?php if (($attributes['event'] ?? '') !== '') : ?>
                                <?php echo $attributes['event']; ?>="1"
                            <?php endif; ?>
                            <?php if (($attributes['eventId'] ?? '') !== '') : ?>
                                <?php echo $attributes['eventId']; ?>="<?php echo $eventId; ?>"
                            <?php endif; ?>
                            <?php if (($attributes['requiredCount'] ?? '') !== '') : ?>
                                <?php echo $attributes['requiredCount']; ?>="<?php echo $requiredCount; ?>"
                            <?php endif; ?>
                        >
                            <a
                                class="uk-accordion-title cmp-ticket-selection-event__toggle
                                    cmp-seat-selection-event__toggle"
                                href="#<?php echo $escape($contentId); ?>"
                            >
                                <span class="cmp-ticket-selection-event__summary">
                                    <time
                                        class="cmp-ticket-selection-event__date"
                                        datetime="<?php echo $escape($event['dateTime'] ?? ''); ?>"
                                    >
                                        <?php echo $escape($event['dateLabel'] ?? ''); ?>
                                    </time>
                                    <span
                                        id="<?php echo $escape($titleId); ?>"
                                        class="cmp-ticket-selection-event__title"
                                    >
                                        <?php echo $escape($eventTitle); ?>
                                    </span>
                                    <span class="cmp-ticket-selection-event__availability">
                                        <span
                                            class="cmp-ticket-selection-event__status-dot
                                                cmp-seat-selection-event__status-dot--<?php echo $escape($statusClass); ?>"
                                            aria-hidden="true"
                                        ></span>
                                        <span class="cmp-seat-selection-event__progress">
                                            <span
                                                <?php if (($attributes['eventCount'] ?? '') !== '') : ?>
                                                    <?php echo $attributes['eventCount']; ?>="1"
                                                <?php endif; ?>
                                            ><?php echo $selectedCount; ?></span>
                                            <span aria-hidden="true">/</span>
                                            <span class="cmp-seat-selection-event__required-count">
                                                <?php echo $requiredCount; ?>
                                            </span>
                                            <span aria-hidden="true">·</span>
                                            <span
                                                <?php if (($attributes['eventStatus'] ?? '') !== '') : ?>
                                                    <?php echo $attributes['eventStatus']; ?>="1"
                                                <?php endif; ?>
                                            ><?php echo $escape(Text::_($statusKey)); ?></span>
                                        </span>
                                    </span>
                                </span>
                                <span
                                    class="uk-icon-button uk-icon cmp-seat-selection-event__complete-icon"
                                    uk-icon="icon: check"
                                    aria-hidden="true"
                                    <?php echo $isComplete ? '' : ' hidden'; ?>
                                ></span>
                                <span
                                    class="cmp-ticket-selection-event__icon"
                                    uk-accordion-icon
                                    aria-hidden="true"
                                ></span>
                            </a>

                            <div
                                id="<?php echo $escape($contentId); ?>"
                                class="uk-accordion-content cmp-ticket-selection-event__content
                                    cmp-seat-selection-event__content"
                            >
                                <form
                                    id="<?php echo $escape($formId); ?>"
                                    class="cmp-form cmp-seat-selection-event__form"
                                    action="<?php echo $escape($formAction); ?>"
                                    method="post"
                                    aria-labelledby="<?php echo $escape($titleId); ?>"
                                    aria-describedby="<?php echo $escape($instructionId . ' ' . $messageId); ?>"
                                    <?php if (($attributes['eventForm'] ?? '') !== '') : ?>
                                        <?php echo $attributes['eventForm']; ?>="1"
                                    <?php endif; ?>
                                >
                                    <fieldset class="uk-fieldset cmp-seat-selection-event__fieldset">
                                        <legend class="visually-hidden">
                                            <?php echo $escape(Text::sprintf(
                                                'COM_COPYMYPAGE_SEAT_SELECTION_EVENT_FORM_LEGEND',
                                                $eventTitle
                                            )); ?>
                                        </legend>

                                        <div class="cmp-seat-selection-event__lead">
                                            <p id="<?php echo $escape($instructionId); ?>">
                                                <?php echo $escape(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_EVENT_INSTRUCTION')); ?>
                                            </p>
                                            <p class="cmp-seat-selection-event__counter">
                                                <span class="cmp-seat-selection-event__counter-label">
                                                    <?php echo $escape(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_COUNTER_LABEL')); ?>
                                                </span>
                                                <strong>
                                                    <span
                                                        <?php if (($attributes['eventCount'] ?? '') !== '') : ?>
                                                            <?php echo $attributes['eventCount']; ?>="1"
                                                        <?php endif; ?>
                                                    ><?php echo $selectedCount; ?></span>
                                                    <span aria-hidden="true">/</span>
                                                    <span class="cmp-seat-selection-event__required-count">
                                                        <?php echo $requiredCount; ?>
                                                    </span>
                                                </strong>
                                                <span
                                                    class="cmp-seat-selection-event__state"
                                                    <?php if (($attributes['eventStatus'] ?? '') !== '') : ?>
                                                        <?php echo $attributes['eventStatus']; ?>="1"
                                                    <?php endif; ?>
                                                ><?php echo $escape(Text::_($statusKey)); ?></span>
                                            </p>
                                        </div>

                                        <div
                                            id="<?php echo $escape($messageId); ?>"
                                            class="cmp-seat-selection-event__message"
                                            role="alert"
                                            aria-label="<?php echo $escape(
                                                Text::_('COM_COPYMYPAGE_SEAT_SELECTION_EVENT_ERROR_LABEL')
                                            ); ?>"
                                            <?php echo $eventMessage === '' ? ' hidden' : ''; ?>
                                            <?php if (($attributes['eventMessage'] ?? '') !== '') : ?>
                                                <?php echo $attributes['eventMessage']; ?>="1"
                                            <?php endif; ?>
                                        ><?php echo $escape($eventMessage); ?></div>

                                        <div class="cmp-seat-selection-event__toolbar">
                                            <div
                                                class="cmp-seat-selection-legend"
                                                role="group"
                                                aria-label="<?php echo $escape(
                                                    Text::_('COM_COPYMYPAGE_SEAT_SELECTION_LEGEND_LABEL')
                                                ); ?>"
                                            >
                                                <span class="cmp-seat-selection-legend__item">
                                                    <span
                                                        class="cmp-seat-selection-legend__seat
                                                            cmp-seat-selection-legend__seat--available"
                                                        aria-hidden="true"
                                                    ></span>
                                                    <?php echo $escape(
                                                        Text::_('COM_COPYMYPAGE_SEAT_SELECTION_LEGEND_AVAILABLE')
                                                    ); ?>
                                                </span>
                                                <span class="cmp-seat-selection-legend__item">
                                                    <span
                                                        class="cmp-seat-selection-legend__seat
                                                            cmp-seat-selection-legend__seat--selected"
                                                        aria-hidden="true"
                                                    >✓</span>
                                                    <?php echo $escape(
                                                        Text::_('COM_COPYMYPAGE_SEAT_SELECTION_LEGEND_SELECTED')
                                                    ); ?>
                                                </span>
                                                <span class="cmp-seat-selection-legend__item">
                                                    <span
                                                        class="cmp-seat-selection-legend__seat
                                                            cmp-seat-selection-legend__seat--unavailable"
                                                        aria-hidden="true"
                                                    >×</span>
                                                    <?php echo $escape(
                                                        Text::_('COM_COPYMYPAGE_SEAT_SELECTION_LEGEND_UNAVAILABLE')
                                                    ); ?>
                                                </span>
                                            </div>

                                            <div class="cmp-seat-selection-event__tools">
                                                <button
                                                    class="uk-button uk-button-default cmp-button
                                                        cmp-button--primary-outline cmp-seat-selection-event__suggest"
                                                    type="submit"
                                                    name="task"
                                                    value="ticketseats.suggest"
                                                    formnovalidate
                                                    <?php echo !$isReady || !$hasMap ? ' disabled' : ''; ?>
                                                    <?php if (($attributes['suggest'] ?? '') !== '') : ?>
                                                        <?php echo $attributes['suggest']; ?>="1"
                                                    <?php endif; ?>
                                                >
                                                    <span uk-icon="icon: bolt" aria-hidden="true"></span>
                                                    <span><?php echo $escape(
                                                        Text::_('COM_COPYMYPAGE_SEAT_SELECTION_SUGGEST')
                                                    ); ?></span>
                                                </button>

                                                <div
                                                    class="cmp-seat-selection-zoom"
                                                    role="group"
                                                    aria-label="<?php echo $escape(
                                                        Text::_('COM_COPYMYPAGE_SEAT_SELECTION_ZOOM_CONTROLS_LABEL')
                                                    ); ?>"
                                                >
                                                    <?php
                                                    $zoomControls = [
                                                        'zoomOut' => [
                                                            'customIcon' => 'cmp-seat-selection-zoom__icon--out',
                                                            'key' => 'COM_COPYMYPAGE_SEAT_SELECTION_ZOOM_OUT',
                                                        ],
                                                        'zoomReset' => [
                                                            'icon' => 'refresh',
                                                            'key' => 'COM_COPYMYPAGE_SEAT_SELECTION_ZOOM_RESET',
                                                        ],
                                                        'zoomIn' => [
                                                            'customIcon' => 'cmp-seat-selection-zoom__icon--in',
                                                            'key' => 'COM_COPYMYPAGE_SEAT_SELECTION_ZOOM_IN',
                                                        ],
                                                    ];
                                                    ?>
                                                    <?php foreach ($zoomControls as $attributeKey => $control) : ?>
                                                        <button
                                                            class="uk-button uk-button-default cmp-button
                                                                cmp-button--secondary cmp-button--icon
                                                                cmp-seat-selection-zoom__button<?php echo isset(
                                                                    $control['customIcon']
                                                                ) ? ' cmp-seat-selection-zoom__button--magnifier' : ''; ?>"
                                                            type="button"
                                                            aria-label="<?php echo $escape(Text::_($control['key'])); ?>"
                                                            title="<?php echo $escape(Text::_($control['key'])); ?>"
                                                            <?php echo !$hasMap ? ' disabled' : ''; ?>
                                                            <?php if (($attributes[$attributeKey] ?? '') !== '') : ?>
                                                                <?php echo $attributes[$attributeKey]; ?>="1"
                                                            <?php endif; ?>
                                                        >
                                                            <?php if (isset($control['customIcon'])) : ?>
                                                                <span
                                                                    class="cmp-seat-selection-zoom__icon <?php echo $escape(
                                                                        $control['customIcon']
                                                                    ); ?>"
                                                                    aria-hidden="true"
                                                                ></span>
                                                            <?php else : ?>
                                                            <span
                                                                uk-icon="icon: <?php echo $escape($control['icon']); ?>"
                                                                aria-hidden="true"
                                                            ></span>
                                                            <?php endif; ?>
                                                        </button>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if ($tables !== []) : ?>
                                            <nav
                                                class="cmp-seat-selection-table-focus"
                                                aria-label="<?php echo $escape(
                                                    Text::_('COM_COPYMYPAGE_SEAT_SELECTION_TABLE_FOCUS_LABEL')
                                                ); ?>"
                                            >
                                                <strong class="cmp-seat-selection-table-focus__title">
                                                    <?php echo $escape(
                                                        Text::_('COM_COPYMYPAGE_SEAT_SELECTION_TABLE_FOCUS_TITLE')
                                                    ); ?>
                                                </strong>
                                                <span class="cmp-seat-selection-table-focus__rail">
                                                    <button
                                                        class="uk-icon-button cmp-seat-selection-table-focus__control
                                                            cmp-seat-selection-table-focus__control--previous"
                                                        type="button"
                                                        aria-label="<?php echo $escape(
                                                            Text::_('COM_COPYMYPAGE_SEAT_SELECTION_TABLE_FOCUS_PREVIOUS')
                                                        ); ?>"
                                                        hidden
                                                        <?php if (($attributes['tableFocusPrevious'] ?? '') !== '') : ?>
                                                            <?php echo $attributes['tableFocusPrevious']; ?>="1"
                                                        <?php endif; ?>
                                                    >
                                                        <span uk-icon="icon: chevron-left" aria-hidden="true"></span>
                                                    </button>

                                                    <span
                                                        class="cmp-seat-selection-table-focus__links"
                                                        <?php if (($attributes['tableFocusLinks'] ?? '') !== '') : ?>
                                                            <?php echo $attributes['tableFocusLinks']; ?>="1"
                                                        <?php endif; ?>
                                                    >
                                                        <?php foreach ($tables as $tableIndex => $table) : ?>
                                                            <?php
                                                            if (!\is_array($table)) {
                                                                continue;
                                                            }

                                                            $tableDomId = 'cmp-seat-selection-table-' . $eventId . '-'
                                                                . $safeId($table['id'] ?? '', (string) ($tableIndex + 1));
                                                            ?>
                                                            <a
                                                                class="cmp-seat-selection-table-focus__link"
                                                                href="#<?php echo $escape($tableDomId); ?>"
                                                                <?php if (($attributes['tableFocus'] ?? '') !== '') : ?>
                                                                    <?php echo $attributes['tableFocus']; ?>="<?php echo $escape(
                                                                        $tableDomId
                                                                    ); ?>"
                                                                <?php endif; ?>
                                                            ><?php echo $escape($table['label'] ?? ''); ?></a>
                                                        <?php endforeach; ?>
                                                    </span>

                                                    <button
                                                        class="uk-icon-button cmp-seat-selection-table-focus__control
                                                            cmp-seat-selection-table-focus__control--next"
                                                        type="button"
                                                        aria-label="<?php echo $escape(
                                                            Text::_('COM_COPYMYPAGE_SEAT_SELECTION_TABLE_FOCUS_NEXT')
                                                        ); ?>"
                                                        hidden
                                                        <?php if (($attributes['tableFocusNext'] ?? '') !== '') : ?>
                                                            <?php echo $attributes['tableFocusNext']; ?>="1"
                                                        <?php endif; ?>
                                                    >
                                                        <span uk-icon="icon: chevron-right" aria-hidden="true"></span>
                                                    </button>
                                                </span>
                                            </nav>
                                        <?php endif; ?>

                                        <?php if ($hasMap) : ?>
                                            <div class="cmp-seat-selection-map">
                                                <div
                                                    class="cmp-seat-selection-map__viewport"
                                                    tabindex="0"
                                                    role="group"
                                                    aria-label="<?php echo $escape(Text::sprintf(
                                                        'COM_COPYMYPAGE_SEAT_SELECTION_MAP_LABEL',
                                                        $layoutTitle !== '' ? $layoutTitle : $eventTitle
                                                    )); ?>"
                                                    aria-describedby="<?php echo $escape($instructionId); ?>"
                                                    <?php if (($attributes['zoomViewport'] ?? '') !== '') : ?>
                                                        <?php echo $attributes['zoomViewport']; ?>="1"
                                                    <?php endif; ?>
                                                >
                                                    <div
                                                        class="cmp-seat-selection-map__canvas-shell"
                                                        style="--cmp-seat-layout-width: <?php echo $escape(
                                                            $cssLength($layoutWidth)
                                                        ); ?>; --cmp-seat-layout-height: <?php echo $escape(
                                                            $cssLength($layoutHeight)
                                                        ); ?>;"
                                                        <?php if (($attributes['zoomCanvas'] ?? '') !== '') : ?>
                                                            <?php echo $attributes['zoomCanvas']; ?>="1"
                                                        <?php endif; ?>
                                                    >
                                                        <div class="cmp-seat-selection-map__canvas">
                                                            <?php foreach ($areas as $areaIndex => $area) : ?>
                                                                <?php
                                                                if (!\is_array($area)) {
                                                                    continue;
                                                                }

                                                                $areaType = strtolower(trim((string) ($area['type'] ?? 'landmark')));
                                                                $areaType = \in_array(
                                                                    $areaType,
                                                                    ['stage', 'aisle', 'entrance', 'exit', 'landmark'],
                                                                    true
                                                                ) ? $areaType : 'landmark';
                                                                ?>
                                                                <div
                                                                    class="cmp-seat-selection-area
                                                                        cmp-seat-selection-area--<?php echo $escape($areaType); ?>"
                                                                    style="--cmp-seat-x: <?php echo $escape(
                                                                        $cssLength($area['x'] ?? 0)
                                                                    ); ?>; --cmp-seat-y: <?php echo $escape(
                                                                        $cssLength($area['y'] ?? 0)
                                                                    ); ?>; --cmp-seat-width: <?php echo $escape(
                                                                        $cssLength($area['width'] ?? 0)
                                                                    ); ?>; --cmp-seat-height: <?php echo $escape(
                                                                        $cssLength($area['height'] ?? 0)
                                                                    ); ?>; --cmp-seat-rotation: <?php echo $escape(
                                                                        $cssAngle($area['rotation'] ?? 0)
                                                                    ); ?>;"
                                                                >
                                                                    <span><?php echo $escape($area['label'] ?? ''); ?></span>
                                                                </div>
                                                            <?php endforeach; ?>

                                                            <?php foreach ($tables as $tableIndex => $table) : ?>
                                                                <?php
                                                                if (!\is_array($table)) {
                                                                    continue;
                                                                }

                                                                $tableShape = strtolower(trim((string) ($table['shape'] ?? 'rectangle')));
                                                                $tableShape = match ($tableShape) {
                                                                    'round', 'circle' => 'round',
                                                                    'oval' => 'oval',
                                                                    default => 'rectangle',
                                                                };
                                                                $tableDomId = 'cmp-seat-selection-table-' . $eventId . '-'
                                                                    . $safeId($table['id'] ?? '', (string) ($tableIndex + 1));
                                                                ?>
                                                                <div
                                                                    id="<?php echo $escape($tableDomId); ?>"
                                                                    class="cmp-seat-selection-table
                                                                        cmp-seat-selection-table--<?php echo $escape(
                                                                            $tableShape
                                                                        ); ?>"
                                                                    style="--cmp-seat-x: <?php echo $escape(
                                                                        $cssLength($table['x'] ?? 0)
                                                                    ); ?>; --cmp-seat-y: <?php echo $escape(
                                                                        $cssLength($table['y'] ?? 0)
                                                                    ); ?>; --cmp-seat-width: <?php echo $escape(
                                                                        $cssLength($table['width'] ?? 0)
                                                                    ); ?>; --cmp-seat-height: <?php echo $escape(
                                                                        $cssLength($table['height'] ?? 0)
                                                                    ); ?>; --cmp-seat-rotation: <?php echo $escape(
                                                                        $cssAngle($table['rotation'] ?? 0)
                                                                    ); ?>;"
                                                                    tabindex="-1"
                                                                    aria-label="<?php echo $escape($table['label'] ?? ''); ?>"
                                                                >
                                                                    <span class="cmp-seat-selection-table__label">
                                                                        <?php echo $escape($table['label'] ?? ''); ?>
                                                                    </span>
                                                                </div>
                                                            <?php endforeach; ?>

                                                            <?php foreach ($seatRecords as $record) : ?>
                                                                <?php
                                                                $seat       = $record['seat'];
                                                                $seatId     = max(0, (int) ($seat['id'] ?? 0));
                                                                $seatStatus = strtolower(trim((string) ($seat['status'] ?? 'unavailable')));
                                                                $seatStatus = \in_array(
                                                                    $seatStatus,
                                                                    ['available', 'selected', 'unavailable'],
                                                                    true
                                                                ) ? $seatStatus : 'unavailable';
                                                                $isSelected   = $seatStatus === 'selected';
                                                                $isUnavailable = !$isReady || $seatStatus === 'unavailable';
                                                                $inputId = 'cmp-seat-selection-seat-' . $eventId . '-' . $seatId;
                                                                ?>
                                                                <span
                                                                    class="cmp-seat-selection-seat
                                                                        cmp-seat-selection-seat--<?php echo $escape(
                                                                            $seatStatus
                                                                        ); ?>"
                                                                    style="--cmp-seat-x: <?php echo $escape(
                                                                        $cssLength($seat['x'] ?? 0)
                                                                    ); ?>; --cmp-seat-y: <?php echo $escape(
                                                                        $cssLength($seat['y'] ?? 0)
                                                                    ); ?>;"
                                                                    <?php if (($attributes['seat'] ?? '') !== '') : ?>
                                                                        <?php echo $attributes['seat']; ?>="1"
                                                                    <?php endif; ?>
                                                                    <?php if (($attributes['seatId'] ?? '') !== '') : ?>
                                                                        <?php echo $attributes['seatId']; ?>="<?php echo $seatId; ?>"
                                                                    <?php endif; ?>
                                                                >
                                                                    <input
                                                                        id="<?php echo $escape($inputId); ?>"
                                                                        class="cmp-seat-selection-seat__input visually-hidden"
                                                                        type="checkbox"
                                                                        name="<?php echo $escape($seatIdsField); ?>[]"
                                                                        value="<?php echo $seatId; ?>"
                                                                        <?php echo $isSelected ? ' checked' : ''; ?>
                                                                        <?php echo $isUnavailable ? ' disabled' : ''; ?>
                                                                    >
                                                                    <label
                                                                        class="cmp-seat-selection-seat__label"
                                                                        for="<?php echo $escape($inputId); ?>"
                                                                        title="<?php echo $escape($seat['label'] ?? ''); ?>"
                                                                    >
                                                                        <span class="visually-hidden">
                                                                            <?php echo $escape($seat['label'] ?? ''); ?>
                                                                        </span>
                                                                        <span aria-hidden="true">
                                                                            <?php echo $escape($seat['number'] ?? ''); ?>
                                                                        </span>
                                                                        <?php if ($isSelected || $isUnavailable) : ?>
                                                                            <span
                                                                                class="cmp-seat-selection-seat__mark"
                                                                                aria-hidden="true"
                                                                            ><?php echo $isSelected ? '✓' : '×'; ?></span>
                                                                        <?php endif; ?>
                                                                    </label>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else : ?>
                                            <div class="cmp-seat-selection-map__empty" role="status">
                                                <span uk-icon="icon: warning" aria-hidden="true"></span>
                                                <p><?php echo $escape(
                                                    Text::_('COM_COPYMYPAGE_SEAT_SELECTION_MAP_EMPTY')
                                                ); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </fieldset>

                                    <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                                    <input
                                        type="hidden"
                                        name="<?php echo $escape($revisionField); ?>"
                                        value="<?php echo $cartRevision; ?>"
                                        <?php if (($attributes['revisionField'] ?? '') !== '') : ?>
                                            <?php echo $attributes['revisionField']; ?>="1"
                                        <?php endif; ?>
                                    >
                                    <?php echo HTMLHelper::_('form.token'); ?>

                                    <div class="cmp-seat-selection-selected">
                                        <div class="cmp-seat-selection-selected__copy">
                                            <h3 class="cmp-seat-selection-selected__title">
                                                <?php echo $escape(
                                                    Text::_('COM_COPYMYPAGE_SEAT_SELECTION_SELECTED_TITLE')
                                                ); ?>
                                            </h3>
                                            <p
                                                class="cmp-seat-selection-selected__empty"
                                                <?php echo $selectedSeatItems !== [] ? ' hidden' : ''; ?>
                                            >
                                                <?php echo $escape(
                                                    Text::_('COM_COPYMYPAGE_SEAT_SELECTION_SELECTED_EMPTY')
                                                ); ?>
                                            </p>
                                            <ul
                                                class="cmp-seat-selection-selected__list"
                                                <?php if (($attributes['selectedSeats'] ?? '') !== '') : ?>
                                                    <?php echo $attributes['selectedSeats']; ?>="1"
                                                <?php endif; ?>
                                            >
                                                <?php foreach ($selectedSeatItems as $selectedSeatId => $label) : ?>
                                                    <?php
                                                    $inputId = 'cmp-seat-selection-seat-' . $eventId . '-'
                                                        . $selectedSeatId;
                                                    ?>
                                                    <li class="cmp-seat-selection-selected__item">
                                                        <button
                                                            class="cmp-seat-selection-selected__remove"
                                                            type="button"
                                                            aria-controls="<?php echo $escape($inputId); ?>"
                                                            aria-label="<?php echo $escape(Text::sprintf(
                                                                'COM_COPYMYPAGE_SEAT_SELECTION_REMOVE_SEAT',
                                                                $label
                                                            )); ?>"
                                                            <?php if (($attributes['seatRemove'] ?? '') !== '') : ?>
                                                                <?php echo $attributes['seatRemove']; ?>="<?php echo (int) $selectedSeatId; ?>"
                                                            <?php endif; ?>
                                                        >
                                                            <span><?php echo $escape($label); ?></span>
                                                            <span
                                                                class="cmp-seat-selection-selected__remove-icon"
                                                                uk-icon="icon: close"
                                                                aria-hidden="true"
                                                            ></span>
                                                        </button>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>

                                        <div class="cmp-form__actions cmp-seat-selection-selected__actions">
                                            <button
                                                class="uk-button uk-button-primary cmp-button cmp-button--primary"
                                                type="submit"
                                                name="task"
                                                value="ticketseats.assign"
                                                <?php echo !$isReady || !$hasMap ? ' disabled' : ''; ?>
                                            >
                                                <span uk-icon="icon: check" aria-hidden="true"></span>
                                                <span><?php echo $escape(
                                                    Text::_('COM_COPYMYPAGE_SEAT_SELECTION_SAVE_EVENT')
                                                ); ?></span>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <nav class="cmp-seat-selection__navigation">
            <a
                class="uk-button uk-button-default cmp-button cmp-button--secondary cmp-button--back cmp-seat-selection__back"
                href="<?php echo $escape($backUrl); ?>"
            >
                <span
                    uk-icon="icon: chevron-left"
                    aria-hidden="true"
                ></span>
                <span><?php echo $escape(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_BACK')); ?></span>
            </a>

            <div class="cmp-seat-selection__continue-wrap">
                <a
                    class="uk-button uk-button-primary cmp-button cmp-button--primary"
                    <?php if ($continueUrl !== '') : ?>
                        href="<?php echo $escape($continueUrl); ?>"
                    <?php else : ?>
                        aria-disabled="true"
                        tabindex="-1"
                    <?php endif; ?>
                    <?php if (($attributes['continue'] ?? '') !== '') : ?>
                        <?php echo $attributes['continue']; ?>="1"
                    <?php endif; ?>
                >
                    <span><?php echo $escape(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_CONTINUE')); ?></span>
                    <span uk-icon="icon: chevron-right" aria-hidden="true"></span>
                </a>
            </div>
        </nav>
    </div>
</div>
