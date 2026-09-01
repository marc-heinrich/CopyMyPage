<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use DigitalPeak\Component\DPCalendar\Administrator\Helper\DPCalendarHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Component\CopyMyPage\Site\Exception\SeatSelectionConflictException;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Projects and atomically replaces concrete seats for the current ticket cart.
 */
final class SeatSelectionService
{
    private const ROOT_ATTRIBUTE = 'data-cmp-seat-selection';

    private const EVENT_ATTRIBUTE = 'data-cmp-seat-selection-event';

    private const EVENT_ID_ATTRIBUTE = 'data-cmp-seat-event-id';

    private const REQUIRED_COUNT_ATTRIBUTE = 'data-cmp-seat-required-count';

    private const EVENT_FORM_ATTRIBUTE = 'data-cmp-seat-selection-form';

    private const EVENT_STATUS_ATTRIBUTE = 'data-cmp-seat-selection-status';

    private const EVENT_COUNT_ATTRIBUTE = 'data-cmp-seat-selection-count';

    private const EVENT_MESSAGE_ATTRIBUTE = 'data-cmp-seat-selection-message';

    private const REVISION_FIELD_ATTRIBUTE = 'data-cmp-seat-cart-revision';

    private const SEAT_ATTRIBUTE = 'data-cmp-seat';

    private const SEAT_ID_ATTRIBUTE = 'data-cmp-seat-id';

    private const SELECTED_SEATS_ATTRIBUTE = 'data-cmp-selected-seats';

    private const SEAT_REMOVE_ATTRIBUTE = 'data-cmp-seat-remove';

    private const SUGGEST_ATTRIBUTE = 'data-cmp-seat-suggest';

    private const ZOOM_VIEWPORT_ATTRIBUTE = 'data-cmp-seat-viewport';

    private const ZOOM_CANVAS_ATTRIBUTE = 'data-cmp-seat-canvas';

    private const ZOOM_OUT_ATTRIBUTE = 'data-cmp-seat-zoom-out';

    private const ZOOM_IN_ATTRIBUTE = 'data-cmp-seat-zoom-in';

    private const ZOOM_RESET_ATTRIBUTE = 'data-cmp-seat-zoom-reset';

    private const TABLE_FOCUS_ATTRIBUTE = 'data-cmp-seat-table-focus';

    private const TABLE_FOCUS_LINKS_ATTRIBUTE = 'data-cmp-seat-table-focus-links';

    private const TABLE_FOCUS_NEXT_ATTRIBUTE = 'data-cmp-seat-table-focus-next';

    private const TABLE_FOCUS_PREVIOUS_ATTRIBUTE = 'data-cmp-seat-table-focus-previous';

    private const GLOBAL_STATUS_ATTRIBUTE = 'data-cmp-seat-global-status';

    private const CONTINUE_ATTRIBUTE = 'data-cmp-seat-continue';

    private const EXPECTED_REVISION_FIELD = 'expectedCartRevision';

    private const SEAT_IDS_FIELD = 'seat_ids';

    private const MAX_SEAT_IDS = 200;

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly TicketCatalogService $catalog,
        private readonly TicketCartContextService $cartContext
    ) {
    }

    /**
     * Stable browser configuration owned by the seating domain.
     *
     * @return array<string, mixed>
     */
    public function getClientConfig(): array
    {
        return [
            'rootSelector' => '[' . self::ROOT_ATTRIBUTE . ']',
            'selectors'    => [
                'continue'           => '[' . self::CONTINUE_ATTRIBUTE . ']',
                'event'              => '[' . self::EVENT_ATTRIBUTE . ']',
                'eventCount'         => '[' . self::EVENT_COUNT_ATTRIBUTE . ']',
                'eventForm'          => '[' . self::EVENT_FORM_ATTRIBUTE . ']',
                'eventMessage'       => '[' . self::EVENT_MESSAGE_ATTRIBUTE . ']',
                'eventStatus'        => '[' . self::EVENT_STATUS_ATTRIBUTE . ']',
                'globalStatus'       => '[' . self::GLOBAL_STATUS_ATTRIBUTE . ']',
                'revisionField'      => '[' . self::REVISION_FIELD_ATTRIBUTE . ']',
                'seat'               => '[' . self::SEAT_ATTRIBUTE . ']',
                'seatRemove'         => '[' . self::SEAT_REMOVE_ATTRIBUTE . ']',
                'selectedSeats'      => '[' . self::SELECTED_SEATS_ATTRIBUTE . ']',
                'suggest'            => '[' . self::SUGGEST_ATTRIBUTE . ']',
                'tableFocus'         => '[' . self::TABLE_FOCUS_ATTRIBUTE . ']',
                'tableFocusLinks'    => '[' . self::TABLE_FOCUS_LINKS_ATTRIBUTE . ']',
                'tableFocusNext'     => '[' . self::TABLE_FOCUS_NEXT_ATTRIBUTE . ']',
                'tableFocusPrevious' => '[' . self::TABLE_FOCUS_PREVIOUS_ATTRIBUTE . ']',
                'zoomCanvas'         => '[' . self::ZOOM_CANVAS_ATTRIBUTE . ']',
                'zoomIn'             => '[' . self::ZOOM_IN_ATTRIBUTE . ']',
                'zoomOut'            => '[' . self::ZOOM_OUT_ATTRIBUTE . ']',
                'zoomReset'          => '[' . self::ZOOM_RESET_ATTRIBUTE . ']',
                'zoomViewport'       => '[' . self::ZOOM_VIEWPORT_ATTRIBUTE . ']',
            ],
            'attributes' => [
                'eventId'       => self::EVENT_ID_ATTRIBUTE,
                'requiredCount' => self::REQUIRED_COUNT_ATTRIBUTE,
                'seatId'        => self::SEAT_ID_ATTRIBUTE,
            ],
            'fields' => [
                'expectedCartRevision' => self::EXPECTED_REVISION_FIELD,
                'seatIds'              => self::SEAT_IDS_FIELD,
            ],
            'endpoints' => [
                'assign' => Route::_('index.php?option=com_copymypage&task=ticketseats.assign&format=json', false),
                'state' => Route::_('index.php?option=com_copymypage&task=ticketseats.state&format=json', false),
                'suggest' => Route::_('index.php?option=com_copymypage&task=ticketseats.suggest&format=json', false),
            ],
            'csrfToken'   => Session::getFormToken(),
            'continueUrl' => Route::_('index.php?option=com_copymypage&view=customerdata', false),
            'maxSeatIds'  => self::MAX_SEAT_IDS,
            'pollInterval' => 15000,
            'zoom' => [
                'max'  => 3,
                'min'  => 1,
                'step' => 0.25,
            ],
            'strings' => [
                'complete'       => Text::_('COM_COPYMYPAGE_SEAT_SELECTION_COMPLETE'),
                'incomplete'     => Text::_('COM_COPYMYPAGE_SEAT_SELECTION_INCOMPLETE'),
                'requestFailed'  => Text::_('COM_COPYMYPAGE_SEAT_SELECTION_ERROR_REQUEST'),
                'removeSeat'     => Text::_('COM_COPYMYPAGE_SEAT_SELECTION_REMOVE_SEAT'),
                'seatDeselected' => Text::_('COM_COPYMYPAGE_SEAT_SELECTION_FEEDBACK_DESELECTED'),
                'seatSelected'   => Text::_('COM_COPYMYPAGE_SEAT_SELECTION_FEEDBACK_SELECTED'),
                'selectionLimit' => Text::_('COM_COPYMYPAGE_SEAT_SELECTION_LIMIT_REACHED'),
                'unavailable'    => Text::_('COM_COPYMYPAGE_SEAT_SELECTION_LEGEND_UNAVAILABLE'),
            ],
        ];
    }

    /**
     * Attribute names shared by the PHP template and browser runtime.
     *
     * @return array<string, string>
     */
    public function getMarkupAttributes(): array
    {
        return [
            'continue'           => self::CONTINUE_ATTRIBUTE,
            'event'              => self::EVENT_ATTRIBUTE,
            'eventCount'         => self::EVENT_COUNT_ATTRIBUTE,
            'eventForm'          => self::EVENT_FORM_ATTRIBUTE,
            'eventId'            => self::EVENT_ID_ATTRIBUTE,
            'eventMessage'       => self::EVENT_MESSAGE_ATTRIBUTE,
            'eventStatus'        => self::EVENT_STATUS_ATTRIBUTE,
            'globalStatus'       => self::GLOBAL_STATUS_ATTRIBUTE,
            'requiredCount'      => self::REQUIRED_COUNT_ATTRIBUTE,
            'revisionField'      => self::REVISION_FIELD_ATTRIBUTE,
            'root'               => self::ROOT_ATTRIBUTE,
            'seat'               => self::SEAT_ATTRIBUTE,
            'seatId'             => self::SEAT_ID_ATTRIBUTE,
            'seatRemove'         => self::SEAT_REMOVE_ATTRIBUTE,
            'selectedSeats'      => self::SELECTED_SEATS_ATTRIBUTE,
            'suggest'            => self::SUGGEST_ATTRIBUTE,
            'tableFocus'         => self::TABLE_FOCUS_ATTRIBUTE,
            'tableFocusLinks'    => self::TABLE_FOCUS_LINKS_ATTRIBUTE,
            'tableFocusNext'     => self::TABLE_FOCUS_NEXT_ATTRIBUTE,
            'tableFocusPrevious' => self::TABLE_FOCUS_PREVIOUS_ATTRIBUTE,
            'zoomCanvas'         => self::ZOOM_CANVAS_ATTRIBUTE,
            'zoomIn'             => self::ZOOM_IN_ATTRIBUTE,
            'zoomOut'            => self::ZOOM_OUT_ATTRIBUTE,
            'zoomReset'          => self::ZOOM_RESET_ATTRIBUTE,
            'zoomViewport'       => self::ZOOM_VIEWPORT_ATTRIBUTE,
        ];
    }

    /**
     * Stable form field names.
     *
     * @return array<string, string>
     */
    public function getFormFieldNames(): array
    {
        return [
            'expectedCartRevision' => self::EXPECTED_REVISION_FIELD,
            'seatIds'              => self::SEAT_IDS_FIELD,
        ];
    }

    /**
     * Complete, private state for the current cart and all of its events.
     *
     * @return array<string, mixed>
     */
    public function getSelectionState(int $requestedEventId = 0): array
    {
        $cart = $this->cartContext->getActiveCart();

        if ($cart === null) {
            return $this->emptyState();
        }

        $cartRows = $this->loadCartItems((int) $cart->id);
        $targets  = $this->buildCartTargets($cartRows);
        $eventIds = array_keys($targets);

        if ($eventIds === []) {
            return $this->emptyState();
        }

        $this->cartContext->markSeatSelectionStarted($cart);

        $displayEvents = $this->catalog->getDisplayEvents($eventIds);
        $catalogEvents = [];

        foreach ($this->catalog->getUpcomingEvents() as $catalogEvent) {
            $catalogEvents[(int) $catalogEvent->id] = true;
        }

        $projections = $this->loadEventProjections($eventIds, (int) $cart->id);
        $events      = [];

        foreach ($eventIds as $eventId) {
            $event       = $displayEvents[$eventId] ?? null;
            $projection  = $projections[$eventId] ?? null;
            $required    = array_sum($targets[$eventId]);
            $continuable = isset($catalogEvents[$eventId]);

            if ($projection === null) {
                $events[] = $this->buildUnavailableEvent(
                    $eventId,
                    $event,
                    $required,
                    $continuable
                );

                continue;
            }

            $projection['title']         = $this->eventTitle($eventId, $event);
            $projection['dateLabel']     = $this->formatEventDateLabel($event);
            $projection['dateTime']      = $this->formatEventDateTime($event);
            $projection['requiredCount'] = $required;
            $projection['selectedCount'] = \count($projection['selectedSeats']);
            $projection['continuable']   = $continuable;
            $projection['complete']      = $projection['ready']
                && $continuable
                && $projection['selectedCount'] === $required;
            $projection['message']       = $projection['ready']
                ? ''
                : Text::_('COM_COPYMYPAGE_SEAT_SELECTION_EVENT_NOT_READY');
            $events[] = $projection;
        }

        $allComplete = $events !== [];

        foreach ($events as $event) {
            if (empty($event['complete'])) {
                $allComplete = false;

                break;
            }
        }

        $selectedEventId = $this->resolveSelectedEventId($events, $requestedEventId);
        $expiresAt       = $this->toIso8601((string) ($cart->expires_at ?? ''));

        return [
            'allComplete'     => $allComplete,
            'cart'            => [
                'active'       => true,
                'cartRevision' => $this->cartContext->getRevision($cart),
                'expiresAt'    => $expiresAt,
                'secondsLeft'  => max(0, strtotime($expiresAt) - time()),
                'totalTickets' => array_sum(array_map('array_sum', $targets)),
            ],
            'events'          => $events,
            'selectedEventId' => $selectedEventId,
        ];
    }

    /**
     * Return one event in the current cart without changing expiry or inventory.
     *
     * @return array<string, mixed>
     */
    public function getEventState(int $eventId): array
    {
        if ($eventId < 1) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_ERROR_EVENT'));
        }

        $state = $this->getSelectionState($eventId);

        foreach ($state['events'] as $event) {
            if ((int) ($event['id'] ?? 0) === $eventId) {
                return [
                    'allComplete'  => (bool) $state['allComplete'],
                    'cartRevision' => (int) ($state['cart']['cartRevision'] ?? 0),
                    'event'        => $event,
                    'eventId'      => $eventId,
                    'expiresAt'    => (string) ($state['cart']['expiresAt'] ?? ''),
                ];
            }
        }

        throw new \DomainException(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_ERROR_EVENT'));
    }

    /**
     * Verify that one active cart can enter the customer-data checkout step.
     *
     * The caller may pass a cart locked by TicketCartContextService so the
     * same authoritative check can be repeated inside a mutation transaction.
     */
    public function isCheckoutReady(?object $cart = null): bool
    {
        $cart ??= $this->cartContext->getActiveCart();
        $cartId = max(0, (int) ($cart->id ?? 0));

        if ($cartId < 1) {
            return false;
        }

        $targets = $this->buildCartTargets($this->loadCartItems($cartId));

        if ($targets === []) {
            return false;
        }

        $continuable = [];

        foreach ($this->catalog->getUpcomingEvents() as $event) {
            $continuable[(int) $event->id] = true;
        }

        $projections = $this->loadEventProjections(array_keys($targets), $cartId);

        foreach ($targets as $eventId => $quantities) {
            $projection = $projections[$eventId] ?? null;
            $required   = array_sum($quantities);

            if (
                $required < 1
                || !isset($continuable[$eventId])
                || !\is_array($projection)
                || empty($projection['ready'])
                || \count((array) ($projection['selectedSeats'] ?? [])) !== $required
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Atomically replace the full desired set for one cart event.
     *
     * @param   array<int|string, mixed>  $rawSeatIds
     *
     * @return array<string, mixed>
     */
    public function assignSeats(
        int $eventId,
        array $rawSeatIds,
        int $expectedCartRevision
    ): array {
        $seatIds = $this->normaliseSeatIds($rawSeatIds);

        return $this->mutateSeats($eventId, $seatIds, $expectedCartRevision, false);
    }

    /**
     * Atomically hold a neutral deterministic proposal for one cart event.
     *
     * @return array<string, mixed>
     */
    public function suggestSeats(int $eventId, int $expectedCartRevision): array
    {
        return $this->mutateSeats($eventId, [], $expectedCartRevision, true);
    }

    /**
     * Bulk inventory constraints for Step-1 quantity availability.
     *
     * @param   array<int, int|string>  $eventIds
     *
     * @return array<int, array<string, bool|int>>
     */
    public function getInventoryConstraints(array $eventIds): array
    {
        $eventIds = $this->normaliseEventIds($eventIds);

        if ($eventIds === []) {
            return [];
        }

        $assignmentQuery = $this->db->getQuery(true)
            ->select($this->db->quoteName(['event_id', 'status']))
            ->from($this->db->quoteName('#__copymypage_event_seating'))
            ->where($this->db->quoteName('event_id') . ' IN (' . implode(',', $eventIds) . ')');
        $result = [];

        foreach ((array) $this->db->setQuery($assignmentQuery)->loadObjectList() as $assignment) {
            $result[(int) $assignment->event_id] = [
                'assigned'     => true,
                'booked'       => 0,
                'capacity'     => 0,
                'layoutCount'  => 0,
                'materialized' => 0,
                'ready'        => false,
            ];
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('a.event_id'))
            ->select($this->db->quoteName('a.status', 'assignment_status'))
            ->select($this->db->quoteName('l.status', 'layout_status'))
            ->select('COUNT(' . $this->db->quoteName('i.id') . ') AS ' . $this->db->quoteName('materialized_count'))
            ->select('(SELECT COUNT(*) FROM '
                . $this->db->quoteName('#__copymypage_event_seats', 'all_inventory')
                . ' WHERE ' . $this->db->quoteName('all_inventory.event_id')
                . ' = ' . $this->db->quoteName('a.event_id') . ') AS '
                . $this->db->quoteName('inventory_count'))
            ->select('COUNT(' . $this->db->quoteName('s.id') . ') AS ' . $this->db->quoteName('layout_count'))
            ->select('SUM(CASE WHEN ' . $this->db->quoteName('i.status') . ' = '
                . EventSeatInventoryService::SEAT_STATUS_BLOCKED . ' THEN 1 ELSE 0 END) AS '
                . $this->db->quoteName('blocked_count'))
            ->select('SUM(CASE WHEN ' . $this->db->quoteName('i.status') . ' = '
                . EventSeatInventoryService::SEAT_STATUS_BOOKED . ' THEN 1 ELSE 0 END) AS '
                . $this->db->quoteName('booked_count'))
            ->select('SUM(CASE WHEN ' . $this->db->quoteName('i.status')
                . ' NOT IN (' . implode(',', [
                    EventSeatInventoryService::SEAT_STATUS_AVAILABLE,
                    EventSeatInventoryService::SEAT_STATUS_HELD,
                    EventSeatInventoryService::SEAT_STATUS_BOOKED,
                    EventSeatInventoryService::SEAT_STATUS_BLOCKED,
                ]) . ') THEN 1 ELSE 0 END) AS '
                . $this->db->quoteName('invalid_status_count'))
            ->from($this->db->quoteName('#__copymypage_event_seating', 'a'))
            ->innerJoin($this->db->quoteName('#__copymypage_seat_layouts', 'l')
                . ' ON ' . $this->db->quoteName('l.id') . ' = ' . $this->db->quoteName('a.layout_id'))
            ->innerJoin($this->db->quoteName('#__copymypage_layout_tables', 't')
                . ' ON ' . $this->db->quoteName('t.layout_id') . ' = ' . $this->db->quoteName('l.id'))
            ->innerJoin($this->db->quoteName('#__copymypage_seats', 's')
                . ' ON ' . $this->db->quoteName('s.layout_table_id') . ' = ' . $this->db->quoteName('t.id'))
            ->leftJoin($this->db->quoteName('#__copymypage_event_seats', 'i')
                . ' ON ' . $this->db->quoteName('i.event_id') . ' = ' . $this->db->quoteName('a.event_id')
                . ' AND ' . $this->db->quoteName('i.seat_id') . ' = ' . $this->db->quoteName('s.id'))
            ->where($this->db->quoteName('a.event_id') . ' IN (' . implode(',', $eventIds) . ')')
            ->group($this->db->quoteName(['a.event_id', 'a.status', 'l.status']));

        foreach ((array) $this->db->setQuery($query)->loadObjectList() as $row) {
            $eventId      = (int) $row->event_id;
            $layoutCount  = max(0, (int) $row->layout_count);
            $materialized = max(0, (int) $row->materialized_count);
            $inventoryCount = max(0, (int) $row->inventory_count);
            $blocked      = max(0, (int) $row->blocked_count);
            $booked       = max(0, (int) $row->booked_count);
            $invalidStatuses = max(0, (int) $row->invalid_status_count);
            $ready        = (int) $row->assignment_status === EventSeatInventoryService::EVENT_STATUS_READY
                && (int) $row->layout_status === SeatLayoutService::STATUS_PUBLISHED
                && $layoutCount > 0
                && $layoutCount <= self::MAX_SEAT_IDS
                && $materialized === $layoutCount
                && $inventoryCount === $layoutCount
                && $invalidStatuses === 0;

            $result[$eventId] = [
                'assigned'     => true,
                'booked'       => $booked,
                'capacity'     => max(0, $layoutCount - $blocked - $booked),
                'layoutCount'  => $layoutCount,
                'materialized' => $materialized,
                'ready'        => $ready,
            ];
        }

        return $result;
    }

    /**
     * Reconcile held seats after a quantity mutation inside its existing transaction.
     * The caller already owns the cart and DPCalendar event locks.
     *
     * @param   array<int, int>  $targetQuantities
     */
    public function reconcileCartEventWithinTransaction(
        int $cartId,
        int $eventId,
        array $targetQuantities
    ): void {
        if ($cartId < 1 || $eventId < 1) {
            return;
        }

        $assignment = $this->lockAssignment($eventId);

        if ($assignment === null) {
            return;
        }

        $rows             = $this->loadInventoryRowsForUpdate($eventId);
        $targetSlots      = $this->buildPriceSlots($targetQuantities);

        if ($targetSlots !== []) {
            $this->assertExactPublishedInventory($assignment, $rows);
        }

        $inventoryChanged = $this->normaliseExpiredHolds($rows);
        $ownRows          = array_values(array_filter(
            $rows,
            static fn(object $row): bool => (int) $row->status === EventSeatInventoryService::SEAT_STATUS_HELD
                && (int) $row->cart_id === $cartId
        ));
        $keepRows    = array_slice($ownRows, 0, \count($targetSlots));
        $releaseRows = array_slice($ownRows, \count($targetSlots));

        if ($releaseRows !== []) {
            $this->releaseInventoryRows(array_map(static fn(object $row): int => (int) $row->inventory_id, $releaseRows));
            $inventoryChanged = true;
        }

        $assignments = [];

        foreach ($keepRows as $index => $row) {
            $priceIndex = $targetSlots[$index];
            $order      = $index + 1;

            if ((int) $row->price_index !== $priceIndex || (int) $row->assignment_order !== $order) {
                $assignments[(int) $row->inventory_id] = [$priceIndex, $order];
            }
        }

        if ($assignments !== []) {
            $this->updateInventoryAssignments($cartId, $eventId, $assignments);
            $inventoryChanged = true;
        }

        if ($inventoryChanged) {
            $this->advanceInventory($eventId);
        }
    }

    /**
     * Shared mutation implementation for manual and suggested full replacements.
     *
     * @param   list<int>  $desiredSeatIds
     *
     * @return array<string, mixed>
     */
    private function mutateSeats(
        int $eventId,
        array $desiredSeatIds,
        int $expectedCartRevision,
        bool $suggest
    ): array {
        if ($eventId < 1 || $expectedCartRevision < 0) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_ERROR_INVALID'));
        }

        $this->cartContext->purgeExpiredCarts();
        $this->cartContext->beginTransaction();

        try {
            $cart = $this->cartContext->getActiveCartForUpdate();

            if ($cart === null) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_ERROR_CART'));
            }

            $this->lockEvent($eventId);
            $targetQuantities = $this->loadCartEventQuantities((int) $cart->id, $eventId);
            $requiredCount    = array_sum($targetQuantities);

            if ($requiredCount < 1) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_ERROR_EVENT'));
            }

            $assignment = $this->lockAssignment($eventId);

            if ($assignment === null
                || (int) $assignment->status !== EventSeatInventoryService::EVENT_STATUS_READY) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_EVENT_NOT_READY'));
            }

            $rows = $this->loadInventoryRowsForUpdate($eventId);
            $this->assertExactPublishedInventory($assignment, $rows);

            $inventoryChanged = $this->normaliseExpiredHolds($rows);
            $currentIds       = [];

            foreach ($rows as $row) {
                if ((int) $row->status === EventSeatInventoryService::SEAT_STATUS_HELD
                    && (int) $row->cart_id === (int) $cart->id) {
                    $currentIds[] = (int) $row->inventory_id;
                }
            }

            if ($suggest) {
                $this->cartContext->assertExpectedRevision($cart, $expectedCartRevision);
                $desiredSeatIds = $this->buildSuggestion($rows, (int) $cart->id, $requiredCount);
            }

            if (\count($desiredSeatIds) > $requiredCount) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_ERROR_LIMIT'));
            }

            $rowsById = [];

            foreach ($rows as $row) {
                $rowsById[(int) $row->inventory_id] = $row;
            }

            $desiredLookup = array_fill_keys($desiredSeatIds, true);

            foreach ($desiredSeatIds as $seatId) {
                if (!isset($rowsById[$seatId])) {
                    throw new \DomainException(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_ERROR_SEAT'));
                }

                $row = $rowsById[$seatId];

                if (!$this->rowCanBeSelected($row, (int) $cart->id)) {
                    throw new SeatSelectionConflictException(
                        Text::_('COM_COPYMYPAGE_SEAT_SELECTION_ERROR_CONFLICT')
                    );
                }

            }

            $orderedDesired = [];

            foreach ($rows as $row) {
                $inventoryId = (int) $row->inventory_id;

                if (isset($desiredLookup[$inventoryId])) {
                    $orderedDesired[] = $inventoryId;
                }
            }

            $currentSorted = $currentIds;
            sort($currentSorted, SORT_NUMERIC);
            $desiredSorted = $orderedDesired;
            sort($desiredSorted, SORT_NUMERIC);
            $sameSelection = $currentSorted === $desiredSorted;

            if (!$sameSelection && !$suggest) {
                $this->cartContext->assertExpectedRevision($cart, $expectedCartRevision);
            }

            if (!$sameSelection) {
                $this->releaseInventoryRows($currentIds);
                $priceSlots  = $this->buildPriceSlots($targetQuantities);
                $assignments = [];

                foreach ($orderedDesired as $index => $seatId) {
                    $assignments[$seatId] = [$priceSlots[$index], $index + 1];
                }

                if ($assignments !== []) {
                    $this->updateInventoryAssignments((int) $cart->id, $eventId, $assignments);
                }

                $inventoryChanged = true;
                $this->cartContext->advanceCart((int) $cart->id);
            }

            if ($inventoryChanged) {
                $this->advanceInventory($eventId);
            }

            $this->cartContext->commitTransaction();
        } catch (\Throwable $exception) {
            $this->cartContext->rollbackTransaction();

            if ($exception instanceof \DomainException) {
                throw $exception;
            }

            throw new \RuntimeException(
                Text::_('COM_COPYMYPAGE_SEAT_SELECTION_ERROR_SAVE'),
                0,
                $exception
            );
        }

        return $this->getEventState($eventId);
    }

    /**
     * @param   list<object>  $rows
     *
     * @return list<int>
     */
    private function buildSuggestion(array $rows, int $cartId, int $requiredCount): array
    {
        $byTable = [];

        foreach ($rows as $row) {
            if ($this->rowCanBeSelected($row, $cartId)) {
                $byTable[(int) $row->table_id][] = $row;
            }
        }

        $candidates = [];

        foreach ($byTable as $tableRows) {
            if (\count($tableRows) >= $requiredCount) {
                $candidates[] = $tableRows;
            }
        }

        if ($candidates !== []) {
            usort(
                $candidates,
                static fn(array $left, array $right): int => [\count($left), (int) $left[0]->table_sort]
                    <=> [\count($right), (int) $right[0]->table_sort]
            );

            return array_map(
                static fn(object $row): int => (int) $row->inventory_id,
                array_slice($candidates[0], 0, $requiredCount)
            );
        }

        $available = [];

        foreach ($byTable as $tableRows) {
            array_push($available, ...$tableRows);
        }

        if (\count($available) < $requiredCount) {
            throw new SeatSelectionConflictException(
                Text::_('COM_COPYMYPAGE_SEAT_SELECTION_ERROR_NOT_ENOUGH')
            );
        }

        return array_map(
            static fn(object $row): int => (int) $row->inventory_id,
            array_slice($available, 0, $requiredCount)
        );
    }

    private function rowCanBeSelected(object $row, int $cartId): bool
    {
        return (int) $row->status === EventSeatInventoryService::SEAT_STATUS_AVAILABLE
            || ((int) $row->status === EventSeatInventoryService::SEAT_STATUS_HELD
                && (int) $row->cart_id === $cartId);
    }

    /**
     * Convert validated relational rows to the public three-state projection.
     *
     * @param   list<int>  $eventIds
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadEventProjections(array $eventIds, int $cartId): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('a.event_id'),
                $this->db->quoteName('a.status', 'assignment_status'),
                $this->db->quoteName('a.inventory_version'),
                '(SELECT COUNT(*) FROM '
                    . $this->db->quoteName('#__copymypage_event_seats', 'all_inventory')
                    . ' WHERE ' . $this->db->quoteName('all_inventory.event_id')
                    . ' = ' . $this->db->quoteName('a.event_id') . ') AS '
                    . $this->db->quoteName('inventory_count'),
                $this->db->quoteName('l.id', 'layout_id'),
                $this->db->quoteName('l.status', 'layout_status'),
                $this->db->quoteName('l.title', 'layout_title'),
                $this->db->quoteName('l.logical_width'),
                $this->db->quoteName('l.logical_height'),
                $this->db->quoteName('l.geometry_json'),
                $this->db->quoteName('t.id', 'table_id'),
                $this->db->quoteName('t.table_code'),
                $this->db->quoteName('t.table_number'),
                $this->db->quoteName('t.label', 'table_label'),
                $this->db->quoteName('t.shape', 'table_shape'),
                $this->db->quoteName('t.x', 'table_x'),
                $this->db->quoteName('t.y', 'table_y'),
                $this->db->quoteName('t.width', 'table_width'),
                $this->db->quoteName('t.height', 'table_height'),
                $this->db->quoteName('t.rotation', 'table_rotation'),
                $this->db->quoteName('t.sort_order', 'table_sort'),
                $this->db->quoteName('s.id', 'seat_id'),
                $this->db->quoteName('s.seat_code'),
                $this->db->quoteName('s.seat_number'),
                $this->db->quoteName('s.x', 'seat_x'),
                $this->db->quoteName('s.y', 'seat_y'),
                $this->db->quoteName('s.sort_order', 'seat_sort'),
                $this->db->quoteName('i.id', 'inventory_id'),
                $this->db->quoteName('i.status', 'inventory_status'),
                $this->db->quoteName('i.cart_id'),
                $this->db->quoteName('c.status', 'held_cart_status'),
                $this->db->quoteName('c.expires_at', 'held_cart_expires'),
            ])
            ->from($this->db->quoteName('#__copymypage_event_seating', 'a'))
            ->innerJoin($this->db->quoteName('#__copymypage_seat_layouts', 'l')
                . ' ON ' . $this->db->quoteName('l.id') . ' = ' . $this->db->quoteName('a.layout_id'))
            ->innerJoin($this->db->quoteName('#__copymypage_layout_tables', 't')
                . ' ON ' . $this->db->quoteName('t.layout_id') . ' = ' . $this->db->quoteName('l.id'))
            ->innerJoin($this->db->quoteName('#__copymypage_seats', 's')
                . ' ON ' . $this->db->quoteName('s.layout_table_id') . ' = ' . $this->db->quoteName('t.id'))
            ->leftJoin($this->db->quoteName('#__copymypage_event_seats', 'i')
                . ' ON ' . $this->db->quoteName('i.event_id') . ' = ' . $this->db->quoteName('a.event_id')
                . ' AND ' . $this->db->quoteName('i.seat_id') . ' = ' . $this->db->quoteName('s.id'))
            ->leftJoin($this->db->quoteName('#__copymypage_ticket_carts', 'c')
                . ' ON ' . $this->db->quoteName('c.id') . ' = ' . $this->db->quoteName('i.cart_id'))
            ->where($this->db->quoteName('a.event_id') . ' IN (' . implode(',', $eventIds) . ')')
            ->order($this->db->quoteName('a.event_id') . ' ASC')
            ->order($this->db->quoteName('t.sort_order') . ' ASC')
            ->order($this->db->quoteName('s.sort_order') . ' ASC')
            ->order($this->db->quoteName('s.id') . ' ASC');
        $result = [];
        $now    = $this->cartContext->now();

        foreach ((array) $this->db->setQuery($query)->loadObjectList() as $row) {
            $eventId = (int) $row->event_id;

            if (!isset($result[$eventId])) {
                $geometry = json_decode((string) $row->geometry_json, true);
                $result[$eventId] = [
                    'complete'         => false,
                    'continuable'      => false,
                    'dateLabel'        => '',
                    'dateTime'         => '',
                    'id'               => $eventId,
                    'inventoryVersion' => (int) $row->inventory_version,
                    'inventoryCount'   => (int) $row->inventory_count,
                    'layoutPublished'  => (int) $row->layout_status === SeatLayoutService::STATUS_PUBLISHED,
                    'layout'           => [
                        'areas'  => \is_array($geometry['areas'] ?? null) ? $geometry['areas'] : [],
                        'height' => (int) $row->logical_height,
                        'id'     => (int) $row->layout_id,
                        'tables' => [],
                        'title'  => (string) $row->layout_title,
                        'width'  => (int) $row->logical_width,
                    ],
                    'materializedCount' => 0,
                    'message'           => '',
                    'ready'             => (int) $row->assignment_status
                        === EventSeatInventoryService::EVENT_STATUS_READY,
                    'requiredCount'     => 0,
                    'selectedCount'     => 0,
                    'selectedSeats'     => [],
                    'statusesValid'     => true,
                    'title'             => '',
                ];
            }

            $tableId = (int) $row->table_id;

            if (!isset($result[$eventId]['layout']['tables'][$tableId])) {
                $result[$eventId]['layout']['tables'][$tableId] = [
                    'code'      => (string) $row->table_code,
                    'height'    => (int) $row->table_height,
                    'id'        => $tableId,
                    'label'     => (string) $row->table_label,
                    'number'    => (string) $row->table_number,
                    'rotation'  => (int) $row->table_rotation,
                    'seats'     => [],
                    'shape'     => (string) $row->table_shape,
                    'sortOrder' => (int) $row->table_sort,
                    'width'     => (int) $row->table_width,
                    'x'         => (int) $row->table_x,
                    'y'         => (int) $row->table_y,
                ];
            }

            $inventoryId = (int) ($row->inventory_id ?? 0);
            $seatStatus  = 'unavailable';

            if ($inventoryId > 0) {
                $result[$eventId]['materializedCount']++;
                $inventoryStatus = (int) $row->inventory_status;
                $result[$eventId]['statusesValid'] = $result[$eventId]['statusesValid']
                    && \in_array(
                        $inventoryStatus,
                        [
                            EventSeatInventoryService::SEAT_STATUS_AVAILABLE,
                            EventSeatInventoryService::SEAT_STATUS_HELD,
                            EventSeatInventoryService::SEAT_STATUS_BOOKED,
                            EventSeatInventoryService::SEAT_STATUS_BLOCKED,
                        ],
                        true
                    );
                $holdActive      = (int) ($row->held_cart_status ?? -1)
                    === TicketCartContextService::STATUS_ACTIVE
                    && (string) ($row->held_cart_expires ?? '') > $now;

                if ($inventoryStatus === EventSeatInventoryService::SEAT_STATUS_AVAILABLE
                    || ($inventoryStatus === EventSeatInventoryService::SEAT_STATUS_HELD && !$holdActive)) {
                    $seatStatus = 'available';
                } elseif ($inventoryStatus === EventSeatInventoryService::SEAT_STATUS_HELD
                    && $holdActive
                    && (int) $row->cart_id === $cartId) {
                    $seatStatus = 'selected';
                }
            }

            $seat = [
                'code'      => (string) $row->seat_code,
                'id'        => $inventoryId,
                'label'     => Text::sprintf(
                    'COM_COPYMYPAGE_SEAT_SELECTION_SEAT_LABEL',
                    (string) $row->table_number,
                    (string) $row->seat_number
                ),
                'number'    => (string) $row->seat_number,
                'sortOrder' => (int) $row->seat_sort,
                'status'    => $seatStatus,
                'x'         => (int) $row->seat_x,
                'y'         => (int) $row->seat_y,
            ];
            $result[$eventId]['layout']['tables'][$tableId]['seats'][] = $seat;

            if ($seatStatus === 'selected') {
                $result[$eventId]['selectedSeats'][] = [
                    'id'          => $inventoryId,
                    'label'       => $seat['label'],
                    'seatNumber'  => $seat['number'],
                    'tableNumber' => (string) $row->table_number,
                ];
            }
        }

        foreach ($result as &$event) {
            $event['layout']['tables'] = array_values($event['layout']['tables']);
            $layoutCount = array_sum(array_map(
                static fn(array $table): int => \count($table['seats']),
                $event['layout']['tables']
            ));
            $event['ready'] = $event['ready']
                && $layoutCount > 0
                && $layoutCount <= self::MAX_SEAT_IDS
                && $event['materializedCount'] === $layoutCount
                && $event['inventoryCount'] === $layoutCount
                && $event['layoutPublished']
                && $event['statusesValid'];
            unset($event['inventoryCount'], $event['layoutPublished'], $event['statusesValid']);
        }
        unset($event);

        return $result;
    }

    /**
     * Lock all materialized event seats in deterministic layout order.
     *
     * @return list<object>
     */
    private function loadInventoryRowsForUpdate(int $eventId): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('i.id', 'inventory_id'),
                $this->db->quoteName('i.seat_id'),
                $this->db->quoteName('i.status'),
                $this->db->quoteName('i.cart_id'),
                $this->db->quoteName('i.price_index'),
                $this->db->quoteName('i.assignment_order'),
                $this->db->quoteName('t.id', 'table_id'),
                $this->db->quoteName('t.sort_order', 'table_sort'),
                $this->db->quoteName('s.sort_order', 'seat_sort'),
            ])
            ->from($this->db->quoteName('#__copymypage_event_seats', 'i'))
            ->innerJoin($this->db->quoteName('#__copymypage_seats', 's')
                . ' ON ' . $this->db->quoteName('s.id') . ' = ' . $this->db->quoteName('i.seat_id'))
            ->innerJoin($this->db->quoteName('#__copymypage_layout_tables', 't')
                . ' ON ' . $this->db->quoteName('t.id') . ' = ' . $this->db->quoteName('s.layout_table_id'))
            ->where($this->db->quoteName('i.event_id') . ' = ' . $eventId)
            ->order($this->db->quoteName('t.sort_order') . ' ASC')
            ->order($this->db->quoteName('s.sort_order') . ' ASC')
            ->order($this->db->quoteName('i.id') . ' ASC');

        return (array) $this->db->setQuery((string) $query . ' FOR UPDATE')->loadObjectList();
    }

    /**
     * Release held rows whose owning cart is no longer active and unexpired.
     *
     * @param   list<object>  $rows
     */
    private function normaliseExpiredHolds(array &$rows): bool
    {
        $cartIds = [];

        foreach ($rows as $row) {
            if ((int) $row->status === EventSeatInventoryService::SEAT_STATUS_HELD
                && (int) $row->cart_id > 0) {
                $cartIds[] = (int) $row->cart_id;
            }
        }

        $cartIds = array_values(array_unique($cartIds));

        if ($cartIds === []) {
            return false;
        }

        sort($cartIds, SORT_NUMERIC);
        $now   = $this->cartContext->now();
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__copymypage_ticket_carts'))
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', $cartIds) . ')')
            ->where($this->db->quoteName('status') . ' = ' . TicketCartContextService::STATUS_ACTIVE)
            ->where($this->db->quoteName('expires_at') . ' > ' . $this->db->quote($now));
        $activeCartIds = array_fill_keys(array_map('intval', $this->db->setQuery($query)->loadColumn()), true);
        $releaseIds    = [];

        foreach ($rows as $row) {
            if ((int) $row->status === EventSeatInventoryService::SEAT_STATUS_HELD
                && !isset($activeCartIds[(int) $row->cart_id])) {
                $releaseIds[]          = (int) $row->inventory_id;
                $row->status           = EventSeatInventoryService::SEAT_STATUS_AVAILABLE;
                $row->cart_id          = null;
                $row->price_index      = null;
                $row->assignment_order = null;
            }
        }

        if ($releaseIds === []) {
            return false;
        }

        $this->releaseInventoryRows($releaseIds);

        return true;
    }

    /**
     * @param   list<int>  $inventoryIds
     */
    private function releaseInventoryRows(array $inventoryIds): void
    {
        $inventoryIds = array_values(array_unique(array_filter(array_map('intval', $inventoryIds))));

        if ($inventoryIds === []) {
            return;
        }

        sort($inventoryIds, SORT_NUMERIC);
        $now   = $this->cartContext->now();
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__copymypage_event_seats'))
            ->set($this->db->quoteName('status') . ' = ' . EventSeatInventoryService::SEAT_STATUS_AVAILABLE)
            ->set($this->db->quoteName('cart_id') . ' = NULL')
            ->set($this->db->quoteName('price_index') . ' = NULL')
            ->set($this->db->quoteName('assignment_order') . ' = NULL')
            ->set($this->db->quoteName('ticket_id') . ' = NULL')
            ->set($this->db->quoteName('block_note') . ' = ' . $this->db->quote(''))
            ->set($this->db->quoteName('modified') . ' = ' . $this->db->quote($now))
            ->set($this->db->quoteName('modified_by') . ' = 0')
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', $inventoryIds) . ')')
            ->where($this->db->quoteName('status') . ' = ' . EventSeatInventoryService::SEAT_STATUS_HELD);
        $this->db->setQuery($query)->execute();
    }

    /**
     * @param   array<int, array{0: int, 1: int}>  $assignments
     */
    private function updateInventoryAssignments(
        int $cartId,
        int $eventId,
        array $assignments
    ): void {
        if ($assignments === []) {
            return;
        }

        ksort($assignments, SORT_NUMERIC);
        $priceCases = [];
        $orderCases = [];
        $ids        = [];

        foreach ($assignments as $inventoryId => [$priceIndex, $order]) {
            $inventoryId = (int) $inventoryId;
            $ids[]        = $inventoryId;
            $priceCases[] = 'WHEN ' . $inventoryId . ' THEN ' . max(0, (int) $priceIndex);
            $orderCases[] = 'WHEN ' . $inventoryId . ' THEN ' . max(1, (int) $order);
        }

        $now   = $this->cartContext->now();
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__copymypage_event_seats'))
            ->set($this->db->quoteName('status') . ' = ' . EventSeatInventoryService::SEAT_STATUS_HELD)
            ->set($this->db->quoteName('cart_id') . ' = ' . $cartId)
            ->set($this->db->quoteName('price_index') . ' = CASE ' . $this->db->quoteName('id')
                . ' ' . implode(' ', $priceCases) . ' END')
            ->set($this->db->quoteName('assignment_order') . ' = CASE ' . $this->db->quoteName('id')
                . ' ' . implode(' ', $orderCases) . ' END')
            ->set($this->db->quoteName('modified') . ' = ' . $this->db->quote($now))
            ->set($this->db->quoteName('modified_by') . ' = 0')
            ->where($this->db->quoteName('event_id') . ' = ' . $eventId)
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');
        $this->db->setQuery($query)->execute();
    }

    private function advanceInventory(int $eventId): void
    {
        $now   = $this->cartContext->now();
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__copymypage_event_seating'))
            ->set($this->db->quoteName('inventory_version') . ' = '
                . $this->db->quoteName('inventory_version') . ' + 1')
            ->set($this->db->quoteName('modified') . ' = ' . $this->db->quote($now))
            ->set($this->db->quoteName('modified_by') . ' = 0')
            ->where($this->db->quoteName('event_id') . ' = ' . $eventId);
        $this->db->setQuery($query)->execute();
    }

    private function lockEvent(int $eventId): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__dpcalendar_events'))
            ->where($this->db->quoteName('id') . ' = ' . $eventId);

        if ((int) $this->db->setQuery((string) $query . ' FOR UPDATE')->loadResult() !== $eventId) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_ERROR_EVENT'));
        }
    }

    /**
     * Return the exact seat IDs of one published immutable layout version.
     *
     * @return list<int>
     */
    private function loadPublishedLayoutSeatIds(int $layoutId): array
    {
        if ($layoutId < 1) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('s.id'))
            ->from($this->db->quoteName('#__copymypage_seat_layouts', 'l'))
            ->innerJoin($this->db->quoteName('#__copymypage_layout_tables', 't')
                . ' ON ' . $this->db->quoteName('t.layout_id') . ' = ' . $this->db->quoteName('l.id'))
            ->innerJoin($this->db->quoteName('#__copymypage_seats', 's')
                . ' ON ' . $this->db->quoteName('s.layout_table_id') . ' = ' . $this->db->quoteName('t.id'))
            ->where($this->db->quoteName('l.id') . ' = :layoutId')
            ->where($this->db->quoteName('l.status') . ' = ' . SeatLayoutService::STATUS_PUBLISHED)
            ->order($this->db->quoteName('t.sort_order') . ' ASC')
            ->order($this->db->quoteName('s.sort_order') . ' ASC')
            ->order($this->db->quoteName('s.id') . ' ASC')
            ->bind(':layoutId', $layoutId, ParameterType::INTEGER);

        return array_map('intval', (array) $this->db->setQuery($query)->loadColumn());
    }

    /**
     * Fail closed unless every and only the assigned layout's seats exist.
     *
     * @param   list<object>  $rows
     */
    private function assertExactPublishedInventory(object $assignment, array $rows): void
    {
        $expectedSeatIds = $this->loadPublishedLayoutSeatIds((int) ($assignment->layout_id ?? 0));
        $actualSeatIds   = array_map(
            static fn(object $row): int => (int) $row->seat_id,
            $rows
        );
        $sortedExpectedSeatIds = $expectedSeatIds;
        $sortedActualSeatIds   = $actualSeatIds;
        sort($sortedExpectedSeatIds, SORT_NUMERIC);
        sort($sortedActualSeatIds, SORT_NUMERIC);
        $statusesValid = true;

        foreach ($rows as $row) {
            if (!\in_array(
                (int) $row->status,
                [
                    EventSeatInventoryService::SEAT_STATUS_AVAILABLE,
                    EventSeatInventoryService::SEAT_STATUS_HELD,
                    EventSeatInventoryService::SEAT_STATUS_BOOKED,
                    EventSeatInventoryService::SEAT_STATUS_BLOCKED,
                ],
                true
            )) {
                $statusesValid = false;

                break;
            }
        }

        if (
            $expectedSeatIds === []
            || \count($expectedSeatIds) > self::MAX_SEAT_IDS
            || $sortedExpectedSeatIds !== $sortedActualSeatIds
            || !$statusesValid
        ) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_EVENT_NOT_READY'));
        }
    }

    private function lockAssignment(int $eventId): ?object
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__copymypage_event_seating'))
            ->where($this->db->quoteName('event_id') . ' = ' . $eventId);
        $row = $this->db->setQuery((string) $query . ' FOR UPDATE')->loadObject();

        return \is_object($row) ? $row : null;
    }

    /**
     * @return array<int, int>
     */
    private function loadCartEventQuantities(int $cartId, int $eventId): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['price_index', 'quantity']))
            ->from($this->db->quoteName('#__copymypage_ticket_cart_items'))
            ->where($this->db->quoteName('cart_id') . ' = :cartId')
            ->where($this->db->quoteName('event_id') . ' = :eventId')
            ->order($this->db->quoteName('price_index') . ' ASC')
            ->bind(':cartId', $cartId, ParameterType::INTEGER)
            ->bind(':eventId', $eventId, ParameterType::INTEGER);
        $result = [];

        foreach ((array) $this->db->setQuery($query)->loadObjectList() as $row) {
            $quantity = max(0, (int) $row->quantity);

            if ($quantity > 0) {
                $result[(int) $row->price_index] = $quantity;
            }
        }

        return $result;
    }

    /**
     * @return list<object>
     */
    private function loadCartItems(int $cartId): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['event_id', 'price_index', 'quantity']))
            ->from($this->db->quoteName('#__copymypage_ticket_cart_items'))
            ->where($this->db->quoteName('cart_id') . ' = :cartId')
            ->where($this->db->quoteName('quantity') . ' > 0')
            ->order($this->db->quoteName('event_id') . ' ASC')
            ->order($this->db->quoteName('price_index') . ' ASC')
            ->bind(':cartId', $cartId, ParameterType::INTEGER);

        return (array) $this->db->setQuery($query)->loadObjectList();
    }

    /**
     * @param   list<object>  $rows
     *
     * @return array<int, array<int, int>>
     */
    private function buildCartTargets(array $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            $eventId  = (int) $row->event_id;
            $quantity = max(0, (int) $row->quantity);

            if ($eventId > 0 && $quantity > 0) {
                $result[$eventId][(int) $row->price_index] = $quantity;
            }
        }

        ksort($result, SORT_NUMERIC);

        return $result;
    }

    /**
     * @param   array<int, int>  $quantities
     *
     * @return list<int>
     */
    private function buildPriceSlots(array $quantities): array
    {
        ksort($quantities, SORT_NUMERIC);
        $slots = [];

        foreach ($quantities as $priceIndex => $quantity) {
            for ($slot = 0; $slot < max(0, (int) $quantity); $slot++) {
                $slots[] = (int) $priceIndex;
            }
        }

        return $slots;
    }

    /**
     * @param   array<int|string, mixed>  $rawSeatIds
     *
     * @return list<int>
     */
    private function normaliseSeatIds(array $rawSeatIds): array
    {
        if (\count($rawSeatIds) > self::MAX_SEAT_IDS) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_ERROR_LIMIT'));
        }

        $result = [];

        foreach ($rawSeatIds as $rawSeatId) {
            $seatId = filter_var(
                $rawSeatId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX]]
            );

            if ($seatId === false || isset($result[(int) $seatId])) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_ERROR_INVALID'));
            }

            $result[(int) $seatId] = true;
        }

        $seatIds = array_keys($result);
        sort($seatIds, SORT_NUMERIC);

        return $seatIds;
    }

    /**
     * @param   array<int, int|string>  $eventIds
     *
     * @return list<int>
     */
    private function normaliseEventIds(array $eventIds): array
    {
        $result = [];

        foreach ($eventIds as $eventId) {
            $validated = filter_var(
                $eventId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX]]
            );

            if ($validated !== false) {
                $result[(int) $validated] = true;
            }
        }

        $ids = array_keys($result);
        sort($ids, SORT_NUMERIC);

        return array_slice($ids, 0, 50);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUnavailableEvent(
        int $eventId,
        ?\stdClass $event,
        int $requiredCount,
        bool $continuable
    ): array {
        return [
            'complete'         => false,
            'continuable'      => $continuable,
            'dateLabel'        => $this->formatEventDateLabel($event),
            'dateTime'         => $this->formatEventDateTime($event),
            'id'               => $eventId,
            'inventoryVersion' => 0,
            'layout'           => [
                'areas' => [],
                'height' => 1,
                'id' => 0,
                'tables' => [],
                'title' => '',
                'width' => 1,
            ],
            'materializedCount' => 0,
            'message'           => Text::_('COM_COPYMYPAGE_SEAT_SELECTION_EVENT_NOT_READY'),
            'ready'             => false,
            'requiredCount'     => $requiredCount,
            'selectedCount'     => 0,
            'selectedSeats'     => [],
            'title'             => $this->eventTitle($eventId, $event),
        ];
    }

    /**
     * @param   array<int, array<string, mixed>>  $events
     */
    private function resolveSelectedEventId(array $events, int $requestedEventId): int
    {
        foreach ($events as $event) {
            if ((int) $event['id'] === $requestedEventId) {
                return $requestedEventId;
            }
        }

        foreach ($events as $event) {
            if (empty($event['complete'])) {
                return (int) $event['id'];
            }
        }

        return (int) ($events[0]['id'] ?? 0);
    }

    private function eventTitle(int $eventId, ?\stdClass $event): string
    {
        $title = trim((string) ($event->title ?? ''));

        return $title !== ''
            ? $title
            : Text::sprintf('COM_COPYMYPAGE_TICKET_SELECTION_CART_EVENT_FALLBACK', $eventId);
    }

    private function formatEventDateLabel(?\stdClass $event): string
    {
        if ($event === null || trim((string) ($event->start_date ?? '')) === '') {
            return '';
        }

        $start = DPCalendarHelper::getDate((string) $event->start_date);

        if (!empty($event->all_day)) {
            return $start->format(Text::_('DATE_FORMAT_LC1'), true);
        }

        $end = DPCalendarHelper::getDate((string) ($event->end_date ?? $event->start_date));

        return Text::sprintf(
            'COM_COPYMYPAGE_TICKET_SELECTION_DATE_TIME',
            $start->format(Text::_('DATE_FORMAT_LC1'), true),
            $start->format('H:i', true),
            $end->format('H:i', true)
        );
    }

    private function formatEventDateTime(?\stdClass $event): string
    {
        if ($event === null || trim((string) ($event->start_date ?? '')) === '') {
            return '';
        }

        return DPCalendarHelper::getDate((string) $event->start_date)->format('c', true, false);
    }

    private function toIso8601(string $date): string
    {
        $timestamp = strtotime($date . ' UTC');

        return $timestamp === false ? '' : gmdate('c', $timestamp);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyState(): array
    {
        return [
            'allComplete'     => false,
            'cart'            => [
                'active'       => false,
                'cartRevision' => 0,
                'expiresAt'    => '',
                'secondsLeft'  => 0,
                'totalTickets' => 0,
            ],
            'events'          => [],
            'selectedEventId' => 0,
        ];
    }
}
