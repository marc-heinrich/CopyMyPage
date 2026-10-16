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
 * Localised country select backed by CopyMyPage's address catalogue.
 */
final class CopymypagecountryField extends ListField
{
    protected $type = 'Copymypagecountry';

    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();
        // Joomla's list layout omits autocomplete; retain its standard renderer.
        $data['dataAttribute'] .= ' autocomplete="' . htmlspecialchars($this->autocomplete, ENT_QUOTES, 'UTF-8') . '"';

        return $data;
    }

    /**
     * @return array<int, object>
     */
    protected function getOptions(): array
    {
        $options   = parent::getOptions();
        $catalogue = Factory::getContainer()->get(AddressCatalogService::class);

        $countries = $catalogue->getCountries();

        foreach ($countries as $code => $name) {
            $options[] = HTMLHelper::_('select.option', $code, $name);
        }

        // Keep rejected input visible for correction; services still validate it.
        $value = trim((string) $this->value);

        if ($value !== '' && !isset($countries[$value])) {
            $options[] = HTMLHelper::_('select.option', $value, $value);
        }

        return $options;
    }
}
