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
use Joomla\Component\CopyMyPage\Site\Service\TicketReservationService;

/**
 * Attach the service-owned ticket cart config and initialize its runtime once.
 */
final class TicketCartAssetItem extends WebAssetItem implements WebAssetAttachBehaviorInterface
{
    public function onAttachCallback(Document $doc): void
    {
        Text::script('COM_COPYMYPAGE_TICKET_SELECTION_JS_RUNTIME_MISSING');
        Text::script('COM_COPYMYPAGE_TICKET_SELECTION_JS_UIKIT_MISSING');

        $doc->addScriptOptions(
            'copymypage.params',
            ['com' => ['ticketCart' => $this->getTicketCartParams()]],
            true
        );
        $doc->addScriptDeclaration(<<<'JS'
(function () {
    'use strict';

    let missingRuntimeReported = false;
    let missingUiKitReported = false;

    const initializeTicketCart = function (context) {
        if (!window.UIkit) {
            if (!missingUiKitReported) {
                missingUiKitReported = true;
                console.error(Joomla.Text._('COM_COPYMYPAGE_TICKET_SELECTION_JS_UIKIT_MISSING'));
            }

            return;
        }

        if (!window.CopyMyPageTicketCart || typeof window.CopyMyPageTicketCart.init !== 'function') {
            if (!missingRuntimeReported) {
                missingRuntimeReported = true;
                console.error(Joomla.Text._('COM_COPYMYPAGE_TICKET_SELECTION_JS_RUNTIME_MISSING'));
            }

            return;
        }

        window.CopyMyPageTicketCart.init(context || document);
    };

    const handleUpdated = function (event) {
        const target = event && event.target;
        const context = target && typeof target.querySelectorAll === 'function'
            ? target
            : document;

        initializeTicketCart(context);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initializeTicketCart(document);
        }, { once: true });
    } else {
        initializeTicketCart(document);
    }

    document.addEventListener('joomla:updated', handleUpdated);
})();
JS);
    }

    /**
     * @return array<string, mixed>
     */
    private function getTicketCartParams(): array
    {
        try {
            $service = Factory::getContainer()->get(TicketReservationService::class);
            $params  = $service->getClientConfig();
        } catch (\Throwable) {
            return [];
        }

        return \is_array($params) ? $params : [];
    }
}
