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
use Joomla\Component\CopyMyPage\Site\Exception\SeatSelectionConflictException;
use Joomla\Component\CopyMyPage\Site\Exception\TicketCartRevisionConflictException;
use Joomla\Component\CopyMyPage\Site\Service\SeatSelectionService;

/**
 * Private cart-state and mutation endpoints for concrete seat holds.
 */
final class TicketseatsController extends BaseController
{
    public function state(): void
    {
        $this->prepareNoStoreResponse();

        if (strtoupper($this->input->getMethod()) !== 'GET') {
            $this->respondJson(
                null,
                Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'),
                true,
                405
            );

            return;
        }

        try {
            $data = $this->getSeatService()->getEventState(
                $this->input->getInt('event_id', 0)
            );
            $data['message'] = '';
            $this->respondJson($data, '', false);
        } catch (\Throwable $exception) {
            $this->respondFailure($exception, $this->input->getInt('event_id', 0));
        }
    }

    public function assign(): void
    {
        $eventId = $this->input->post->getInt('event_id', 0);

        if (!$this->allowsMutation($eventId)) {
            return;
        }

        try {
            $service = $this->getSeatService();
            $fields  = $service->getFormFieldNames();
            $seatField = $this->validFieldName($fields['seatIds'] ?? '');

            if ($seatField === '') {
                throw new \RuntimeException(Text::_('COM_COPYMYPAGE_SEAT_SELECTION_ERROR_SAVE'));
            }

            $data = $service->assignSeats(
                $eventId,
                $this->input->post->get($seatField, [], 'array'),
                $this->getExpectedRevision($service)
            );
            $this->respondMutation(
                $data,
                Text::_('COM_COPYMYPAGE_SEAT_SELECTION_SAVE_SUCCESS'),
                false,
                $eventId
            );
        } catch (\Throwable $exception) {
            $this->respondFailure($exception, $eventId);
        }
    }

    public function suggest(): void
    {
        $eventId = $this->input->post->getInt('event_id', 0);

        if (!$this->allowsMutation($eventId)) {
            return;
        }

        try {
            $service = $this->getSeatService();
            $data    = $service->suggestSeats(
                $eventId,
                $this->getExpectedRevision($service)
            );
            $this->respondMutation(
                $data,
                Text::_('COM_COPYMYPAGE_SEAT_SELECTION_SUGGEST_SUCCESS'),
                false,
                $eventId
            );
        } catch (\Throwable $exception) {
            $this->respondFailure($exception, $eventId);
        }
    }

    private function allowsMutation(int $eventId): bool
    {
        $this->prepareNoStoreResponse();

        if (strtoupper($this->input->getMethod()) !== 'POST') {
            $this->respondMutation(
                null,
                Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'),
                true,
                $eventId,
                405
            );

            return false;
        }

        if (!Session::checkToken('post')) {
            $this->respondMutation(null, Text::_('JINVALID_TOKEN'), true, $eventId, 403);

            return false;
        }

        return true;
    }

    /**
     * @param   array<string, mixed>|null  $data
     */
    private function respondMutation(
        ?array $data,
        string $message,
        bool $error,
        int $eventId,
        int $status = 200
    ): void {
        if ($this->isJsonRequest()) {
            if ($data !== null) {
                $data['message'] = $message;
            }

            $this->respondJson($data, $message, $error, $status);

            return;
        }

        $url = 'index.php?option=com_copymypage&view=seatselection';

        if ($eventId > 0) {
            $url .= '&event_id=' . $eventId;
        }

        $this->setRedirect(
            Route::_($url, false),
            $message,
            $error ? 'warning' : 'message'
        );
        $this->redirect();
    }

    private function respondFailure(\Throwable $exception, int $eventId): void
    {
        if (!$exception instanceof \DomainException) {
            $this->logException($exception);
        }

        $message = $exception instanceof \DomainException && trim($exception->getMessage()) !== ''
            ? $exception->getMessage()
            : Text::_('COM_COPYMYPAGE_SEAT_SELECTION_ERROR_SAVE');
        $data = null;

        try {
            $data = $this->getSeatService()->getEventState($eventId);
        } catch (\Throwable $stateException) {
            if (!$stateException instanceof \DomainException) {
                $this->logException($stateException);
            }
        }

        $status = match (true) {
            $exception instanceof TicketCartRevisionConflictException,
            $exception instanceof SeatSelectionConflictException => 409,
            $exception instanceof \DomainException => 422,
            default => 500,
        };

        $this->respondMutation($data, $message, true, $eventId, $status);
    }

    /**
     * @param   array<string, mixed>|null  $data
     */
    private function respondJson(
        ?array $data,
        string $message,
        bool $error,
        int $status = 200
    ): void {
        $this->sendJsonHeaders($status);
        echo new JsonResponse($data, $message, $error);
        $this->app->close();
    }

    private function getExpectedRevision(SeatSelectionService $service): int
    {
        $fields = $service->getFormFieldNames();
        $field  = $this->validFieldName($fields['expectedCartRevision'] ?? '');

        return $field === '' ? -1 : $this->input->post->getInt($field, -1);
    }

    private function validFieldName(mixed $field): string
    {
        $field = trim((string) $field);

        return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $field) === 1 ? $field : '';
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

    private function sendJsonHeaders(int $status): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);

        if ($status !== 200) {
            $this->app->setHeader('Status', (string) $status, true);
        }

        $this->app->sendHeaders();
    }

    private function getSeatService(): SeatSelectionService
    {
        return Factory::getContainer()->get(SeatSelectionService::class);
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
            'CopyMyPage seat selection: ' . implode(' <- ', $details),
            Log::ERROR,
            'com_copymypage'
        );
    }
}
