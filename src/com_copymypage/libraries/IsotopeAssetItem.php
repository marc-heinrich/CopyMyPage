<?php
/**
 * @package     Joomla.Libraries
 * @subpackage  WebAssetItem
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\CMS\WebAsset\AssetItem;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\Document;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetAttachBehaviorInterface;
use Joomla\CMS\WebAsset\WebAssetItem;

/**
 * Web Asset Item class for the CopyMyPage gallery Isotope bootstrap logic.
 */
final class IsotopeAssetItem extends WebAssetItem implements WebAssetAttachBehaviorInterface
{
    /**
     * Attach the Isotope options and initializer.
     *
     * @param  Document  $doc  The document instance.
     */
    public function onAttachCallback(Document $doc): void
    {
        Text::script('TPL_COPYMYPAGE_JS_ERROR_NOT_DEFINED');

        $galleryParams = $this->getGalleryModuleParams();
        $options       = [
            'mod' => [
                'gallery' => $galleryParams,
            ],
        ];

        $doc->addScriptOptions('copymypage.params', $options, true);

        $doc->addScriptDeclaration("
            (function () {
                const initIsotope = function () {
                    {$this->getIsotopeJS()}
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initIsotope, { once: true });
                } else {
                    initIsotope();
                }

                document.addEventListener('joomla:updated', initIsotope);
            })();
        ");
    }

    /**
     * Build the Isotope initialization script.
     *
     * @return  string  JavaScript initializer snippet.
     */
    private function getIsotopeJS(): string
    {
        return "
            if (typeof window.Isotope === 'undefined') {
                console.error(Joomla.Text._('TPL_COPYMYPAGE_JS_ERROR_NOT_DEFINED').replace('%s', 'Isotope'));
                return;
            }

            const rootSelector = '[data-cmp-gallery-isotope]:not([data-cmp-gallery-isotope-init])';
            const gridSelector = '[data-cmp-gallery-isotope-grid]';
            const filterSelector = '[data-cmp-gallery-isotope-filter]';
            const options = Joomla.getOptions('copymypage.params', {}) || {};
            const cfg = options.mod?.gallery || {};

            const roots = Array.from(document.querySelectorAll(rootSelector));

            if (roots.length === 0) {
                return;
            }

            roots.forEach((root) => {
                const grid = root.querySelector(gridSelector);

                if (!grid) {
                    return;
                }

                const controls = Array.from(root.querySelectorAll(filterSelector));
                const isotope = new window.Isotope(grid, cfg);
                let layoutFrame = null;

                const scheduleLayout = () => {
                    if (layoutFrame !== null) {
                        return;
                    }

                    layoutFrame = window.requestAnimationFrame(() => {
                        layoutFrame = null;
                        isotope.layout();
                    });
                };

                const setActiveControl = (activeControl) => {
                    controls.forEach((control) => {
                        const isActive = control === activeControl;
                        const item = control.closest('li');

                        item?.classList.toggle('uk-active', isActive);

                        if (isActive) {
                            control.setAttribute('aria-current', 'true');
                        } else {
                            control.removeAttribute('aria-current');
                        }
                    });
                };

                root.addEventListener('click', (event) => {
                    const target = event.target instanceof Element
                        ? event.target.closest(filterSelector)
                        : null;

                    if (!target || !root.contains(target)) {
                        return;
                    }

                    event.preventDefault();

                    const filter = String(target.dataset.cmpGalleryIsotopeFilter || '*').trim() || '*';

                    setActiveControl(target);
                    isotope.arrange({ filter });
                });

                root.dataset.cmpGalleryIsotopeInit = '1';

                if (cfg.initLayout !== false) {
                    grid.querySelectorAll('img').forEach((image) => {
                        if (image.complete) {
                            return;
                        }

                        image.addEventListener('load', scheduleLayout, { once: true });
                        image.addEventListener('error', scheduleLayout, { once: true });
                    });
                    scheduleLayout();
                }
            });
        ";
    }

    /**
     * Fetch gallery module parameters.
     *
     * @return  array<string, mixed>
     */
    private function getGalleryModuleParams(): array
    {
        $helper = $this->getGalleryModuleHelper();

        if ($helper === null || !method_exists($helper, 'getClientConfig')) {
            return [];
        }

        $params = $helper->getClientConfig();

        return \is_array($params) ? $params : [];
    }

    /**
     * Resolve the gallery module helper via module bootstrapping.
     */
    private function getGalleryModuleHelper(): ?object
    {
        $app = Factory::getApplication();

        if (!method_exists($app, 'bootModule')) {
            return null;
        }

        try {
            return $app->bootModule('mod_copymypage_gallery', 'site')->getHelper('GalleryHelper');
        } catch (\Throwable) {
            return null;
        }
    }
}
