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

use DigitalPeak\Component\DPCalendar\Site\Helper\RouteHelper as DPCalendarRouteHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Builds the status-guarded Step-5 projection from an authorised DPCalendar booking.
 *
 * @since  0.0.19
 */
final class BookingCompletionService
{
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly TicketSeatProjectionService $ticketSeats
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(\stdClass $booking): array
    {
        $state     = $this->createBaseState($booking);
        $bookingId = (int) $state['bookingId'];

        if ($bookingId < 1) {
            return $this->getFailureState($booking);
        }

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('status'),
            ])
            ->from($this->db->quoteName('#__copymypage_ticket_carts'))
            ->where($this->db->quoteName('booking_id') . ' = :bookingId')
            ->order($this->db->quoteName('id') . ' ASC')
            ->bind(':bookingId', $bookingId, ParameterType::INTEGER);
        $carts = (array) $this->db->setQuery($query)->loadObjectList();

        if ($carts === []) {
            return $state;
        }

        $state['managed'] = true;
        $state['scope']   = 'managed';
        $state['integrityOk'] = \count($carts) === 1;
        $cartStatus = \count($carts) === 1 ? (int) ($carts[0]->status ?? -1) : -1;
        $bookingStatus = (int) $state['state'];

        if (\in_array($bookingStatus, [1, 3, 4, 10], true)) {
            $state['integrityOk'] = $state['integrityOk']
                && $cartStatus === TicketCartContextService::STATUS_CONVERTED;
        } elseif (\in_array($bookingStatus, [6, 7, -2], true)) {
            $state['integrityOk'] = $state['integrityOk']
                && $cartStatus === TicketCartContextService::STATUS_RELEASED;
        }

        $tickets     = [];
        $eventGroups = [];

        foreach ((array) ($booking->tickets ?? []) as $ticket) {
            if (
                !$ticket instanceof \stdClass
                || (int) ($ticket->booking_id ?? 0) !== $bookingId
                || (int) ($ticket->id ?? 0) < 1
            ) {
                continue;
            }

            $tickets[] = $ticket;
        }

        $seatAssignments = $this->ticketSeats->getForBooking($bookingId);
        $missingSeats     = 0;

        foreach ($tickets as $ticket) {
            $ticketId = (int) $ticket->id;
            $eventId  = max(0, (int) ($ticket->event_id ?? 0));
            $eventKey = $eventId > 0 ? $eventId : -$ticketId;
            $seat     = $seatAssignments[$ticketId] ?? null;

            if (!\is_array($seat) || trim((string) ($seat['label'] ?? '')) === '') {
                $missingSeats++;
            }

            if (!isset($eventGroups[$eventKey])) {
                $eventTitle = trim((string) ($ticket->event_title ?? ''));

                $eventGroups[$eventKey] = [
                    'eventId' => $eventId,
                    'tickets' => [],
                    'title'   => $eventTitle !== ''
                        ? $eventTitle
                        : Text::sprintf('COM_COPYMYPAGE_BOOKING_COMPLETION_EVENT_FALLBACK', $eventId),
                ];
            }

            $ticketUid = trim((string) ($ticket->uid ?? ''));
            $typeLabel = trim((string) ($ticket->price_label ?? ''));
            $eventGroups[$eventKey]['tickets'][] = [
                'id'        => $ticketId,
                'seatLabel' => \is_array($seat) ? trim((string) ($seat['label'] ?? '')) : '',
                'typeLabel' => $typeLabel !== ''
                    ? $typeLabel
                    : Text::_('COM_COPYMYPAGE_TICKET_SELECTION_TICKET_TYPE_DEFAULT'),
                'uid'       => $ticketUid,
                'url'       => $ticketUid !== ''
                    ? DPCalendarRouteHelper::getTicketRoute($ticket)
                    : '',
            ];
        }

        $state['events']      = array_values($eventGroups);
        $state['ticketCount'] = \count($tickets);

        if (\in_array($bookingStatus, [1, 3, 4, 10], true)) {
            $state['integrityOk'] = $state['integrityOk']
                && $tickets !== []
                && $missingSeats === 0;
        }

        return $state;
    }

    /**
     * A service failure must never inherit a successful presentation from the layout.
     *
     * @return array<string, mixed>
     */
    public function getFailureState(\stdClass $booking): array
    {
        $state = $this->createBaseState($booking);
        $state['completed']   = false;
        $state['integrityOk'] = false;
        $state['managed']     = false;
        $state['scope']       = 'unknown';

        return array_replace($state, $this->getPresentation(-1, false, false));
    }

    /**
     * @return array<string, mixed>
     */
    private function createBaseState(\stdClass $booking): array
    {
        $bookingStatus     = (int) ($booking->state ?? -1);
        $paymentRequired   = (float) ($booking->price ?? 0.0) > 0;
        $transactionStarted = trim((string) ($booking->transaction_id ?? '')) !== '';

        return array_replace(
            [
                'bookingId'         => max(0, (int) ($booking->id ?? 0)),
                'bookingUid'        => trim((string) ($booking->uid ?? '')),
                'completed'         => $bookingStatus === 1,
                'events'            => [],
                'integrityOk'       => true,
                'managed'           => false,
                'paymentRequired'   => $paymentRequired,
                'scope'             => 'unmanaged',
                'state'             => $bookingStatus,
                'ticketCount'       => 0,
                'transactionStarted' => $transactionStarted,
            ],
            $this->getPresentation($bookingStatus, $paymentRequired, $transactionStarted)
        );
    }

    /**
     * @return array{icon: string, introKey: string, titleKey: string, tone: string}
     */
    private function getPresentation(
        int $bookingStatus,
        bool $paymentRequired,
        bool $transactionStarted
    ): array {
        return match ($bookingStatus) {
            1 => [
                'icon'     => 'check',
                'introKey' => $paymentRequired
                    ? 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_COMPLETE_PAID_INTRO'
                    : 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_COMPLETE_FREE_INTRO',
                'titleKey' => 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_COMPLETE_TITLE',
                'tone'     => 'success',
            ],
            3 => [
                'icon'     => 'clock',
                'introKey' => $transactionStarted
                    ? 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_PENDING_STARTED_INTRO'
                    : 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_PENDING_READY_INTRO',
                'titleKey' => 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_PENDING_TITLE',
                'tone'     => 'warning',
            ],
            4 => [
                'icon'     => 'refresh',
                'introKey' => 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_PROCESSING_INTRO',
                'titleKey' => 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_PROCESSING_TITLE',
                'tone'     => 'info',
            ],
            6 => [
                'icon'     => 'close',
                'introKey' => 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_CANCELLED_INTRO',
                'titleKey' => 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_CANCELLED_TITLE',
                'tone'     => 'danger',
            ],
            7 => [
                'icon'     => 'reply',
                'introKey' => 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_REFUNDED_INTRO',
                'titleKey' => 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_REFUNDED_TITLE',
                'tone'     => 'info',
            ],
            10 => [
                'icon'     => 'warning',
                'introKey' => 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_PARTIAL_INTRO',
                'titleKey' => 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_PARTIAL_TITLE',
                'tone'     => 'warning',
            ],
            default => [
                'icon'     => 'warning',
                'introKey' => 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_UNKNOWN_INTRO',
                'titleKey' => 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_UNKNOWN_TITLE',
                'tone'     => 'danger',
            ],
        };
    }
}
