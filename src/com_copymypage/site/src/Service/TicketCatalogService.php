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

use DigitalPeak\Component\DPCalendar\Administrator\Calendar\CalendarInterface;
use DigitalPeak\Component\DPCalendar\Administrator\Helper\Booking;
use DigitalPeak\Component\DPCalendar\Administrator\Helper\DPCalendarHelper;
use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Provides the public DPCalendar ticket catalogue selected by the landing module.
 */
final class TicketCatalogService
{
    private const FETCH_LIMIT = 120;

    /** @var array<string, bool> */
    private array $calendarBookingAccess = [];

    /** @var array{calendarIds: array<int, int>, monthsAhead: int}|null */
    private ?array $moduleScope = null;

    private ?object $currencyModel = null;

    private bool $dpCalendarBooted = false;

    public function __construct(
        private readonly CMSWebApplicationInterface $app,
        private readonly DatabaseInterface $db
    ) {
    }

    /**
     * Return upcoming public events from explicitly selected ticket calendars.
     *
     * @return array<int, \stdClass>
     */
    public function getUpcomingEvents(): array
    {
        $scope = $this->getModuleScope();

        return $this->getUpcomingEventsForScope(
            $scope['calendarIds'],
            $scope['monthsAhead']
        );
    }

    /**
     * Return upcoming public ticket events for one explicitly supplied module scope.
     *
     * @param   array<int, int|string>  $calendarIds  Selected local calendar IDs.
     *
     * @return array<int, \stdClass>
     */
    public function getUpcomingEventsForScope(array $calendarIds, int $monthsAhead): array
    {
        $calendarIds = $this->normalizeCalendarIds($calendarIds);
        $monthsAhead = min(36, max(1, $monthsAhead));

        if ($calendarIds === []) {
            return [];
        }

        $this->bootDPCalendar();

        $component = $this->app->bootComponent('dpcalendar');
        $model     = $component->getMVCFactory()->createModel(
            'Events',
            'Site',
            ['ignore_request' => true]
        );

        if (!\is_object($model) || !method_exists($model, 'setState') || !method_exists($model, 'getItems')) {
            throw new \RuntimeException('DPCalendar events model is unavailable.');
        }

        $startDate = DPCalendarHelper::getDate();
        $endDate   = clone $startDate;
        $endDate->modify('+' . $monthsAhead . ' months');

        $model->setState('category.id', $calendarIds);
        $model->setState('category.recursive', true);
        $model->setState('filter.c.published', 1);
        $model->setState('filter.expand', true);
        $model->setState('filter.featured', false);
        $model->setState('filter.language', method_exists($this->app, 'getLanguageFilter')
            ? (bool) $this->app->getLanguageFilter()
            : false);
        $model->setState('filter.publish_date', true);
        $model->setState('filter.state', [1]);
        $model->setState('list.direction', 'ASC');
        $model->setState('list.end-date', $endDate->format('c', true, false));
        $model->setState('list.limit', self::FETCH_LIMIT);
        $model->setState('list.ordering', 'a.start_date');
        $model->setState('list.start', 0);
        $model->setState('list.start-date', $startDate->format('c', true, false));

        $events = $model->getItems();

        if (!\is_array($events)) {
            return [];
        }

        $allowedCalendarIds = array_map('strval', $calendarIds);
        $result             = [];

        foreach ($events as $event) {
            if (
                !$event instanceof \stdClass
                || (int) ($event->id ?? 0) < 1
                || (int) ($event->state ?? 0) !== 1
                || (int) ($event->access ?? 0) !== 1
                || !\in_array((string) ($event->catid ?? ''), $allowedCalendarIds, true)
                || !$this->supportsTicketing($event)
            ) {
                continue;
            }

            $this->setupCurrencyPrices($event);
            $result[] = $event;
        }

        return $result;
    }

    /**
     * Resolve one event only if it remains inside the public ticket catalogue.
     */
    public function getEvent(int $eventId): ?\stdClass
    {
        if ($eventId < 1) {
            return null;
        }

        foreach ($this->getUpcomingEvents() as $event) {
            if ((int) $event->id === $eventId) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Load a minimal, escaped-by-consumer display projection for existing cart events.
     *
     * This lookup deliberately ignores the current public module scope so a cart
     * item cannot disappear merely because its event was closed or unpublished.
     * It grants no booking permission and exposes only fields needed by the cart.
     *
     * @param   array<int, int|string>  $eventIds  Stored cart event IDs.
     *
     * @return array<int, \stdClass>
     */
    public function getDisplayEvents(array $eventIds): array
    {
        $ids = [];

        foreach ($eventIds as $eventId) {
            $validated = filter_var(
                $eventId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($validated !== false) {
                $ids[] = (int) $validated;
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);
        $ids = array_slice($ids, 0, 50);

        if ($ids === []) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id', 'title', 'start_date', 'end_date', 'all_day']))
            ->from($this->db->quoteName('#__dpcalendar_events'))
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', $ids) . ')')
            ->order($this->db->quoteName('id') . ' ASC');

        $result = [];

        foreach ((array) $this->db->setQuery($query)->loadObjectList() as $event) {
            $eventId = (int) ($event->id ?? 0);

            if ($eventId > 0) {
                $result[$eventId] = $event;
            }
        }

        return $result;
    }

    /**
     * Normalize DPCalendar price objects into a stable CopyMyPage contract.
     *
     * @return array<int, array{
     *     index: int,
     *     label: string,
     *     description: string,
     *     value: float,
     *     currency: string,
     *     limit: int
     * }>
     */
    public function getPriceTypes(\stdClass $event): array
    {
        $prices   = $event->prices ?? null;
        $maxTotal = $this->getMaximumTickets($event);

        if (!$prices instanceof \stdClass || get_object_vars($prices) === []) {
            return [[
                'index'       => 0,
                'label'       => Text::_('COM_COPYMYPAGE_TICKET_SELECTION_TICKET_TYPE_DEFAULT'),
                'description' => '',
                'value'       => 0.0,
                'currency'    => $this->getActualCurrency(),
                'limit'       => $maxTotal,
            ]];
        }

        $result = [];

        foreach ((array) $prices as $key => $price) {
            if (!\is_object($price)) {
                continue;
            }

            $index = preg_replace('/\D/', '', (string) $key);

            if ($index === '') {
                continue;
            }

            $value = $price->value ?? null;

            if (!\is_numeric($value) || (float) $value < 0) {
                continue;
            }

            $rawLimit = $price->limit ?? null;
            $limit    = $rawLimit === null || $rawLimit === ''
                ? $maxTotal
                : max(0, (int) $rawLimit);

            $result[(int) $index] = [
                'index'       => (int) $index,
                'label'       => trim((string) ($price->label ?? ''))
                    ?: Text::_('COM_COPYMYPAGE_TICKET_SELECTION_TICKET_TYPE_DEFAULT'),
                'description' => trim(strip_tags((string) ($price->description ?? ''))),
                'value'       => round((float) $value, 4),
                'currency'    => $this->normalizeCurrency((string) ($price->currency ?? '')),
                'limit'       => min($maxTotal, $limit),
            ];
        }

        ksort($result, SORT_NUMERIC);

        return array_values($result);
    }

    /**
     * DPCalendar treats zero as one ticket per booking.
     */
    public function getMaximumTickets(\stdClass $event): int
    {
        $maximum = (int) ($event->max_tickets ?? 0);

        return $maximum > 0 ? $maximum : 1;
    }

    /**
     * Keep DPCalendar responsible for dates, calendar permission and sale windows.
     */
    public function isOpenForBooking(\stdClass $event): bool
    {
        $this->bootDPCalendar();

        return Booking::openForBooking($event);
    }

    /**
     * Build the shared, language-neutral booking and capacity state.
     *
     * Consumers add their own labels without recalculating DPCalendar rules.
     *
     * @return array{
     *     bookable: bool,
     *     saleOpen: bool,
     *     capacity: ?int,
     *     held: int,
     *     nativeUsed: int,
     *     used: int,
     *     remaining: ?int,
     *     status: string,
     *     opensAt: object,
     *     closesAt: object,
     *     progress: ?int
     * }
     */
    public function getAvailability(\stdClass $event, int $held = 0): array
    {
        $this->bootDPCalendar();

        $rawCapacity = $event->capacity ?? null;
        $capacity    = $rawCapacity === null ? null : max(0, (int) $rawCapacity);
        $native    = max(0, (int) ($event->capacity_used ?? 0));
        $held      = max(0, $held);
        $used      = $native + $held;
        $remaining = $capacity === null ? null : max(0, $capacity - $used);
        $saleOpen  = Booking::openForBooking($event);
        $bookable  = $saleOpen && ($capacity === null || $remaining > 0);
        $status    = 'available';
        $now       = DPCalendarHelper::getDate();
        $opensAt   = Booking::getRegistrationStartDate($event);
        $closesAt  = Booking::getRegistrationEndDate($event);

        if ((int) $opensAt->format('U', true, false) > (int) $now->format('U', true, false)) {
            $status   = 'not-open';
            $bookable = false;
        } elseif ((int) $closesAt->format('U', true, false) < (int) $now->format('U', true, false)) {
            $status   = 'closed';
            $bookable = false;
        } elseif ($capacity !== null && $remaining < 1) {
            $status   = 'sold-out';
            $bookable = false;
        } elseif (!$saleOpen) {
            $status   = 'unavailable';
            $bookable = false;
        }

        $progress = null;

        if ($capacity !== null && $capacity > 0) {
            $progress = min(100, max(0, (int) round((min($used, $capacity) / $capacity) * 100)));
        }

        return [
            'bookable'   => $bookable,
            'saleOpen'   => $saleOpen,
            'capacity'   => $capacity,
            'held'       => $held,
            'nativeUsed' => $native,
            'used'      => $used,
            'remaining' => $remaining,
            'status'    => $status,
            'opensAt'   => $opensAt,
            'closesAt'  => $closesAt,
            'progress'  => $progress,
        ];
    }

    /**
     * Format a price using DPCalendar's active currency presentation.
     */
    public function formatPrice(float $price): string
    {
        $this->bootDPCalendar();

        $rendered = DPCalendarHelper::renderPrice(number_format($price, 2, '.', ''));

        return trim(html_entity_decode(strip_tags($rendered), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Read the explicitly configured public calendar scope from ticket modules.
     *
     * @return array{calendarIds: array<int, int>, monthsAhead: int}
     */
    private function getModuleScope(): array
    {
        if ($this->moduleScope !== null) {
            return $this->moduleScope;
        }

        $module   = 'mod_copymypage_tickets';
        $position = 'tickets';
        $query    = $this->db->getQuery(true)
            ->select($this->db->quoteName('params'))
            ->from($this->db->quoteName('#__modules'))
            ->where($this->db->quoteName('module') . ' = :module')
            ->where($this->db->quoteName('client_id') . ' = 0')
            ->where($this->db->quoteName('published') . ' = 1')
            ->where($this->db->quoteName('position') . ' = :position')
            ->order($this->db->quoteName('ordering') . ' ASC')
            ->order($this->db->quoteName('id') . ' ASC')
            ->bind(':module', $module, ParameterType::STRING)
            ->bind(':position', $position, ParameterType::STRING);

        $calendarIds = [];
        $monthsAhead = null;

        foreach ((array) $this->db->setQuery($query)->loadColumn() as $rawParams) {
            $params = new Registry((string) $rawParams);
            $layout = preg_replace(
                '/[^a-z0-9_]/',
                '',
                strtolower((string) $params->get('layoutVariant', 'tickets_default'))
            ) ?: 'tickets_default';
            $values = (array) $params->get($layout . '_calendarIds', []);

            foreach ($values as $value) {
                $calendarId = filter_var(
                    $value,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                if ($calendarId !== false) {
                    $calendarIds[] = (int) $calendarId;
                }
            }

            $configuredMonths = min(
                36,
                max(1, (int) $params->get($layout . '_monthsAhead', 18))
            );
            $monthsAhead = $monthsAhead === null
                ? $configuredMonths
                : max($monthsAhead, $configuredMonths);
        }

        $calendarIds = $this->normalizeCalendarIds($calendarIds);

        $this->moduleScope = [
            'calendarIds' => $calendarIds,
            'monthsAhead' => $monthsAhead ?? 18,
        ];

        return $this->moduleScope;
    }

    /**
     * Ticket events must be local, capacity-enabled and bookable in their calendar.
     */
    private function supportsTicketing(\stdClass $event): bool
    {
        if (DPCalendarHelper::isFree()) {
            return false;
        }

        $capacity = $event->capacity ?? null;

        if ($capacity !== null && (int) $capacity <= 0) {
            return false;
        }

        $calendarId = trim((string) ($event->catid ?? ''));

        if ($calendarId === '') {
            return false;
        }

        if (\array_key_exists($calendarId, $this->calendarBookingAccess)) {
            return $this->calendarBookingAccess[$calendarId];
        }

        try {
            $model    = $this->app->bootComponent('dpcalendar')
                ->getMVCFactory()
                ->createModel('Calendar', 'Administrator');
            $calendar = $model->getCalendar($calendarId);
            $allowed  = $calendar instanceof CalendarInterface && $calendar->canBook();
        } catch (\Throwable) {
            $allowed = false;
        }

        $this->calendarBookingAccess[$calendarId] = $allowed;

        return $allowed;
    }

    private function setupCurrencyPrices(\stdClass $event): void
    {
        $model = $this->getCurrencyModel();

        if ($model === null || !method_exists($model, 'setupCurrencyPrices')) {
            return;
        }

        $configuredPrices = $event->prices ?? null;

        try {
            $model->setupCurrencyPrices($event);
        } catch (\Throwable) {
            $event->prices = $configuredPrices;
        }
    }

    private function getActualCurrency(): string
    {
        $model = $this->getCurrencyModel();

        if ($model !== null && method_exists($model, 'getActualCurrency')) {
            try {
                $currency = $model->getActualCurrency();

                if (\is_object($currency)) {
                    return $this->normalizeCurrency((string) ($currency->currency ?? ''));
                }
            } catch (\Throwable) {
                // The stable DPCalendar default below remains usable.
            }
        }

        return 'EUR';
    }

    private function getCurrencyModel(): ?object
    {
        if ($this->currencyModel !== null) {
            return $this->currencyModel;
        }

        try {
            $model = $this->app->bootComponent('dpcalendar')
                ->getMVCFactory()
                ->createModel('Currency', 'Administrator');

            if (\is_object($model)) {
                $this->currencyModel = $model;
            }
        } catch (\Throwable) {
            return null;
        }

        return $this->currencyModel;
    }

    private function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'EUR';
    }

    /**
     * @param   array<int, int|string>  $calendarIds  Untrusted module values.
     *
     * @return array<int, int>
     */
    private function normalizeCalendarIds(array $calendarIds): array
    {
        $result = [];

        foreach ($calendarIds as $calendarId) {
            $validated = filter_var(
                $calendarId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($validated !== false) {
                $result[] = (int) $validated;
            }
        }

        $result = array_values(array_unique($result));
        sort($result, SORT_NUMERIC);

        return $result;
    }

    private function bootDPCalendar(): void
    {
        if ($this->dpCalendarBooted) {
            return;
        }

        $this->app->bootComponent('dpcalendar');

        if (!class_exists(DPCalendarHelper::class) || !class_exists(Booking::class)) {
            throw new \RuntimeException('DPCalendar classes are unavailable.');
        }

        $this->dpCalendarBooted = true;
    }
}
