<?php
namespace Joomla\Component\CopyMyPage\Site\Model;
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\CopyMyPage\Site\Service\ReservationCleanupService;

/** Wraps the original deletion, including all native ticket deletions, in one managed scope. */
class NativeCleanupBookingModel extends \DigitalPeak\Component\DPCalendar\Administrator\Model\BookingModel
{
    protected $name = 'booking';
    protected $option = 'com_dpcalendar';
    public function delete(&$pks)
    {
        if ($this->getDatabase() !== Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class)) {
            throw new \DomainException('Native deletion and seat release must use the same database connection.');
        }
        foreach (array_unique(array_map('intval', (array) $pks)) as $id) {
            $one = [$id];
            if (!Factory::getContainer()->get(ReservationCleanupService::class)->deleteNative(
                'booking', $id, fn() => parent::delete($one), $this->getCurrentUser())) {
                return false;
            }
        }
        return true;
    }
}
