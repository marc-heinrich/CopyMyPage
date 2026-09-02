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
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Owns temporary multi-event carts without modifying DPCalendar core data.
 */
final class TicketReservationService
{
    private const ROOT_ATTRIBUTE = 'data-cmp-ticket-selection';

    private const INITIALIZED_ATTRIBUTE = 'data-cmp-ticket-selection-initialized';

    private const EVENT_ATTRIBUTE = 'data-cmp-ticket-selection-event';

    private const EVENT_ID_ATTRIBUTE = 'data-cmp-ticket-event-id';

    private const EVENT_ANCHOR_PREFIX = 'cmp-ticket-selection-event-';

    private const EVENT_FORM_ATTRIBUTE = 'data-cmp-ticket-selection-form';

    private const EVENT_STATUS_ATTRIBUTE = 'data-cmp-ticket-selection-status';

    private const EVENT_AVAILABLE_ATTRIBUTE = 'data-cmp-ticket-selection-available';

    private const QUANTITY_ATTRIBUTE = 'data-cmp-ticket-selection-quantity';

    private const CONTINUE_ATTRIBUTE = 'data-cmp-ticket-selection-continue';

    private const CART_ATTRIBUTE = 'data-cmp-ticket-cart';

    private const CART_EMPTY_ATTRIBUTE = 'data-cmp-ticket-cart-empty';

    private const CART_EXPIRY_ATTRIBUTE = 'data-cmp-ticket-cart-expiry';

    private const CART_ITEMS_ATTRIBUTE = 'data-cmp-ticket-cart-items';

    private const CART_SUMMARY_ATTRIBUTE = 'data-cmp-ticket-cart-summary';

    private const CART_TOTAL_ATTRIBUTE = 'data-cmp-ticket-cart-total';

    private const CLEAR_FORM_ATTRIBUTE = 'data-cmp-ticket-cart-clear';

    private const REMOVE_FORM_ATTRIBUTE = 'data-cmp-ticket-cart-remove';

    private const BASKET_INDICATOR_ATTRIBUTE = 'data-cmp-ticket-cart-indicator';

    private const BASKET_INDICATOR_EXPIRY_ATTRIBUTE = 'data-cmp-ticket-cart-indicator-expires';

    private const REVISION_FIELD_ATTRIBUTE = 'data-cmp-ticket-cart-revision';

    private const EXPECTED_REVISION_FIELD = 'expectedCartRevision';

    private const BASKET_MESSAGE_TYPE = 'copymypage:ticket-cart-state';

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly TicketCatalogService $catalog,
        private readonly TicketCartContextService $cartContext,
        private readonly SeatSelectionService $seatSelection
    ) {
    }

    /**
     * Stable selectors, endpoints and localized messages for the browser runtime.
     *
     * @return array<string, mixed>
     */
    public function getClientConfig(): array
    {
        return [
            'rootSelector'         => '[' . self::ROOT_ATTRIBUTE . ']',
            'initializedAttribute' => self::INITIALIZED_ATTRIBUTE,
            'selectors'            => [
                'event'           => '[' . self::EVENT_ATTRIBUTE . ']',
                'eventForm'       => '[' . self::EVENT_FORM_ATTRIBUTE . ']',
                'eventStatus'     => '[' . self::EVENT_STATUS_ATTRIBUTE . ']',
                'eventAvailable'  => '[' . self::EVENT_AVAILABLE_ATTRIBUTE . ']',
                'quantity'        => '[' . self::QUANTITY_ATTRIBUTE . ']',
                'continue'        => '[' . self::CONTINUE_ATTRIBUTE . ']',
                'cart'            => '[' . self::CART_ATTRIBUTE . ']',
                'cartEmpty'       => '[' . self::CART_EMPTY_ATTRIBUTE . ']',
                'cartExpiry'      => '[' . self::CART_EXPIRY_ATTRIBUTE . ']',
                'cartItems'       => '[' . self::CART_ITEMS_ATTRIBUTE . ']',
                'cartSummary'     => '[' . self::CART_SUMMARY_ATTRIBUTE . ']',
                'cartTotal'       => '[' . self::CART_TOTAL_ATTRIBUTE . ']',
                'clearForm'       => '[' . self::CLEAR_FORM_ATTRIBUTE . ']',
                'removeForm'      => '[' . self::REMOVE_FORM_ATTRIBUTE . ']',
                'revisionField'   => '[' . self::REVISION_FIELD_ATTRIBUTE . ']',
                'basketIndicator' => '[' . self::BASKET_INDICATOR_ATTRIBUTE . ']',
            ],
            'attributes'           => [
                'basketIndicator'       => self::BASKET_INDICATOR_ATTRIBUTE,
                'basketIndicatorExpiry' => self::BASKET_INDICATOR_EXPIRY_ATTRIBUTE,
                'eventId'               => self::EVENT_ID_ATTRIBUTE,
                'removeForm'            => self::REMOVE_FORM_ATTRIBUTE,
                'revisionField'         => self::REVISION_FIELD_ATTRIBUTE,
            ],
            'fields'               => [
                'expectedCartRevision' => self::EXPECTED_REVISION_FIELD,
            ],
            'basketMessageType'    => self::BASKET_MESSAGE_TYPE,
            'csrfToken'            => Session::getFormToken(),
            'endpoints'            => [
                'reserve' => Route::_(
                    'index.php?option=com_copymypage&task=ticketcart.reserve&format=json',
                    false
                ),
                'remove' => Route::_(
                    'index.php?option=com_copymypage&task=ticketcart.remove&format=json',
                    false
                ),
                'clear' => Route::_(
                    'index.php?option=com_copymypage&task=ticketcart.clear&format=json',
                    false
                ),
            ],
            'fallbackActions'      => [
                'remove' => Route::_(
                    'index.php?option=com_copymypage&task=ticketcart.remove',
                    false
                ),
            ],
            'strings'              => [
                'clear'             => Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CART_CLEAR'),
                'countdown'         => Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CART_COUNTDOWN'),
                'empty'             => Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CART_EMPTY'),
                'expired'           => Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CART_EXPIRED'),
                'expires'           => Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CART_EXPIRES'),
                'remove'            => Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CART_REMOVE'),
                'removeAria'        => Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CART_REMOVE_ARIA'),
                'requestFailed'     => Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_REQUEST'),
                'runtimeMissing'    => Text::_('COM_COPYMYPAGE_TICKET_SELECTION_JS_RUNTIME_MISSING'),
                'tickets'           => Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CART_TICKETS'),
                'total'             => Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CART_TOTAL'),
                'update'            => Text::_('COM_COPYMYPAGE_TICKET_SELECTION_UPDATE'),
            ],
        ];
    }

    /**
     * Attribute contract consumed by the PHP template and JavaScript config.
     *
     * @return array<string, string>
     */
    public function getMarkupAttributes(): array
    {
        return [
            'root'            => self::ROOT_ATTRIBUTE,
            'event'           => self::EVENT_ATTRIBUTE,
            'eventId'         => self::EVENT_ID_ATTRIBUTE,
            'eventForm'       => self::EVENT_FORM_ATTRIBUTE,
            'eventStatus'     => self::EVENT_STATUS_ATTRIBUTE,
            'eventAvailable'  => self::EVENT_AVAILABLE_ATTRIBUTE,
            'quantity'        => self::QUANTITY_ATTRIBUTE,
            'continue'        => self::CONTINUE_ATTRIBUTE,
            'cart'            => self::CART_ATTRIBUTE,
            'cartEmpty'       => self::CART_EMPTY_ATTRIBUTE,
            'cartExpiry'      => self::CART_EXPIRY_ATTRIBUTE,
            'cartItems'       => self::CART_ITEMS_ATTRIBUTE,
            'cartSummary'     => self::CART_SUMMARY_ATTRIBUTE,
            'cartTotal'       => self::CART_TOTAL_ATTRIBUTE,
            'clearForm'       => self::CLEAR_FORM_ATTRIBUTE,
            'removeForm'      => self::REMOVE_FORM_ATTRIBUTE,
            'revisionField'   => self::REVISION_FIELD_ATTRIBUTE,
            'basketIndicator'       => self::BASKET_INDICATOR_ATTRIBUTE,
            'basketIndicatorExpiry' => self::BASKET_INDICATOR_EXPIRY_ATTRIBUTE,
        ];
    }

    /**
     * Stable mutation field names shared by PHP templates and JavaScript.
     *
     * @return array<string, string>
     */
    public function getFormFieldNames(): array
    {
        return [
            'expectedCartRevision' => self::EXPECTED_REVISION_FIELD,
        ];
    }

    /**
     * Link used by the landing cards to open one accordion entry.
     */
    public function getSelectionUrl(int $eventId): string
    {
        $url = 'index.php?option=com_copymypage&view=ticketselection';

        if ($eventId > 0) {
            $url .= '&event_id=' . $eventId;
        }

        $url = Route::_($url, false);

        return $eventId > 0
            ? $url . '#' . $this->getSelectionAnchorId($eventId)
            : $url;
    }

    /**
     * Stable fragment target shared by landing links and accordion cards.
     */
    public function getSelectionAnchorId(int $eventId): string
    {
        return self::EVENT_ANCHOR_PREFIX . max(0, $eventId);
    }

    /**
     * Complete state for the server-rendered view and mutation responses.
     *
     * @return array<string, mixed>
     */
    public function getSelectionState(int $requestedEventId = 0): array
    {
        $events   = $this->catalog->getUpcomingEvents();
        $eventIds = array_map(static fn(\stdClass $event): int => (int) $event->id, $events);
        $held     = $this->getHeldQuantities($eventIds);
        $seating  = $this->seatSelection->getInventoryConstraints($eventIds);
        $cart     = $this->cartContext->getActiveCart();
        $rows     = $cart === null ? [] : $this->loadCartItems((int) $cart->id);
        $current  = $this->mapCurrentQuantities($rows);
        $items    = [];

        foreach ($events as $event) {
            $eventId      = (int) $event->id;
            $availability = $this->buildAvailability(
                $event,
                $held[$eventId] ?? 0,
                $seating[$eventId] ?? null
            );
            $eventCurrent = $current[$eventId] ?? [];
            $currentTotal = array_sum($eventCurrent);
            $remainingForOwnChange = $availability['capacity'] === null
                ? $this->catalog->getMaximumTickets($event)
                : $availability['remaining'] + $currentTotal;
            $prices = [];

            foreach ($this->catalog->getPriceTypes($event) as $price) {
                $priceIndex = (int) $price['index'];
                $quantity   = max(0, (int) ($eventCurrent[$priceIndex] ?? 0));
                $maximum    = min(
                    $this->catalog->getMaximumTickets($event),
                    max(0, (int) $price['limit']),
                    max(0, $remainingForOwnChange)
                );

                $prices[] = [
                    'index'          => $priceIndex,
                    'label'          => $price['label'],
                    'description'    => $price['description'],
                    'value'          => $price['value'],
                    'currency'       => $price['currency'],
                    'formattedPrice' => $this->catalog->formatPrice($price['value']),
                    'limit'          => $maximum,
                    'quantity'       => min($quantity, $maximum),
                ];
            }

            $items[] = [
                'anchorId'      => $this->getSelectionAnchorId($eventId),
                'id'            => $eventId,
                'title'         => trim((string) ($event->title ?? '')),
                'dateTime'      => $this->formatEventDateTime($event),
                'dateLabel'     => $this->formatEventDateLabel($event),
                'availability'  => $availability,
                'prices'        => $prices,
                'cartQuantity'  => $currentTotal,
                'canReserve'    => $availability['bookable'] || $currentTotal > 0,
                'submitLabel'   => $currentTotal > 0
                    ? Text::_('COM_COPYMYPAGE_TICKET_SELECTION_UPDATE')
                    : Text::_('COM_COPYMYPAGE_TICKET_SELECTION_RESERVE'),
            ];
        }

        $selectedEventId = $this->resolveSelectedEventId($items, $requestedEventId, $rows);

        return [
            'events'          => $items,
            'availability'    => $this->indexAvailability($items),
            'cart'            => $this->buildCartState($cart, $rows, $items),
            'selectedEventId' => $selectedEventId,
        ];
    }

    /**
     * Current session cart for drawer and navbar presentation.
     *
     * @return array<string, mixed>
     */
    public function getCartState(): array
    {
        $state = $this->getSelectionState();

        return \is_array($state['cart'] ?? null) ? $state['cart'] : [];
    }

    /**
     * Lightweight initial-state check for navbar basket indicators.
     */
    public function hasActiveCartItems(): bool
    {
        return $this->getBasketIndicatorState()['active'];
    }

    /**
     * Lightweight session state for server-rendered and live basket indicators.
     *
     * @return array{active: bool, expiresAt: string}
     */
    public function getBasketIndicatorState(): array
    {
        $cart   = $this->cartContext->getActiveCart();
        $active = $cart !== null && $this->countCartItems((int) $cart->id) > 0;

        return [
            'active'    => $active,
            'expiresAt' => $active ? $this->toIso8601((string) ($cart->expires_at ?? '')) : '',
        ];
    }

    /**
     * Public read-only availability used by landing pages.
     *
     * @param   array<int, int|string>  $requestedEventIds  Event IDs present in the page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailabilitySnapshot(array $requestedEventIds = []): array
    {
        $requestedEventIds = $this->normalizeEventIds($requestedEventIds);
        $events            = $this->catalog->getUpcomingEvents();

        if ($requestedEventIds !== []) {
            $events = array_values(array_filter(
                $events,
                static fn(\stdClass $event): bool => \in_array(
                    (int) $event->id,
                    $requestedEventIds,
                    true
                )
            ));
        }

        $eventIds = array_map(static fn(\stdClass $event): int => (int) $event->id, $events);
        $held     = $this->getHeldQuantities($eventIds);
        $seating  = $this->seatSelection->getInventoryConstraints($eventIds);
        $result   = [];

        foreach ($events as $event) {
            $eventId          = (int) $event->id;
            $availability     = $this->buildAvailability(
                $event,
                $held[$eventId] ?? 0,
                $seating[$eventId] ?? null
            );
            $availability['selectionUrl'] = $availability['bookable']
                ? $this->getSelectionUrl($eventId)
                : '';
            $result[$eventId] = $availability;
        }

        return $result;
    }

    /**
     * Sum all unexpired active reservations by event.
     *
     * @param   array<int, int|string>  $eventIds  Events to aggregate.
     *
     * @return array<int, int>
     */
    public function getHeldQuantities(array $eventIds): array
    {
        $eventIds = $this->normalizeEventIds($eventIds);

        if ($eventIds === []) {
            return [];
        }

        $this->cartContext->purgeExpiredCarts();

        $now   = $this->cartContext->now();
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('i.event_id'))
            ->select('SUM(' . $this->db->quoteName('i.quantity') . ') AS ' . $this->db->quoteName('quantity'))
            ->from($this->db->quoteName('#__copymypage_ticket_cart_items', 'i'))
            ->join(
                'INNER',
                $this->db->quoteName('#__copymypage_ticket_carts', 'c')
                    . ' ON ' . $this->db->quoteName('c.id') . ' = ' . $this->db->quoteName('i.cart_id')
            )
            ->where(
                $this->db->quoteName('c.status') . ' = ' . TicketCartContextService::STATUS_ACTIVE
            )
            ->where($this->db->quoteName('c.expires_at') . ' > :now')
            ->where($this->db->quoteName('i.event_id') . ' IN (' . implode(',', $eventIds) . ')')
            ->group($this->db->quoteName('i.event_id'))
            ->bind(':now', $now, ParameterType::STRING);

        $result = [];

        foreach ((array) $this->db->setQuery($query)->loadObjectList() as $row) {
            $result[(int) $row->event_id] = max(0, (int) $row->quantity);
        }

        return $result;
    }

    /**
     * Atomically replace one event's quantities in the current cart.
     *
     * @param   array<int|string, mixed>  $rawQuantities  Price-indexed quantities.
     *
     * @return array<string, mixed>
     */
    public function reserveEvent(
        int $eventId,
        array $rawQuantities,
        int $expectedCartRevision
    ): array
    {
        $quantities = $this->normalizeQuantities($rawQuantities);

        if ($eventId < 1 || $quantities === [] || $expectedCartRevision < 0) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_INVALID'));
        }

        if (array_sum($quantities) === 0) {
            return $this->removeEvent($eventId, $expectedCartRevision);
        }

        $this->cartContext->purgeExpiredCarts();
        $this->cartContext->beginTransaction();

        try {
            $cart            = $this->cartContext->getActiveCartForUpdate();
            $existingCart    = $cart !== null;

            if (!$existingCart) {
                $this->cartContext->assertInitialRevision($expectedCartRevision);
                $cart = $this->cartContext->ensureActiveCartForUpdate();
            }

            // Lock ordering is cart -> event -> items. The event lock deliberately
            // precedes the transaction's first ordinary SELECT so REPEATABLE READ
            // observes the last competing cart commit instead of an older snapshot.
            $this->lockEvent($eventId);

            $current        = $this->getStoredEventQuantities((int) $cart->id, $eventId);
            $alreadyApplied = $this->quantitiesMatch($current, $quantities);
            $seating        = $this->seatSelection->getInventoryConstraints([$eventId])[$eventId] ?? null;

            if ($seating === null || empty($seating['ready'])) {
                throw new \DomainException(
                    Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_SEATING_NOT_READY')
                );
            }

            if (!$alreadyApplied) {
                if ($existingCart) {
                    $this->cartContext->assertExpectedRevision($cart, $expectedCartRevision);
                }

                $event = $this->catalog->getEvent($eventId);

                if (!$event instanceof \stdClass) {
                    throw new \DomainException(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_EVENT'));
                }

                if (!$this->catalog->isOpenForBooking($event)) {
                    throw new \DomainException(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_CLOSED'));
                }

                $priceTypes = [];

                foreach ($this->catalog->getPriceTypes($event) as $price) {
                    $priceTypes[(int) $price['index']] = $price;
                }

                $total = 0;

                foreach ($quantities as $priceIndex => $quantity) {
                    if (!isset($priceTypes[$priceIndex])) {
                        throw new \DomainException(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_PRICE'));
                    }

                    if ($quantity > (int) $priceTypes[$priceIndex]['limit']) {
                        throw new \DomainException(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_LIMIT'));
                    }

                    $total += $quantity;
                }

                if ($total > $this->catalog->getMaximumTickets($event)) {
                    throw new \DomainException(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_LIMIT'));
                }

                $otherHolds = $this->getHeldQuantityForEvent($eventId, (int) $cart->id);
                $capacity   = $event->capacity === null ? null : max(0, (int) $event->capacity);
                $used       = max(0, (int) ($event->capacity_used ?? 0));
                if ($capacity !== null && $total > max(0, $capacity - $used - $otherHolds)) {
                    throw new \DomainException(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_CAPACITY'));
                }

                if ($seating !== null
                    && $total > max(0, (int) $seating['capacity'] - $otherHolds)) {
                    throw new \DomainException(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_CAPACITY'));
                }

                $now = $this->cartContext->now();
                $this->deleteEventItems((int) $cart->id, $eventId);

                foreach ($quantities as $priceIndex => $quantity) {
                    if ($quantity < 1) {
                        continue;
                    }

                    $price = $priceTypes[$priceIndex];
                    $row   = (object) [
                        'cart_id'     => (int) $cart->id,
                        'event_id'    => $eventId,
                        'price_index' => $priceIndex,
                        'quantity'    => $quantity,
                        'unit_price'  => number_format((float) $price['value'], 4, '.', ''),
                        'currency'    => (string) $price['currency'],
                        'price_label' => (string) $price['label'],
                        'created'     => $now,
                        'modified'    => $now,
                    ];

                    $this->db->insertObject('#__copymypage_ticket_cart_items', $row);
                }

                $this->seatSelection->reconcileCartEventWithinTransaction(
                    (int) $cart->id,
                    $eventId,
                    $quantities
                );

                $this->cartContext->advanceCart((int) $cart->id);
            }

            $this->cartContext->commitTransaction();
        } catch (\Throwable $exception) {
            $this->cartContext->rollbackTransaction();
            $this->rethrowMutationException($exception);
        }

        return $this->getSelectionState($eventId);
    }

    /**
     * Release one event while keeping other reservations alive.
     *
     * @return array<string, mixed>
     */
    public function removeEvent(int $eventId, int $expectedCartRevision): array
    {
        if ($eventId < 1 || $expectedCartRevision < 0) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_INVALID'));
        }

        $this->cartContext->purgeExpiredCarts();
        $this->cartContext->beginTransaction();

        try {
            $cart = $this->cartContext->getActiveCartForUpdate();

            if ($cart !== null) {
                $this->lockEventIfPresent($eventId);
                $current = $this->getStoredEventQuantities((int) $cart->id, $eventId);

                if ($current !== []) {
                    $this->cartContext->assertExpectedRevision($cart, $expectedCartRevision);
                    $this->seatSelection->reconcileCartEventWithinTransaction(
                        (int) $cart->id,
                        $eventId,
                        []
                    );
                    $this->deleteEventItems((int) $cart->id, $eventId);

                    if ($this->countCartItems((int) $cart->id) > 0) {
                        $this->cartContext->advanceCart((int) $cart->id);
                    } else {
                        $this->cartContext->releaseCart((int) $cart->id);
                    }
                }
            }

            $this->cartContext->commitTransaction();
        } catch (\Throwable $exception) {
            $this->cartContext->rollbackTransaction();
            $this->rethrowMutationException($exception);
        }

        return $this->getSelectionState($eventId);
    }

    /**
     * Release the complete current cart.
     *
     * @return array<string, mixed>
     */
    public function clearCart(int $expectedCartRevision): array
    {
        if ($expectedCartRevision < 0) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_INVALID'));
        }

        $this->cartContext->purgeExpiredCarts();
        // This multi-event operation must read fresh committed cart state after
        // all event locks are held. Statement-level snapshots avoid both a
        // stale REPEATABLE READ view and locking cart-item gaps before events.
        $this->db->setQuery('SET TRANSACTION ISOLATION LEVEL READ COMMITTED')->execute();
        $this->cartContext->beginTransaction();

        try {
            $cart = $this->cartContext->getActiveCartForUpdate();

            if ($cart !== null) {
                $this->cartContext->assertExpectedRevision($cart, $expectedCartRevision);

                $eventIds = $this->loadCartEventIds((int) $cart->id);

                // Acquire every event lock in one deterministic phase before
                // reconciliation performs its first ordinary SELECT. Together
                // with READ COMMITTED this keeps multi-event carts fresh while
                // preserving one global lock order for competing reservations.
                foreach ($eventIds as $eventId) {
                    $this->lockEventIfPresent($eventId);
                }

                foreach ($eventIds as $eventId) {
                    $this->seatSelection->reconcileCartEventWithinTransaction(
                        (int) $cart->id,
                        $eventId,
                        []
                    );
                }

                $this->cartContext->releaseCart((int) $cart->id);
            }

            $this->cartContext->commitTransaction();
        } catch (\Throwable $exception) {
            $this->cartContext->rollbackTransaction();
            $this->rethrowMutationException($exception);
        }

        return $this->getSelectionState();
    }

    /**
     * Build the CopyMyPage availability state; waiting-list sales stay disabled.
     *
     * @return array<string, mixed>
     */
    private function buildAvailability(
        \stdClass $event,
        int $held,
        ?array $seating = null
    ): array
    {
        $availability = $this->catalog->getAvailability($event, $held);

        if ($seating === null || empty($seating['ready'])) {
            $availability['bookable'] = false;
            $availability['remaining'] = 0;
            $availability['status'] = 'unavailable';
        } else {
            $seatCapacity  = max(0, (int) ($seating['capacity'] ?? 0));
            $seatRemaining = max(0, $seatCapacity - $held);
            $availability['remaining'] = $availability['capacity'] === null
                ? $seatRemaining
                : min((int) $availability['remaining'], $seatRemaining);

            if ($availability['saleOpen']) {
                $availability['bookable'] = $availability['remaining'] > 0;
                $availability['status']   = $availability['bookable']
                    ? 'available'
                    : 'sold-out';
            }

            $availability['capacity'] = $availability['capacity'] === null
                ? $seatCapacity
                : min((int) $availability['capacity'], $seatCapacity);
            $availability['used'] = max(
                0,
                (int) $availability['capacity'] - (int) $availability['remaining']
            );
            $availability['progress'] = (int) $availability['capacity'] > 0
                ? min(
                    100,
                    max(
                        0,
                        (int) round(
                            ($availability['used'] / (int) $availability['capacity']) * 100
                        )
                    )
                )
                : 100;
        }

        $capacity     = $availability['capacity'];
        $remaining    = $availability['remaining'];
        $status       = $availability['status'];

        switch ($status) {
            case 'available':
                $label = $capacity === null
                    ? Text::_('COM_COPYMYPAGE_TICKET_SELECTION_AVAILABLE_UNLIMITED')
                    : Text::plural('COM_COPYMYPAGE_TICKET_SELECTION_AVAILABLE_COUNT', $remaining);
                break;

            case 'not-open':
                $label = Text::sprintf(
                    'COM_COPYMYPAGE_TICKET_SELECTION_NOT_OPEN',
                    $availability['opensAt']->format(Text::_('DATE_FORMAT_LC2'), true)
                );
                break;

            case 'closed':
                $label = Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CLOSED');
                break;

            case 'sold-out':
                $label = Text::_('COM_COPYMYPAGE_TICKET_SELECTION_SOLD_OUT');
                break;

            default:
                $status = 'unavailable';
                $label  = Text::_('COM_COPYMYPAGE_TICKET_SELECTION_UNAVAILABLE');
                break;
        }

        return [
            'bookable'      => $availability['bookable'],
            'saleOpen'      => $availability['saleOpen'],
            'capacity'      => $capacity,
            'held'          => $availability['held'],
            'nativeUsed'    => $availability['nativeUsed'],
            'used'          => $availability['used'],
            'remaining'     => $remaining,
            'status'        => $status,
            'statusLabel'   => $label,
            'progress'      => $availability['progress'],
            'progressLabel' => $capacity === null
                ? ''
                : Text::sprintf(
                    'COM_COPYMYPAGE_TICKET_SELECTION_ALLOCATION_PROGRESS',
                    min($availability['used'], $capacity),
                    $capacity
                ),
        ];
    }

    private function lockEvent(int $eventId): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__dpcalendar_events'))
            ->where($this->db->quoteName('id') . ' = ' . $eventId);

        if ((int) $this->db->setQuery((string) $query . ' FOR UPDATE')->loadResult() !== $eventId) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_EVENT'));
        }
    }

    /**
     * Serialize cleanup with an existing DPCalendar event while still allowing
     * a stale cart item whose source event was deleted to be removed.
     */
    private function lockEventIfPresent(int $eventId): void
    {
        if ($eventId < 1) {
            return;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__dpcalendar_events'))
            ->where($this->db->quoteName('id') . ' = ' . $eventId);
        $this->db->setQuery((string) $query . ' FOR UPDATE')->loadResult();
    }

    private function getHeldQuantityForEvent(int $eventId, int $excludedCartId): int
    {
        $now   = $this->cartContext->now();
        $query = $this->db->getQuery(true)
            ->select('COALESCE(SUM(' . $this->db->quoteName('i.quantity') . '), 0)')
            ->from($this->db->quoteName('#__copymypage_ticket_cart_items', 'i'))
            ->join(
                'INNER',
                $this->db->quoteName('#__copymypage_ticket_carts', 'c')
                    . ' ON ' . $this->db->quoteName('c.id') . ' = ' . $this->db->quoteName('i.cart_id')
            )
            ->where($this->db->quoteName('i.event_id') . ' = :eventId')
            ->where($this->db->quoteName('i.cart_id') . ' <> :cartId')
            ->where(
                $this->db->quoteName('c.status') . ' = ' . TicketCartContextService::STATUS_ACTIVE
            )
            ->where($this->db->quoteName('c.expires_at') . ' > :now')
            ->bind(':eventId', $eventId, ParameterType::INTEGER)
            ->bind(':cartId', $excludedCartId, ParameterType::INTEGER)
            ->bind(':now', $now, ParameterType::STRING);

        return max(0, (int) $this->db->setQuery($query)->loadResult());
    }

    /**
     * @return array<int, object>
     */
    private function loadCartItems(int $cartId): array
    {
        if ($cartId < 1) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__copymypage_ticket_cart_items'))
            ->where($this->db->quoteName('cart_id') . ' = :cartId')
            ->order($this->db->quoteName('event_id') . ' ASC')
            ->order($this->db->quoteName('price_index') . ' ASC')
            ->bind(':cartId', $cartId, ParameterType::INTEGER);

        return (array) $this->db->setQuery($query)->loadObjectList();
    }

    /**
     * Count the current cart's actually held seats by event for the cart summary.
     *
     * @return array<int, int>
     */
    private function loadSelectedSeatCounts(int $cartId): array
    {
        if ($cartId < 1) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('event_id'))
            ->select('COUNT(*) AS ' . $this->db->quoteName('selected_count'))
            ->from($this->db->quoteName('#__copymypage_event_seats'))
            ->where($this->db->quoteName('cart_id') . ' = :cartId')
            ->where($this->db->quoteName('status') . ' = ' . EventSeatInventoryService::SEAT_STATUS_HELD)
            ->group($this->db->quoteName('event_id'))
            ->bind(':cartId', $cartId, ParameterType::INTEGER);
        $counts = [];

        foreach ((array) $this->db->setQuery($query)->loadObjectList() as $row) {
            $eventId = max(0, (int) ($row->event_id ?? 0));

            if ($eventId > 0) {
                $counts[$eventId] = max(0, (int) ($row->selected_count ?? 0));
            }
        }

        return $counts;
    }

    /**
     * @return list<int>
     */
    private function loadCartEventIds(int $cartId): array
    {
        if ($cartId < 1) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select('DISTINCT ' . $this->db->quoteName('event_id'))
            ->from($this->db->quoteName('#__copymypage_ticket_cart_items'))
            ->where($this->db->quoteName('cart_id') . ' = :cartId')
            ->where($this->db->quoteName('quantity') . ' > 0')
            ->order($this->db->quoteName('event_id') . ' ASC')
            ->bind(':cartId', $cartId, ParameterType::INTEGER);

        return array_map('intval', (array) $this->db->setQuery($query)->loadColumn());
    }

    /**
     * Return the positive quantity target currently stored for one event.
     *
     * @return array<int, int>
     */
    private function getStoredEventQuantities(int $cartId, int $eventId): array
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
            $quantity = max(0, (int) ($row->quantity ?? 0));

            if ($quantity > 0) {
                $result[(int) ($row->price_index ?? 0)] = $quantity;
            }
        }

        return $result;
    }

    /**
     * Compare normalized desired quantities with the stored event target.
     *
     * @param   array<int, int>  $stored     Stored positive quantities.
     * @param   array<int, int>  $requested  Normalized browser quantities.
     */
    private function quantitiesMatch(array $stored, array $requested): bool
    {
        $requested = array_filter(
            $requested,
            static fn(int $quantity): bool => $quantity > 0
        );
        ksort($stored, SORT_NUMERIC);
        ksort($requested, SORT_NUMERIC);

        return $stored === $requested;
    }

    /**
     * @param   array<int, object>  $rows  Stored cart rows.
     *
     * @return array<int, array<int, int>>
     */
    private function mapCurrentQuantities(array $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            $eventId   = (int) ($row->event_id ?? 0);
            $price     = (int) ($row->price_index ?? 0);
            $quantity  = max(0, (int) ($row->quantity ?? 0));

            if ($eventId > 0 && $quantity > 0) {
                $result[$eventId][$price] = $quantity;
            }
        }

        return $result;
    }

    /**
     * @param   array<int, object>               $rows    Stored cart rows.
     * @param   array<int, array<string, mixed>> $events  Prepared public events.
     *
     * @return array<string, mixed>
     */
    private function buildCartState(?object $cart, array $rows, array $events): array
    {
        $eventMap = [];

        foreach ($events as $event) {
            $eventMap[(int) $event['id']] = $event;
        }

        $cartEventIds = [];

        foreach ($rows as $row) {
            $eventId = (int) ($row->event_id ?? 0);

            if ($eventId > 0 && !isset($eventMap[$eventId])) {
                $cartEventIds[] = $eventId;
            }
        }

        $displayEvents      = $this->catalog->getDisplayEvents($cartEventIds);
        $selectedSeatCounts = $cart === null
            ? []
            : $this->loadSelectedSeatCounts((int) $cart->id);

        $grouped      = [];
        $totalTickets = 0;
        $totalPrice   = 0.0;

        foreach ($rows as $row) {
            $eventId   = (int) ($row->event_id ?? 0);
            $quantity  = max(0, (int) ($row->quantity ?? 0));
            $unitPrice = max(0.0, (float) ($row->unit_price ?? 0));

            if ($eventId < 1 || $quantity < 1) {
                continue;
            }

            if (!isset($grouped[$eventId])) {
                $publicEvent  = $eventMap[$eventId] ?? null;
                $displayEvent = $displayEvents[$eventId] ?? null;
                $continuable  = \is_array($publicEvent)
                    && !empty($publicEvent['availability']['saleOpen']);
                $title        = \is_array($publicEvent)
                    ? trim((string) ($publicEvent['title'] ?? ''))
                    : trim((string) ($displayEvent->title ?? ''));

                if ($title === '') {
                    $title = Text::sprintf(
                        'COM_COPYMYPAGE_TICKET_SELECTION_CART_EVENT_FALLBACK',
                        $eventId
                    );
                }

                $grouped[$eventId] = [
                    'eventId'       => $eventId,
                    'title'         => $title,
                    'dateLabel'     => \is_array($publicEvent)
                        ? (string) ($publicEvent['dateLabel'] ?? '')
                        : ($displayEvent instanceof \stdClass
                            ? $this->formatEventDateLabel($displayEvent)
                            : ''),
                    'quantity'           => 0,
                    'selectedSeatsLabel' => '',
                    'prices'             => [],
                    'continuable'   => $continuable,
                    'statusLabel'   => $continuable
                        ? ''
                        : Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CART_ITEM_UNAVAILABLE'),
                ];
            }

            $priceLabel = trim((string) ($row->price_label ?? ''));

            if ($priceLabel === '' || $priceLabel === 'COM_DPCALENDAR_TICKET') {
                $priceLabel = Text::_('COM_COPYMYPAGE_TICKET_SELECTION_TICKET_TYPE_DEFAULT');
            }

            $lineTotal = round($unitPrice * $quantity, 4);
            $grouped[$eventId]['quantity'] += $quantity;
            $grouped[$eventId]['prices'][] = [
                'index'          => (int) ($row->price_index ?? 0),
                'label'          => $priceLabel,
                'quantity'       => $quantity,
                'unitPrice'      => $unitPrice,
                'unitFormatted'  => $this->catalog->formatPrice($unitPrice),
                'lineTotal'      => $lineTotal,
                'lineFormatted'  => $this->catalog->formatPrice($lineTotal),
                'summaryLabel'   => Text::sprintf(
                    'COM_COPYMYPAGE_TICKET_SELECTION_CART_LINE',
                    $quantity,
                    $priceLabel,
                    $this->catalog->formatPrice($lineTotal)
                ),
            ];
            $totalTickets += $quantity;
            $totalPrice   += $lineTotal;
        }

        foreach ($grouped as $eventId => &$item) {
            $selectedSeatCount = max(0, (int) ($selectedSeatCounts[$eventId] ?? 0));

            if ($selectedSeatCount > 0) {
                $item['selectedSeatsLabel'] = Text::plural(
                    'COM_COPYMYPAGE_TICKET_SELECTION_CART_SELECTED_SEATS',
                    $selectedSeatCount
                );
            }
        }

        unset($item);

        $expiresAt   = $cart === null ? '' : $this->toIso8601((string) ($cart->expires_at ?? ''));
        $seconds     = $cart === null ? 0 : max(0, strtotime($expiresAt) - time());
        $continuable = $grouped !== [];

        foreach ($grouped as $item) {
            if (empty($item['continuable'])) {
                $continuable = false;

                break;
            }
        }

        return [
            'active'         => $cart !== null && $grouped !== [],
            'cartRevision'   => $this->cartContext->getRevision($cart),
            'continuable'    => $continuable,
            'showTicketSelectionBack' => $this->cartContext->hasSeatSelectionStarted($cart),
            'items'          => array_values($grouped),
            'totalTickets'   => $totalTickets,
            'totalPrice'     => round($totalPrice, 4),
            'totalFormatted' => $this->catalog->formatPrice($totalPrice),
            'expiresAt'      => $expiresAt,
            'secondsLeft'    => $seconds,
        ];
    }

    /**
     * @param   array<int, array<string, mixed>>  $events  Prepared selection events.
     *
     * @return array<int, array<string, mixed>>
     */
    private function indexAvailability(array $events): array
    {
        $result = [];

        foreach ($events as $event) {
            $result[(int) $event['id']] = $event['availability'];
        }

        return $result;
    }

    /**
     * @param   array<int, array<string, mixed>>  $events  Prepared events.
     * @param   array<int, object>                $rows    Current cart rows.
     */
    private function resolveSelectedEventId(array $events, int $requestedEventId, array $rows): int
    {
        $eventIds = array_map(static fn(array $event): int => (int) $event['id'], $events);

        if (\in_array($requestedEventId, $eventIds, true)) {
            return $requestedEventId;
        }

        foreach ($rows as $row) {
            $cartEventId = (int) ($row->event_id ?? 0);

            if (\in_array($cartEventId, $eventIds, true)) {
                return $cartEventId;
            }
        }

        foreach ($events as $event) {
            if (!empty($event['canReserve'])) {
                return (int) $event['id'];
            }
        }

        return $eventIds[0] ?? 0;
    }

    private function deleteEventItems(int $cartId, int $eventId): void
    {
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__copymypage_ticket_cart_items'))
            ->where($this->db->quoteName('cart_id') . ' = :cartId')
            ->where($this->db->quoteName('event_id') . ' = :eventId')
            ->bind(':cartId', $cartId, ParameterType::INTEGER)
            ->bind(':eventId', $eventId, ParameterType::INTEGER);

        $this->db->setQuery($query)->execute();
    }

    private function countCartItems(int $cartId): int
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__copymypage_ticket_cart_items'))
            ->where($this->db->quoteName('cart_id') . ' = :cartId')
            ->bind(':cartId', $cartId, ParameterType::INTEGER);

        return (int) $this->db->setQuery($query)->loadResult();
    }

    /**
     * @param   array<int|string, mixed>  $rawQuantities  Browser values.
     *
     * @return array<int, int>
     */
    private function normalizeQuantities(array $rawQuantities): array
    {
        $result = [];

        foreach ($rawQuantities as $rawIndex => $rawQuantity) {
            $index = filter_var(
                $rawIndex,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0, 'max_range' => 999]]
            );
            $quantity = filter_var(
                $rawQuantity,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0, 'max_range' => 10000]]
            );

            if ($index === false || $quantity === false) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_INVALID'));
            }

            $result[(int) $index] = (int) $quantity;
        }

        ksort($result, SORT_NUMERIC);

        return $result;
    }

    /**
     * @param   array<int, int|string>  $eventIds  Untrusted IDs.
     *
     * @return array<int, int>
     */
    private function normalizeEventIds(array $eventIds): array
    {
        $result = [];

        foreach ($eventIds as $eventId) {
            $validated = filter_var(
                $eventId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($validated !== false) {
                $result[] = (int) $validated;
            }
        }

        $result = array_values(array_unique($result));
        sort($result, SORT_NUMERIC);

        return array_slice($result, 0, 50);
    }

    public function getReservationMinutes(): int
    {
        return $this->cartContext->getReservationMinutes();
    }

    private function toIso8601(string $date): string
    {
        $timestamp = strtotime($date . ' UTC');

        return $timestamp === false ? '' : gmdate('c', $timestamp);
    }

    private function formatEventDateLabel(\stdClass $event): string
    {
        $start = DPCalendarHelper::getDate((string) ($event->start_date ?? ''));

        if (!empty($event->all_day)) {
            return $start->format(Text::_('DATE_FORMAT_LC1'), true);
        }

        $end = DPCalendarHelper::getDate((string) ($event->end_date ?? $event->start_date ?? ''));

        return Text::sprintf(
            'COM_COPYMYPAGE_TICKET_SELECTION_DATE_TIME',
            $start->format(Text::_('DATE_FORMAT_LC1'), true),
            $start->format('H:i', true),
            $end->format('H:i', true)
        );
    }

    private function formatEventDateTime(\stdClass $event): string
    {
        return DPCalendarHelper::getDate((string) ($event->start_date ?? ''))
            ->format('c', true, false);
    }

    private function rethrowMutationException(\Throwable $exception): never
    {
        if ($exception instanceof \DomainException) {
            throw $exception;
        }

        throw new \RuntimeException(
            Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_SAVE'),
            0,
            $exception
        );
    }
}
