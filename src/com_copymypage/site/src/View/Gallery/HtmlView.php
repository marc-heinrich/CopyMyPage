<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.9
 */

namespace Joomla\Component\CopyMyPage\Site\View\Gallery;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

/**
 * HTML view for the CopyMyPage gallery detail page.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Active gallery item.
     *
     * @var object|null
     */
    protected ?object $item = null;

    /**
     * Component state object.
     *
     * @var object|null
     */
    protected ?object $state = null;

    /**
     * Component params.
     *
     * @var Registry|null
     */
    protected ?Registry $params = null;

    /**
     * Sigplus module params.
     *
     * @var Registry|null
     */
    protected ?Registry $itemParams = null;

    /**
     * Warning messages for the current gallery page.
     *
     * @var array<int, array<string, string>>
     */
    protected array $warnings = [];

    /**
     * Back link to the onepage.
     *
     * @var string
     */
    protected string $backUrl = '';

    /**
     * Gallery page headline.
     *
     * @var string
     */
    protected string $headline = '';

    /**
     * Optional gallery summary text.
     *
     * @var string
     */
    protected string $summary = '';

    /**
     * Number of images in the gallery.
     *
     * @var int
     */
    protected int $imageCount = 0;

    /**
     * Displays the gallery view.
     *
     * @param   string|null  $tpl  Template name.
     *
     * @return  void
     */
    public function display($tpl = null): void
    {
        $this->item  = $this->get('Item');
        $this->state = $this->get('State');

        if (\count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        if (!\is_object($this->item)) {
            throw new GenericDataException(Text::_('COM_COPYMYPAGE_VIEW_GALLERY_ERROR_NOT_FOUND'), 404);
        }

        $this->params = $this->resolveParams();
        $this->itemParams = new Registry((string) ($this->item->params ?? ''));
        $this->imageCount = $this->resolveImageCount();
        $this->headline = $this->resolveHeadline();
        $this->summary = trim((string) $this->itemParams->get('caption_summary_template', ''));
        $this->backUrl = Route::link('site', 'index.php?option=com_copymypage&view=onepage') . '#gallery';

        $sigplusPlugin = null;
        $model = $this->getModel();

        if (\is_object($model) && \method_exists($model, 'getSigplusPlugin')) {
            $sigplusPlugin = $model->getSigplusPlugin();
        }

        if ($sigplusPlugin === null) {
            $this->warnings[] = [
                'info' => Text::_('COM_COPYMYPAGE_VIEW_GALLERY_MSG_NOSIGPLUS_INFO'),
                'desc' => Text::_('COM_COPYMYPAGE_VIEW_GALLERY_MSG_NOSIGPLUS_DESC'),
            ];
        } elseif ((int) ($sigplusPlugin->enabled ?? 0) !== 1) {
            $pluginLink = Route::link(
                'administrator',
                'index.php?option=com_plugins&task=plugin.edit&extension_id=' . (int) $sigplusPlugin->id
            );

            $this->warnings[] = [
                'info' => Text::_('COM_COPYMYPAGE_VIEW_GALLERY_MSG_SIGPLUS_DISABLED_INFO'),
                'desc' => Text::sprintf('COM_COPYMYPAGE_VIEW_GALLERY_MSG_SIGPLUS_DISABLED_DESC', $pluginLink),
            ];
        } elseif (trim((string) $this->itemParams->get('source', '')) === '') {
            $this->warnings[] = [
                'info' => Text::_('COM_COPYMYPAGE_VIEW_GALLERY_MSG_NO_SOURCE_INFO'),
                'desc' => Text::_('COM_COPYMYPAGE_VIEW_GALLERY_MSG_NO_SOURCE_DESC'),
            ];
        }

        $this->prepareDocument();

        parent::display($tpl);
    }

    /**
     * Prepares the document metadata.
     *
     * @return  void
     */
    protected function prepareDocument(): void
    {
        $document = $this->document;
        $title = $this->headline !== '' ? $this->headline : Text::_('COM_COPYMYPAGE_VIEW_GALLERY_TITLE');

        $document->setTitle($title);

        if ($this->summary !== '') {
            $document->setDescription($this->summary);

            return;
        }

        $document->setDescription(
            Text::sprintf('COM_COPYMYPAGE_VIEW_GALLERY_META_DESCRIPTION', $this->imageCount)
        );
    }

    /**
     * Resolves component params from the current state object.
     *
     * @return  Registry
     */
    private function resolveParams(): Registry
    {
        if (\is_object($this->state) && \property_exists($this->state, 'params') && $this->state->params instanceof Registry) {
            return $this->state->params;
        }

        if (\is_object($this->state) && \method_exists($this->state, 'get')) {
            $params = $this->state->get('params');

            if ($params instanceof Registry) {
                return $params;
            }
        }

        return new Registry();
    }

    /**
     * Resolves the gallery image count from the view state.
     *
     * @return  int
     */
    private function resolveImageCount(): int
    {
        if (\is_object($this->state) && \method_exists($this->state, 'get')) {
            return (int) $this->state->get('item.count', 0);
        }

        return 0;
    }

    /**
     * Resolves the gallery page headline with sensible fallbacks.
     *
     * @return  string
     */
    private function resolveHeadline(): string
    {
        $headline = trim((string) $this->itemParams?->get('caption_title_template', ''));

        if ($headline !== '') {
            return $headline;
        }

        $headline = trim((string) ($this->item->title ?? ''));

        if ($headline !== '') {
            return $headline;
        }

        return Text::_('COM_COPYMYPAGE_VIEW_GALLERY_TITLE');
    }
}
