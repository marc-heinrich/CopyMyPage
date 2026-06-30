<?php
/**
 * @package     Joomla.Libraries
 * @subpackage  WebAssetItem
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.16
 */

namespace Joomla\CMS\WebAsset\AssetItem;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\Document;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetAttachBehaviorInterface;
use Joomla\CMS\WebAsset\WebAssetItem;

/**
 * Web Asset Item class for PureCounter bootstrap logic.
 */
final class PureCounterAssetItem extends WebAssetItem implements WebAssetAttachBehaviorInterface
{
    /**
     * Attach the PureCounter initializer.
     *
     * @param  Document  $doc  The document instance.
     */
    public function onAttachCallback(Document $doc): void
    {
        Text::script('TPL_COPYMYPAGE_JS_ERROR_NOT_DEFINED');

        $doc->addScriptDeclaration("
            (function () {
                const initPureCounter = function () {
                    {$this->getPureCounterJS()}
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initPureCounter, { once: true });
                } else {
                    initPureCounter();
                }

                document.addEventListener('joomla:updated', initPureCounter);
            })();
        ");
    }

    /**
     * Build the PureCounter initialization script.
     *
     * @return  string  JavaScript initializer snippet.
     */
    private function getPureCounterJS(): string
    {
        return "
            if (typeof window.PureCounter === 'undefined') {
                console.error(Joomla.Text._('TPL_COPYMYPAGE_JS_ERROR_NOT_DEFINED').replace('%s', 'PureCounter'));
                return;
            }

            const selector = '.purecounter:not([data-cmp-purecounter-bound=\"1\"])';
            const pendingClass = 'cmp-purecounter-pending';
            const counters = Array.from(document.querySelectorAll(selector));

            if (counters.length === 0) {
                return;
            }

            counters.forEach((counter) => {
                counter.dataset.cmpPurecounterBound = '1';
                counter.classList.add(pendingClass);
            });

            new window.PureCounter({
                selector: '.' + pendingClass
            });

            counters.forEach((counter) => {
                counter.classList.remove(pendingClass);
            });
        ";
    }
}
