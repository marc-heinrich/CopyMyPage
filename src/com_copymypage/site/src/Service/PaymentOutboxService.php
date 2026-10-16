<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/** Logical send deduplication. SMTP acceptance is not proof of physical exactly-once delivery. */
final class PaymentOutboxService
{
    private readonly \Closure $transportFactory;

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly CMSApplicationInterface $app,
        private readonly BookingIntegrityService $integrity,
        private readonly PaymentAttemptRepository $attempts,
        private readonly MailerFactoryInterface $mailerFactory,
        ?callable $transportFactory = null
    ) {
        $this->transportFactory = $transportFactory !== null ? \Closure::fromCallable($transportFactory) : fn (): Mail => $this->mailerFactory->createMailer();
    }

    /** Called inside the finalization transaction only after full native postconditions. */
    public function enqueue(array $inspection, array $messages, ?object $attempt): void
    {
        if (empty($inspection['valid']) || (int) $inspection['booking']->state !== 1) {
            throw new \DomainException('Booking mail requires a complete active booking.');
        }
        $booking = $inspection['booking'];
        $snapshot = hash('sha256', (string) $inspection['cart']->terms_snapshot);
        if ($attempt !== null) {
            if ((int) $attempt->booking_id !== (int) $booking->id || !PaymentProof::hasDurableCapture($attempt)
                || (int) $attempt->owner_id !== (int) $booking->user_id
                || (string) $attempt->provider_id !== (string) $booking->payment_provider
                || !hash_equals((string) $attempt->snapshot_hash, $snapshot)
                || !in_array($attempt->state, ['finalizing', 'finalized'], true)) {
                throw new \DomainException('Booking mail has no durable verified payment proof.');
            }
        } elseif ((float) $booking->price !== 0.0 || (string) $booking->payment_provider !== '') {
            throw new \DomainException('Paid booking mail cannot use a free-booking proof.');
        }
        foreach ($messages as $message) {
            $recipients = array_values(array_unique(array_map('strval', (array) ($message['recipients'] ?? []))));
            sort($recipients, SORT_STRING);
            if ($recipients === [] || !str_starts_with((string) ($message['kind'] ?? ''), 'com_dpcalendar.booking.')
                || strlen((string) $message['kind']) > 96 || empty($message['sender']) || empty($message['mime'])) {
                throw new \DomainException('Invalid deferred booking message.');
            }
            foreach (array_merge($recipients, [(string) $message['sender']]) as $address) {
                if (!filter_var($address, FILTER_VALIDATE_EMAIL) || strpbrk($address, "\r\n") !== false) {
                    throw new \DomainException('Invalid deferred booking mail envelope.');
                }
            }
            $key = hash('sha256', implode("\0", [(string) $booking->id, $attempt !== null ? (string) $attempt->capture_id : 'free:' . $snapshot, (string) $message['kind'], implode(',', $recipients)]));
            $mime = preg_replace('/^Message-ID:[^\r\n]*(?:\r?\n[ \t][^\r\n]*)*/mi', 'Message-ID: <cmp-' . $key . '@copymypage.invalid>', (string) $message['mime'], 1);
            $values = [
                'dedupe_key' => $key, 'booking_id' => (int) $booking->id, 'attempt_id' => $attempt !== null ? (string) $attempt->id : null,
                'snapshot_hash' => $snapshot, 'kind' => (string) $message['kind'], 'state' => 'ready',
                'sender' => (string) $message['sender'], 'recipients' => json_encode($recipients, JSON_THROW_ON_ERROR),
                'mime' => $mime, 'created' => PaymentAttemptRepository::now(), 'modified' => PaymentAttemptRepository::now(),
            ];
            $query = $this->db->getQuery(true)->insert($this->db->quoteName('#__copymypage_mail_outbox'))
                ->columns(array_map($this->db->quoteName(...), array_keys($values)));
            $bindings = [];
            foreach ($values as $name => $value) {
                $bindings[] = $value === null ? 'NULL' : ':' . $name;
                if ($value !== null) {
                    $query->bind(':' . $name, $values[$name], is_int($value) ? ParameterType::INTEGER : ParameterType::STRING);
                }
            }
            $query->values(implode(',', $bindings));
            // A unique logical job survives retries; never overwrite a sent/unknown payload or state.
            $query->setQuery((string) $query . ' ON DUPLICATE KEY UPDATE dedupe_key = dedupe_key');
            $this->db->setQuery($query)->execute();
        }
    }

    /** Commit the send lease before I/O. A lost worker becomes unknown, never automatically redeliverable. */
    public function dispatchBooking(int $bookingId, int $limit = 20): array
    {
        $report = ['sent' => 0, 'retryable' => 0, 'unknown' => 0, 'skipped' => 0];
        if ($bookingId < 1) {
            return $report;
        }
        $expired = PaymentAttemptRepository::now();
        $query = $this->db->getQuery(true)->update('#__copymypage_mail_outbox')
            ->set("state = 'unknown'")->set("last_error = 'worker_interrupted'")->set('modified = :now')
            ->where('booking_id = :booking')->where("state = 'sending'")->where('lease_expires < :expired')
            ->bind(':now', $expired)->bind(':expired', $expired)->bind(':booking', $bookingId, ParameterType::INTEGER);
        $this->db->setQuery($query)->execute();
        $report['unknown'] += $this->db->getAffectedRows();
        $ids = $this->db->setQuery('SELECT id FROM #__copymypage_mail_outbox WHERE booking_id = ' . $bookingId
            . " AND state IN ('ready','retryable') ORDER BY id", 0, min(100, max(1, $limit)))->loadColumn();
        foreach ($ids as $id) {
            $row = $this->claim((int) $id);
            if ($row === null) {
                ++$report['skipped'];
                continue;
            }
            $outcome = $this->send($row);
            ++$report[$outcome];
        }

        return $report;
    }

    private function claim(int $id): ?object
    {
        $this->db->transactionStart();
        try {
            $row = $this->db->setQuery('SELECT * FROM #__copymypage_mail_outbox WHERE id = ' . $id . ' FOR UPDATE')->loadObject();
            if (!$row || !in_array($row->state, ['ready','retryable'], true)) {
                $this->db->transactionCommit();
                return null;
            }
            $lease = PaymentAttemptRepository::uuid();
            $expires = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+120 seconds')->format('Y-m-d H:i:s.u');
            $now = PaymentAttemptRepository::now();
            $query = $this->db->getQuery(true)->update('#__copymypage_mail_outbox')
                ->set("state = 'sending'")->set('lease_id = :lease')->set('lease_expires = :expires')
                ->set('attempts = attempts + 1')->set('modified = :now')
                ->where('id = :id')->where("state IN ('ready','retryable')")
                ->bind(':lease', $lease)->bind(':expires', $expires)->bind(':now', $now)->bind(':id', $id, ParameterType::INTEGER);
            $this->db->setQuery($query)->execute();
            if ($this->db->getAffectedRows() !== 1) {
                $this->db->transactionRollback();
                return null;
            }
            $this->db->transactionCommit();
            $row->lease_id = $lease;

            return $row;
        } catch (\Throwable $error) {
            $this->db->transactionRollback();
            throw $error;
        }
    }

    private function send(object $row): string
    {
        $dataStarted = false;
        $accepted = false;
        $smtp = null;
        try {
            $inspection = $this->integrity->inspect((int) $row->booking_id);
            if (!$inspection['valid'] || (int) $inspection['booking']->state !== 1
                || !hash_equals((string) $row->snapshot_hash, hash('sha256', (string) $inspection['cart']->terms_snapshot))) {
                $this->finish($row, 'unknown', 'booking_no_longer_sendable');
                return 'unknown';
            }
            if ($row->attempt_id !== null) {
                $attempt = $this->attempts->getForBooking((int) $row->booking_id);
                if (!$attempt || !hash_equals((string) $row->attempt_id, (string) $attempt->id) || !$this->attempts->isFinalized((int) $row->booking_id)) {
                    $this->finish($row, 'unknown', 'payment_proof_unavailable');
                    return 'unknown';
                }
            } elseif ((float) $inspection['booking']->price !== 0.0 || (string) $inspection['booking']->payment_provider !== '') {
                $this->finish($row, 'unknown', 'free_proof_changed');
                return 'unknown';
            }
            if (!$this->app->get('mailonline', 1)) {
                $this->finish($row, 'retryable', 'mail_disabled');
                return 'retryable';
            }
            $mailer = ($this->transportFactory)();
            if (!$mailer instanceof Mail || $mailer->Mailer !== 'smtp') {
                // PHP mail()/sendmail() cannot classify an accepted-but-lost delivery acknowledgement.
                $this->finish($row, 'retryable', 'smtp_transport_required');
                return 'retryable';
            }
            $mailer->SMTPDebug = 0;
            $mailer->Timeout = 20;
            if (!$mailer->smtpConnect()) {
                $this->finish($row, 'retryable', 'smtp_connect_failed');
                return 'retryable';
            }
            $smtp = $mailer->getSMTPInstance();
            if (!$smtp->mail((string) $row->sender)) {
                $this->finish($row, 'retryable', 'smtp_sender_rejected');
                return 'retryable';
            }
            $recipients = json_decode((string) $row->recipients, true, 512, JSON_THROW_ON_ERROR);
            foreach ($recipients as $recipient) {
                if (!$smtp->recipient($recipient)) {
                    $this->finish($row, 'retryable', 'smtp_recipient_rejected');
                    return 'retryable';
                }
            }
            $dataStarted = true;
            $accepted = (bool) $smtp->data((string) $row->mime);
            $state = $accepted ? 'sent' : 'unknown';
            $this->finish($row, $state, $accepted ? null : 'smtp_data_outcome_unknown');

            return $state;
        } catch (\Throwable) {
            $state = $dataStarted ? 'unknown' : 'retryable';
            $this->finish($row, $state, $accepted ? 'smtp_accepted_local_ack_failed' : ($dataStarted ? 'smtp_delivery_unknown' : 'smtp_before_data_failed'));

            return $state;
        } finally {
            if ($smtp !== null) {
                try { $smtp->quit(); } catch (\Throwable) {}
                try { $smtp->close(); } catch (\Throwable) {}
            }
        }
    }

    private function finish(object $row, string $state, ?string $reason): void
    {
        $id = (int) $row->id;
        $lease = (string) $row->lease_id;
        $now = PaymentAttemptRepository::now();
        $query = $this->db->getQuery(true)->update('#__copymypage_mail_outbox')
            ->set('state = :state')->set('modified = :now')
            ->where('id = :id')->where("state = 'sending'")->where('lease_id = :lease')
            ->bind(':state', $state)->bind(':now', $now)->bind(':id', $id, ParameterType::INTEGER)->bind(':lease', $lease);
        if ($reason === null) {
            $query->set('last_error = NULL');
        } else {
            $query->set('last_error = :reason')->bind(':reason', $reason);
        }
        if ($state === 'sent') {
            $query->set('sent_at = :sent')->bind(':sent', $now);
        }
        $this->db->setQuery($query)->execute();
        if ($this->db->getAffectedRows() !== 1) {
            throw new \RuntimeException('Outbox delivery state could not be acknowledged.');
        }
    }
}
