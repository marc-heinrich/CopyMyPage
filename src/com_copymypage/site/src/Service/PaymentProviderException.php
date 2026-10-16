<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

/** Sanitized error category; provider responses, OAuth tokens and HTTP headers stay private. */
final class PaymentProviderException extends \RuntimeException
{
    public function __construct(public readonly string $category, public readonly bool $uncertain = true)
    {
        parent::__construct('CopyMyPage payment provider operation requires recovery.');
    }
}
