<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Component\CopyMyPage\Site\Service\SeatLayoutService;

/**
 * Lists allowlisted JSON files from the fixed component data directory.
 */
final class BundledlayoutField extends ListField
{
    protected $type = 'Bundledlayout';

    protected function getOptions(): array
    {
        $options = parent::getOptions();
        $service = Factory::getContainer()->get(SeatLayoutService::class);

        foreach ($service->getBundledDefinitions() as $definition) {
            $options[] = HTMLHelper::_(
                'select.option',
                $definition['file'],
                Text::sprintf(
                    'COM_COPYMYPAGE_EVENT_SEATING_FIELD_DEFINITION_OPTION',
                    $definition['title'],
                    $definition['alias'],
                    $definition['version'],
                    $definition['seatCount']
                )
            );
        }

        return $options;
    }
}
