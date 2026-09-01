<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.20
 */

namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use DigitalPeak\Component\DPCalendar\Administrator\Booking\Stages\CollectEventsAndTickets;
use DigitalPeak\Component\DPCalendar\Administrator\Booking\Stages\SetupForNew;
use DigitalPeak\Component\DPCalendar\Administrator\Pipeline\Pipeline;
use DigitalPeak\Component\DPCalendar\Site\Helper\RouteHelper as DPCalendarRouteHelper;
use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\Component\Content\Site\Helper\RouteHelper as ContentRouteHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Owns the guarded Step-4 confirmation and the one-way DPCalendar hand-off.
 */
final class OrderCheckoutService
{
    private const PROVIDER_ID_PATTERN = '/^[A-Za-z0-9_.-]{1,255}$/';

    public function __construct(
        private readonly CMSWebApplicationInterface $app,
        private readonly DatabaseInterface $db,
        private readonly OrderReviewService $orderReview,
        private readonly TicketCartContextService $cartContext,
        private readonly TicketCatalogService $ticketCatalog,
        private readonly TicketSeatProjectionService $ticketSeats
    ) {
    }

    /**
     * Add the non-mutating terms, payment and final-price state to Step 4.
     *
     * @param   array<string, mixed>  $reviewState
     *
     * @return array<string, mixed>
     */
    public function getViewState(array $reviewState): array
    {
        $state = $this->emptyViewState($reviewState);

        if (!empty($reviewState['blocked'])) {
            return $state;
        }

        try {
            $prepared = $this->prepareCheckout($reviewState);

            unset(
                $prepared['_bookingInput'],
                $prepared['_cartId'],
                $prepared['_rawProviders'],
                $prepared['_termsSnapshot']
            );

            return array_replace($state, $prepared);
        } catch (\Throwable $exception) {
            Log::add(
                'CopyMyPage order checkout could not be prepared (' . $exception::class . ').',
                Log::WARNING,
                'com_copymypage'
            );
            $state['checkoutIssues'][] = Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_PREPARE');

            return $state;
        }
    }

    /**
     * Atomically convert the current cart and return the DPCalendar payment/order route.
     *
     * @return array{bookingId: int, paymentRequired: bool, statusUrl: string, url: string}
     */
    public function checkout(
        int $expectedRevision,
        bool $termsAccepted,
        string $paymentProvider,
        string $checkoutSignature
    ): array {
        if (!$termsAccepted) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_TERMS'));
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $checkoutSignature)) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_CONFLICT'));
        }

        $bookingId = 0;
        $this->cartContext->beginTransaction();

        try {
            $cart = $this->cartContext->getActiveCartForUpdate();

            if ($cart === null) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_BLOCKED_MESSAGE'));
            }

            $this->cartContext->assertExpectedRevision($cart, $expectedRevision);
            $reviewState = $this->orderReview->getViewState();

            if (!empty($reviewState['blocked'])) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_BLOCKED_MESSAGE'));
            }

            $eventIds = $this->extractEventIds($reviewState);
            $this->lockEvents($eventIds);
            $heldSeats = $this->lockAndValidateHeldSeats(
                (int) $cart->id,
                $reviewState
            );

            // Repeat every authoritative projection after all relevant rows are locked.
            $reviewState = $this->orderReview->getViewState();
            $prepared    = $this->prepareCheckout($reviewState, $cart);

            if (
                empty($prepared['checkoutReady'])
                || !hash_equals((string) $prepared['checkoutSignature'], $checkoutSignature)
            ) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_CONFLICT'));
            }

            $providers       = (array) ($prepared['_rawProviders'] ?? []);
            $paymentRequired = !empty($prepared['paymentRequired']);

            if ($paymentRequired) {
                if (!preg_match(self::PROVIDER_ID_PATTERN, $paymentProvider) || !isset($providers[$paymentProvider])) {
                    throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_PAYMENT_PROVIDER'));
                }
            } else {
                $paymentProvider = '';
            }

            $selectedProvider = $paymentRequired
                ? $this->findPublicProvider((array) $prepared['paymentProviders'], $paymentProvider)
                : [
                    'currency'       => (string) ($prepared['currency'] ?? ''),
                    'fee'            => 0.0,
                    'feeFormatted'   => $this->ticketCatalog->formatPrice(0.0),
                    'id'             => '',
                    'label'          => '',
                    'total'          => (float) ($prepared['baseTotal'] ?? 0.0),
                    'totalFormatted' => (string) ($prepared['baseTotalFormatted'] ?? ''),
                ];

            $bookingId = $this->createDPCalendarBooking((array) $prepared['_bookingInput']);
            $this->linkSeatsToTickets($bookingId, (int) $cart->id, $reviewState, $heldSeats);
            $this->ticketSeats->clearBooking($bookingId);

            $acceptedAt = $this->cartContext->now();
            $snapshot   = $this->buildAcceptanceSnapshot(
                $acceptedAt,
                $expectedRevision,
                (array) $prepared['_termsSnapshot'],
                $selectedProvider,
                $eventIds,
                $checkoutSignature
            );
            $this->cartContext->convertCart(
                (int) $cart->id,
                $bookingId,
                $paymentProvider,
                $acceptedAt,
                $snapshot
            );

            $booking = $this->confirmDPCalendarBooking($bookingId, $paymentProvider);
            $this->assertConfirmedBooking($booking, $selectedProvider, $paymentRequired);

            $bookingRoute = DPCalendarRouteHelper::getBookingRoute($booking);
            $route        = $bookingRoute
                . '&layout=' . ($paymentRequired ? 'pay' : 'order');

            $this->cartContext->commitTransaction();

            return [
                'bookingId'       => $bookingId,
                'paymentRequired' => $paymentRequired,
                'statusUrl'       => $bookingRoute . '&layout=order',
                'url'             => $route,
            ];
        } catch (\Throwable $exception) {
            $this->cartContext->rollbackTransaction();

            if (
                $bookingId > 0
                && (int) $this->app->getSession()->get('com_dpcalendar.booking_id', 0) === $bookingId
            ) {
                $this->app->getSession()->remove('com_dpcalendar.booking_id');
            }

            throw $exception;
        }
    }

    /**
     * Release CopyMyPage seats when the related DPCalendar booking is cancelled or deleted.
     */
    public function releaseBookingSeats(int $bookingId): void
    {
        if ($bookingId < 1) {
            return;
        }

        $this->db->transactionStart();

        try {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__copymypage_ticket_carts'))
                ->where($this->db->quoteName('booking_id') . ' = :bookingId')
                ->order($this->db->quoteName('id') . ' ASC')
                ->bind(':bookingId', $bookingId, ParameterType::INTEGER);
            $cartIds = array_map(
                'intval',
                (array) $this->db->setQuery((string) $query . ' FOR UPDATE')->loadColumn()
            );

            if ($cartIds === []) {
                $this->db->transactionCommit();

                return;
            }

            $modified = $this->cartContext->now();
            $userId   = max(0, (int) ($this->app->getIdentity()->id ?? 0));
            $ids      = implode(',', $cartIds);
            $query    = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__copymypage_event_seats'))
                ->set($this->db->quoteName('status') . ' = ' . EventSeatInventoryService::SEAT_STATUS_AVAILABLE)
                ->set($this->db->quoteName('cart_id') . ' = NULL')
                ->set($this->db->quoteName('price_index') . ' = NULL')
                ->set($this->db->quoteName('assignment_order') . ' = NULL')
                ->set($this->db->quoteName('ticket_id') . ' = NULL')
                ->set($this->db->quoteName('modified') . ' = :modified')
                ->set($this->db->quoteName('modified_by') . ' = :userId')
                ->where($this->db->quoteName('cart_id') . ' IN (' . $ids . ')')
                ->where($this->db->quoteName('status') . ' = ' . EventSeatInventoryService::SEAT_STATUS_BOOKED)
                ->bind(':modified', $modified, ParameterType::STRING)
                ->bind(':userId', $userId, ParameterType::INTEGER);
            $this->db->setQuery($query)->execute();

            $query = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__copymypage_ticket_carts'))
                ->set($this->db->quoteName('status') . ' = ' . TicketCartContextService::STATUS_RELEASED)
                ->set($this->db->quoteName('revision') . ' = ' . $this->db->quoteName('revision') . ' + 1')
                ->set($this->db->quoteName('modified') . ' = :modified')
                ->where($this->db->quoteName('id') . ' IN (' . $ids . ')')
                ->where($this->db->quoteName('status') . ' = ' . TicketCartContextService::STATUS_CONVERTED)
                ->bind(':modified', $modified, ParameterType::STRING);
            $this->db->setQuery($query)->execute();
            $this->db->transactionCommit();
        } catch (\Throwable $exception) {
            $this->db->transactionRollback();

            throw $exception;
        }
    }

    /**
     * @param   array<string, mixed>  $reviewState
     *
     * @return array<string, mixed>
     */
    private function emptyViewState(array $reviewState): array
    {
        return [
            'baseTotal'          => (float) ($reviewState['cart']['totalPrice'] ?? 0.0),
            'baseTotalFormatted' => (string) ($reviewState['cart']['totalFormatted'] ?? ''),
            'checkoutAction'     => Route::_(
                'index.php?option=com_copymypage&task=orderreview.checkout',
                false
            ),
            'checkoutIssues'     => [],
            'checkoutReady'      => false,
            'checkoutSignature'  => '',
            'currency'           => '',
            'expectedRevision'   => max(0, (int) ($reviewState['cart']['cartRevision'] ?? 0)),
            'paymentProviders'   => [],
            'paymentRequired'    => (float) ($reviewState['cart']['totalPrice'] ?? 0.0) > 0,
            'terms'              => [],
        ];
    }

    /**
     * @param   array<string, mixed>  $reviewState
     *
     * @return array<string, mixed>
     */
    private function prepareCheckout(array $reviewState, ?object $lockedCart = null): array
    {
        if (!empty($reviewState['blocked'])) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_BLOCKED_MESSAGE'));
        }

        $cart = $lockedCart ?? $this->cartContext->getActiveCart();

        if (
            $cart === null
            || (int) ($cart->revision ?? -1) !== (int) ($reviewState['cart']['cartRevision'] ?? -2)
        ) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_CONFLICT'));
        }

        $eventIds      = $this->extractEventIds($reviewState);
        $eventSettings = $this->loadEventSettings($eventIds);
        $termsState    = $this->loadTerms($eventSettings);
        $bookingInput  = $this->buildBookingInput($reviewState);
        $bookingData   = $this->calculateBookingData($bookingInput);
        $baseTotal     = round(max(0.0, (float) ($bookingData['price'] ?? 0.0)), 2);
        $currency      = trim((string) ($bookingData['currency'] ?? ''));
        $issues        = [];

        if (!$termsState['complete']) {
            $issues[] = Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_TERMS_CONFIGURATION');
        }

        if ($currency === '') {
            $issues[] = Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_PREPARE');
        }

        $providerState   = $this->loadPaymentProviders($eventSettings, $bookingData);
        $paymentRequired = $baseTotal > 0;

        if ($paymentRequired && $providerState['public'] === []) {
            $issues[] = Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_PAYMENT_CONFIGURATION');
        }

        $signature = $issues === []
            ? $this->createCheckoutSignature(
                (int) $cart->id,
                (int) $cart->revision,
                $termsState['terms'],
                $providerState['public'],
                $baseTotal,
                $currency
            )
            : '';

        return [
            '_bookingInput'      => $bookingInput,
            '_cartId'            => (int) $cart->id,
            '_rawProviders'      => $providerState['raw'],
            '_termsSnapshot'     => $termsState['snapshots'],
            'baseTotal'          => $baseTotal,
            'baseTotalFormatted' => $this->ticketCatalog->formatPrice($baseTotal),
            'checkoutAction'     => Route::_(
                'index.php?option=com_copymypage&task=orderreview.checkout',
                false
            ),
            'checkoutIssues'     => array_values(array_unique($issues)),
            'checkoutReady'      => $issues === [],
            'checkoutSignature'  => $signature,
            'currency'           => $currency,
            'expectedRevision'   => (int) $cart->revision,
            'paymentProviders'   => $providerState['public'],
            'paymentRequired'    => $paymentRequired,
            'terms'              => $termsState['terms'],
        ];
    }

    /**
     * @param   array<string, mixed>  $reviewState
     *
     * @return list<int>
     */
    private function extractEventIds(array $reviewState): array
    {
        $eventIds = [];

        foreach ((array) ($reviewState['items'] ?? []) as $item) {
            $eventId = max(0, (int) ($item['eventId'] ?? 0));

            if ($eventId < 1) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_CONFLICT'));
            }

            $eventIds[$eventId] = $eventId;
        }

        if ($eventIds === []) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_CONFLICT'));
        }

        sort($eventIds, SORT_NUMERIC);

        return array_values($eventIds);
    }

    /**
     * @param   list<int>  $eventIds
     *
     * @return array<int, object>
     */
    private function loadEventSettings(array $eventIds): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('payment_provider'),
                $this->db->quoteName('terms'),
            ])
            ->from($this->db->quoteName('#__dpcalendar_events'))
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', $eventIds) . ')')
            ->order($this->db->quoteName('id') . ' ASC');
        $settings = [];

        foreach ((array) $this->db->setQuery($query)->loadObjectList() as $event) {
            $settings[(int) $event->id] = $event;
        }

        if (
            \count($settings) !== \count($eventIds)
            || array_keys($settings) !== $eventIds
        ) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_CONFLICT'));
        }

        return $settings;
    }

    /**
     * @param   array<int, object>  $eventSettings
     *
     * @return array{
     *     complete: bool,
     *     snapshots: list<array<string, mixed>>,
     *     terms: list<array<string, mixed>>
     * }
     */
    private function loadTerms(array $eventSettings): array
    {
        $defaultArticleId = max(
            0,
            (int) ComponentHelper::getParams('com_dpcalendar')->get('event_form_terms', 0)
        );
        $articleEventIds = [];
        $articleIds      = [];
        $complete        = true;

        foreach ($eventSettings as $event) {
            $articleId = max(0, (int) ($event->terms ?? 0));

            if ($articleId < 1) {
                $articleId = $defaultArticleId;
            }

            if ($articleId < 1) {
                $complete = false;
                continue;
            }

            $articleIds[$articleId] = $articleId;
            $articleEventIds[$articleId][] = max(0, (int) ($event->id ?? 0));
        }

        $snapshots = [];
        $terms     = [];

        if ($articleIds !== []) {
            $model = $this->app->bootComponent('com_content')
                ->getMVCFactory()
                ->createModel('Article', 'Site', ['ignore_request' => true]);
            $model->setState('params', new Registry());
            $model->setState('filter.published', 1);

            foreach ($articleIds as $articleId) {
                try {
                    $article = $model->getItem($articleId);
                } catch (\Throwable) {
                    $article = null;
                }

                if (!$article instanceof \stdClass || (int) ($article->id ?? 0) !== $articleId) {
                    $complete = false;
                    continue;
                }

                $title     = trim((string) ($article->title ?? ''));
                $modified  = (string) ($article->modified ?? '');
                $introtext = (string) ($article->introtext ?? '');
                $fulltext  = (string) ($article->fulltext ?? '');

                if ($title === '') {
                    $complete = false;
                    continue;
                }

                $hash = hash(
                    'sha256',
                    implode("\0", [
                        (string) $articleId,
                        $title,
                        $modified,
                        $introtext,
                        $fulltext,
                    ])
                );
                $url  = Route::_(ContentRouteHelper::getArticleRoute(
                    $articleId,
                    (int) ($article->catid ?? 0),
                    (string) ($article->language ?? '*')
                ));

                $terms[] = [
                    'hash'     => $hash,
                    'id'       => $articleId,
                    'modified' => $modified,
                    'title'    => $title,
                    'url'      => $url,
                ];
                $snapshots[] = [
                    'content'  => [
                        'fulltext'  => $fulltext,
                        'introtext' => $introtext,
                    ],
                    'eventIds' => array_values($articleEventIds[$articleId] ?? []),
                    'hash'     => $hash,
                    'id'       => $articleId,
                    'modified' => $modified,
                    'title'    => $title,
                    'url'      => $url,
                ];
            }
        }

        if (\count($terms) !== \count($articleIds)) {
            $complete = false;
        }

        return [
            'complete'  => $complete && $terms !== [],
            'snapshots' => $snapshots,
            'terms'     => $terms,
        ];
    }

    /**
     * @param   array<int, object>    $eventSettings
     * @param   array<string, mixed>  $bookingData
     *
     * @return array{public: list<array<string, mixed>>, raw: array<string, object>}
     */
    private function loadPaymentProviders(array $eventSettings, array $bookingData): array
    {
        PluginHelper::importPlugin('dpcalendarpay');
        $available = [];

        foreach ($this->app->triggerEvent('onDPPaymentProviders') as $pluginProviders) {
            foreach ((array) $pluginProviders as $provider) {
                if (!$provider instanceof \stdClass) {
                    continue;
                }

                $providerId = trim((string) ($provider->id ?? ''));

                if (!preg_match(self::PROVIDER_ID_PATTERN, $providerId)) {
                    continue;
                }

                $available[$providerId] = $provider;
            }
        }

        $eligibleIds = array_keys($available);

        foreach ($eventSettings as $event) {
            $configured = array_values(array_filter(
                array_map('trim', explode(',', (string) ($event->payment_provider ?? ''))),
                static fn(string $providerId): bool => $providerId !== ''
            ));

            if ($configured !== []) {
                $eligibleIds = array_values(array_intersect($eligibleIds, $configured));
            }
        }

        sort($eligibleIds, SORT_STRING);
        $public = [];
        $raw    = [];

        foreach ($eligibleIds as $providerId) {
            $provider = $available[$providerId];
            $price     = $this->calculateProviderPrice($bookingData, $provider);
            $pluginKey = 'PLG_' . strtoupper(
                (string) ($provider->plugin_type ?? 'dpcalendarpay')
                . '_'
                . (string) ($provider->plugin_name ?? '')
            ) . '_TITLE';
            $pluginTitle   = trim(Text::_($pluginKey));
            $providerTitle = trim(Text::_((string) ($provider->title ?? '')));
            $label         = $providerTitle !== '' ? $providerTitle : $pluginTitle;

            if (
                $pluginTitle !== ''
                && $pluginTitle !== $pluginKey
                && $providerTitle !== ''
                && $providerTitle !== $pluginTitle
            ) {
                $label = $pluginTitle . ' – ' . $providerTitle;
            }

            if ($label === '') {
                $label = $providerId;
            }

            $descriptionKey = trim((string) ($provider->description ?? ''));
            $description    = $descriptionKey === '' ? '' : trim(strip_tags(Text::_($descriptionKey)));
            $public[]       = [
                'currency'       => (string) ($bookingData['currency'] ?? ''),
                'description'    => $description,
                'fee'            => $price['fee'],
                'feeFormatted'   => $this->ticketCatalog->formatPrice($price['fee']),
                'id'             => $providerId,
                'label'          => $label,
                'total'          => $price['total'],
                'totalFormatted' => $this->ticketCatalog->formatPrice($price['total']),
            ];
            $raw[$providerId] = $provider;
        }

        return ['public' => $public, 'raw' => $raw];
    }

    /**
     * Reproduce DPCalendar's provider-fee calculation for the Step-4 total.
     *
     * @param   array<string, mixed>  $bookingData
     *
     * @return array{fee: float, total: float}
     */
    private function calculateProviderPrice(array $bookingData, object $provider): array
    {
        $originalPrice = max(0.0, (float) ($bookingData['price'] ?? 0.0));
        $price         = $originalPrice;
        $tax           = max(0.0, (float) ($bookingData['tax'] ?? 0.0));
        $taxRate       = max(0.0, (float) ($bookingData['tax_rate'] ?? 0.0));
        $taxInclusive  = !empty($bookingData['tax_inclusive']);

        if ($tax > 0 && !$taxInclusive) {
            $price -= $tax;
        }

        $feeAmount = max(0.0, (float) ($provider->fee_amount ?? 0.0));
        $fee       = (string) ($provider->fee_type ?? 'percentage') === 'value'
            ? $feeAmount
            : ($feeAmount * $price) / 100;

        if ($fee <= 0) {
            return ['fee' => 0.0, 'total' => round($originalPrice, 2)];
        }

        $price += $fee;

        if ($tax > 0 && $taxRate > 0) {
            $tax = $taxInclusive
                ? $price - ($price / (1 + ($taxRate / 100)))
                : ($price / 100) * $taxRate;
            $price += $taxInclusive ? 0 : $tax;
        }

        return ['fee' => round($fee, 2), 'total' => round($price, 2)];
    }

    /**
     * @param   array<string, mixed>  $reviewState
     *
     * @return array<string, mixed>
     */
    private function buildBookingInput(array $reviewState): array
    {
        $eventSelection = [];

        foreach ((array) ($reviewState['items'] ?? []) as $item) {
            $eventId = max(0, (int) ($item['eventId'] ?? 0));
            $tickets = [];

            foreach ((array) ($item['prices'] ?? []) as $price) {
                $priceIndex = max(0, (int) ($price['index'] ?? 0));
                $quantity   = max(0, (int) ($price['quantity'] ?? 0));

                if ($quantity < 1 || isset($tickets[$priceIndex])) {
                    throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_CONFLICT'));
                }

                $tickets[$priceIndex] = $quantity;
            }

            if ($eventId < 1 || $tickets === []) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_CONFLICT'));
            }

            $eventSelection[$eventId] = ['tickets' => $tickets];
        }

        $customer = (array) ($reviewState['customer'] ?? []);

        return [
            'city'       => (string) ($customer['city'] ?? ''),
            'country'    => (string) ($customer['countryCode'] ?? ''),
            'email'      => (string) ($customer['email'] ?? ''),
            'event_id'   => $eventSelection,
            'first_name' => (string) ($customer['firstName'] ?? ''),
            'name'       => (string) ($customer['lastName'] ?? ''),
            'number'     => (string) ($customer['houseNumber'] ?? ''),
            'province'   => (string) (
                ($customer['regionName'] ?? '') !== ''
                    ? $customer['regionName']
                    : ($customer['regionCode'] ?? '')
            ),
            'series'     => 0,
            'state'      => 2,
            'street'     => (string) ($customer['street'] ?? ''),
            'telephone'  => (string) ($customer['telephone'] ?? ''),
            'user_id'    => max(0, (int) ($this->app->getIdentity()->id ?? 0)),
            'zip'        => (string) ($customer['postcode'] ?? ''),
        ];
    }

    /**
     * Run DPCalendar's own read-only price pipeline and verify that it kept every ticket.
     *
     * @param   array<string, mixed>  $bookingInput
     *
     * @return array<string, mixed>
     */
    private function calculateBookingData(array $bookingInput): array
    {
        $factory = $this->app->bootComponent('dpcalendar')->getMVCFactory();
        $model   = $factory->createModel('Booking', 'Administrator', ['ignore_request' => true]);
        $payload = (object) [
            'data'              => $bookingInput,
            'events'            => [],
            'eventsWithTickets' => [],
            'tickets'           => [],
        ];
        $pipeline = new Pipeline();
        $pipeline->add(new CollectEventsAndTickets($this->app, $model));
        $pipeline->add(new SetupForNew(
            $this->app,
            $this->app->getIdentity(),
            $factory->createModel('Taxrate', 'Administrator', ['ignore_request' => true]),
            $factory->createModel('Coupon', 'Administrator', ['ignore_request' => true]),
            $factory->createModel('Currency', 'Administrator', ['ignore_request' => true]),
            ComponentHelper::getParams('com_dpcalendar'),
            true
        ));
        $pipeline->process($payload);

        $expected = [];

        foreach ((array) $bookingInput['event_id'] as $eventId => $selection) {
            foreach ((array) ($selection['tickets'] ?? []) as $priceIndex => $quantity) {
                $expected[(int) $eventId][(int) $priceIndex] = (int) $quantity;
            }
        }

        $actual = [];

        foreach ((array) $payload->events as $event) {
            foreach ((array) ($event->amount_tickets ?? []) as $priceIndex => $quantity) {
                if ((int) $quantity > 0) {
                    $actual[(int) $event->id][(int) $priceIndex] = (int) $quantity;
                }
            }
        }

        ksort($expected, SORT_NUMERIC);
        ksort($actual, SORT_NUMERIC);

        foreach ($expected as &$ticketGroups) {
            ksort($ticketGroups, SORT_NUMERIC);
        }
        unset($ticketGroups);

        foreach ($actual as &$ticketGroups) {
            ksort($ticketGroups, SORT_NUMERIC);
        }
        unset($ticketGroups);

        if ($actual !== $expected) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_AVAILABILITY'));
        }

        return (array) $payload->data;
    }

    /**
     * @param   list<int>  $eventIds
     */
    private function lockEvents(array $eventIds): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__dpcalendar_events'))
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', $eventIds) . ')')
            ->order($this->db->quoteName('id') . ' ASC');
        $lockedIds = array_map(
            'intval',
            (array) $this->db->setQuery((string) $query . ' FOR UPDATE')->loadColumn()
        );

        if ($lockedIds !== $eventIds) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_AVAILABILITY'));
        }
    }

    /**
     * @param   array<string, mixed>  $reviewState
     *
     * @return list<object>
     */
    private function lockAndValidateHeldSeats(int $cartId, array $reviewState): array
    {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('event_id'),
                $this->db->quoteName('price_index'),
                $this->db->quoteName('assignment_order'),
                $this->db->quoteName('ticket_id'),
            ])
            ->from($this->db->quoteName('#__copymypage_event_seats'))
            ->where($this->db->quoteName('cart_id') . ' = :cartId')
            ->where($this->db->quoteName('status') . ' = ' . EventSeatInventoryService::SEAT_STATUS_HELD)
            ->order($this->db->quoteName('event_id') . ' ASC')
            ->order($this->db->quoteName('price_index') . ' ASC')
            ->order($this->db->quoteName('assignment_order') . ' ASC')
            ->order($this->db->quoteName('id') . ' ASC')
            ->bind(':cartId', $cartId, ParameterType::INTEGER);
        $rows     = (array) $this->db->setQuery((string) $query . ' FOR UPDATE')->loadObjectList();
        $expected = $this->expectedTicketGroups($reviewState);
        $actual   = [];

        foreach ($rows as $row) {
            if ((int) ($row->ticket_id ?? 0) > 0 || (int) ($row->assignment_order ?? 0) < 1) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_AVAILABILITY'));
            }

            $actual[(int) $row->event_id][(int) $row->price_index][] = $row;
        }

        foreach ($actual as &$eventGroups) {
            foreach ($eventGroups as &$groupRows) {
                $groupRows = array_values($groupRows);
            }
            unset($groupRows);
        }
        unset($eventGroups);

        $actualCounts = [];

        foreach ($actual as $eventId => $groups) {
            foreach ($groups as $priceIndex => $groupRows) {
                $actualCounts[$eventId][$priceIndex] = \count($groupRows);
            }
        }

        if ($actualCounts !== $expected) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_AVAILABILITY'));
        }

        return array_values($rows);
    }

    /**
     * @param   array<string, mixed>  $reviewState
     *
     * @return array<int, array<int, int>>
     */
    private function expectedTicketGroups(array $reviewState): array
    {
        $expected = [];

        foreach ((array) ($reviewState['items'] ?? []) as $item) {
            $eventId = (int) ($item['eventId'] ?? 0);

            foreach ((array) ($item['prices'] ?? []) as $price) {
                $expected[$eventId][(int) ($price['index'] ?? 0)] = (int) ($price['quantity'] ?? 0);
            }
        }

        ksort($expected, SORT_NUMERIC);

        foreach ($expected as &$groups) {
            ksort($groups, SORT_NUMERIC);
        }
        unset($groups);

        return $expected;
    }

    /**
     * @param   array<string, mixed>  $bookingInput
     */
    private function createDPCalendarBooking(array $bookingInput): int
    {
        $model = $this->app->bootComponent('dpcalendar')
            ->getMVCFactory()
            ->createModel('Booking', 'Administrator', ['ignore_request' => true]);

        if (!$model->save($bookingInput, true)) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_SAVE'));
        }

        $bookingId = max(0, (int) $this->app->getInput()->getInt('b_id', 0));

        if ($bookingId < 1) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_SAVE'));
        }

        $table = $model->getTable('Booking');

        if (!$table->load($bookingId) || (int) $table->state !== 2) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_AVAILABILITY'));
        }

        return $bookingId;
    }

    /**
     * @param   array<string, mixed>  $reviewState
     * @param   list<object>          $heldSeats
     */
    private function linkSeatsToTickets(
        int $bookingId,
        int $cartId,
        array $reviewState,
        array $heldSeats
    ): void {
        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('event_id'),
                $this->db->quoteName('type'),
            ])
            ->from($this->db->quoteName('#__dpcalendar_tickets'))
            ->where($this->db->quoteName('booking_id') . ' = :bookingId')
            ->order($this->db->quoteName('event_id') . ' ASC')
            ->order($this->db->quoteName('type') . ' ASC')
            ->order($this->db->quoteName('id') . ' ASC')
            ->bind(':bookingId', $bookingId, ParameterType::INTEGER);
        $tickets = (array) $this->db->setQuery((string) $query . ' FOR UPDATE')->loadObjectList();
        $groups  = [];

        foreach ($tickets as $ticket) {
            $groups[(int) $ticket->event_id][(int) $ticket->type][] = (int) $ticket->id;
        }

        $seatGroups = [];

        foreach ($heldSeats as $seat) {
            $seatGroups[(int) $seat->event_id][(int) $seat->price_index][] = (int) $seat->id;
        }

        if ($this->groupCounts($groups) !== $this->expectedTicketGroups($reviewState)) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_AVAILABILITY'));
        }

        $modified = $this->cartContext->now();
        $userId   = max(0, (int) ($this->app->getIdentity()->id ?? 0));

        foreach ($groups as $eventId => $priceGroups) {
            foreach ($priceGroups as $priceIndex => $ticketIds) {
                $seatIds = $seatGroups[$eventId][$priceIndex] ?? [];

                if (\count($seatIds) !== \count($ticketIds)) {
                    throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_AVAILABILITY'));
                }

                foreach ($ticketIds as $index => $ticketId) {
                    $seatId = (int) $seatIds[$index];
                    $query  = $this->db->getQuery(true)
                        ->update($this->db->quoteName('#__copymypage_event_seats'))
                        ->set($this->db->quoteName('status') . ' = ' . EventSeatInventoryService::SEAT_STATUS_BOOKED)
                        ->set($this->db->quoteName('ticket_id') . ' = :ticketId')
                        ->set($this->db->quoteName('modified') . ' = :modified')
                        ->set($this->db->quoteName('modified_by') . ' = :userId')
                        ->where($this->db->quoteName('id') . ' = :seatId')
                        ->where($this->db->quoteName('cart_id') . ' = :cartId')
                        ->where($this->db->quoteName('status') . ' = ' . EventSeatInventoryService::SEAT_STATUS_HELD)
                        ->bind(':ticketId', $ticketId, ParameterType::INTEGER)
                        ->bind(':modified', $modified, ParameterType::STRING)
                        ->bind(':userId', $userId, ParameterType::INTEGER)
                        ->bind(':seatId', $seatId, ParameterType::INTEGER)
                        ->bind(':cartId', $cartId, ParameterType::INTEGER);
                    $this->db->setQuery($query)->execute();

                    if ($this->db->getAffectedRows() !== 1) {
                        throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_AVAILABILITY'));
                    }
                }
            }
        }
    }

    /**
     * @param   array<int, array<int, list<int>>>  $groups
     *
     * @return array<int, array<int, int>>
     */
    private function groupCounts(array $groups): array
    {
        $counts = [];

        foreach ($groups as $eventId => $priceGroups) {
            foreach ($priceGroups as $priceIndex => $items) {
                $counts[(int) $eventId][(int) $priceIndex] = \count($items);
            }
        }

        ksort($counts, SORT_NUMERIC);

        foreach ($counts as &$priceGroups) {
            ksort($priceGroups, SORT_NUMERIC);
        }
        unset($priceGroups);

        return $counts;
    }

    private function confirmDPCalendarBooking(int $bookingId, string $paymentProvider): \stdClass
    {
        $input = $this->app->getInput();
        $input->set('b_id', $bookingId);
        $input->set('payment_provider', $paymentProvider);
        $factory    = $this->app->bootComponent('dpcalendar')->getMVCFactory();
        $controller = $factory->createController('Booking', 'Site', [], $this->app, $input);

        if (!method_exists($controller, 'confirm') || !$controller->confirm()) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_SAVE'));
        }

        $table = $factory->createTable('Booking', 'Administrator');

        if (!$table->load($bookingId)) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_SAVE'));
        }

        return (object) $table->getData();
    }

    /**
     * @param   array<string, mixed>  $selectedProvider
     */
    private function assertConfirmedBooking(
        \stdClass $booking,
        array $selectedProvider,
        bool $paymentRequired
    ): void {
        $expectedState = $paymentRequired ? 3 : 1;
        $expectedPrice = round((float) ($selectedProvider['total'] ?? 0.0), 2);
        $actualPrice   = round((float) ($booking->price ?? -1), 2);

        if (
            (int) ($booking->state ?? 0) !== $expectedState
            || abs($actualPrice - $expectedPrice) > 0.01
            || ($paymentRequired
                && (string) ($booking->payment_provider ?? '') !== (string) ($selectedProvider['id'] ?? ''))
        ) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_PRICE_CHANGED'));
        }
    }

    /**
     * @param   list<array<string, mixed>>  $providers
     *
     * @return array<string, mixed>
     */
    private function findPublicProvider(array $providers, string $providerId): array
    {
        foreach ($providers as $provider) {
            if ((string) ($provider['id'] ?? '') === $providerId) {
                return $provider;
            }
        }

        throw new \DomainException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_PAYMENT_PROVIDER'));
    }

    /**
     * @param   list<array<string, mixed>>  $terms
     * @param   array<string, mixed>        $provider
     * @param   list<int>                   $eventIds
     */
    private function buildAcceptanceSnapshot(
        string $acceptedAt,
        int $cartRevision,
        array $terms,
        array $provider,
        array $eventIds,
        string $signature
    ): string {
        $snapshotTerms = [];

        foreach ($terms as $term) {
            $snapshotTerms[] = [
                'content'  => [
                    'fulltext'  => (string) ($term['content']['fulltext'] ?? ''),
                    'introtext' => (string) ($term['content']['introtext'] ?? ''),
                ],
                'eventIds' => array_values(array_map('intval', (array) ($term['eventIds'] ?? []))),
                'hash'     => (string) ($term['hash'] ?? ''),
                'id'       => (int) ($term['id'] ?? 0),
                'modified' => (string) ($term['modified'] ?? ''),
                'title'    => (string) ($term['title'] ?? ''),
                'url'      => (string) ($term['url'] ?? ''),
            ];
        }

        return json_encode(
            [
                'acceptedAt'  => $acceptedAt,
                'cartRevision' => $cartRevision,
                'eventIds'    => $eventIds,
                'provider'    => [
                    'currency' => (string) ($provider['currency'] ?? ''),
                    'fee'      => round((float) ($provider['fee'] ?? 0.0), 2),
                    'id'       => (string) ($provider['id'] ?? ''),
                    'label'    => (string) ($provider['label'] ?? ''),
                    'total'    => round((float) ($provider['total'] ?? 0.0), 2),
                ],
                'signature'    => $signature,
                'terms'        => $snapshotTerms,
                'version'      => 2,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * @param   list<array<string, mixed>>  $terms
     * @param   list<array<string, mixed>>  $providers
     */
    private function createCheckoutSignature(
        int $cartId,
        int $cartRevision,
        array $terms,
        array $providers,
        float $baseTotal,
        string $currency
    ): string {
        $canonicalTerms = [];

        foreach ($terms as $term) {
            $canonicalTerms[] = [
                'hash' => (string) ($term['hash'] ?? ''),
                'id'   => (int) ($term['id'] ?? 0),
            ];
        }

        $canonicalProviders = [];

        foreach ($providers as $provider) {
            $canonicalProviders[] = [
                'fee'   => round((float) ($provider['fee'] ?? 0.0), 2),
                'id'    => (string) ($provider['id'] ?? ''),
                'total' => round((float) ($provider['total'] ?? 0.0), 2),
            ];
        }

        $payload = json_encode(
            [
                'baseTotal' => round($baseTotal, 2),
                'cartId'    => $cartId,
                'currency'  => $currency,
                'providers' => $canonicalProviders,
                'revision'  => $cartRevision,
                'terms'     => $canonicalTerms,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $secret = (string) $this->app->get('secret', '');

        if ($secret === '') {
            throw new \RuntimeException('The Joomla secret is unavailable.');
        }

        return hash_hmac('sha256', $payload, $secret);
    }
}
