<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Site\View\Basket;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\CopyMyPage\Site\Service\TicketReservationService;

/**
 * HTML view for the temporary ticket basket drawer and fallback page.
 */
final class HtmlView extends BaseHtmlView
{
    /** @var array<string, mixed> */
    protected array $cart = [];

    /** @var array<string, string> */
    protected array $markupAttributes = [];

    /** @var array<string, string> */
    protected array $formFieldNames = [];

    /**
     * Render the current session's temporary ticket basket.
     *
     * @param   string|null  $tpl  Template name.
     */
    public function display($tpl = null): void
    {
        $this->preparePrivateDocument();

        try {
            $service                = Factory::getContainer()->get(TicketReservationService::class);
            $this->cart             = $service->getCartState();
            $this->markupAttributes = $service->getMarkupAttributes();
            $this->formFieldNames    = $service->getFormFieldNames();
        } catch (\Throwable $exception) {
            Log::add(
                'CopyMyPage ticket basket view: ' . $exception->getMessage(),
                Log::ERROR,
                'com_copymypage'
            );
            throw new GenericDataException(
                Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_REQUEST'),
                500,
                $exception
            );
        }

        $wa = $this->document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_copymypage');
        $wa->useScript('copymypage.ticket-cart');

        parent::display($tpl);
    }

    /**
     * Keep session-specific basket output out of search indexes and caches.
     */
    private function preparePrivateDocument(): void
    {
        $app = Factory::getApplication();

        $app->allowCache(false);
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0', true);
        $app->setHeader('Pragma', 'no-cache', true);

        $this->document->setMetaData('robots', 'noindex, nofollow');
        $this->document->setTitle(Text::_('COM_COPYMYPAGE_BASKET_TITLE'));
    }
}
