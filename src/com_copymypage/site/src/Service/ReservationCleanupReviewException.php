<?php

namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

/** An expected inconsistency: retain the entire reservation for manual review. */
final class ReservationCleanupReviewException extends \DomainException
{
    public function __construct(
        public readonly string $reason,
        public readonly ?int $eventId = null
    ) {
        parent::__construct($reason);
    }
}
