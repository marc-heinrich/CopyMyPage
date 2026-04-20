<?php
/**
 * @package     Joomla.Libraries
 * @subpackage  WebAssetItem
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.10
 * @see         https://docs.joomla.org/J4.x:Web_Assets and CoreAssetItem.php
 */

namespace Joomla\CMS\WebAsset\AssetItem;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\Document;
use Joomla\CMS\Factory;
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
     * Attach script options and the CopyMyPage initializer.
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
                'navbar'      => $navbarParams['shared'] ?? [],
                'navbarSlots' => $navbarParams['slots'] ?? [],
            ],
            'view'  => [],
        ];

        // Expose options via Joomla.getOptions('copymypage.params').
        $doc->addScriptOptions('copymypage.params', $options, true);

        // Initialize immediately when possible, otherwise wait for DOM readiness.
        $doc->addScriptDeclaration("
            (function () {
                const initCopyMyPage = function () {
                    {$this->getCopyMyPageJS()}
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initCopyMyPage, { once: true });
                    return;
                }

                initCopyMyPage();
            })();
        ");
    }

    /**
     * Get the JavaScript initialization code for CopyMyPage.
     *
     * @return  string  The JavaScript code for initializing CopyMyPage.
     */
    private function getCopyMyPageJS(): string
    {
        return "
            if (typeof window.CopyMyPage !== 'undefined' && typeof window.CopyMyPage.createRuntime === 'function') {
                const destroyCopyMyPage = function() {
                    if (window.__copyMyPageInstance && typeof window.__copyMyPageInstance.destroy === 'function') {
                        window.__copyMyPageInstance.destroy();
                    }
                };

                // Ensure re-inits (e.g. partial page updates) do not leak listeners.
                destroyCopyMyPage();

                const copyMyPage = window.CopyMyPage.createRuntime();
                copyMyPage.init();
                window.__copyMyPageInstance = copyMyPage;

                // Bind lifecycle hooks once.
                if (!window.__copyMyPageLifecycleBound) {
                    window.addEventListener('beforeunload', destroyCopyMyPage);
                    window.addEventListener('pagehide', destroyCopyMyPage);
                    document.addEventListener('turbo:before-cache', destroyCopyMyPage);
                    document.addEventListener('pjax:beforeReplace', destroyCopyMyPage);
                    document.addEventListener('copymypage:destroy', destroyCopyMyPage);
                    window.__copyMyPageLifecycleBound = true;
                }
            } else {
                console.error(Joomla.Text._('TPL_COPYMYPAGE_JS_ERROR_NOT_DEFINED').replace('%s', 'CopyMyPage.createRuntime'));
            }
        ";
    }

    /**
     * Register language strings used by inline scripts.
     */
    private function addLanguageStrings(): void
    {
        // Core strings used by CopyMyPage dialog and form helpers.
        Text::script('ERROR');
        Text::script('MESSAGE');
        Text::script('NOTICE');
        Text::script('WARNING');
        Text::script('JNOTICE');
        Text::script('JOK');
        Text::script('JYES');
        Text::script('JNO');
        Text::script('JGLOBAL_VALIDATION_FORM_FAILED');

        // Template-specific runtime error messages.
        Text::script('TPL_COPYMYPAGE_JS_ERROR_BACKTOTOP_NOT_FOUND');
        Text::script('TPL_COPYMYPAGE_JS_ERROR_ELEMENT_NOT_FOUND');
        Text::script('TPL_COPYMYPAGE_JS_ERROR_INIT_SKIPPED');
        Text::script('TPL_COPYMYPAGE_JS_ERROR_INVALID_PARAMS');
        Text::script('TPL_COPYMYPAGE_JS_ERROR_NOT_DEFINED');
        Text::script('TPL_COPYMYPAGE_JS_ERROR_PRELOADER_NOT_FOUND');
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
        $helper = $this->getNavbarModuleHelper();

        if ($helper === null || !method_exists($helper, 'getClientConfig')) {
            return [];
        }

        $params = $helper->getClientConfig();

        return is_array($params) ? $params : [];
    }

    /**
     * Resolve the navbar module helper via module bootstrapping.
     */
    private function getNavbarModuleHelper(): ?object
    {
        $app = Factory::getApplication();

        if (!method_exists($app, 'bootModule')) {
            return null;
        }

        try {
            return $app->bootModule('mod_copymypage_navbar', 'site')->getHelper('NavbarHelper');
        } catch (\Throwable) {
            return null;
        }
    }
}
