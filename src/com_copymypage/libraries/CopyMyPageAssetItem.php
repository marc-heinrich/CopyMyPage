<?php
/**
 * @package     Joomla.Libraries
 * @subpackage  WebAssetItem
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 * @see         https://docs.joomla.org/J4.x:Web_Assets and CoreAssetItem.php
 */

namespace Joomla\CMS\WebAsset\AssetItem;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\Document;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetAttachBehaviorInterface;
use Joomla\CMS\WebAsset\WebAssetItem;
use Joomla\Registry\Registry;

/**
 * Web Asset Item class for CopyMyPage.
 */
final class CopyMyPageAssetItem extends WebAssetItem implements WebAssetAttachBehaviorInterface
{
    /**
     * Prepare the parameters and initialize the JS for CopyMyPage and third-party modules.
     *
     * @param  Document  $doc  The document object.
     */
    public function onAttachCallback(Document $doc): void
    {
        // Add necessary language strings for error handling.
        $this->addLanguageStrings();

        // Fetch DB-backed configuration from template style + navbar module instances.
        $templateParams = $this->getTemplateParams();
        $navbarParams   = $this->getNavbarModuleParams();

        // Merge all options into a single object.
        $options = [
            'tmpl'  => $templateParams,
            'mod'   => [
                'navbar' => $navbarParams,
            ],
        ];      

        // Expose for JS (Joomla.getOptions('copymypage.params')).
        // Note: whether merge with existing (true) or replace (false).
        $doc->addScriptOptions('copymypage.params', $options, false);

        // Same object goes into CopyMyPage init.
        $jsonParams = json_encode($options) ?: '{}';

        // Pass only navbar params into mmenu-light init (single source of truth for ids + options).
        $jsonNavbarParams = json_encode($navbarParams) ?: '{}';

        // Add the central event listener for DOMContentLoaded.
        $doc->addScriptDeclaration("
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize CopyMyPage and other third-party modules.
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
     * Get the JavaScript initialization code for MmenuLight (third-party).
     *
     * @param   string  $jsonNavbarParams  JSON encoded navbar module params (DB-backed).
     *
     * @return  string  The JavaScript code for initializing MmenuLight.
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

                    const open = () => {
                        closeAllExcept(drawer);

                        if (drawer && typeof drawer.open === 'function') {
                            drawer.open();
                        }
                    };

                    // Bind all openers pointing to this menu id.
                    document.querySelectorAll('[data-cmp-mmenulight-open=\"#' + m.id + '\"]').forEach((opener) => {
                        opener.addEventListener('click', (ev) => {
                            ev.preventDefault();
                            open();
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
     * Adds necessary language strings for error handling.
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
     * Fetch template style parameters (DB-backed).
     *
     * This is the single source of truth for global layout/UI hooks.
     * Returns a plain array so it can be merged into a JS runtime config.
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
     * Fetch navbar module parameters (DB-backed).
     *
     * We try to resolve the active module instance via positions first because the module
     * can be duplicated (e.g. "navbar" + "mobilemenu"). If both exist, we merge them and
     * let "mobilemenu" override "navbar" (useful for mmenu-light options).
     *
     * @return array<string, mixed>
     */
    private function getNavbarModuleParams(): array
    {
        // Get params from both positions.
        $navbar = $this->getNavbarModuleParamsByPosition('navbar');
        $mobile = $this->getNavbarModuleParamsByPosition('mobilemenu');

        if ($navbar === [] && $mobile === []) {
            return [];
        }

        // Merge both, with "mobilemenu" taking precedence.
        return array_replace_recursive($navbar, $mobile);
    }

    /**
     * Resolve a mod_copymypage_navbar instance by module position and return its params.
     *
     * @param  string  $position  The module position name (e.g. "navbar", "mobilemenu").
     *
     * @return array<string, mixed>
     */
    private function getNavbarModuleParamsByPosition(string $position): array
    {
        $modules = ModuleHelper::getModules($position);

        foreach ($modules as $module) {

            // Ensure we are dealing with the correct module type.
            if (($module->module ?? '') !== 'mod_copymypage_navbar') {
                continue;
            }

            // Load module params from JSON.
            $registry = new Registry();
            $registry->loadString((string) ($module->params ?? ''), 'JSON');

            return $registry->toArray();
        }

        return [];
    }
}
