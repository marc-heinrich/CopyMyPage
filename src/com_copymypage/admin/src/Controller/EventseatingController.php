<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Component\CopyMyPage\Administrator\Model\EventseatingModel;

/**
 * Handles the two deliberately separate seating setup mutations.
 */
final class EventseatingController extends BaseController
{
    public function importDefinition(): void
    {
        $this->assertMutationRequest();
        $eventId = $this->input->post->getInt('event_id');
        $data    = $this->input->post->get('jform', [], 'array');
        $file    = trim((string) ($data['definition'] ?? ''));

        try {
            $result = $this->getEventseatingModel()->importDefinition(
                $file,
                (int) $this->app->getIdentity()->id
            );
            $key = (bool) $result['imported']
                ? 'COM_COPYMYPAGE_EVENT_SEATING_IMPORT_SUCCESS'
                : 'COM_COPYMYPAGE_EVENT_SEATING_IMPORT_EXISTS';
            $this->app->enqueueMessage(
                Text::sprintf(
                    $key,
                    (string) $result['title'],
                    (int) $result['version'],
                    (int) $result['seatCount']
                ),
                'success'
            );
        } catch (\DomainException $exception) {
            $this->app->enqueueMessage($exception->getMessage(), 'warning');
        } catch (\Throwable $exception) {
            Log::add(
                'CopyMyPage seating definition import failed: ' . $exception->getMessage(),
                Log::ERROR,
                'com_copymypage'
            );
            $this->app->enqueueMessage(
                Text::_('COM_COPYMYPAGE_SEAT_LAYOUT_ERROR_SAVE'),
                'error'
            );
        }

        $this->setRedirect($this->getReturnUrl($eventId));
    }

    public function assignLayout(): void
    {
        $this->assertMutationRequest();
        $eventId = $this->input->post->getInt('event_id');
        $data    = $this->input->post->get('jform', [], 'array');
        $layoutId = max(0, (int) ($data['layout_id'] ?? 0));

        try {
            $result = $this->getEventseatingModel()->assignLayout(
                $eventId,
                $layoutId,
                (int) $this->app->getIdentity()->id
            );
            $this->app->enqueueMessage(
                Text::sprintf(
                    'COM_COPYMYPAGE_EVENT_SEATING_ASSIGN_SUCCESS',
                    (int) $result['seatCount'],
                    (int) $result['inventoryVersion']
                ),
                'success'
            );
        } catch (\DomainException $exception) {
            $this->app->enqueueMessage($exception->getMessage(), 'warning');
        } catch (\Throwable $exception) {
            Log::add(
                'CopyMyPage event seat inventory assignment failed: ' . $exception->getMessage(),
                Log::ERROR,
                'com_copymypage'
            );
            $this->app->enqueueMessage(
                Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_SAVE'),
                'error'
            );
        }

        $this->setRedirect($this->getReturnUrl($eventId));
    }

    public function markReady(): void
    {
        $this->assertMutationRequest();
        $eventId = $this->input->post->getInt('event_id');

        try {
            $result = $this->getEventseatingModel()->markReady(
                $eventId,
                (int) $this->app->getIdentity()->id
            );
            $this->app->enqueueMessage(
                Text::sprintf(
                    'COM_COPYMYPAGE_EVENT_SEATING_READY_SUCCESS',
                    (int) $result['seatCount'],
                    (int) $result['inventoryVersion']
                ),
                'success'
            );
        } catch (\DomainException $exception) {
            $this->app->enqueueMessage($exception->getMessage(), 'warning');
        } catch (\Throwable $exception) {
            Log::add(
                'CopyMyPage event seat inventory activation failed: ' . $exception->getMessage(),
                Log::ERROR,
                'com_copymypage'
            );
            $this->app->enqueueMessage(
                Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_SAVE'),
                'error'
            );
        }

        $this->setRedirect($this->getReturnUrl($eventId));
    }

    private function assertMutationRequest(): void
    {
        if (!$this->app->getIdentity()->authorise('copymypage.seating.configure', 'com_copymypage')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        if (strtoupper($this->input->getMethod()) !== 'POST') {
            throw new NotAllowed(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 405);
        }

        $this->checkToken('post');
    }

    private function getEventseatingModel(): EventseatingModel
    {
        /** @var EventseatingModel $model */
        $model = $this->getModel('Eventseating');

        return $model;
    }

    private function getReturnUrl(int $eventId): string
    {
        return Route::_(
            'index.php?option=com_copymypage&view=eventseating&event_id=' . max(0, $eventId),
            false
        );
    }
}
