<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Site\View\Ticketselection;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\CopyMyPage\Site\Service\TicketReservationService;

/**
 * UIkit accordion entry point for a multi-event DPCalendar ticket cart.
 */
final class HtmlView extends BaseHtmlView
{
    /** @var array<int, array<string, mixed>> */
    protected array $events = [];

    /** @var array<string, mixed> */
    protected array $cart = [];

    /** @var array<string, string> */
    protected array $markupAttributes = [];

    /** @var array<string, string> */
    protected array $formFieldNames = [];

    protected int $selectedEventId = 0;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        try {
            $service = Factory::getContainer()->get(TicketReservationService::class);
            $state   = $service->getSelectionState(
                $app->getInput()->getInt('event_id', 0)
            );
        } catch (\Throwable $exception) {
            Log::add(
                'CopyMyPage ticket selection view: ' . $exception->getMessage(),
                Log::ERROR,
                'com_copymypage'
            );
            throw new GenericDataException(
                Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_REQUEST'),
                500,
                $exception
            );
        }

        $this->events           = (array) ($state['events'] ?? []);
        $this->cart             = (array) ($state['cart'] ?? []);
        $this->selectedEventId  = max(0, (int) ($state['selectedEventId'] ?? 0));
        $this->markupAttributes = $service->getMarkupAttributes();
        $this->formFieldNames    = $service->getFormFieldNames();

        $this->prepareDocument();

        $wa = $this->document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_copymypage');
        $wa->useScript('copymypage.ticket-cart');

        parent::display($tpl);
    }

    private function prepareDocument(): void
    {
        $app = Factory::getApplication();

        $app->allowCache(false);
        $app->setHeader(
            'Cache-Control',
            'no-store, no-cache, must-revalidate, private, max-age=0',
            true
        );
        $app->setHeader('Pragma', 'no-cache', true);

        $this->document->setTitle(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_TITLE'));
        $this->document->setDescription(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_META_DESCRIPTION'));
        $this->document->setMetaData('robots', 'noindex, nofollow');
    }
}
