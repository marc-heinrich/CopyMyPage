<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Plugin\System\CopyMyPage\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\Component\CopyMyPage\Site\Service\EventSeatInventoryService;
use Joomla\Component\CopyMyPage\Site\Service\SeatLayoutService;

/**
 * Selects one allowlisted JSON layout from the DPCalendar event form.
 */
final class EventseatingField extends FormField
{
    protected $type = 'Eventseating';

    protected function getInput(): string
    {
        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('copymypage.seating.configure', 'com_copymypage')) {
            return '';
        }

        $eventId = max(
            0,
            (int) $this->form->getValue('id'),
            $app->getInput()->getInt('e_id')
        );

        try {
            $container   = Factory::getContainer();
            $definitions = $container->get(SeatLayoutService::class)->getBundledDefinitions();
            $summary     = $eventId > 0
                ? $container->get(EventSeatInventoryService::class)->getEventSummary($eventId)
                : ['assignment' => null];
        } catch (\Throwable) {
            return $this->alert(
                'warning',
                Text::_('PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_UNAVAILABLE')
            );
        }

        $assignment = \is_array($summary['assignment'] ?? null)
            ? $summary['assignment']
            : null;
        $status = $assignment === null
            ? Text::_('PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_STATUS_NONE')
            : match ((string) ($assignment['status'] ?? '')) {
                'draft' => Text::_('PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_STATUS_DRAFT'),
                'ready' => Text::_('PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_STATUS_READY'),
                default => Text::_('PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_STATUS_UNKNOWN'),
            };
        $details = $assignment === null
            ? Text::_('PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_NO_LAYOUT')
            : Text::sprintf(
                'PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_LAYOUT_SUMMARY',
                (string) ($assignment['layoutTitle'] ?? ''),
                (int) ($assignment['layoutVersion'] ?? 0),
                (int) ($assignment['materializedCount'] ?? 0),
                (int) ($assignment['seatCount'] ?? 0)
            );
        $selectedAlias   = (string) ($assignment['layoutAlias'] ?? '');
        $selectedVersion = (int) ($assignment['layoutVersion'] ?? 0);
        $locked          = $assignment !== null;
        $isDraft         = $locked && ($assignment['status'] ?? '') === 'draft';
        $selectId        = $this->escape($this->id . '-layout-file');
        $descriptionId   = $selectId . '-description';
        $options         = '<option value="">'
            . $this->escape(Text::_('PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_SELECT_NONE'))
            . '</option>';
        $matched = false;

        foreach ($definitions as $definition) {
            $file      = (string) ($definition['file'] ?? '');
            $alias     = (string) ($definition['alias'] ?? '');
            $version   = (int) ($definition['version'] ?? 0);
            $isSelected = $locked && $alias === $selectedAlias && $version === $selectedVersion;
            $matched    = $matched || $isSelected;
            $options   .= '<option value="' . $this->escape($file) . '"'
                . ($isSelected ? ' selected' : '') . '>'
                . $this->escape(
                    Text::sprintf(
                        'PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_SELECT_OPTION',
                        (string) ($definition['title'] ?? ''),
                        $version,
                        (int) ($definition['seatCount'] ?? 0),
                        $file
                    )
                )
                . '</option>';
        }

        if ($locked && !$matched) {
            $options .= '<option value="" selected>'
                . $this->escape(
                    Text::sprintf(
                        'PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_SELECT_ASSIGNED',
                        (string) ($assignment['layoutTitle'] ?? ''),
                        $selectedVersion
                    )
                )
                . '</option>';
        }

        $select = $definitions === [] && !$locked
            ? $this->alert('warning', Text::_('PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_SELECT_EMPTY'))
            : '<label class="form-label" for="' . $selectId . '">'
                . $this->escape(Text::_('PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_SELECT_LABEL'))
                . '</label>'
                . '<select class="form-select" id="' . $selectId . '"'
                . ' name="jform[copymypage_layout_file]"'
                . ' aria-describedby="' . $descriptionId . '"'
                . ($locked ? ' disabled aria-disabled="true"' : '') . '>'
                . $options
                . '</select>'
                . '<div class="form-text" id="' . $descriptionId . '">'
                . $this->escape(
                    Text::_($eventId > 0
                        ? 'PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_SELECT_DESC'
                        : 'PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_SELECT_NEW_DESC')
                )
                . '</div>';

        $html = '<div class="alert alert-info mb-3">'
            . '<p class="mb-1"><strong>' . $this->escape($status) . '</strong></p>'
            . '<p class="mb-0">' . $this->escape($details) . '</p>'
            . '</div>'
            . '<div class="mb-3">' . $select . '</div>';

        if ($isDraft) {
            $html .= '<input id="cmp-event-seating-action" type="hidden"'
                . ' name="jform[copymypage_seating_action]" value="">'
                . '<p class="form-text mb-3">'
                . $this->escape(Text::_('PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_READY_DESC'))
                . '</p>'
                . '<div class="cmp-form__actions cmp-event-seating__actions">'
                . '<button class="btn btn-success cmp-button cmp-button--success" type="button"'
                . ' onclick="document.getElementById(\'cmp-event-seating-action\').value = \'mark_ready\';'
                . ' Joomla.submitbutton(\'event.apply\'); return false;">'
                . $this->escape(Text::_('PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_READY_ACTION'))
                . '</button>'
                . '</div>';
        }

        return $html;
    }

    private function alert(string $type, string $message): string
    {
        return '<div class="alert alert-' . $this->escape($type) . ' mb-0" role="status">'
            . $this->escape($message)
            . '</div>';
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
