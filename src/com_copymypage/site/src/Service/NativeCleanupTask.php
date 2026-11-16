<?php
namespace Joomla\Component\CopyMyPage\Site\Service;
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Scheduler\Scheduler;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Registry\Registry;

/** Supplies the stored task owner's real ACL identity to the unchanged native routine. */
class NativeCleanupTask extends \DigitalPeak\Plugin\Task\DPCalendar\Extension\DPCalendar
{
    public function deleteBookings(ExecuteTaskEvent $event): int
    {
        $db = $this->getDatabase();
        $task = $db->setQuery('SELECT id,type,state,created_by,locked,params FROM #__scheduler_tasks WHERE id='
            . $event->getTaskId())->loadObject();
        $subject = $event->getArgument('subject');
        if ($db !== Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class)
            || !$task || $task->type !== 'plg_task_dpcalendar_booking_deletions'
            || $task->type !== $event->getRoutineId() || (int) $task->state < 0
            || $task->locked === null || $task->locked !== $subject->get('locked')
            || (int) $task->created_by !== (int) $subject->get('created_by')
            || (new Registry($event->getArgument('params')))->toArray() !== json_decode($task->params, true)) {
            throw new \DomainException('Native cleanup requires the locked, unchanged Joomla scheduler task.');
        }
        $user = $this->getUserFactory()->loadUserById((int) $task->created_by);
        if ((int) $user->id !== (int) $task->created_by || !Scheduler::isAuthorizedToRun($task, $user)) {
            throw new \DomainException('The native cleanup task owner is no longer authorised to run the task.');
        }
        $cleanup = Factory::getContainer()->get(ReservationCleanupService::class);
        try {
            $exit = $cleanup->withNativeTaskUser($user, fn(): int => parent::deleteBookings($event), $task);
        } catch (\Throwable) {
            // Vendor/SQL exceptions may contain data. Never include them or a
            // previous exception in the scheduler's notification/output channel.
            $this->logTask('CMP native_cleanup_error: booking operation rolled back; manual review required. '
                . json_encode($cleanup->getNativeTaskReport(), JSON_THROW_ON_ERROR), 'error');
            return Status::KNOCKOUT;
        }
        $report = $cleanup->getNativeTaskReport();
        $this->logTask('CMP native_cleanup_summary: ' . json_encode($report, JSON_THROW_ON_ERROR),
            $report['held'] > 0 ? 'warning' : 'info');
        return $exit;
    }
}
