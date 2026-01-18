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
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetAttachBehaviorInterface;
use Joomla\CMS\WebAsset\WebAssetItem;

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

        // Encode parameters as JSON for JavaScript consumption.
        $jsonParams = json_encode($this->getParams()) ?: '{}';

        // Add the central event listener for DOMContentLoaded.
        $doc->addScriptDeclaration("
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize CopyMyPage and other third-party modules
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
     * Get the parameters for the CopyMyPage class.
     *
     * @return array The parameters for CopyMyPage.
     */
    private function getParams(): array
    {
        return [
            'pageWrapperClass'  => '.cmp-page',
            'backToTopID'       => '#back-top',
            'scrollTopPosition' => 100,
            'navbarClass'       => '.cmp-navbar',
            'mobileMenuClass'   => '.cmp-mobilemenu',
            'contactFormID'     => '#contact-form',
            'userDropdownHoldOpen' => [
                'desktopMin'      => 960,
                'closeDelay'      => 180,
                'closeOnNavClick' => true,
                'selectors'       => [
                    'root'     => '.cmp-module--navbar',
                    'user'     => '.cmp-navbar-user',
                    'toggle'   => 'a.cmp-navbar-icon',
                    'dropdown' => '.cmp-navbar-user .uk-navbar-dropdown',
                ],
            ],
        ];
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
