<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Administrator\View\Eventseating;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\CopyMyPage\Administrator\Model\EventseatingModel;

/**
 * Administrator view for seating definition import and draft assignment.
 */
final class HtmlView extends BaseHtmlView
{
    public Form $form;

    public int $eventId = 0;

    /** @var array<string, mixed> */
    public array $summary = [];

    public bool $hasBundledDefinitions = false;

    public bool $hasPublishedLayouts = false;

    public bool $canAssign = false;

    public bool $canMarkReady = false;

    public string $backUrl = '';

    public function display($tpl = null): void
    {
        /** @var EventseatingModel $model */
        $model = $this->getModel();
        $model->setUseExceptions(true);

        $this->eventId              = $model->getEventId();
        $this->summary              = $model->getSummary();
        $this->hasBundledDefinitions = $model->getBundledDefinitions() !== [];
        $this->hasPublishedLayouts   = $model->getPublishedLayouts() !== [];
        $this->canAssign             = $this->calculateCanAssign($this->summary);
        $this->canMarkReady          = $this->calculateCanMarkReady($this->summary);
        $this->form                  = $model->getForm();

        $startDate = (string) ($this->summary['event']['startDate'] ?? '');

        if ($startDate !== '') {
            $this->summary['event']['startDate'] = HTMLHelper::_(
                'date',
                $startDate,
                Text::_('DATE_FORMAT_LC2')
            );
        }

        if ($this->eventId > 0) {
            $this->backUrl = 'index.php?option=com_dpcalendar&task=event.edit&e_id=' . $this->eventId;
        }

        ToolbarHelper::title(Text::_('COM_COPYMYPAGE_EVENT_SEATING_TITLE'), 'grid-2');

        parent::display($tpl);
    }

    /**
     * @param   array<string, mixed>  $summary
     */
    private function calculateCanAssign(array $summary): bool
    {
        $event      = \is_array($summary['event'] ?? null) ? $summary['event'] : [];
        $assignment = \is_array($summary['assignment'] ?? null) ? $summary['assignment'] : null;

        return $this->eventId > 0
            && (bool) ($event['isUpcoming'] ?? false)
            && (int) ($summary['activeCartQuantity'] ?? 0) === 0
            && ($assignment === null || ($assignment['status'] ?? '') === 'draft');
    }

    /**
     * @param   array<string, mixed>  $summary
     */
    private function calculateCanMarkReady(array $summary): bool
    {
        $event      = \is_array($summary['event'] ?? null) ? $summary['event'] : [];
        $assignment = \is_array($summary['assignment'] ?? null) ? $summary['assignment'] : null;

        return $this->eventId > 0
            && (bool) ($event['isUpcoming'] ?? false)
            && (int) ($summary['activeCartQuantity'] ?? 0) === 0
            && (int) ($summary['nativeTicketCount'] ?? 0) === 0
            && (int) ($event['capacityUsed'] ?? 0) === 0
            && $assignment !== null
            && ($assignment['status'] ?? '') === 'draft'
            && (int) ($assignment['materializedCount'] ?? 0) === (int) ($assignment['seatCount'] ?? -1)
            && (int) ($assignment['seatCount'] ?? 0) > 0;
    }
}
