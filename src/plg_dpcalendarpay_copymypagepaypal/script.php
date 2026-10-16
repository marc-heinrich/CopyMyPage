<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\CopyMyPage\Administrator\Service\PaymentSchema;
use Joomla\Component\CopyMyPage\Site\Service\PaymentIntegrationContract;
use Joomla\Database\DatabaseInterface;

class PlgDpcalendarpayCopymypagepaypalInstallerScript
{
    public function preflight(string $type, $adapter): bool
    {
        if ($type === 'uninstall') {
            return true;
        }
        if (!is_file(JPATH_ADMINISTRATOR . '/components/com_copymypage/src/Service/PaymentSchema.php')
            || !is_file(JPATH_SITE . '/components/com_copymypage/src/Service/PaymentIntegrationContract.php')) {
            Factory::getApplication()->enqueueMessage('CopyMyPage payment services must be installed first.', 'error');
            return false;
        }
        return true;
    }

    public function postflight(string $type, $adapter): bool
    {
        if (!in_array($type, ['install','update','discover_install'], true)) {
            return true;
        }
        try {
            require_once JPATH_ADMINISTRATOR . '/components/com_copymypage/src/Service/PaymentSchema.php';
            require_once JPATH_SITE . '/components/com_copymypage/src/Service/PaymentIntegrationContract.php';
            PaymentIntegrationContract::assertQualified();
            (new PaymentSchema(Factory::getContainer()->get(DatabaseInterface::class)))->ensure();
        } catch (\Throwable) {
            Factory::getApplication()->enqueueMessage('CopyMyPage payment schema/integration verification failed.', 'error');
            return false;
        }
        return true;
    }
}
