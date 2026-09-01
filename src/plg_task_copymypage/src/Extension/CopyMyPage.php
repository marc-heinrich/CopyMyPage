<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Task.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Plugin\Task\CopyMyPage\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\CopyMyPage\Site\Service\PaymentReconciliationService;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;

/**
 * Scheduled reconciliation for abandoned CopyMyPage payment bookings.
 *
 * @since  0.0.19
 */
final class CopyMyPage extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /**
     * @var array<string, array<string, string>>
     * @since  0.0.19
     */
    private const TASKS_MAP = [
        'copymypage.reconcile_payments' => [
            'langConstPrefix' => 'PLG_TASK_COPYMYPAGE',
            'method'          => 'reconcilePayments',
            'form'            => 'reconcilePayments',
        ],
    ];

    /**
     * @var bool
     * @since  0.0.19
     */
    protected $autoloadLanguage = true;

    public function __construct(
        array $config,
        private readonly PaymentReconciliationService $paymentReconciliation
    ) {
        parent::__construct($config);
    }

    /**
     * @return array<string, string>
     *
     * @since   0.0.19
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * @since   0.0.19
     */
    private function reconcilePayments(ExecuteTaskEvent $event): int
    {
        $params         = $event->getArgument('params');
        $timeoutMinutes = (int) ($params->timeout_minutes ?? 60);
        $batchSize      = (int) ($params->batch_size ?? 50);
        $dryRun         = (bool) ($params->dry_run ?? false);

        try {
            $report = $this->paymentReconciliation->reconcilePending(
                $timeoutMinutes,
                $batchSize,
                $dryRun
            );
        } catch (\Throwable) {
            $this->logTask(Text::_('PLG_TASK_COPYMYPAGE_LOG_FAILURE'), 'error');

            return Status::KNOCKOUT;
        }

        $this->logTask(
            Text::sprintf(
                'PLG_TASK_COPYMYPAGE_LOG_SUMMARY',
                (int) $report['scanned'],
                (int) $report['released'],
                (int) $report['repaired'],
                (int) $report['manualReview'],
                (int) $report['skipped'],
                (int) $report['errors'],
                Text::_($report['dryRun'] ? 'JYES' : 'JNO')
            )
        );

        if ($report['manualReviewIds'] !== []) {
            $this->logTask(
                Text::sprintf(
                    'PLG_TASK_COPYMYPAGE_LOG_MANUAL_IDS',
                    implode(', ', array_map('intval', $report['manualReviewIds']))
                ),
                'warning'
            );
        }

        if ($report['errorIds'] !== []) {
            $this->logTask(
                Text::sprintf(
                    'PLG_TASK_COPYMYPAGE_LOG_ERROR_IDS',
                    implode(', ', array_map('intval', $report['errorIds']))
                ),
                'error'
            );
        }

        return (int) $report['errors'] > 0 ? Status::KNOCKOUT : Status::OK;
    }
}
