<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.2
 */

namespace Joomla\Component\CopyMyPage\Site\View\Onepage;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Registry\Registry;

/**
 * HtmlView class for the Onepage view.
 *
 * This view is a lightweight shell that prepares the
 * document metadata while all visible content is rendered
 * via modules in the template.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Component and menu parameters.
     *
     * @var  Registry|null
     */
    protected ?Registry $params = null;

    /**
     * Displays the Onepage view.
     *
     * @param   string|null  $tpl  The name of the layout file to parse.
     *
     * @return  void
     */
    public function display($tpl = null): void
    {
        $app          = Factory::getApplication();
        $this->params = $app->getParams();

        $this->prepareDocument();

        parent::display($tpl);
    }

    /**
     * Prepares the document metadata for the Onepage view.
     *
     * @return  void
     */
    protected function prepareDocument(): void
    {
        $app      = Factory::getApplication();
        $menu     = $app->getMenu()->getActive();
        $document = $this->document;

        // Title: prefer menu title, fallback to language string.
        if ($menu && $menu->title) {
            $title = $menu->title;
        } else {
            $title = Text::_('COM_COPYMYPAGE_ONEPAGE_TITLE');
        }

        $document->setTitle($title);

        if ($this->params instanceof Registry) {
            $metaDescription = (string) $this->params->get('meta_description', '');
            $metaKeywords    = (string) $this->params->get('meta_keywords', '');

            if ($metaDescription !== '') {
                $document->setDescription($metaDescription);
            }

            if ($metaKeywords !== '') {
                $document->setMetaData('keywords', $metaKeywords);
            }
        }
    }
}
