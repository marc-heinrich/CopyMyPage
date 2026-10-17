<?php

namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

/** One administrator source; each attempt retains its own immutable snapshot. */
final class PaymentReservationPolicy
{
    public const DEFAULT_MINUTES = 60;
    public const MIN_MINUTES = 15;
    public const MAX_MINUTES = 240;

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    public static function validateMinutes(mixed $value): ?int
    {
        if ((!is_int($value) && !is_string($value)) || preg_match('/^[1-9][0-9]{0,2}$/D', (string) $value) !== 1) {
            return null;
        }

        $minutes = (int) $value;

        return $minutes >= self::MIN_MINUTES && $minutes <= self::MAX_MINUTES ? $minutes : null;
    }

    public function minutesForNewAttempt(): ?int
    {
        try {
            $type = 'copymypage.reconcile_payments';
            $query = $this->db->getQuery(true)->select('params')->from('#__scheduler_tasks')
                ->where('type = :type')->where('state IN (0, 1)')->bind(':type', $type);
            $tasks = $this->db->setQuery($query, 0, 2)->loadObjectList();
            if ($tasks === []) {
                return self::DEFAULT_MINUTES;
            }
            if (count($tasks) !== 1) {
                throw new \DomainException('multiple_tasks');
            }

            $params = json_decode((string) $tasks[0]->params, false, 64, JSON_THROW_ON_ERROR);
            if ($params === []) {
                return self::DEFAULT_MINUTES;
            }
            if (!$params instanceof \stdClass) {
                throw new \DomainException('invalid_parameters');
            }
            if (!property_exists($params, 'payment_reservation_minutes')) {
                return self::DEFAULT_MINUTES;
            }

            $minutes = self::validateMinutes($params->payment_reservation_minutes);
            if ($minutes === null) {
                throw new \DomainException('invalid_duration');
            }

            return $minutes;
        } catch (\Throwable) {
            // Do not log task JSON, database errors or payment capabilities.
            Log::add('CopyMyPage payment reservation configuration is unavailable or ambiguous; automatic deadline release is disabled for the new attempt.', Log::WARNING, 'com_copymypage');

            return null;
        }
    }

    public static function expiresAt(string $started, int $minutes): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $started, new \DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d H:i:s.u') !== $started || self::validateMinutes($minutes) === null) {
            throw new \DomainException('Invalid payment reservation timestamp.');
        }

        return $date->modify('+' . $minutes . ' minutes')->format('Y-m-d H:i:s.u');
    }

    /** Legacy rows never acquire an invented deadline, including old prepared rows. */
    public static function state(object $attempt, ?string $now = null): string
    {
        $minutes = $attempt->reservation_minutes ?? null;
        $expires = $attempt->reservation_expires_at ?? null;
        if ($minutes === null && $expires === null) {
            return 'legacy';
        }
        if (self::validateMinutes($minutes) === null) {
            return 'invalid';
        }
        if (empty($attempt->create_started)) {
            return $expires === null ? 'unstarted' : 'invalid';
        }
        try {
            if (!is_string($expires) || self::expiresAt((string) $attempt->create_started, (int) $minutes) !== $expires) {
                return 'invalid';
            }
        } catch (\DomainException) {
            return 'invalid';
        }

        return $expires <= ($now ?? PaymentAttemptRepository::now()) ? 'expired' : 'active';
    }

    public static function hasLocalClosure(object $attempt): bool
    {
        $evidence = json_decode((string) ($attempt->provider_evidence ?? ''), true);

        return is_array($evidence) && array_key_exists('local_closure', $evidence);
    }

    public static function isClosed(object $attempt): bool
    {
        return in_array($attempt->state, ['finalized', 'cancelled', 'failed', 'manual'], true)
            || self::hasLocalClosure($attempt);
    }
}
