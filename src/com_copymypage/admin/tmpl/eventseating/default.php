<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_copymypage
 *
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\CopyMyPage\Administrator\View\Eventseating\HtmlView $this */

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('form.validate');

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$text   = static fn(string $key): string => $escape(Text::_($key));

$eventId     = max(0, (int) ($this->eventId ?? 0));
$summary     = \is_array($this->summary ?? null) ? $this->summary : [];
$event       = \is_array($summary['event'] ?? null) ? $summary['event'] : [];
$assignment  = \is_array($summary['assignment'] ?? null) ? $summary['assignment'] : null;
$diagnostics = \is_array($summary['diagnostics'] ?? null) ? $summary['diagnostics'] : [];

$eventTitle     = trim((string) ($event['title'] ?? ''));
$eventStartDate = trim((string) ($event['startDate'] ?? ''));
$eventCapacity  = max(0, (int) ($event['capacity'] ?? 0));
$capacityUsed   = max(0, (int) ($event['capacityUsed'] ?? 0));
$maxTickets     = max(0, (int) ($event['maxTickets'] ?? 0));
$waitingList    = (bool) ($event['waitingList'] ?? false);
$isUpcoming     = (bool) ($event['isUpcoming'] ?? false);

$activeCartQuantity = max(0, (int) ($summary['activeCartQuantity'] ?? 0));
$nativeTicketCount  = max(0, (int) ($summary['nativeTicketCount'] ?? 0));

$assignmentStatus = $assignment === null
    ? 'none'
    : strtolower(trim((string) ($assignment['status'] ?? '')));

$assignmentPresentation = match ($assignmentStatus) {
    'draft' => [
        'badgeClass' => 'bg-warning text-dark',
        'borderClass' => 'border-warning',
        'label' => Text::_('COM_COPYMYPAGE_EVENT_SEATING_STATUS_DRAFT'),
    ],
    'ready' => [
        'badgeClass' => 'bg-success',
        'borderClass' => 'border-success',
        'label' => Text::_('COM_COPYMYPAGE_EVENT_SEATING_STATUS_READY'),
    ],
    'none' => [
        'badgeClass' => 'bg-secondary',
        'borderClass' => 'border-secondary',
        'label' => Text::_('COM_COPYMYPAGE_EVENT_SEATING_STATUS_NONE'),
    ],
    default => [
        'badgeClass' => 'bg-info text-dark',
        'borderClass' => 'border-info',
        'label' => Text::_('COM_COPYMYPAGE_EVENT_SEATING_STATUS_UNKNOWN'),
    ],
};

$layoutTitle      = trim((string) ($assignment['layoutTitle'] ?? ''));
$layoutAlias      = trim((string) ($assignment['layoutAlias'] ?? ''));
$layoutVersion    = max(0, (int) ($assignment['layoutVersion'] ?? 0));
$inventoryVersion = max(0, (int) ($assignment['inventoryVersion'] ?? 0));
$seatCount        = max(0, (int) ($assignment['seatCount'] ?? 0));
$materializedCount = max(0, (int) ($assignment['materializedCount'] ?? 0));

$hasBundledDefinitions = (bool) ($this->hasBundledDefinitions ?? false);
$hasPublishedLayouts   = (bool) ($this->hasPublishedLayouts ?? false);
$canAssign             = (bool) ($this->canAssign ?? false);
$canMarkReady          = (bool) ($this->canMarkReady ?? false);
$importDisabled        = !$hasBundledDefinitions;
$assignDisabled        = !$hasPublishedLayouts || !$canAssign || $eventId === 0;

if ($importDisabled) {
    $this->form->setFieldAttribute('definition', 'disabled', 'true');
}

if ($assignDisabled) {
    $this->form->setFieldAttribute('layout_id', 'disabled', 'true');
}

$materializedBadgeClass = match (true) {
    $assignment === null || $seatCount === 0 => 'bg-secondary',
    $materializedCount === $seatCount => 'bg-success',
    default => 'bg-warning text-dark',
};
$activeCartBadgeClass   = $activeCartQuantity === 0 ? 'bg-success' : 'bg-warning text-dark';
$nativeTicketBadgeClass = $nativeTicketCount === 0 ? 'bg-success' : 'bg-danger';

$actionUrl = Route::_(
    'index.php?option=com_copymypage&view=eventseating&event_id=' . $eventId
);
$backUrl   = trim((string) ($this->backUrl ?? ''));
$backRoute = $backUrl === '' ? '' : Route::_($backUrl);

$importDescriptionIds = 'cmp-event-seating-import-help';

if ($importDisabled) {
    $importDescriptionIds .= ' cmp-event-seating-import-unavailable';
}

$assignDescriptionIds = 'cmp-event-seating-assign-help';

if (!$hasPublishedLayouts) {
    $assignDescriptionIds .= ' cmp-event-seating-assign-no-layouts';
}

if (!$canAssign) {
    $assignDescriptionIds .= ' cmp-event-seating-assign-blocked';
}

if ($eventId === 0) {
    $assignDescriptionIds .= ' cmp-event-seating-assign-invalid-event';
}

$diagnosticPresentation = [
    'success' => [
        'badgeClass' => 'bg-success',
        'label' => Text::_('COM_COPYMYPAGE_EVENT_SEATING_DIAGNOSTIC_STATUS_SUCCESS'),
    ],
    'warning' => [
        'badgeClass' => 'bg-warning text-dark',
        'label' => Text::_('COM_COPYMYPAGE_EVENT_SEATING_DIAGNOSTIC_STATUS_WARNING'),
    ],
    'danger' => [
        'badgeClass' => 'bg-danger',
        'label' => Text::_('COM_COPYMYPAGE_EVENT_SEATING_DIAGNOSTIC_STATUS_DANGER'),
    ],
    'info' => [
        'badgeClass' => 'bg-info text-dark',
        'label' => Text::_('COM_COPYMYPAGE_EVENT_SEATING_DIAGNOSTIC_STATUS_INFO'),
    ],
];
?>
<div class="com-copymypage-eventseating">
    <header class="alert alert-info mb-4" aria-labelledby="cmp-event-seating-intro-title">
        <h2 class="h5 alert-heading" id="cmp-event-seating-intro-title">
            <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_INTRO_TITLE'); ?>
        </h2>
        <p class="mb-0">
            <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_INTRO_DESC'); ?>
        </p>
    </header>

    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-4">
            <section class="card h-100" aria-labelledby="cmp-event-seating-event-title">
                <h2 class="h5 card-header" id="cmp-event-seating-event-title">
                    <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_EVENT_OVERVIEW_TITLE'); ?>
                </h2>
                <div class="card-body">
                    <div class="border-bottom pb-3 mb-3">
                        <p class="small text-body-secondary mb-1">
                            <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_EVENT_TITLE_LABEL'); ?>
                        </p>
                        <h3 class="h6 mb-3">
                            <?php echo $eventTitle === '' ? $text('JNONE') : $escape($eventTitle); ?>
                        </h3>
                        <dl class="row gy-1 small mb-0">
                            <dt class="col-sm-5 text-body-secondary">
                                <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_EVENT_ID_LABEL'); ?>
                            </dt>
                            <dd class="col-sm-7">
                                <?php echo $escape($eventId); ?>
                            </dd>

                            <dt class="col-sm-5 text-body-secondary">
                                <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_EVENT_START_LABEL'); ?>
                            </dt>
                            <dd class="col-sm-7">
                                <?php echo $eventStartDate === '' ? $text('JNONE') : $escape($eventStartDate); ?>
                            </dd>
                        </dl>
                    </div>

                    <dl class="row gy-2 small mb-0">
                        <dt class="col-sm-5 text-body-secondary">
                            <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_EVENT_CAPACITY_LABEL'); ?>
                        </dt>
                        <dd class="col-sm-7">
                            <?php echo $escape($eventCapacity); ?>
                        </dd>

                        <dt class="col-sm-5 text-body-secondary">
                            <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_EVENT_CAPACITY_USED_LABEL'); ?>
                        </dt>
                        <dd class="col-sm-7">
                            <?php echo $escape($capacityUsed); ?>
                        </dd>

                        <dt class="col-sm-5 text-body-secondary">
                            <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_EVENT_MAX_TICKETS_LABEL'); ?>
                        </dt>
                        <dd class="col-sm-7">
                            <?php echo $escape($maxTickets); ?>
                        </dd>

                        <dt class="col-sm-5 text-body-secondary">
                            <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_EVENT_WAITING_LIST_LABEL'); ?>
                        </dt>
                        <dd class="col-sm-7">
                            <?php echo $text($waitingList ? 'JYES' : 'JNO'); ?>
                        </dd>

                        <dt class="col-sm-5 text-body-secondary">
                            <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_EVENT_UPCOMING_LABEL'); ?>
                        </dt>
                        <dd class="col-sm-7">
                            <?php echo $text($isUpcoming ? 'JYES' : 'JNO'); ?>
                        </dd>

                        <dt class="col-sm-5 text-body-secondary">
                            <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_ASSIGNMENT_STATUS_LABEL'); ?>
                        </dt>
                        <dd class="col-sm-7">
                            <span class="badge <?php echo $escape($assignmentPresentation['badgeClass']); ?>">
                                <?php echo $escape($assignmentPresentation['label']); ?>
                            </span>
                        </dd>
                    </dl>
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-8">
            <section
                class="card h-100 <?php echo $escape($assignmentPresentation['borderClass']); ?>"
                aria-labelledby="cmp-event-seating-readiness-title"
            >
                <h2 class="h5 card-header" id="cmp-event-seating-readiness-title">
                    <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_READINESS_TITLE'); ?>
                </h2>
                <div class="card-body">
                    <p class="text-body-secondary">
                        <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_READINESS_LEAD'); ?>
                    </p>

                    <ul
                        class="row g-2 list-unstyled mb-4"
                        aria-labelledby="cmp-event-seating-readiness-title"
                    >
                        <li class="col-12 col-sm-6 col-xl-3">
                            <div class="border rounded p-3 h-100">
                                <span class="d-block small text-body-secondary mb-2">
                                    <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_ASSIGNMENT_STATUS_LABEL'); ?>
                                </span>
                                <span class="d-block">
                                    <span
                                        class="badge <?php echo $escape($assignmentPresentation['badgeClass']); ?>"
                                    >
                                        <?php echo $escape($assignmentPresentation['label']); ?>
                                    </span>
                                </span>
                            </div>
                        </li>
                        <li class="col-12 col-sm-6 col-xl-3">
                            <div class="border rounded p-3 h-100">
                                <span class="d-block small text-body-secondary mb-2">
                                    <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_MATERIALIZED_COUNT_LABEL'); ?>
                                </span>
                                <span class="d-block">
                                    <span class="badge fs-5 <?php echo $escape($materializedBadgeClass); ?>">
                                        <?php echo $escape($materializedCount); ?> / <?php echo $escape($seatCount); ?>
                                    </span>
                                </span>
                            </div>
                        </li>
                        <li class="col-12 col-sm-6 col-xl-3">
                            <div class="border rounded p-3 h-100">
                                <span class="d-block small text-body-secondary mb-2">
                                    <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_ACTIVE_CART_QUANTITY_LABEL'); ?>
                                </span>
                                <span class="d-block">
                                    <span class="badge fs-5 <?php echo $escape($activeCartBadgeClass); ?>">
                                        <?php echo $escape($activeCartQuantity); ?>
                                    </span>
                                </span>
                            </div>
                        </li>
                        <li class="col-12 col-sm-6 col-xl-3">
                            <div class="border rounded p-3 h-100">
                                <span class="d-block small text-body-secondary mb-2">
                                    <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_NATIVE_TICKET_COUNT_LABEL'); ?>
                                </span>
                                <span class="d-block">
                                    <span class="badge fs-5 <?php echo $escape($nativeTicketBadgeClass); ?>">
                                        <?php echo $escape($nativeTicketCount); ?>
                                    </span>
                                </span>
                            </div>
                        </li>
                    </ul>

                    <?php if ($assignment === null) : ?>
                        <div class="alert alert-secondary" role="status">
                            <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_ASSIGNMENT_EMPTY'); ?>
                        </div>
                    <?php else : ?>
                        <dl class="row gy-2 small border-bottom pb-3 mb-4">
                            <dt class="col-sm-5">
                                <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_LAYOUT_LABEL'); ?>
                            </dt>
                            <dd class="col-sm-7">
                                <?php echo $layoutTitle === '' ? $text('JNONE') : $escape($layoutTitle); ?>
                                <?php if ($layoutAlias !== '' && $layoutVersion > 0) : ?>
                                    <span class="d-block text-body-secondary small">
                                        <?php
                                        echo $escape(
                                            Text::sprintf(
                                                'COM_COPYMYPAGE_EVENT_SEATING_LAYOUT_IDENTIFIER',
                                                $layoutAlias,
                                                $layoutVersion
                                            )
                                        );
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </dd>

                            <dt class="col-sm-5">
                                <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_INVENTORY_VERSION_LABEL'); ?>
                            </dt>
                            <dd class="col-sm-7">
                                <?php echo $escape($inventoryVersion); ?>
                            </dd>
                        </dl>
                    <?php endif; ?>

                    <h3 class="h6" id="cmp-event-seating-diagnostics-title">
                        <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_DIAGNOSTICS_TITLE'); ?>
                    </h3>

                    <?php if ($diagnostics === []) : ?>
                        <p class="text-body-secondary mb-0">
                            <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_DIAGNOSTICS_EMPTY'); ?>
                        </p>
                    <?php else : ?>
                        <ul
                            class="list-group list-group-flush"
                            aria-labelledby="cmp-event-seating-diagnostics-title"
                        >
                            <?php foreach ($diagnostics as $diagnostic) : ?>
                                <?php
                                if (!\is_array($diagnostic)) {
                                    continue;
                                }

                                $diagnosticStatus = strtolower(trim((string) ($diagnostic['status'] ?? 'info')));
                                $diagnosticStatus = isset($diagnosticPresentation[$diagnosticStatus])
                                    ? $diagnosticStatus
                                    : 'info';
                                $diagnosticMessage = trim((string) ($diagnostic['message'] ?? ''));

                                if ($diagnosticMessage === '') {
                                    continue;
                                }

                                $presentation = $diagnosticPresentation[$diagnosticStatus];
                                ?>
                                <li
                                    class="list-group-item d-flex flex-wrap align-items-center gap-2 px-0"
                                >
                                    <span class="badge <?php echo $escape($presentation['badgeClass']); ?>">
                                        <?php echo $escape($presentation['label']); ?>
                                    </span>
                                    <span><?php echo $escape($diagnosticMessage); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ($assignmentStatus === 'draft') : ?>
                        <form
                            action="<?php echo $escape($actionUrl); ?>"
                            method="post"
                            class="cmp-form mt-4"
                            aria-label="<?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_READY_FORM_LEGEND'); ?>"
                        >
                            <input type="hidden" name="event_id" value="<?php echo $escape($eventId); ?>">
                            <input type="hidden" name="task" value="eventseating.markReady">
                            <?php echo HTMLHelper::_('form.token'); ?>

                            <div class="cmp-form__actions d-flex flex-wrap gap-2">
                                <button
                                    type="submit"
                                    class="btn btn-success cmp-button cmp-button--primary"
                                    <?php if (!$canMarkReady) : ?>disabled aria-disabled="true"<?php endif; ?>
                                >
                                    <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_READY_BUTTON'); ?>
                                </button>
                            </div>

                            <?php if (!$canMarkReady) : ?>
                                <p class="small text-body-secondary mt-2 mb-0">
                                    <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_READY_BLOCKED'); ?>
                                </p>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
            <section class="card h-100" aria-labelledby="cmp-event-seating-import-title">
                <h2 class="h5 card-header" id="cmp-event-seating-import-title">
                    <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_IMPORT_TITLE'); ?>
                </h2>
                <div class="card-body">
                    <p class="text-body-secondary" id="cmp-event-seating-import-help">
                        <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_IMPORT_HELP'); ?>
                    </p>

                    <?php if ($importDisabled) : ?>
                        <div
                            class="alert alert-warning"
                            id="cmp-event-seating-import-unavailable"
                            role="status"
                        >
                            <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_IMPORT_UNAVAILABLE'); ?>
                        </div>
                    <?php endif; ?>

                    <form
                        action="<?php echo $escape($actionUrl); ?>"
                        method="post"
                        id="cmp-event-seating-import-form"
                        class="cmp-form cmp-event-seating__import-form form-validate"
                        aria-labelledby="cmp-event-seating-import-title"
                    >
                        <fieldset class="border-0 p-0 m-0">
                            <legend class="visually-hidden">
                                <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_IMPORT_FORM_LEGEND'); ?>
                            </legend>
                            <?php echo $this->form->renderField('definition'); ?>
                        </fieldset>

                        <input type="hidden" name="event_id" value="<?php echo $escape($eventId); ?>">
                        <input type="hidden" name="task" value="eventseating.importDefinition">
                        <?php echo HTMLHelper::_('form.token'); ?>

                        <div class="cmp-form__actions d-flex flex-wrap gap-2 mt-3">
                            <button
                                type="submit"
                                class="btn btn-outline-primary cmp-button cmp-button--primary-outline"
                                aria-describedby="<?php echo $escape($importDescriptionIds); ?>"
                                <?php if ($importDisabled) : ?>disabled aria-disabled="true"<?php endif; ?>
                            >
                                <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_IMPORT_BUTTON'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-6">
            <section class="card h-100" aria-labelledby="cmp-event-seating-assign-title">
                <h2 class="h5 card-header" id="cmp-event-seating-assign-title">
                    <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_ASSIGN_TITLE'); ?>
                </h2>
                <div class="card-body">
                    <p class="text-body-secondary" id="cmp-event-seating-assign-help">
                        <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_ASSIGN_HELP'); ?>
                    </p>

                    <?php if (!$hasPublishedLayouts) : ?>
                        <div
                            class="alert alert-warning"
                            id="cmp-event-seating-assign-no-layouts"
                            role="status"
                        >
                            <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_ASSIGN_NO_LAYOUTS'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$canAssign) : ?>
                        <div
                            class="alert alert-warning"
                            id="cmp-event-seating-assign-blocked"
                            role="status"
                        >
                            <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_ASSIGN_BLOCKED'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($eventId === 0) : ?>
                        <div
                            class="alert alert-danger"
                            id="cmp-event-seating-assign-invalid-event"
                            role="alert"
                        >
                            <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_ASSIGN_INVALID_EVENT'); ?>
                        </div>
                    <?php endif; ?>

                    <form
                        action="<?php echo $escape($actionUrl); ?>"
                        method="post"
                        id="cmp-event-seating-assign-form"
                        class="cmp-form cmp-event-seating__assign-form form-validate"
                        aria-labelledby="cmp-event-seating-assign-title"
                    >
                        <fieldset class="border-0 p-0 m-0">
                            <legend class="visually-hidden">
                                <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_ASSIGN_FORM_LEGEND'); ?>
                            </legend>
                            <?php echo $this->form->renderField('layout_id'); ?>
                        </fieldset>

                        <input type="hidden" name="event_id" value="<?php echo $escape($eventId); ?>">
                        <input type="hidden" name="task" value="eventseating.assignLayout">
                        <?php echo HTMLHelper::_('form.token'); ?>

                        <div class="cmp-form__actions d-flex flex-wrap gap-2 mt-3">
                            <button
                                type="submit"
                                class="btn btn-primary cmp-button cmp-button--primary"
                                aria-describedby="<?php echo $escape($assignDescriptionIds); ?>"
                                <?php if ($assignDisabled) : ?>disabled aria-disabled="true"<?php endif; ?>
                            >
                                <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_ASSIGN_BUTTON'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>

    <?php if ($backRoute !== '') : ?>
        <nav aria-label="<?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_BACK_TO_EVENT'); ?>">
            <a
                class="btn btn-outline-secondary cmp-button cmp-button--secondary"
                href="<?php echo $escape($backRoute); ?>"
            >
                <?php echo $text('COM_COPYMYPAGE_EVENT_SEATING_BACK_TO_EVENT'); ?>
            </a>
        </nav>
    <?php endif; ?>
</div>
