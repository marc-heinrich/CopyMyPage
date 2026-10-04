<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @license     GNU General Public License version 3 or later
 */

namespace Joomla\Component\CopyMyPage\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Component\CopyMyPage\Site\Service\TicketCheckinService;
use Joomla\Component\CopyMyPage\Site\Service\TicketCheckinResultStore;

/** Protected POST operation and the presentation bridge for existing ticket QR URLs. */
final class TicketcheckinController extends BaseController
{
    public function checkin(): void
    {
        $service = $this->authorisedService();
        if (strtoupper($this->input->getMethod()) !== 'POST') {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 405);
        }

        // Avoid Session::checkToken's expired/new-session redirect branch: missing
        // tokens must fail, even when a new session is used by a technical client.
        $token = Session::getFormToken();
        $bodyToken = $this->input->post->get($token, null, 'raw');
        $headerToken = $this->input->server->getString('HTTP_X_CSRF_TOKEN', '');
        if ((!\in_array($bodyToken, [1, '1'], true) && !hash_equals($token, $headerToken))
            || !Session::checkToken('post')) {
            throw new \RuntimeException(Text::_('JINVALID_TOKEN'), 403);
        }

        $rawUid = $this->input->post->get('uid', null, 'raw');
        $result = $service->checkin($rawUid);
        $uid = TicketCheckinService::normaliseUid($rawUid);
        $receipt = (new TicketCheckinResultStore($this->app->getSession()))->store(
            (int) $this->app->getIdentity()->id, $uid, $result
        );
        $url = Route::_('index.php?option=com_copymypage&task=ticketcheckin.context'
            . ($uid !== null ? '&uid=' . rawurlencode($uid) : '')
            . '&result=' . $receipt, false);
        $this->app->redirect($url, 303);
    }

    /** Keep the intercepted task route; render the normal read-only MVC view. */
    public function context(): void
    {
        if (strtoupper($this->input->getMethod()) !== 'GET') {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 405);
        }

        $this->input->set('view', 'ticketcheckin');
        $this->input->set('layout', 'default');
        parent::display(false);
    }

    private function authorisedService(): TicketCheckinService
    {
        $service = Factory::getContainer()->get(TicketCheckinService::class);
        if (!$service->isAuthorised()) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return $service;
    }
}
