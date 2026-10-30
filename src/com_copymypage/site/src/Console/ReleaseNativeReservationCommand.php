<?php
namespace Joomla\Component\CopyMyPage\Site\Console;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\CopyMyPage\Site\Service\NativePayPalOrderStatus;
use Joomla\Component\CopyMyPage\Site\Service\ReservationCleanupService;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** OS-authorised Joomla CLI command; dry-run by default, no new web mutation endpoint. */
final class ReleaseNativeReservationCommand extends AbstractCommand
{
    protected static $defaultName = 'copymypage:reservation:release';

    protected function configure(): void
    {
        $this->setDescription('Verify a terminal native PayPal Sandbox order and release its reservation.');
        foreach (['booking', 'provider', 'order', 'merchant', 'actor', 'case'] as $option) {
            $this->addOption($option, null, InputOption::VALUE_REQUIRED, 'Explicit ' . $option . ' binding (required).');
        }
        $this->addOption('execute', null, InputOption::VALUE_NONE, 'Commit the release and audit; otherwise dry-run.');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        try {
            foreach (['booking', 'provider', 'order', 'merchant', 'actor', 'case'] as $option) {
                $value = $input->getOption($option);
                if (!\is_string($value) || $value === '') {
                    throw new \DomainException('All explicit reservation/audit options are required.');
                }
            }
            foreach (['booking', 'actor'] as $option) {
                if (!preg_match('/^[1-9][0-9]*$/D', $input->getOption($option))) {
                    throw new \DomainException('Positive booking and administrator IDs are required.');
                }
            }
            $container = Factory::getContainer();
            $actor = $container->get(UserFactoryInterface::class)->loadUserById((int) $input->getOption('actor'));
            $result = $container->get(ReservationCleanupService::class)->releaseExcludedNative(
                (int) $input->getOption('booking'), $input->getOption('provider'), $input->getOption('order'),
                $input->getOption('merchant'), $actor, $input->getOption('case'),
                $container->get(NativePayPalOrderStatus::class),
                !$input->getOption('execute')
            );
            $output->writeln(json_encode($result, JSON_THROW_ON_ERROR));
            return 0;
        } catch (\DomainException $error) {
            $output->writeln($error->getMessage());
            return 1;
        } catch (\Throwable) {
            $output->writeln('Native reservation operation failed. Review the reservation and audit before retrying.');
            return 1;
        }
    }
}
