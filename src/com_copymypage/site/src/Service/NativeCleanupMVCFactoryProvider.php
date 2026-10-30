<?php
namespace Joomla\Component\CopyMyPage\Site\Service;
\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Component\CopyMyPage\Site\Model\NativeCleanupBookingModel;
use Joomla\Component\CopyMyPage\Site\Model\NativeCleanupTicketModel;

/** Joomla's own service injection and class resolution; only two delete() wrappers differ. */
final class NativeCleanupMVCFactoryProvider extends \Joomla\CMS\Extension\Service\Provider\MVCFactory
{
    protected function createMVCFactory(): MVCFactoryInterface
    {
        return new class($this->getNamespace()) extends \Joomla\CMS\MVC\Factory\MVCFactory {
            public function createModel($name, $prefix = '', array $config = [])
            {
                $model = parent::createModel($name, $prefix, $config);
                $user = \Joomla\CMS\Factory::getContainer()->get(ReservationCleanupService::class)->getNativeTaskUser();
                // Includes DPCalendar's nested Tickets list model and its normal access filters.
                if ($model instanceof \Joomla\CMS\User\CurrentUserInterface && $user !== null) {
                    $model->setCurrentUser($user);
                }
                return $model;
            }

            protected function getClassName(string $suffix, string $prefix)
            {
                if (strtolower($prefix) === 'administrator') {
                    if ($suffix === 'Model\\BookingModel') { return NativeCleanupBookingModel::class; }
                    if ($suffix === 'Model\\TicketModel') { return NativeCleanupTicketModel::class; }
                }
                return parent::getClassName($suffix, $prefix);
            }
        };
    }
}
