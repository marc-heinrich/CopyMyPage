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

        // Provide navbar/module params for other JS consumers (e.g. MmenuLight) without polluting the root.
        // Use a dedicated key to avoid accidental collisions with template param names.
        $templateParams['navParams'] = $navbarParams;

        // Encode parameters as JSON for JavaScript consumption.
        $jsonParams = json_encode($templateParams) ?: '{}';

        // Add the central event listener for DOMContentLoaded.
        $doc->addScriptDeclaration("
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize CopyMyPage and other third-party modules.
                {$this->getCopyMyPageJS($jsonParams)}
                {$this->getMmenuLightJS()}
            });
        ");
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

    /**
     * Get the JavaScript initialization code for CopyMyPage.
     *
     * @param string $jsonParams The JSON-encoded parameters for CopyMyPage.
     * @return string The JavaScript code for initializing CopyMyPage.
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
     * @return string The JavaScript code for initializing MmenuLight.
     */
    private function getMmenuLightJS(): string
    {
        return "
            if (typeof window.MmenuLight !== 'undefined') {

                const menu = new window.MmenuLight(document.querySelector('#menu'), 'all');

                menu.navigation({
                    // selected: 'Selected',
                    // slidingSubmenus: true,
                    // theme: 'dark',
                    // title: 'Menu'
                });

                const drawer = menu.offcanvas({
                    // position: 'left'
                });

                const opener = document.querySelector('a[href=\"#menu\"]');

                if (opener) {
                    opener.addEventListener('click', function (event) {
                        event.preventDefault();
                        drawer.open();
                    });
                }
            } else {
                console.error(Joomla.Text._('TPL_COPYMYPAGE_JS_ERROR_NOT_DEFINED').replace('%s', 'MmenuLight'));
            }
        ";
    }
}
