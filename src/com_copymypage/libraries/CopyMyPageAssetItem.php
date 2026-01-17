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
     * Prepare the parameters and initialize the CopyMyPage JS.
     *
     * @param  Document  $doc  The document object.
     */
    public function onAttachCallback(Document $doc): void
    {
        // Add necessary language strings for error handling.
        $this->addLanguageStrings();

        // Encode parameters as JSON for JavaScript consumption.
        $jsonParams = json_encode($this->getParams()) ?: '{}';

        // Add script declaration to initialize CopyMyPage on DOMContentLoaded.
        $doc->addScriptDeclaration("
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof window.CopyMyPage !== 'undefined') {
                    const copyMyPage = new window.CopyMyPage($jsonParams);
                    copyMyPage.init();
                } else {
                    console.error(Joomla.Text._('TPL_COPYMYPAGE_JS_ERROR_NOT_DEFINED'));
                }
            });
        ");
    }

    /**
     * Adds necessary language strings for error handling.
     */
    private function addLanguageStrings(): void
    {
        // Load required language strings for CopyMyPage JS, alphabetically.
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
        // Hardcoded defaults for now; later sourced from com_copymypage (DB) to keep all selectors/IDs centralized.
        return [
            // Page wrapper selector.
            'pageWrapperClass'  => '.cmp-page',

            // Back to top button configuration.
            'backToTopID'       => '#back-top',
            'scrollTopPosition' => 100,

            // Navbar and mobile menu selectors.
            'navbarClass'       => '.cmp-navbar',
            'mobileMenuClass'   => '.cmp-mobilemenu',
            
            // Form IDs.
            'contactFormID'     => '#contact-form',            

            // Desktop user dropdown: keep-open behavior (UIkit drop) configuration.
            'userDropdownHoldOpen' => [
                'desktopMin'      => 960,
                'closeDelay'      => 180,
                'closeOnNavClick' => true,
                'selectors'       => [
                    'root'     => '.cmp-module--navbar',
                    'user'     => '.cmp-navbar-user',
                    'toggle'   => 'a.cmp-navbar-icon',
                    'dropdown' => '.uk-navbar-dropdown',
                ],
            ],
        ];
    }
}
