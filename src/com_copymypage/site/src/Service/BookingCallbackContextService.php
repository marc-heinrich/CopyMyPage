<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @license     GNU General Public License version 3 or later
 */

namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Language\Text;

/**
 * Supplies the native callback with the token of one already authorised booking.
 * Provider processing, status updates and redirects remain native DPCalendar.
 */
final class BookingCallbackContextService
{
    public function __construct(
        private readonly CMSWebApplicationInterface $app,
        private readonly PaymentReconciliationService $payments,
        private readonly BookingIntegrityService $integrity,
        private readonly ?PayPalPaymentService $ownedPayments = null
    ) {
    }

    public function execute(int $bookingId, string $task): void
    {
        if (!\in_array($task, ['booking.pay', 'booking.paycancel'], true)) {
            throw new \InvalidArgumentException('Invalid native payment callback task.');
        }

        $input = $this->app->getInput();
        $identity = $this->app->getIdentity();
        $requestToken = trim($input->getString('dptoken', $input->getString('token', '')));
        $decision = $this->payments->getCallbackDecision(
            $bookingId,
            $requestToken,
            (int) $this->app->getSession()->get('com_dpcalendar.booking_id', 0),
            max(0, (int) ($identity->id ?? 0)),
            $identity->authorise('dpcalendar.admin.book', 'com_dpcalendar')
        );

        if (
            empty($decision['managed']) || empty($decision['authorized'])
            || !empty($decision['block']) || $decision['bookingToken'] === ''
        ) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $integrity = $this->integrity->inspect($bookingId);

        if (!$integrity['valid']) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_DATA_ERROR'), 409);
        }

        $originalInput = [];

        foreach (['b_id', 'token', 'dptoken'] as $key) {
            $originalInput[$key] = $input->get($key, null, 'raw');
        }

        try {
            $input->set('b_id', $bookingId);
            $input->set('token', $decision['bookingToken']);
            $input->set('dptoken', $decision['bookingToken']);
            $factory = $this->app->bootComponent('dpcalendar')->getMVCFactory();
            $model = $factory->createModel('Booking', 'Administrator', ['ignore_request' => true]);
            $booking = $model->getItem(['token' => $decision['bookingToken']]);
            $nativeIds = array_map(static fn(object $ticket): int => (int) $ticket->id, (array) ($booking->tickets ?? []));
            $expectedIds = array_map(static fn(object $ticket): int => (int) $ticket->id, $integrity['tickets']);
            sort($nativeIds, SORT_NUMERIC);
            sort($expectedIds, SORT_NUMERIC);

            if (!$booking || (int) $booking->id !== $bookingId || $nativeIds !== $expectedIds) {
                throw new \RuntimeException(Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_DATA_ERROR'), 409);
            }

            if (PaymentProviderConfig::isOwned((string) $integrity['booking']->payment_provider)) {
                if ($this->ownedPayments === null) {
                    throw new \RuntimeException('Managed payment adapter unavailable.');
                }
                $providerOrder = $input->get->getString('token', '');
                $this->ownedPayments->reconcile($bookingId, $providerOrder !== '' ? $providerOrder : null, $task === 'booking.paycancel');
                $this->app->redirect(\DigitalPeak\Component\DPCalendar\Site\Helper\RouteHelper::getBookingRoute($booking) . '&layout=order');
                $this->app->close();
                return;
            }

            $controller = $factory->createController('Booking', 'Site', [], $this->app, $input);

            if ($task === 'booking.pay') {
                $controller->pay();
            } else {
                $controller->paycancel();
            }

            // pay redirects directly; paycancel sets a controller redirect.
            $controller->redirect();
            $this->app->close();
        } finally {
            foreach ($originalInput as $key => $value) {
                $input->set($key, $value);
            }
        }
    }
}
