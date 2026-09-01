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

/**
 * Builds the guarded authoritative order summary for checkout Step 4.
 */
final class OrderReviewService
{
    public function __construct(
        private readonly CustomerDataService $customerData,
        private readonly SeatSelectionService $seatSelection,
        private readonly TicketReservationService $ticketReservations
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewState(): array
    {
        $state    = $this->emptyState();
        $customer = $this->customerData->getReviewCustomerData();

        if ($customer === []) {
            return $state;
        }

        $cart          = $this->ticketReservations->getCartState();
        $selection     = $this->seatSelection->getSelectionState();
        $cartItems     = (array) ($cart['items'] ?? []);
        $selectedItems = (array) ($selection['events'] ?? []);

        if (
            empty($cart['active'])
            || empty($cart['continuable'])
            || (int) ($cart['secondsLeft'] ?? 0) < 1
            || $cartItems === []
            || empty($selection['allComplete'])
            || $selectedItems === []
            || (int) ($cart['cartRevision'] ?? 0)
                !== (int) ($selection['cart']['cartRevision'] ?? -1)
            || (int) ($cart['totalTickets'] ?? 0)
                !== (int) ($selection['cart']['totalTickets'] ?? -1)
        ) {
            return $state;
        }

        $selectionByEvent = [];

        foreach ($selectedItems as $event) {
            if (!\is_array($event)) {
                return $state;
            }

            $eventId       = (int) ($event['id'] ?? 0);
            $requiredCount = (int) ($event['requiredCount'] ?? 0);
            $selectedSeats = [];

            if ($eventId < 1 || $requiredCount < 1 || empty($event['complete'])) {
                return $state;
            }

            foreach ((array) ($event['selectedSeats'] ?? []) as $seat) {
                if (!\is_array($seat)) {
                    return $state;
                }

                $label = trim((string) ($seat['label'] ?? ''));

                if ((int) ($seat['id'] ?? 0) < 1 || $label === '') {
                    return $state;
                }

                $selectedSeats[] = [
                    'label'       => $label,
                    'seatNumber'  => (string) ($seat['seatNumber'] ?? ''),
                    'tableNumber' => (string) ($seat['tableNumber'] ?? ''),
                ];
            }

            if (\count($selectedSeats) !== $requiredCount) {
                return $state;
            }

            $selectionByEvent[$eventId] = [
                'dateTime' => (string) ($event['dateTime'] ?? ''),
                'seats'    => $selectedSeats,
            ];
        }

        $items       = [];
        $ticketCount = 0;

        foreach ($cartItems as $item) {
            if (!\is_array($item)) {
                return $state;
            }

            $eventId  = (int) ($item['eventId'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            $prices   = (array) ($item['prices'] ?? []);

            if (
                $eventId < 1
                || $quantity < 1
                || empty($item['continuable'])
                || $prices === []
                || !isset($selectionByEvent[$eventId])
                || \count($selectionByEvent[$eventId]['seats']) !== $quantity
            ) {
                return $state;
            }

            $priceQuantity = 0;

            foreach ($prices as $price) {
                if (!\is_array($price) || (int) ($price['quantity'] ?? 0) < 1) {
                    return $state;
                }

                $priceQuantity += (int) $price['quantity'];
            }

            if ($priceQuantity !== $quantity) {
                return $state;
            }

            $item['dateTime'] = $selectionByEvent[$eventId]['dateTime'];
            $item['seats']    = $selectionByEvent[$eventId]['seats'];
            $items[]          = $item;
            $ticketCount     += $quantity;
        }

        if (
            $ticketCount !== (int) ($cart['totalTickets'] ?? 0)
            || \count($items) !== \count($selectionByEvent)
        ) {
            return $state;
        }

        $cart['items'] = $items;

        return [
            'blocked'          => false,
            'cart'             => $cart,
            'customer'         => $customer,
            'customerDataUrl'  => $state['customerDataUrl'],
            'items'            => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyState(): array
    {
        return [
            'blocked'          => true,
            'cart'             => [],
            'customer'         => [],
            'customerDataUrl'  => $this->customerData->getCustomerDataUrl(),
            'items'            => [],
        ];
    }
}
