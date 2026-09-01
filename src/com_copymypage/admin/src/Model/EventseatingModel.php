<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Component\CopyMyPage\Site\Service\EventSeatInventoryService;
use Joomla\Component\CopyMyPage\Site\Service\SeatLayoutService;

/**
 * Model for server-side seating setup.
 */
final class EventseatingModel extends FormModel
{
    /** @var array<string, mixed>|null */
    private ?array $summary = null;

    protected function populateState(): void
    {
        $this->setState(
            'eventseating.event_id',
            Factory::getApplication()->getInput()->getInt('event_id')
        );
    }

    public function getForm($data = [], $loadData = true): Form
    {
        return $this->loadForm(
            'com_copymypage.eventseating',
            'eventseating',
            ['control' => 'jform', 'load_data' => $loadData]
        );
    }

    protected function loadFormData(): array
    {
        $assignment = $this->getSummary()['assignment'] ?? null;

        return [
            'definition' => '',
            'layout_id'  => \is_array($assignment) ? (int) ($assignment['layoutId'] ?? 0) : 0,
        ];
    }

    public function getEventId(): int
    {
        return max(0, (int) $this->getState('eventseating.event_id'));
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        return $this->summary ??= $this->getInventoryService()->getEventSummary(
            $this->getEventId()
        );
    }

    /**
     * @return list<array<string, int|string>>
     */
    public function getBundledDefinitions(): array
    {
        return $this->getLayoutService()->getBundledDefinitions();
    }

    /**
     * @return list<array<string, int|string>>
     */
    public function getPublishedLayouts(): array
    {
        return $this->getLayoutService()->getPublishedLayouts();
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function importDefinition(string $fileName, int $userId): array
    {
        return $this->getLayoutService()->importBundledDefinition($fileName, $userId);
    }

    /**
     * @return array<string, int|string>
     */
    public function assignLayout(int $eventId, int $layoutId, int $userId): array
    {
        return $this->getInventoryService()->assignDraft($eventId, $layoutId, $userId);
    }

    /**
     * @return array<string, int|string>
     */
    public function markReady(int $eventId, int $userId): array
    {
        return $this->getInventoryService()->markReady($eventId, $userId);
    }

    private function getLayoutService(): SeatLayoutService
    {
        return Factory::getContainer()->get(SeatLayoutService::class);
    }

    private function getInventoryService(): EventSeatInventoryService
    {
        return Factory::getContainer()->get(EventSeatInventoryService::class);
    }
}
