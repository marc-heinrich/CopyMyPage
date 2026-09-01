<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Component\CopyMyPage\Site\Exception\TicketCartRevisionConflictException;
use Joomla\Component\CopyMyPage\Site\Service\TicketReservationService;

/**
 * Public AJAX and POST-fallback controller for temporary ticket reservations.
 */
final class TicketcartController extends BaseController
{
    public function availability(): void
    {
        $this->prepareNoStoreResponse();
        $eventIds = array_filter(array_map(
            'trim',
            explode(',', $this->input->getString('event_ids', ''))
        ));

        try {
            $service = $this->getTicketService();
            $data    = [
                'availability' => $service->getAvailabilitySnapshot($eventIds),
                'cart'         => $service->getBasketIndicatorState(),
            ];
            $this->sendJsonHeaders();
            echo new JsonResponse($data);
        } catch (\Throwable $exception) {
            $this->logException($exception);
            $this->sendJsonHeaders();
            echo new JsonResponse(
                null,
                Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_REQUEST'),
                true
            );
        }

        $this->app->close();
    }

    public function reserve(): void
    {
        $eventId = $this->input->post->getInt('event_id', 0);

        if (!$this->allowsMutation()) {
            return;
        }

        try {
            $service = $this->getTicketService();
            $state   = $service->reserveEvent(
                $eventId,
                $this->input->post->get('quantities', [], 'array'),
                $this->getExpectedCartRevision($service)
            );
            $this->respondToMutation(
                $state,
                Text::sprintf(
                    'COM_COPYMYPAGE_TICKET_SELECTION_RESERVE_SUCCESS',
                    $service->getReservationMinutes()
                ),
                false,
                $eventId
            );
        } catch (\Throwable $exception) {
            $this->respondToMutationFailure($exception, $eventId);
        }
    }

    public function remove(): void
    {
        $eventId = $this->input->post->getInt('event_id', 0);

        if (!$this->allowsMutation()) {
            return;
        }

        try {
            $service = $this->getTicketService();
            $state   = $service->removeEvent(
                $eventId,
                $this->getExpectedCartRevision($service)
            );
            $this->respondToMutation(
                $state,
                Text::_('COM_COPYMYPAGE_TICKET_SELECTION_REMOVE_SUCCESS'),
                false,
                $eventId
            );
        } catch (\Throwable $exception) {
            $this->respondToMutationFailure($exception, $eventId);
        }
    }

    public function clear(): void
    {
        if (!$this->allowsMutation()) {
            return;
        }

        try {
            $service = $this->getTicketService();
            $state   = $service->clearCart($this->getExpectedCartRevision($service));
            $this->respondToMutation(
                $state,
                Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CLEAR_SUCCESS'),
                false,
                0
            );
        } catch (\Throwable $exception) {
            $this->respondToMutationFailure($exception, 0);
        }
    }

    private function allowsMutation(): bool
    {
        $this->prepareNoStoreResponse();

        if (strtoupper($this->input->getMethod()) !== 'POST') {
            $this->respondToMutation(
                null,
                Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'),
                true,
                0
            );

            return false;
        }

        if (!Session::checkToken('post')) {
            $this->respondToMutation(null, Text::_('JINVALID_TOKEN'), true, 0);

            return false;
        }

        return true;
    }

    /**
     * @param   array<string, mixed>|null  $state  Current server-side state.
     */
    private function respondToMutation(
        ?array $state,
        string $message,
        bool $error,
        int $eventId,
        int $httpStatus = 200
    ): void {
        if ($this->isJsonRequest()) {
            $this->sendJsonHeaders($httpStatus);
            echo new JsonResponse($state, $message, $error);
            $this->app->close();

            return;
        }

        $returnView = $this->input->post->getCmd('return_view', 'ticketselection');
        $returnView = $returnView === 'basket' ? 'basket' : 'ticketselection';
        $url        = 'index.php?option=com_copymypage&view=' . $returnView;

        if ($returnView === 'ticketselection' && $eventId > 0) {
            $url .= '&event_id=' . $eventId;
        }

        $this->setRedirect(Route::_($url, false), $message, $error ? 'warning' : 'message');
        $this->redirect();
    }

    private function respondToMutationFailure(\Throwable $exception, int $eventId): void
    {
        if (!$exception instanceof \DomainException) {
            $this->logException($exception);
        }

        $message = trim($exception->getMessage()) !== ''
            ? $exception->getMessage()
            : Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_SAVE');
        $state   = null;

        try {
            $state = $this->getTicketService()->getSelectionState($eventId);
        } catch (\Throwable $stateException) {
            $this->logException($stateException);
        }

        $httpStatus = $exception instanceof TicketCartRevisionConflictException ? 409 : 200;

        $this->respondToMutation($state, $message, true, $eventId, $httpStatus);
    }

    private function getExpectedCartRevision(TicketReservationService $service): int
    {
        $fields = $service->getFormFieldNames();
        $field  = trim((string) ($fields['expectedCartRevision'] ?? ''));

        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $field)) {
            return -1;
        }

        return $this->input->post->getInt($field, -1);
    }

    private function isJsonRequest(): bool
    {
        return strtolower($this->input->getCmd('format', 'html')) === 'json';
    }

    private function prepareNoStoreResponse(): void
    {
        $this->app->allowCache(false);
        $this->app->setHeader(
            'Cache-Control',
            'no-store, no-cache, must-revalidate, private, max-age=0',
            true
        );
        $this->app->setHeader('Pragma', 'no-cache', true);
    }

    private function sendJsonHeaders(int $httpStatus = 200): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);

        if ($httpStatus !== 200) {
            $this->app->setHeader('Status', (string) $httpStatus, true);
        }

        $this->app->sendHeaders();
    }

    private function getTicketService(): TicketReservationService
    {
        return Factory::getContainer()->get(TicketReservationService::class);
    }

    private function logException(\Throwable $exception): void
    {
        $details = [];
        $current = $exception;

        for ($depth = 0; $depth < 4 && $current instanceof \Throwable; $depth++) {
            $details[] = $current::class . ': ' . $current->getMessage();
            $current   = $current->getPrevious();
        }

        Log::add(
            'CopyMyPage ticket reservation: ' . implode(' <- ', $details),
            Log::ERROR,
            'com_copymypage'
        );
    }
}
