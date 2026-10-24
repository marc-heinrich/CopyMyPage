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

    /**
     * Recover persisted paid-booking jobs independently of payment finalization.
     * A five-minute retry interval uses the existing modified timestamp; no schema migration.
     * The job limit is hard. The time budget stops new work, never interrupts an SMTP outcome.
     */
    public function recoverPending(int $limit = 5, bool $dryRun = true): array
    {
        $limit = min(20, max(1, $limit));
        $deadline = hrtime(true) + 30_000_000_000;
        $now = PaymentAttemptRepository::now();
        $retryBefore = (new \DateTimeImmutable($now, new \DateTimeZone('UTC')))
            ->modify('-5 minutes')->format('Y-m-d H:i:s.u');
        $query = $this->db->getQuery(true)->select('id, booking_id')->from('#__copymypage_mail_outbox')
            ->where('attempt_id IS NOT NULL')
            ->where("(state = 'ready' OR (state = 'retryable' AND modified <= :retry)"
                . " OR (state = 'sending' AND lease_expires < :now))")
            ->bind(':retry', $retryBefore)->bind(':now', $now)->order('modified, id');
        $jobs = $this->db->setQuery($query, 0, $limit)->loadObjectList();
        $report = ['dryRun' => $dryRun, 'selected' => count($jobs), 'processed' => 0,
            'sent' => 0, 'retryable' => 0, 'unknown' => 0, 'skipped' => 0, 'errors' => 0,
            'timeLimitReached' => false];

        if (!$dryRun) {
            foreach ($jobs as $job) {
                if (hrtime(true) >= $deadline) {
                    $report['timeLimitReached'] = true;
                    break;
                }
                try {
                    // Exact job scope prevents a booking with many messages exceeding the batch limit.
                    $result = $this->dispatchBooking((int) $job->booking_id, 1, (int) $job->id, $retryBefore);
                    foreach (['sent', 'retryable', 'unknown', 'skipped'] as $key) {
                        $report[$key] += $result[$key];
                    }
                } catch (\Throwable) {
                    // Never expose transport exceptions or reset an uncertain delivery to ready.
                    ++$report['errors'];
                    try {
                        $id = (int) $job->id;
                        $modified = PaymentAttemptRepository::now();
                        $query = $this->db->getQuery(true)->update('#__copymypage_mail_outbox')
                            ->set("state = 'retryable'")->set("last_error = 'recovery_failed'")
                            ->set('modified = :modified')->where('id = :id')
                            ->where("(state = 'ready' OR (state = 'retryable' AND modified <= :retry))")
                            ->bind(':modified', $modified)->bind(':id', $id, ParameterType::INTEGER)
                            ->bind(':retry', $retryBefore);
                        // Only provably unsent states may be deferred. Sending/sent/unknown stay untouched.
                        $this->db->setQuery($query)->execute();
                    } catch (\Throwable) {
                        // A database outage is reported, never worked around by an unleased send.
                    }
                }
                ++$report['processed'];
            }
        }

        $now = PaymentAttemptRepository::now();
        $query = $this->db->getQuery(true)
            ->select("COALESCE(SUM(state = 'ready'), 0) AS ready")
            ->select("COALESCE(SUM(state = 'retryable' AND modified <= :retryDue), 0) AS retryDue")
            ->select("COALESCE(SUM(state = 'retryable' AND modified > :retryWait), 0) AS notDue")
            ->select("COALESCE(SUM(state = 'unknown'), 0) AS manualReview")
            ->select("COALESCE(SUM(state = 'sending' AND lease_expires < :expired), 0) AS expiredSending")
            ->select("COALESCE(SUM(state = 'sending' AND (lease_expires IS NULL OR lease_expires >= :active)), 0) AS sending")
            ->from('#__copymypage_mail_outbox')->where('attempt_id IS NOT NULL')
            ->bind(':retryDue', $retryBefore)->bind(':retryWait', $retryBefore)
            ->bind(':expired', $now)->bind(':active', $now);
        $backlog = array_map('intval', $this->db->setQuery($query)->loadAssoc());
        $report['backlog'] = $backlog;
        $report['notDue'] = $backlog['notDue'];
        $report['held'] = $backlog['manualReview'] + $backlog['sending'];
        $report['critical'] = $backlog['ready'] + $backlog['retryDue']
            + $backlog['expiredSending'] + $backlog['manualReview'];

        return $report;
    }

    /** Commit the send lease before I/O. A lost worker becomes unknown, never automatically redeliverable. */
    public function dispatchBooking(int $bookingId, int $limit = 20, ?int $jobId = null, ?string $retryBefore = null): array
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
        if ($jobId !== null) {
            $query->where('id = :job')->bind(':job', $jobId, ParameterType::INTEGER);
        }
        $this->db->setQuery($query)->execute();
        $report['unknown'] += $this->db->getAffectedRows();
        $query = $this->db->getQuery(true)->select('id')->from('#__copymypage_mail_outbox')
            ->where('booking_id = :booking')->bind(':booking', $bookingId, ParameterType::INTEGER)
            ->where("state IN ('ready','retryable')")->order('id');
        if ($jobId !== null) {
            $query->where('id = :job')->bind(':job', $jobId, ParameterType::INTEGER);
        }
        if ($retryBefore !== null) {
            $query->where("(state = 'ready' OR modified <= :retry)")->bind(':retry', $retryBefore);
        }
        $ids = $this->db->setQuery($query, 0, min(100, max(1, $limit)))->loadColumn();
        if ($jobId !== null && $ids === [] && $report['unknown'] === 0) {
            ++$report['skipped'];
        }
        foreach ($ids as $id) {
            $row = $this->claim((int) $id, $retryBefore);
            if ($row === null) {
                ++$report['skipped'];
                continue;
            }
            $outcome = $this->send($row);
            ++$report[$outcome];
        }

        return $report;
    }

    private function claim(int $id, ?string $retryBefore = null): ?object
    {
        $this->db->transactionStart();
        try {
            $row = $this->db->setQuery('SELECT * FROM #__copymypage_mail_outbox WHERE id = ' . $id . ' FOR UPDATE')->loadObject();
            if (!$row || !in_array($row->state, ['ready','retryable'], true)
                || ($row->state === 'retryable' && $retryBefore !== null && $row->modified > $retryBefore)) {
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
            $attempt = null;
            if ($row->attempt_id !== null) {
                $attempt = $this->attempts->getForBooking((int) $row->booking_id);
                $booking = $inspection['booking'];
                if (!$attempt || !hash_equals((string) $row->attempt_id, (string) $attempt->id)
                    || $attempt->state !== 'finalized' || empty($attempt->finalized_at)
                    || !PaymentProof::hasDurableCapture($attempt)
                    || (int) $attempt->cart_id !== (int) $inspection['cart']->id
                    || (int) $attempt->owner_id !== (int) $booking->user_id
                    || !hash_equals((string) $attempt->snapshot_hash, (string) $row->snapshot_hash)
                    || !hash_equals((string) $attempt->provider_id, (string) $booking->payment_provider)
                    || !hash_equals((string) $attempt->expected_currency, (string) $booking->currency)
                    || !hash_equals((string) $attempt->expected_amount, PaymentAmount::fromBooking($booking))
                    || !hash_equals((string) $attempt->capture_id, (string) $booking->transaction_id)) {
                    $this->finish($row, 'unknown', 'payment_proof_unavailable');
                    return 'unknown';
                }
            } elseif ((float) $inspection['booking']->price !== 0.0 || (string) $inspection['booking']->payment_provider !== '') {
                $this->finish($row, 'unknown', 'free_proof_changed');
                return 'unknown';
            }
            // Recheck the persisted logical envelope; never derive new recipients during recovery.
            try {
                $recipients = json_decode((string) $row->recipients, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($recipients) || $recipients === [] || !array_is_list($recipients)
                    || !str_starts_with((string) $row->kind, 'com_dpcalendar.booking.')
                    || strlen((string) $row->kind) > 96 || (string) $row->mime === '') {
                    throw new \DomainException('Invalid persisted envelope.');
                }
                foreach (array_merge($recipients, [$row->sender]) as $address) {
                    if (!is_string($address) || !filter_var($address, FILTER_VALIDATE_EMAIL)
                        || strpbrk($address, "\r\n") !== false) {
                        throw new \DomainException('Invalid persisted address.');
                    }
                }
                $recipients = array_values(array_unique($recipients));
                sort($recipients, SORT_STRING);
                $key = hash('sha256', implode("\0", [(string) $row->booking_id,
                    $attempt !== null ? (string) $attempt->capture_id : 'free:' . $row->snapshot_hash,
                    (string) $row->kind, implode(',', $recipients)]));
                if (!hash_equals((string) $row->dedupe_key, $key)) {
                    throw new \DomainException('Persisted message binding changed.');
                }
            } catch (\Throwable) {
                $this->finish($row, 'unknown', 'message_binding_invalid');
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
