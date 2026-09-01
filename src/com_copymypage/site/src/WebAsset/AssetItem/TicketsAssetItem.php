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

/**
 * Attach normalized ticket options and own the idempotent Swiper lifecycle.
 */
final class TicketsAssetItem extends WebAssetItem implements WebAssetAttachBehaviorInterface
{
    /**
     * Attach options and a single DOM-ready/joomla:updated initializer.
     */
    public function onAttachCallback(Document $doc): void
    {
        Text::script('MOD_COPYMYPAGE_TICKETS_JS_RUNTIME_MISSING');
        Text::script('MOD_COPYMYPAGE_TICKETS_JS_SWIPER_MISSING');

        $options = [
            'mod' => [
                'tickets' => $this->getTicketsModuleParams(),
            ],
        ];

        $doc->addScriptOptions('copymypage.params', $options, true);
        $doc->addScriptDeclaration(<<<'JS'
(function () {
    'use strict';

    let missingRuntimeReported = false;
    let missingSwiperReported = false;

    const initializeTickets = function (context) {
        if (typeof window.Swiper !== 'function') {
            if (!missingSwiperReported) {
                missingSwiperReported = true;
                console.error(Joomla.Text._('MOD_COPYMYPAGE_TICKETS_JS_SWIPER_MISSING'));
            }

            return;
        }

        if (!window.CopyMyPageTickets || typeof window.CopyMyPageTickets.init !== 'function') {
            if (!missingRuntimeReported) {
                missingRuntimeReported = true;
                console.error(Joomla.Text._('MOD_COPYMYPAGE_TICKETS_JS_RUNTIME_MISSING'));
            }

            return;
        }

        window.CopyMyPageTickets.init(context || document);
    };

    const handleUpdated = function (event) {
        const target = event && event.target;
        const context = target && typeof target.querySelectorAll === 'function'
            ? target
            : document;

        initializeTickets(context);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initializeTickets(document);
        }, { once: true });
    } else {
        initializeTickets(document);
    }

    document.addEventListener('joomla:updated', handleUpdated);
})();
JS);
    }

    /**
     * Fetch the helper-owned configuration defensively.
     *
     * @return array<string, mixed>
     */
    private function getTicketsModuleParams(): array
    {
        $helper = $this->getTicketsModuleHelper();

        if ($helper === null || !method_exists($helper, 'getClientConfig')) {
            return [];
        }

        try {
            $params = $helper->getClientConfig();
        } catch (\Throwable) {
            return [];
        }

        return \is_array($params) ? $params : [];
    }

    /**
     * Resolve the module helper through Joomla's module boot process.
     */
    private function getTicketsModuleHelper(): ?object
    {
        $app = Factory::getApplication();

        if (!method_exists($app, 'bootModule')) {
            return null;
        }

        try {
            return $app->bootModule('mod_copymypage_tickets', 'site')->getHelper('TicketsHelper');
        } catch (\Throwable) {
            return null;
        }
    }
}
