<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Site\WebAsset\AssetItem;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\Document;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetAttachBehaviorInterface;
use Joomla\CMS\WebAsset\WebAssetItem;
use Joomla\Component\CopyMyPage\Site\Service\SeatSelectionService;

/**
 * Attach the service-owned private seating configuration.
 */
final class TicketSeatsAssetItem extends WebAssetItem implements WebAssetAttachBehaviorInterface
{
    public function onAttachCallback(Document $doc): void
    {
        Text::script('COM_COPYMYPAGE_SEAT_SELECTION_ERROR_REQUEST');

        $doc->addScriptOptions(
            'copymypage.params',
            ['com' => ['ticketSeats' => $this->getParams()]],
            true
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getParams(): array
    {
        try {
            $params = Factory::getContainer()->get(SeatSelectionService::class)->getClientConfig();
        } catch (\Throwable) {
            return [];
        }

        return \is_array($params) ? $params : [];
    }
}
