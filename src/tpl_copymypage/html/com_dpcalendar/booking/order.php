<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

\defined('_JEXEC') or die;

use DigitalPeak\Component\DPCalendar\Site\Helper\RouteHelper as DPCalendarRouteHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;
use Joomla\Component\CopyMyPage\Site\Service\BookingCompletionService;

/** @var \DigitalPeak\Component\DPCalendar\Site\View\Booking\HtmlView $this */

$app = Factory::getApplication();
$app->getLanguage()->load(
    'com_copymypage',
    JPATH_SITE . '/components/com_copymypage',
    null,
    true
);
$completionService = null;

try {
    $completionService = Factory::getContainer()->get(BookingCompletionService::class);
    $state             = $completionService->getState($this->booking);
} catch (\Throwable $exception) {
    Log::add(
        'CopyMyPage booking completion could not be projected for booking ID '
            . (int) ($this->booking->id ?? 0) . ' (' . $exception::class . ').',
        Log::ERROR,
        'com_copymypage'
    );
    $state = $completionService instanceof BookingCompletionService
        ? $completionService->getFailureState($this->booking)
        : [
            'bookingUid'  => trim((string) ($this->booking->uid ?? '')),
            'completed'   => false,
            'events'      => [],
            'icon'        => 'warning',
            'integrityOk' => false,
            'introKey'    => 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_UNKNOWN_INTRO',
            'managed'     => false,
            'scope'       => 'unknown',
            'state'       => (int) ($this->booking->state ?? -1),
            'titleKey'    => 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_UNKNOWN_TITLE',
            'tone'        => 'danger',
        ];
}

// Preserve DPCalendar's complete native success view for unrelated bookings only.
if (($state['scope'] ?? '') === 'unmanaged' && (int) ($state['state'] ?? -1) === 1) {
    require JPATH_SITE . '/components/com_dpcalendar/tmpl/booking/order.php';

    return;
}

$app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
$app->setHeader('Pragma', 'no-cache', true);
$app->getDocument()->setMetaData('robots', 'noindex, nofollow');

$bookingRoute = DPCalendarRouteHelper::getBookingRoute($this->booking);
$taskSuffix   = (string) ($this->tmpl ?? '');
$displayData  = [
    'invoiceUrl'    => '',
    'paymentAction' => $bookingRoute . '&layout=pay',
    'receiptUrl'    => '',
    'refreshUrl'    => $bookingRoute . '&layout=order',
    'selectionUrl'  => Route::_('index.php?option=com_copymypage&view=ticketselection', false),
    'showBack'      => !\in_array((int) ($state['state'] ?? -1), [1, 3, 4, 10], true),
    'showRefresh'   => !\in_array((int) ($state['state'] ?? -1), [1, 6, 7], true),
    'showResume'    => false,
    'showSteps'     => !empty($state['managed']),
    'state'         => $state,
];

if (!empty($state['completed'])) {
    $displayData['receiptUrl'] = $this->router->route(
        'index.php?option=com_dpcalendar&task=booking.receipt&b_id='
            . (int) $this->booking->id . $taskSuffix
    );

    if ((int) ($this->booking->invoice ?? 0) === 1 && (float) ($this->booking->price ?? 0.0) > 0) {
        $displayData['invoiceUrl'] = $this->router->route(
            'index.php?option=com_dpcalendar&task=booking.invoice&b_id='
                . (int) $this->booking->id . $taskSuffix
        );
    }
}

if (
    !empty($state['managed'])
    && !empty($state['integrityOk'])
    && (int) ($state['state'] ?? -1) === 3
    && empty($state['transactionStarted'])
) {
    try {
        $displayData['paymentHandoff'] = Factory::getContainer()
            ->get(\Joomla\Component\CopyMyPage\Site\Service\PaymentHandoffService::class)
            ->issue((int) $this->booking->id);
        $displayData['showResume'] = true;
    } catch (\Throwable $exception) {
        Log::add(
            'CopyMyPage payment resume hand-off could not be issued for booking ID '
                . (int) $this->booking->id . ' (' . $exception::class . ').',
            Log::WARNING,
            'com_copymypage'
        );
    }
}

echo LayoutHelper::render('copymypage.tickets.completion', $displayData);
