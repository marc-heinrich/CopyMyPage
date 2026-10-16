<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use DigitalPeak\Component\DPCalendar\Administrator\Mail\MustacheMailTemplate;
use Joomla\CMS\Event\Mail\BeforeRenderingMailTemplateEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\Mail;

/** Request-local, booking-specific transport policy; Joomla registration/activation is untouched. */
final class PaymentMailScope
{
    private static ?self $active = null;
    private array $messages = [];

    private function __construct(private int $bookingId, private readonly int $ownerId, private readonly bool $collect)
    {
    }

    /** @return array{result: mixed, messages: array, bookingId: int} */
    public static function run(int $bookingId, int $ownerId, bool $collect, callable $operation): array
    {
        if (self::$active !== null || $bookingId < 0 || $ownerId < 0) {
            throw new \LogicException('Invalid or nested booking mail scope.');
        }
        PaymentIntegrationContract::assertQualified();
        $dispatcher = Factory::getApplication()->getDispatcher();
        $listener = [self::class, 'beforeRendering'];
        if ($dispatcher->hasListener($listener, 'onMailBeforeRendering')) {
            throw new \LogicException('Booking mail interception is already registered.');
        }
        // Scoped public event subscription: installed before native save, removed even on rollback.
        $dispatcher->addListener('onMailBeforeRendering', $listener, PHP_INT_MIN);
        $scope = new self($bookingId, $ownerId, $collect);
        self::$active = $scope;
        try {
            $result = $operation();

            return ['result' => $result, 'messages' => $scope->messages, 'bookingId' => $scope->bookingId];
        } finally {
            self::$active = null;
            $dispatcher->removeListener('onMailBeforeRendering', $listener);
        }
    }

    /** Joomla's documented template event occurs after alternative transport settings have been applied. */
    public static function beforeRendering(BeforeRenderingMailTemplateEvent $event): void
    {
        $scope = self::$active;
        $template = $event->getTemplate();
        if ($scope === null || !str_starts_with($event->getTemplateId(), 'com_dpcalendar.booking.')) {
            return;
        }
        if (!$template instanceof MustacheMailTemplate) {
            throw new \RuntimeException('Native booking mail no longer supports the qualified template contract.');
        }
        $data = $template->getTemplateData();
        $booking = $data['booking'] ?? null;
        if (!is_object($booking) || (int) ($booking->id ?? 0) < 1) {
            throw new \RuntimeException('Native booking mail lost its booking context.');
        }
        if ($scope->bookingId === 0) {
            // During native creation DPCalendar sets this server-owned input before constructing mail stages.
            $input = Factory::getApplication()->getInput();
            if ((int) $input->getInt('b_id', 0) !== (int) $booking->id || (int) $booking->user_id !== $scope->ownerId) {
                throw new \RuntimeException('Native creation mail has an unexpected owner or booking.');
            }
            $scope->bookingId = (int) $booking->id;
        }
        if ((int) $booking->id !== $scope->bookingId) {
            throw new \RuntimeException('Unexpected nested native booking mail.');
        }
        if ((int) $booking->user_id !== $scope->ownerId) {
            throw new \RuntimeException('Native booking mail owner changed.');
        }
        foreach ((array) ($data['bookings'] ?? [$booking]) as $item) {
            if ((int) ($item->id ?? 0) !== $scope->bookingId) {
                throw new \RuntimeException('Native notification mixes booking contexts.');
            }
        }
        $mailer = $template->getMailerInstance();
        if (!$mailer instanceof Mail) {
            throw new \RuntimeException('Native booking mail no longer supports the qualified transport contract.');
        }
        $mailer->isSMTP();
        $mailer->setSMTPInstance(new DeferredMailTransport($scope, $template->getTemplateId()));
    }

    public function accept(string $kind, string $sender, array $recipients, string $mime): void
    {
        if (self::$active !== $this || $this->bookingId < 1 || $recipients === [] || $sender === '' || $mime === '') {
            throw new \RuntimeException('Booking mail acceptance has no active scope.');
        }
        if ($this->collect) {
            $this->messages[] = ['kind' => $kind, 'sender' => $sender, 'recipients' => $recipients, 'mime' => $mime];
        }
    }
}
