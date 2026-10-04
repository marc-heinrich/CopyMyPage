<?php
namespace Joomla\Component\CopyMyPage\Site\View\Ticketcheckin;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\Component\CopyMyPage\Site\Helper\TicketCheckinReturn;

final class HtmlView extends BaseHtmlView
{
    protected array $item = [];

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        if (strtoupper($app->getInput()->getMethod()) !== 'GET') {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 405);
        }
        $app->allowCache(false);
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0', true);
        $app->setHeader('Referrer-Policy', 'no-referrer', true);
        $this->document->setTitle(Text::_('COM_COPYMYPAGE_TICKET_CHECKIN_TITLE'));
        $this->document->setMetaData('robots', 'noindex, nofollow');
        $this->item = $this->getModel()->getItem();
        if ($this->item['status'] === 'login_required') {
            $return = TicketCheckinReturn::context($app->getInput()->get('uid', null, 'raw'),
                $app->getInput()->getString('result', ''));
            $app->redirect(Route::_('index.php?option=com_users&view=login&return='
                . rawurlencode(base64_encode($return)), false), 303);
            $app->close();
        }
        if ($this->item['status'] === 'access_denied') {
            $app->setHeader('status', '403 Forbidden', true);
        }
        parent::display($tpl);
    }
}
