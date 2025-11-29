<?php
/**
 * @package     Joomla.Libraries
 * @subpackage  WebAssetItem
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.3
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
 *
 * @since 0.0.3
 */
final class CopyMyPageAssetItem extends WebAssetItem implements WebAssetAttachBehaviorInterface
{
    /**
     * The ID or selector for the back-to-top button.
     * 
     * @var string
     * 
     * This will eventually be stored as a component parameter in the database.
     * @since 0.0.3
     */
    protected $backToTopID = '#back-top';

    /**
     * The class for the navbar element.
     * 
     * @var string
     * 
     * This will eventually be stored as a component parameter in the database.
     * @since 0.0.3
     */
    protected $navbarClass = '.cmp-navbar';

    /**
     * The class for the mobile menu element.
     * 
     * @var string
     * 
     * This will eventually be stored as a component parameter in the database.
     * @since 0.0.3
     */
    protected $mobileMenuClass = '.cmp-mobilemenu';

    /**
     * The class for the page wrapper element.
     * 
     * @var string
     * 
     * This will eventually be stored as a component parameter in the database.
     * @since 0.0.3
     */
    protected $pageWrapperClass = '.cmp-page';

    /**
     * The ID of the form element.
     * 
     * @var string
     * 
     * This will eventually be stored as a component parameter in the database.
     * @since 0.0.3
     */
    protected $formID = '#contact-form';

    /**
     * The scroll position at which the back-to-top button appears.
     * Default value is 20px.
     * 
     * @var int
     * 
     * This will eventually be stored as a component parameter in the database.
     * @since 0.0.3
     */
    protected $scrollTopPosition = 100;

    /**
     * Prepare the parameters and initialize the CopyMyPage JS.
     * 
     * @param Document $doc The document object
     */
    public function onAttachCallback(Document $doc): void
    {
        // Add language strings for error messages.
        $this->addLanguageStrings();

        // Prepare the parameters as an associative array.
        $params = $this->getParams();

        // Encode parameters to JSON.
        $jsonParams = json_encode($params);

        // Initialize CopyMyPage JS.
        $doc->addScriptDeclaration("
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof window.CopyMyPage !== 'undefined') {
                    const copyMyPage = new window.CopyMyPage($jsonParams);
                    copyMyPage.init();
                } else {
                    const errorMessage = Joomla.Text._('TPL_COPYMYPAGE_JS_ERROR_NOT_DEFINED');
                    console.error(errorMessage);
                }
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
            'backToTopID'      => $this->backToTopID,
            'navbarClass'      => $this->navbarClass,
            'mobileMenuClass'  => $this->mobileMenuClass,
            'pageWrapperClass' => $this->pageWrapperClass,
            'formID'           => $this->formID,
            'scrollTopPosition'=> $this->scrollTopPosition,
        ];
    }
}
