<?php

namespace Joomla\Plugin\Task\CopyMyPage\Rule;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\Component\CopyMyPage\Site\Service\PaymentReservationPolicy;
use Joomla\Registry\Registry;

/** Reject fractional and coerced values before they can become a ledger snapshot. */
final class PaymentreservationRule extends FormRule
{
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        return PaymentReservationPolicy::validateMinutes($value) !== null;
    }
}
