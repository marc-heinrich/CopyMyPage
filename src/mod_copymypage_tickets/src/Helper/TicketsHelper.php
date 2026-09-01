<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Module\CopyMyPage\Tickets\Site\Helper;

\defined('_JEXEC') or die;

use DigitalPeak\Component\DPCalendar\Administrator\Helper\DPCalendarHelper;
use DigitalPeak\Component\DPCalendar\Site\Helper\RouteHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\CopyMyPage\Site\Service\TicketCatalogService;
use Joomla\Component\CopyMyPage\Site\Service\TicketReservationService;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * Data and configuration helper for the CopyMyPage Tickets module.
 */
final class TicketsHelper
{
    private const DEFAULT_SLOT = 'tickets';

    private const DEFAULT_IMAGE_HEIGHT = 900;

    private const DEFAULT_IMAGE_WIDTH = 1600;

    /** @var array<int, int> */
    private const CARD_IMAGE_VARIANT_WIDTHS = [360, 480, 720, 960, 1200];

    private const CARD_IMAGE_SIZES = '(min-width: 960px) 352px, '
        . '(min-width: 640px) 320px, calc(100vw - 48px)';

    private const FLAT_VIEWPORT_CLASS = 'is-small';

    private const ROOT_ATTRIBUTE = 'data-cmp-tickets-swiper';

    private const INITIALIZED_ATTRIBUTE = 'data-cmp-tickets-initialized';

    private const NEXT_ATTRIBUTE = 'data-cmp-tickets-next';

    private const PAGINATION_ATTRIBUTE = 'data-cmp-tickets-pagination';

    private const PAGINATION_LABEL_ATTRIBUTE = 'data-cmp-tickets-pagination-label';

    private const PREVIOUS_ATTRIBUTE = 'data-cmp-tickets-previous';

    private const SLIDE_CLASS = 'cmp-tickets__slide';

    private const SWIPER_CLASS = 'swiper';

    private const SWIPER_SLIDE_CLASS = 'swiper-slide';

    private const SWIPER_WRAPPER_CLASS = 'swiper-wrapper';

    private const EVENT_ID_ATTRIBUTE = 'data-cmp-ticket-event-id';

    private const STATUS_ATTRIBUTE = 'data-cmp-ticket-status';

    private const ACTION_ATTRIBUTE = 'data-cmp-ticket-action';

    private const ACTION_LABEL_ATTRIBUTE = 'data-cmp-ticket-action-label';

    private const ACTION_URL_ATTRIBUTE = 'data-cmp-ticket-action-url';

    private const PROGRESS_ATTRIBUTE = 'data-cmp-ticket-progress';

    private const PROGRESS_LABEL_ATTRIBUTE = 'data-cmp-ticket-progress-label';

    private string $defaultLayout = '';

    private string $layoutPrefix = '';

    /**
     * Set the validated layout context supplied by the dispatcher.
     */
    public function setLayoutContext(string $defaultLayout, string $layoutPrefix = ''): void
    {
        $this->defaultLayout = self::normalizeLayoutKey($defaultLayout);
        $this->layoutPrefix  = self::normalizeLayoutKey($layoutPrefix);
    }

    /**
     * Return Open Graph metadata in the shared CopyMyPage contract.
     *
     * @return array<string, string>
     */
    public function getOGTags(Registry $params, ?object $module = null, string $slot = '', string $layout = ''): array
    {
        $config         = $params->toArray();
        $layout         = $this->resolveLayoutVariant($config, $layout, $slot);
        $configuredMeta = $this->resolveConfiguredOpenGraphMeta($config);
        $primaryMeta    = [
            'image'       => '',
            'imageWidth'  => '',
            'imageHeight' => '',
            'imageAlt'    => '',
        ];

        if (
            $configuredMeta['image'] === ''
            || $configuredMeta['imageWidth'] === ''
            || $configuredMeta['imageHeight'] === ''
            || $configuredMeta['imageAlt'] === ''
        ) {
            try {
                $primaryMeta = $this->resolvePrimaryItemMeta($this->getItems($config, $layout));
            } catch (\Throwable) {
                $primaryMeta = [
                    'image'       => '',
                    'imageWidth'  => '',
                    'imageHeight' => '',
                    'imageAlt'    => '',
                ];
            }
        }

        $resolvedSlot = trim($slot) !== '' ? strtolower(trim($slot)) : self::DEFAULT_SLOT;
        $meta         = self::mergeOpenGraphMeta(
            [
                'title'       => self::htmlToPlainText($this->getHeadline($config, $layout)),
                'description' => self::htmlToPlainText($this->getLead($config, $layout)),
                'image'       => $primaryMeta['image'],
                'imageWidth'  => $primaryMeta['imageWidth'],
                'imageHeight' => $primaryMeta['imageHeight'],
                'imageAlt'    => $primaryMeta['imageAlt'],
                'twitterCard' => 'summary_large_image',
            ],
            $configuredMeta
        );

        if ($meta['title'] === '') {
            $moduleTitle   = self::htmlToPlainText((string) ($module->title ?? ''));
            $meta['title'] = $moduleTitle !== ''
                ? $moduleTitle
                : Text::_('MOD_COPYMYPAGE_TICKETS_DEFAULT_HEADLINE');
        }

        if ($meta['twitterCard'] === '') {
            $meta['twitterCard'] = $meta['image'] !== '' ? 'summary_large_image' : 'summary';
        }

        return [
            'slot'        => $resolvedSlot,
            'label'       => Text::_('MOD_COPYMYPAGE_TICKETS_OG_LABEL'),
            'title'       => $meta['title'],
            'description' => $meta['description'],
            'image'       => $meta['image'],
            'imageWidth'  => $meta['imageWidth'],
            'imageHeight' => $meta['imageHeight'],
            'imageAlt'    => $meta['imageAlt'],
            'twitterCard' => $meta['twitterCard'],
        ];
    }

    /**
     * The sole source of normalized JavaScript defaults and future module values.
     *
     * @return array<string, mixed>
     */
    public function getClientConfig(): array
    {
        return [
            'rootSelector'             => '[' . self::ROOT_ATTRIBUTE . ']',
            'initializedAttribute'     => self::INITIALIZED_ATTRIBUTE,
            'flatViewportClass'        => self::FLAT_VIEWPORT_CLASS,
            'slideSelector'            => '.' . self::SLIDE_CLASS,
            'navigation'               => [
                'nextSelector'     => '[' . self::NEXT_ATTRIBUTE . ']',
                'previousSelector' => '[' . self::PREVIOUS_ATTRIBUTE . ']',
            ],
            'paginationSelector'       => '[' . self::PAGINATION_ATTRIBUTE . ']',
            'paginationLabelAttribute' => self::PAGINATION_LABEL_ATTRIBUTE,
            'desktopQuery'             => '(min-width: 1024px)',
            'mobileQuery'              => '(max-width: 639px)',
            'reducedMotionQuery'       => '(prefers-reduced-motion: reduce)',
            'availability'             => [
                'endpoint'        => Route::_(
                    'index.php?option=com_copymypage&task=ticketcart.availability&format=json',
                    false
                ),
                'intervalMs'      => 25000,
                'cardSelector'    => '[' . self::EVENT_ID_ATTRIBUTE . ']',
                'statusSelector'  => '[' . self::STATUS_ATTRIBUTE . ']',
                'actionSelector'  => '[' . self::ACTION_ATTRIBUTE . ']',
                'progressSelector'=> '[' . self::PROGRESS_ATTRIBUTE . ']',
                'progressLabelSelector' => '[' . self::PROGRESS_LABEL_ATTRIBUTE . ']',
                'attributes'      => [
                    'eventId'     => self::EVENT_ID_ATTRIBUTE,
                    'actionLabel' => self::ACTION_LABEL_ATTRIBUTE,
                    'actionUrl'   => self::ACTION_URL_ATTRIBUTE,
                ],
                'statusClassPrefix' => 'cmp-ticket-card--',
            ],
            'desktopSwiper'            => [
                'initialSlide' => 1,
            ],
            'mobileSwiper'             => [
                'centeredSlides' => false,
                'effect'         => 'slide',
                'grabCursor'     => false,
                'initialSlide'   => 0,
                'pagination'     => [
                    'dynamicBullets' => true,
                ],
            ],
            'reducedMotionSwiper'      => [
                'grabCursor' => false,
                'speed'      => 0,
            ],
            'swiper'                   => [
                'a11y'                => [
                    'enabled'                  => true,
                    'containerMessage'         => Text::_('MOD_COPYMYPAGE_TICKETS_SWIPER_CONTAINER_LABEL'),
                    'firstSlideMessage'        => Text::_('MOD_COPYMYPAGE_TICKETS_SWIPER_FIRST'),
                    'lastSlideMessage'         => Text::_('MOD_COPYMYPAGE_TICKETS_SWIPER_LAST'),
                    'nextSlideMessage'         => Text::_('MOD_COPYMYPAGE_TICKETS_SWIPER_NEXT'),
                    'paginationBulletMessage'  => Text::_('MOD_COPYMYPAGE_TICKETS_SWIPER_PAGINATION'),
                    'prevSlideMessage'         => Text::_('MOD_COPYMYPAGE_TICKETS_SWIPER_PREVIOUS'),
                    'slideLabelMessage'        => Text::_('MOD_COPYMYPAGE_TICKETS_SWIPER_SLIDE_LABEL'),
                ],
                'centeredSlides'      => true,
                'centerInsufficientSlides' => false,
                'coverflowEffect'     => [
                    'depth'        => 110,
                    'modifier'     => 1,
                    'rotate'       => 24,
                    'scale'        => 0.94,
                    'slideShadows' => false,
                    'stretch'      => -8,
                ],
                'effect'              => 'coverflow',
                'grabCursor'          => true,
                'keyboard'            => [
                    'enabled'        => true,
                    'onlyInViewport' => true,
                    'pageUpDown'     => true,
                ],
                'loop'                => false,
                'initialSlide'        => 0,
                'pagination'          => [
                    'clickable' => true,
                    'type'      => 'bullets',
                ],
                'roundLengths'        => true,
                'slidesPerView'       => 'auto',
                'spaceBetween'        => 20,
                'speed'               => 520,
                'watchOverflow'       => true,
            ],
        ];
    }

    /**
     * Markup attribute names derived from the same constants as getClientConfig().
     *
     * @return array<string, string>
     */
    public function getMarkupAttributes(): array
    {
        return [
            'next'            => self::NEXT_ATTRIBUTE,
            'pagination'      => self::PAGINATION_ATTRIBUTE,
            'paginationLabel' => self::PAGINATION_LABEL_ATTRIBUTE,
            'previous'        => self::PREVIOUS_ATTRIBUTE,
            'root'            => self::ROOT_ATTRIBUTE,
            'eventId'         => self::EVENT_ID_ATTRIBUTE,
            'status'          => self::STATUS_ATTRIBUTE,
            'action'          => self::ACTION_ATTRIBUTE,
            'actionLabel'     => self::ACTION_LABEL_ATTRIBUTE,
            'actionUrl'       => self::ACTION_URL_ATTRIBUTE,
            'progress'        => self::PROGRESS_ATTRIBUTE,
            'progressLabel'   => self::PROGRESS_LABEL_ATTRIBUTE,
        ];
    }

    /**
     * Swiper and module class tokens shared with the server-rendered markup.
     *
     * @return array<string, string>
     */
    public function getMarkupClasses(): array
    {
        return [
            'root'          => self::SWIPER_CLASS,
            'slide'         => self::SLIDE_CLASS,
            'swiperSlide'   => self::SWIPER_SLIDE_CLASS,
            'swiperWrapper' => self::SWIPER_WRAPPER_CLASS,
        ];
    }

    public function getEyebrow(array $cfg, string $layout): string
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);

        return trim(self::cfgString(
            $layoutConfig,
            'eyebrow',
            Text::_('MOD_COPYMYPAGE_TICKETS_DEFAULT_EYEBROW')
        ));
    }

    public function getHeadline(array $cfg, string $layout): string
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);
        $headline     = trim(self::cfgString(
            $layoutConfig,
            'headline',
            Text::_('MOD_COPYMYPAGE_TICKETS_DEFAULT_HEADLINE')
        ));

        return $headline !== '' ? $headline : Text::_('MOD_COPYMYPAGE_TICKETS_DEFAULT_HEADLINE');
    }

    public function getLead(array $cfg, string $layout): string
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);

        return trim(self::cfgString(
            $layoutConfig,
            'lead',
            Text::_('MOD_COPYMYPAGE_TICKETS_DEFAULT_LEAD')
        ));
    }

    /**
     * Fetch upcoming bookable DPCalendar events and prepare card view models.
     *
     * @return array<int, object>
     */
    public function getItems(array $cfg, string $layout): array
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);
        $maxItems     = self::cfgInt($layoutConfig, 'maxItems', 6, 1, 12);
        $monthsAhead  = self::cfgInt($layoutConfig, 'monthsAhead', 18, 1, 36);
        $showSoldOut  = self::cfgBool($layoutConfig, 'showSoldOut', true);
        $fallback     = self::cfgString($layoutConfig, 'fallbackImage');
        $calendarIds  = self::normalizeCalendarIds($layoutConfig['calendarIds'] ?? [-1]);
        $items        = [];

        $catalog               = $this->getTicketCatalogService();
        $events                = $catalog->getUpcomingEventsForScope($calendarIds, $monthsAhead);
        $latestPurchaseDates   = $this->getLatestPurchaseDates($events);
        $availabilitySnapshots = $this->getAvailabilitySnapshots($events);

        foreach ($events as $event) {
            if (!\is_object($event)) {
                continue;
            }

            $eventId = (int) ($event->id ?? 0);
            $item    = $this->prepareEvent(
                $event,
                $fallback,
                $latestPurchaseDates[(string) $eventId] ?? null,
                $availabilitySnapshots[$eventId] ?? []
            );

            if ($item === null || (!$showSoldOut && $item->status === 'sold-out')) {
                continue;
            }

            $items[] = $item;

            if (\count($items) >= $maxItems) {
                break;
            }
        }

        return $items;
    }

    /**
     * Extract layout-prefixed parameters from the flat Joomla module config.
     *
     * @return array<string, mixed>
     */
    public static function getLayoutConfig(array $cfg, string $layout): array
    {
        $layout = self::normalizeLayoutKey($layout);

        if ($layout === '') {
            return [];
        }

        $prefix = $layout . '_';
        $result = [];

        foreach ($cfg as $key => $value) {
            $key = (string) $key;

            if (!str_starts_with($key, $prefix)) {
                continue;
            }

            $result[substr($key, \strlen($prefix))] = $value;
        }

        return $result;
    }

    /**
     * Resolve the central ticket catalogue registered by the CopyMyPage system plugin.
     */
    private function getTicketCatalogService(): TicketCatalogService
    {
        $container = Factory::getContainer();

        if (!$container->has(TicketCatalogService::class)) {
            throw new \RuntimeException('CopyMyPage ticket catalogue is unavailable.');
        }

        return $container->get(TicketCatalogService::class);
    }

    /**
     * Convert a DPCalendar event into a presentation-only card object.
     */
    private function prepareEvent(
        \stdClass $event,
        string $fallbackImage,
        ?string $latestPurchaseDate = null,
        array $availabilityFacts = []
    ): ?object
    {
        $title = trim((string) ($event->title ?? ''));

        if ($title === '') {
            return null;
        }

        $action        = $this->resolveActionLabels($event, $title);
        $availability  = $this->resolveAvailability($availabilityFacts, $latestPurchaseDate);
        $date          = $this->resolveDateMeta($event);
        $image         = $this->resolveEventImage($event, $fallbackImage, $title);
        $eventUrl      = '';
        $bookingUrl    = $this->resolveSelectionUrl((int) ($event->id ?? 0));

        try {
            $eventUrl = RouteHelper::getEventRoute(
                $event->id ?? 0,
                isset($event->catid) ? (string) $event->catid : ''
            );

        } catch (\Throwable) {
            $eventUrl   = '';
        }

        $eventUrl   = self::normalizeRouteUrl($eventUrl);
        $bookingUrl = self::normalizeRouteUrl($bookingUrl);

        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) ($event->id ?? 'event')) ?? 'event';

        return (object) [
            'actionAriaLabel' => $action['ariaLabel'],
            'actionLabel'     => $action['label'],
            'bookingUrl'      => trim($bookingUrl),
            'bookable'        => $availability['bookable'],
            'dateDay'         => $date['day'],
            'dateLabel'       => $date['label'],
            'dateMonth'       => $date['month'],
            'dateTime'        => $date['dateTime'],
            'dateWeekday'     => $date['weekday'],
            'dateYear'        => $date['year'],
            'eventUrl'          => trim($eventUrl),
            'id'                => $safeId,
            'eventId'           => (int) ($event->id ?? 0),
            'image'             => $image['src'],
            'imageAlt'          => $image['alt'],
            'imageAvifSrcset'   => $image['avifSrcset'],
            'imageHeight'       => $image['height'],
            'imageSizes'        => $image['sizes'],
            'imageSrcset'       => $image['srcset'],
            'imageWebpSrcset'   => $image['webpSrcset'],
            'imageWidth'        => $image['width'],
            'lastPurchaseLabel' => $availability['lastPurchaseLabel'],
            'paginationLabel'   => $date['paginationLabel'],
            'progress'          => $availability['progress'],
            'progressLabel'     => $availability['progressLabel'],
            'status'            => $availability['status'],
            'statusLabel'       => $availability['statusLabel'],
            'timeLabel'         => $date['timeLabel'],
            'title'             => $title,
        ];
    }

    /**
     * Resolve sale state, capacity text and progress without personalized ticket limits.
     *
     * @return array{
     *     bookable: bool,
     *     lastPurchaseLabel: string,
     *     progress: ?int,
     *     progressLabel: string,
     *     status: string,
     *     statusLabel: string
     * }
     */
    private function resolveAvailability(
        array $availability,
        ?string $latestPurchaseDate
    ): array
    {
        $capacity   = $availability['capacity'] ?? null;
        $nativeUsed = max(0, (int) ($availability['nativeUsed'] ?? 0));
        $used       = max(0, (int) ($availability['used'] ?? $nativeUsed));
        $remaining  = $capacity === null
            ? null
            : max(0, (int) ($availability['remaining'] ?? 0));
        $bookable   = !empty($availability['bookable']);
        $status     = (string) ($availability['status'] ?? 'unavailable');

        switch ($status) {
            case 'available':
                $label = $capacity === null
                    ? Text::_('MOD_COPYMYPAGE_TICKETS_STATUS_AVAILABLE_UNLIMITED')
                    : Text::plural('MOD_COPYMYPAGE_TICKETS_STATUS_AVAILABLE_COUNT', $remaining);
                break;

            case 'not-open':
                $opensAt = $availability['opensAt'] ?? null;
                $label   = \is_object($opensAt) && method_exists($opensAt, 'format')
                    ? Text::sprintf(
                        'MOD_COPYMYPAGE_TICKETS_STATUS_NOT_OPEN',
                        $opensAt->format(Text::_('DATE_FORMAT_LC2'), true)
                    )
                    : Text::_('MOD_COPYMYPAGE_TICKETS_STATUS_UNAVAILABLE');
                break;

            case 'closed':
                $label = Text::_('MOD_COPYMYPAGE_TICKETS_STATUS_CLOSED');
                break;

            case 'sold-out':
                $label = Text::_('MOD_COPYMYPAGE_TICKETS_STATUS_SOLD_OUT');
                break;

            default:
                $status = 'unavailable';
                $label  = Text::_('MOD_COPYMYPAGE_TICKETS_STATUS_UNAVAILABLE');
                break;
        }

        $lastPurchaseLabel = $this->resolveLastPurchaseLabel($latestPurchaseDate, $nativeUsed);
        $progressLabel     = '';
        $progress          = null;

        if ($capacity !== null && $capacity > 0) {
            $visibleUsed = min($used, $capacity);
            $progressLabel = Text::sprintf(
                'MOD_COPYMYPAGE_TICKETS_ALLOCATION_PROGRESS',
                $visibleUsed,
                $capacity
            );
            $progress = isset($availability['progress'])
                ? min(100, max(0, (int) $availability['progress']))
                : min(100, max(0, (int) round(($visibleUsed / $capacity) * 100)));
        }

        return [
            'bookable'          => $bookable,
            'lastPurchaseLabel' => $lastPurchaseLabel,
            'progress'          => $progress,
            'progressLabel'     => $progressLabel,
            'status'            => $status,
            'statusLabel'       => $label,
        ];
    }

    /**
     * Build the localized CTA from DPCalendar's configured event prices.
     *
     * @return array{ariaLabel: string, label: string}
     */
    private function resolveActionLabels(\stdClass $event, string $title): array
    {
        $configuredPrices = $event->prices ?? null;
        $hasPrices        = $configuredPrices instanceof \stdClass
            && get_object_vars($configuredPrices) !== [];
        $values           = [];

        if ($hasPrices) {
            foreach ((array) ($event->prices ?? []) as $price) {
                $value = \is_object($price) ? ($price->value ?? null) : null;

                if (!\is_numeric($value) || (float) $value < 0) {
                    continue;
                }

                $values[] = (float) $value;
            }
        }

        if (!$hasPrices) {
            $label = Text::_('MOD_COPYMYPAGE_TICKETS_ACTION_FREE');
        } elseif ($values === []) {
            $label = Text::_('MOD_COPYMYPAGE_TICKETS_ACTION');
        } else {
            $minimum     = min($values);
            $uniquePrice = array_unique(array_map(
                static fn(float $value): string => number_format($value, 4, '.', ''),
                $values
            ));
            $priceLabel = self::formatCompactPrice($minimum);

            if (\count($uniquePrice) > 1) {
                $priceLabel = Text::sprintf('MOD_COPYMYPAGE_TICKETS_PRICE_FROM', $priceLabel);
            }

            $label = Text::sprintf('MOD_COPYMYPAGE_TICKETS_ACTION_PRICE', $priceLabel);
        }

        return [
            'ariaLabel' => Text::sprintf('MOD_COPYMYPAGE_TICKETS_ACTION_ARIA_LABEL', $label, $title),
            'label'     => $label,
        ];
    }

    /**
     * Use DPCalendar's current currency and compact integral prices for the card CTA.
     */
    private static function formatCompactPrice(float $price): string
    {
        $rendered = self::htmlToPlainText(DPCalendarHelper::renderPrice(
            number_format($price, 2, '.', '')
        ));

        if (abs($price - round($price)) < 0.00001) {
            $rendered = preg_replace('/([,.])00(?=\D|$)/u', '', $rendered, 1) ?? $rendered;
        }

        return $rendered;
    }

    /**
     * Resolve the CopyMyPage accordion URL without falling back to DPCalendar's single-event form.
     */
    private function resolveSelectionUrl(int $eventId): string
    {
        if ($eventId < 1) {
            return '';
        }

        try {
            $container = Factory::getContainer();

            if (!$container->has(TicketReservationService::class)) {
                return '';
            }

            return $container->get(TicketReservationService::class)->getSelectionUrl($eventId);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Fetch the shared, seat-aware ticket availability for the rendered cards.
     *
     * @param   array<int, object>  $events  DPCalendar events selected for the module.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getAvailabilitySnapshots(array $events): array
    {
        $eventIds = [];

        foreach ($events as $event) {
            if (\is_object($event) && (int) ($event->id ?? 0) > 0) {
                $eventIds[] = (int) $event->id;
            }
        }

        if ($eventIds === []) {
            return [];
        }

        try {
            $container = Factory::getContainer();

            if (!$container->has(TicketReservationService::class)) {
                return [];
            }

            return $container->get(TicketReservationService::class)->getAvailabilitySnapshot($eventIds);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Fetch the latest completed ticket creation date for every local event in one query.
     *
     * @param   array<int, object>  $events  DPCalendar events selected for the module.
     *
     * @return array<string, string>
     */
    private function getLatestPurchaseDates(array $events): array
    {
        $eventIds = [];

        foreach ($events as $event) {
            if (!\is_object($event)) {
                continue;
            }

            $eventId = filter_var(
                $event->id ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($eventId !== false) {
                $eventIds[] = $eventId;
            }
        }

        $eventIds = array_values(array_unique($eventIds));

        if ($eventIds === []) {
            return [];
        }

        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('event_id'))
                ->select(
                    'MAX(' . $db->quoteName('created') . ') AS ' . $db->quoteName('latest_purchase_date')
                )
                ->from($db->quoteName('#__dpcalendar_tickets'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('event_id') . ' IN (' . implode(',', $eventIds) . ')')
                ->group($db->quoteName('event_id'));

            $rows = $db->setQuery($query)->loadAssocList();
        } catch (\Throwable) {
            return [];
        }

        $latestPurchaseDates = [];

        foreach ($rows as $row) {
            $eventId = trim((string) ($row['event_id'] ?? ''));
            $date    = trim((string) ($row['latest_purchase_date'] ?? ''));

            if ($eventId !== '' && $date !== '') {
                $latestPurchaseDates[$eventId] = $date;
            }
        }

        return $latestPurchaseDates;
    }

    /**
     * Format the latest completed ticket creation date without exposing buyer data.
     */
    private function resolveLastPurchaseLabel(?string $latestPurchaseDate, int $used): string
    {
        $latestPurchaseDate = trim((string) $latestPurchaseDate);

        if ($latestPurchaseDate === '') {
            return $used === 0 ? Text::_('MOD_COPYMYPAGE_TICKETS_LAST_PURCHASE_NONE') : '';
        }

        try {
            $date = DPCalendarHelper::getDate($latestPurchaseDate);
            $label = trim($date->format(Text::_('DATE_FORMAT_LC4'), true));
        } catch (\Throwable) {
            return '';
        }

        return $label !== ''
            ? Text::sprintf('MOD_COPYMYPAGE_TICKETS_LAST_PURCHASE', $label)
            : '';
    }

    /**
     * Build localized, semantic start date and time values.
     *
     * @return array{dateTime: string, day: string, label: string, month: string, paginationLabel: string, timeLabel: string, weekday: string, year: string}
     */
    private function resolveDateMeta(\stdClass $event): array
    {
        $allDay = (bool) ($event->all_day ?? false);
        $start  = DPCalendarHelper::getDate($event->start_date ?? null, $allDay);
        $end    = DPCalendarHelper::getDate($event->end_date ?? ($event->start_date ?? null), $allDay);

        if ($allDay) {
            $timeLabel = Text::_('MOD_COPYMYPAGE_TICKETS_ALL_DAY');
        } elseif (
            $start->format('Y-m-d', true, false) === $end->format('Y-m-d', true, false)
            && $start->format('H:i', true, false) !== $end->format('H:i', true, false)
        ) {
            $timeLabel = Text::sprintf(
                'MOD_COPYMYPAGE_TICKETS_TIME_RANGE',
                $start->format('H:i', true, false),
                $end->format('H:i', true, false)
            );
        } else {
            $timeLabel = Text::sprintf(
                'MOD_COPYMYPAGE_TICKETS_TIME',
                $start->format('H:i', true, false)
            );
        }

        return [
            'dateTime'        => $start->format('c', true, false),
            'day'             => $start->format('j', true, false),
            'label'           => $start->format('l, j. F Y', true),
            'month'           => $start->format('F', true),
            'paginationLabel' => $start->format('j.n.', true, false),
            'timeLabel'       => $timeLabel,
            'weekday'         => $start->format('l', true),
            'year'            => $start->format('Y', true, false),
        ];
    }

    /**
     * Resolve DPCalendar intro/full images and responsive CopyMyPage variants.
     *
     * @return array{
     *     alt: string,
     *     avifSrcset: string,
     *     height: int,
     *     sizes: string,
     *     src: string,
     *     srcset: string,
     *     webpSrcset: string,
     *     width: int
     * }
     */
    private function resolveEventImage(\stdClass $event, string $fallbackImage, string $title): array
    {
        $images    = \is_object($event->images ?? null) ? $event->images : new \stdClass();
        $rawImage  = trim((string) ($images->image_intro ?? ''));
        $imageAlt  = trim((string) ($images->image_intro_alt ?? ''));
        $rawWidth  = (int) ($images->image_intro_width ?? 0);
        $rawHeight = (int) ($images->image_intro_height ?? 0);

        if ($rawImage === '') {
            $rawImage  = trim((string) ($images->image_full ?? ''));
            $imageAlt  = trim((string) ($images->image_full_alt ?? ''));
            $rawWidth  = (int) ($images->image_full_width ?? 0);
            $rawHeight = (int) ($images->image_full_height ?? 0);
        }

        if ($rawImage === '') {
            $rawImage = trim($fallbackImage);
        }

        if ($rawImage === '') {
            $rawImage = '/modules/mod_copymypage_tickets/images/placeholder.svg';
            $imageAlt = Text::_('MOD_COPYMYPAGE_TICKETS_DEFAULT_IMAGE_ALT');
        }

        try {
            $imageHelper = Factory::getContainer()->get('copymypage.helper.image');
            $resolved    = $imageHelper->resolveMediaImage($rawImage);
            $picture     = $imageHelper->buildResponsiveImageData(
                $resolved['src'],
                self::CARD_IMAGE_VARIANT_WIDTHS,
                self::CARD_IMAGE_SIZES
            );
            $src = trim((string) ($picture['src'] ?? ''));

            if ($src === '') {
                $src = $imageHelper->toAbsoluteUrl((string) ($resolved['src'] ?? ''));
            }

            return [
                'alt'        => $imageAlt !== '' ? $imageAlt : $title,
                'avifSrcset' => trim((string) ($picture['avifSrcset'] ?? '')),
                'height'     => (int) ($picture['height'] ?: ($resolved['height'] ?: $rawHeight)),
                'sizes'      => trim((string) ($picture['sizes'] ?? self::CARD_IMAGE_SIZES)),
                'src'        => $src,
                'srcset'     => trim((string) ($picture['srcset'] ?? '')),
                'webpSrcset' => trim((string) ($picture['webpSrcset'] ?? '')),
                'width'      => (int) ($picture['width'] ?: ($resolved['width'] ?: $rawWidth)),
            ];
        } catch (\Throwable) {
            return [
                'alt'        => $imageAlt !== '' ? $imageAlt : $title,
                'avifSrcset' => '',
                'height'     => $rawHeight > 0 ? $rawHeight : self::DEFAULT_IMAGE_HEIGHT,
                'sizes'      => self::CARD_IMAGE_SIZES,
                'src'        => self::toAbsoluteUrl($rawImage),
                'srcset'     => '',
                'webpSrcset' => '',
                'width'      => $rawWidth > 0 ? $rawWidth : self::DEFAULT_IMAGE_WIDTH,
            ];
        }
    }

    /**
     * Resolve explicit OG values and normalize the configured media field.
     *
     * @return array<string, string>
     */
    private function resolveConfiguredOpenGraphMeta(array $config): array
    {
        $image       = trim((string) ($config['og_image'] ?? ''));
        $imageWidth  = max(0, (int) ($config['og_image_width'] ?? 0));
        $imageHeight = max(0, (int) ($config['og_image_height'] ?? 0));

        if ($image !== '') {
            try {
                $imageHelper = Factory::getContainer()->get('copymypage.helper.image');
                $resolved    = $imageHelper->resolveMediaImage($image);
                $image       = $imageHelper->toAbsoluteUrl($resolved['src']);
                $imageWidth  = $imageWidth > 0 ? $imageWidth : (int) $resolved['width'];
                $imageHeight = $imageHeight > 0 ? $imageHeight : (int) $resolved['height'];
            } catch (\Throwable) {
                $image = self::toAbsoluteUrl($image);
            }
        }

        $twitterCard = trim((string) ($config['og_twitter_card'] ?? ''));
        $twitterCard = \in_array($twitterCard, ['summary', 'summary_large_image'], true)
            ? $twitterCard
            : '';

        return [
            'title'       => self::htmlToPlainText((string) ($config['og_title'] ?? '')),
            'description' => self::htmlToPlainText((string) ($config['og_description'] ?? '')),
            'image'       => $image,
            'imageWidth'  => $imageWidth > 0 ? (string) $imageWidth : '',
            'imageHeight' => $imageHeight > 0 ? (string) $imageHeight : '',
            'imageAlt'    => self::htmlToPlainText((string) ($config['og_image_alt'] ?? '')),
            'twitterCard' => $twitterCard,
        ];
    }

    /**
     * @param   array<int, object>  $items  Prepared ticket cards.
     *
     * @return array<string, string>
     */
    private function resolvePrimaryItemMeta(array $items): array
    {
        $item = $items[0] ?? null;

        if (!\is_object($item)) {
            return [
                'image'       => '',
                'imageWidth'  => '',
                'imageHeight' => '',
                'imageAlt'    => '',
            ];
        }

        $width  = max(0, (int) ($item->imageWidth ?? 0));
        $height = max(0, (int) ($item->imageHeight ?? 0));

        return [
            'image'       => trim((string) ($item->image ?? '')),
            'imageWidth'  => $width > 0 ? (string) $width : '',
            'imageHeight' => $height > 0 ? (string) $height : '',
            'imageAlt'    => trim((string) ($item->imageAlt ?? $item->title ?? '')),
        ];
    }

    /**
     * @param   array<string, string>  $defaults    Derived metadata.
     * @param   array<string, string>  $configured  Explicit overrides.
     *
     * @return array<string, string>
     */
    private static function mergeOpenGraphMeta(array $defaults, array $configured): array
    {
        foreach ($configured as $key => $value) {
            if (trim($value) !== '') {
                $defaults[$key] = trim($value);
            }
        }

        return $defaults;
    }

    private function resolveLayoutVariant(array $config, string $layout, string $slot): string
    {
        $layout = self::normalizeLayoutKey($layout);

        if ($layout !== '') {
            return $layout;
        }

        $configured = self::normalizeLayoutKey((string) ($config['layoutVariant'] ?? ''));

        if ($configured !== '') {
            return $configured;
        }

        if ($this->defaultLayout !== '') {
            return $this->defaultLayout;
        }

        $prefix = $this->layoutPrefix !== ''
            ? $this->layoutPrefix
            : self::normalizeLayoutKey($slot);

        return ($prefix !== '' ? $prefix : self::DEFAULT_SLOT) . '_default';
    }

    /**
     * @return array<int, int|string>
     */
    private static function normalizeCalendarIds(mixed $value): array
    {
        if ($value instanceof Registry) {
            $value = $value->toArray();
        } elseif (\is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }
        } elseif (!\is_array($value)) {
            $value = [$value];
        }

        $ids = [];

        foreach ($value as $id) {
            $id = trim((string) $id);

            if ($id === '' || $id === '-1' || strtolower($id) === 'root') {
                return [];
            }

            $ids[] = ctype_digit($id) ? (int) $id : $id;
        }

        return array_values(array_unique($ids, SORT_REGULAR));
    }

    private static function cfgBool(array $config, string $key, bool $default = false): bool
    {
        if (!\array_key_exists($key, $config)) {
            return $default;
        }

        $value = filter_var($config[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $value ?? $default;
    }

    private static function cfgInt(array $config, string $key, int $default, int $minimum, int $maximum): int
    {
        $value = filter_var($config[$key] ?? $default, FILTER_VALIDATE_INT);
        $value = $value === false ? $default : $value;

        return min($maximum, max($minimum, $value));
    }

    private static function cfgString(array $config, string $key, string $default = ''): string
    {
        $value = $config[$key] ?? $default;

        if (\is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return $default;
    }

    private static function normalizeLayoutKey(string $layout): string
    {
        $layout = strtolower(trim($layout));

        return preg_match('/^[a-z0-9_-]+$/', $layout) ? $layout : '';
    }

    private static function htmlToPlainText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /**
     * Convert Joomla's XHTML route separators back to raw query separators.
     * The template applies the final context-aware HTML escaping exactly once.
     */
    private static function normalizeRouteUrl(string $url): string
    {
        return trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function toAbsoluteUrl(string $url): string
    {
        $url = trim(preg_replace('/#.*$/', '', $url) ?? '');

        if ($url === '' || preg_match('#^(?:https?:)?//#i', $url) || str_starts_with($url, 'data:')) {
            return $url;
        }

        return rtrim(Uri::root(), '/') . '/' . ltrim($url, '/');
    }
}
