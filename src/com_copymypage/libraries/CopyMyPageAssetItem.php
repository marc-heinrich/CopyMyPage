<?php
/**
 * @package     Joomla.Libraries
 * @subpackage  WebAssetItem
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.5
 * @see         https://docs.joomla.org/J4.x:Web_Assets and CoreAssetItem.php
 */

namespace Joomla\CMS\WebAsset\AssetItem;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\Document;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetAttachBehaviorInterface;
use Joomla\CMS\WebAsset\WebAssetItem;
use Joomla\Component\CopyMyPage\Site\Helper\Helpers\NavbarParamsHelper as CopyMyPageNavbarParamsHelper;
use Joomla\Component\CopyMyPage\Site\Helper\Registry as CopyMyPageRegistry;
use Joomla\Registry\Registry;

/**
 * Web Asset Item class for CopyMyPage.
 */
final class CopyMyPageAssetItem extends WebAssetItem implements WebAssetAttachBehaviorInterface
{
    /**
     * Attach script options and inline initializers for CopyMyPage.
     *
     * @param  Document  $doc  The document instance.
     */
    public function onAttachCallback(Document $doc): void
    {
        // Register language strings used by client-side error logging.
        $this->addLanguageStrings();

        // Load parameters from template style and navbar module instances.
        $templateParams = $this->getTemplateParams();
        $navbarParams   = $this->getNavbarModuleParams();

        // Build options payload for client-side initialization.
        $options = [
            'tmpl'  => $templateParams,
            'mod'   => [
                'navbar' => $navbarParams,
            ],
        ];      

        // Expose options via Joomla.getOptions('copymypage.params').
        $doc->addScriptOptions('copymypage.params', $options, false);

        // Encode options for the inline initializer.
        $jsonParams = json_encode($options) ?: '{}';

        // Encode navbar options for mmenu-light initialization.
        $jsonNavbarParams = json_encode($navbarParams) ?: '{}';

        // Attach a DOMContentLoaded initializer.
        $doc->addScriptDeclaration("
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize CopyMyPage and MmenuLight.
                {$this->getCopyMyPageJS($jsonParams)}
                {$this->getMmenuLightJS($jsonNavbarParams)}
            });
        ");
    }

    /**
     * Get the JavaScript initialization code for CopyMyPage.
     *
     * @param   string  $jsonParams The JSON-encoded parameters for CopyMyPage.
     * @return  string  The JavaScript code for initializing CopyMyPage.
     */
    private function getCopyMyPageJS(string $jsonParams): string
    {
        return "
            if (typeof window.CopyMyPage !== 'undefined') {
                const copyMyPage = new window.CopyMyPage($jsonParams);
                copyMyPage.init();
            } else {
                console.error(Joomla.Text._('TPL_COPYMYPAGE_JS_ERROR_NOT_DEFINED').replace('%s', 'CopyMyPage'));
            }
        ";
    }

    /**
     * Build the MmenuLight initialization script.
     *
     * @param   string  $jsonNavbarParams  JSON-encoded navbar module parameters.
     *
     * @return  string  JavaScript initializer snippet.
     */
    private function getMmenuLightJS(string $jsonNavbarParams): string
    {
        return "
            (function () {
                if (typeof window.MmenuLight === 'undefined') {
                    console.error(Joomla.Text._('TPL_COPYMYPAGE_JS_ERROR_NOT_DEFINED').replace('%s', 'MmenuLight'));
                    return;
                }

                const cfg = $jsonNavbarParams || {};

                const mediaQuery        = cfg.mmenuLightMediaQuery || 'all';
                const theme             = cfg.mmenuLightTheme || 'light';
                const selected          = cfg.mmenuLightSelectedClass || '';
                const closeOnClick      = !!cfg.mmenuLightCloseOnClick;
                const slidingSubmenus   = (cfg.mmenuLightSlidingSubmenus !== undefined)
                    ? !!cfg.mmenuLightSlidingSubmenus
                    : true;

                const menus = [
                    {
                        id: cfg.navOffcanvasId,
                        title: cfg.mmenuLightNavTitle,
                        position: cfg.mmenuLightNavPosition || 'left'
                    },
                    {
                        id: cfg.userOffcanvasId,
                        title: cfg.mmenuLightUserTitle,
                        position: cfg.mmenuLightUserPosition || 'right'
                    },
                    {
                        id: cfg.basketOffcanvasId,
                        title: cfg.mmenuLightBasketTitle,
                        position: cfg.mmenuLightBasketPosition || 'right'
                    }
                ].filter((m) => !!(m && m.id));

                const drawers = [];

                const closeAllExcept = (keep) => {
                    drawers.forEach((d) => {
                        if (d !== keep && d && typeof d.close === 'function') {
                            d.close();
                        }
                    });
                };

                menus.forEach((m) => {
                    const nav = document.getElementById(m.id);

                    // Skip if the source node is missing or already initialized.
                    if (!nav || nav.dataset.cmpMmenulightInit === '1') {
                        return;
                    }

                    nav.dataset.cmpMmenulightInit = '1';

                    const menu = new window.MmenuLight(nav, mediaQuery);

                    menu.navigation({
                        theme: theme,
                        selected: selected || undefined,
                        slidingSubmenus: slidingSubmenus,
                        title: (m.title && String(m.title).trim()) ? String(m.title).trim() : undefined
                    });

                    const drawer = menu.offcanvas({
                        position: (m.position === 'right') ? 'right' : 'left'
                    });

                    drawers.push(drawer);

                    const openers = Array.from(
                        document.querySelectorAll('[data-cmp-mmenulight-open=\"#' + m.id + '\"]')
                    );

                    const setOpenersState = (isOpen) => {
                        openers.forEach((opener) => {
                            opener.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

                            if (typeof opener.state !== 'undefined') {
                                opener.state = isOpen ? 'cross' : 'bars';
                            }
                        });
                    };

                    const open = () => {
                        closeAllExcept(drawer);

                        if (drawer && typeof drawer.open === 'function') {
                            drawer.open();
                        }
                    };

                    if (drawer && drawer.wrapper instanceof Element) {
                        const syncState = () => {
                            setOpenersState(drawer.wrapper.classList.contains('mm-ocd--open'));
                        };

                        const observer = new MutationObserver((mutations) => {
                            mutations.forEach((mutation) => {
                                if (mutation.attributeName === 'class') {
                                    syncState();
                                }
                            });
                        });

                        observer.observe(drawer.wrapper, {
                            attributes: true,
                            attributeFilter: ['class']
                        });

                        syncState();
                    } else {
                        setOpenersState(false);
                    }

                    // Bind all openers pointing to this menu id.
                    openers.forEach((opener) => {
                        opener.addEventListener('click', (ev) => {
                            ev.preventDefault();
                            open();
                        });

                        opener.addEventListener('keyup', (ev) => {
                            if (ev.key === 'Enter' || ev.key === ' ') {
                                ev.preventDefault();
                                open();
                            }
                        });
                    });

                    // Optional: close drawer when a real link inside the menu is clicked.
                    if (closeOnClick) {
                        nav.addEventListener('click', (ev) => {
                            const a = ev.target.closest('a');
                            if (!a) {
                                return;
                            }

                            const href = a.getAttribute('href') || '';
                            if (!href || href === '#') {
                                return;
                            }

                            if (drawer && typeof drawer.close === 'function') {
                                drawer.close();
                            }
                        });
                    }
                });
            })();
        ";
    }

    /**
     * Register language strings used by inline scripts.
     */
    private function addLanguageStrings(): void
    {
        Text::script('TPL_COPYMYPAGE_JS_ERROR_BACKTOTOP_NOT_FOUND');
        Text::script('TPL_COPYMYPAGE_JS_ERROR_ELEMENT_NOT_FOUND');
        Text::script('TPL_COPYMYPAGE_JS_ERROR_INIT_SKIPPED');
        Text::script('TPL_COPYMYPAGE_JS_ERROR_INVALID_PARAMS');
        Text::script('TPL_COPYMYPAGE_JS_ERROR_NOT_DEFINED');
        Text::script('TPL_COPYMYPAGE_JS_ERROR_SELECTOR');
    }

    /**
     * Fetch template style parameters.
     *
     * @return array<string, mixed>
     */
    private function getTemplateParams(): array
    {
        $app = Factory::getApplication();

        // Check if we can access the template.
        if (!method_exists($app, 'getTemplate')) {
            return [];
        }

        // Get the active template style.
        $template = $app->getTemplate(true);

        // Ensure we have valid params.
        if (!isset($template->params) || !$template->params instanceof Registry) {
            return [];
        }

        return $template->params->toArray();
    }

    /**
     * Fetch navbar module parameters.
     *
     * @return array<string, mixed>
     */
    private function getNavbarModuleParams(): array
    {
        $helper = $this->getNavbarParamsHelper();

        if ($helper === null || !method_exists($helper, 'getModuleParams')) {
            return [];
        }

        $params = $helper->getModuleParams();

        return is_array($params) ? $params : [];
    }

    /**
     * Resolve the navbar parameters helper from the CopyMyPage registry.
     *
     * Falls back to direct helper instantiation when the registry service is unavailable.
     */
    private function getNavbarParamsHelper(): ?object
    {
        $container = Factory::getContainer();

        if ($container->has(CopyMyPageRegistry::class)) {
            /** @var CopyMyPageRegistry $registry */
            $registry = $container->get(CopyMyPageRegistry::class);

            if ($registry->hasService('navbar')) {
                $handler = $registry->getService('navbar');

                if (is_string($handler) && class_exists($handler)) {
                    $handler = new $handler();
                }

                if (is_object($handler)) {
                    return $handler;
                }
            }
        }

        if (class_exists(CopyMyPageNavbarParamsHelper::class)) {
            return new CopyMyPageNavbarParamsHelper();
        }

        return null;
    }
}
