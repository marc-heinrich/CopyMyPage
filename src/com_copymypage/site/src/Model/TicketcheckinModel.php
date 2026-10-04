<?php
namespace Joomla\Component\CopyMyPage\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Component\CopyMyPage\Site\Service\TicketCheckinService;
use Joomla\Component\CopyMyPage\Site\Service\TicketCheckinResultStore;

/** Presentation consumes the authoritative service, never a parallel ticket query. */
final class TicketcheckinModel extends BaseDatabaseModel
{
    public function getItem(): array
    {
        $app = Factory::getApplication();
        if ($app->getIdentity()->guest) {
            return ['status' => 'login_required'];
        }
        $service = Factory::getContainer()->get(TicketCheckinService::class);
        if (!$service->isAuthorised()) {
            return ['status' => 'access_denied'];
        }
        $rawUid = $app->getInput()->get('uid', null, 'raw');
        $context = $service->getContext($rawUid);
        // Re-read current authoritative state before consuming a bound receipt.
        $result = (new TicketCheckinResultStore($app->getSession()))->consume(
            (int) $app->getIdentity()->id, TicketCheckinService::normaliseUid($rawUid),
            $app->getInput()->getString('result', '')
        );
        $status = $context['status'];
        if (in_array($status, ['ready', 'already_checked_in'], true) && $result !== null) {
            $status = $result['status'];
        } elseif (($context['reason'] ?? '') === 'invalid_uid') {
            $status = 'invalid';
        }
        return ['status' => $status, 'context' => $context,
            'uid' => TicketCheckinService::normaliseUid($rawUid),
            'canCheckin' => $context['status'] === 'ready' && $status === 'ready'];
    }
}
