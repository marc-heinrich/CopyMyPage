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
 * Lists imported and published immutable seating layout versions.
 */
final class SeatlayoutField extends ListField
{
    protected $type = 'Seatlayout';

    protected function getOptions(): array
    {
        $options = parent::getOptions();
        $service = Factory::getContainer()->get(SeatLayoutService::class);

        foreach ($service->getPublishedLayouts() as $layout) {
            $options[] = HTMLHelper::_(
                'select.option',
                $layout['id'],
                Text::sprintf(
                    'COM_COPYMYPAGE_EVENT_SEATING_FIELD_LAYOUT_OPTION',
                    $layout['title'],
                    $layout['alias'],
                    $layout['version'],
                    $layout['tableCount'],
                    $layout['seatCount']
                )
            );
        }

        return $options;
    }
}
