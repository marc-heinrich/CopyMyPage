<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Component\CopyMyPage\Site\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Component\CopyMyPage\Site\Service\AddressCatalogService;

/**
 * Country-dependent region select backed by CopyMyPage's address catalogue.
 */
final class CopymypageregionField extends ListField
{
    protected $type = 'Copymypageregion';

    /**
     * @return array<int, object>
     */
    protected function getOptions(): array
    {
        $options     = parent::getOptions();
        $countryCode = $this->form !== null
            ? trim((string) $this->form->getValue('country_code'))
            : '';

        if ($countryCode === '') {
            return $options;
        }

        $catalogue = Factory::getContainer()->get(AddressCatalogService::class);

        foreach ($catalogue->getRegions($countryCode) as $code => $name) {
            $options[] = HTMLHelper::_('select.option', $code, $name);
        }

        return $options;
    }
}
