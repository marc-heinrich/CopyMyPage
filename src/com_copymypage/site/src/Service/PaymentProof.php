<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

/** Checks only authenticated server responses, never browser or webhook resource claims. */
final class PaymentProof
{
    public static function reference(object $attempt): string
    {
        return 'cmp:' . $attempt->booking_id . ':' . $attempt->id;
    }

    public static function invoice(object $attempt): string
    {
        return 'CMP-' . $attempt->booking_id . '-' . $attempt->id;
    }

    public static function verifyOrder(object $attempt, array $order): void
    {
        $units = $order['purchase_units'] ?? [];
        $unit = $units[0] ?? [];
        if (($order['id'] ?? '') !== $attempt->order_id || ($order['intent'] ?? '') !== 'CAPTURE'
            || count($units) !== 1 || ($unit['reference_id'] ?? '') !== $attempt->id
            || ($unit['custom_id'] ?? '') !== self::reference($attempt)
            || ($unit['invoice_id'] ?? '') !== self::invoice($attempt)
            || ($unit['payee']['merchant_id'] ?? '') !== $attempt->expected_payee
            || ($unit['amount']['currency_code'] ?? '') !== $attempt->expected_currency
            || PaymentAmount::normalize((string) ($unit['amount']['value'] ?? ''), $attempt->expected_currency) !== $attempt->expected_amount
            || !empty($unit['payments']['authorizations'])) {
            throw new \DomainException('PayPal order binding mismatch.');
        }
    }

    /** Capture representation can omit the purchase-unit amount/payee already verified before POST. */
    public static function verifyCapture(object $attempt, array $order): array
    {
        $unit = $order['purchase_units'][0] ?? [];
        $captures = $unit['payments']['captures'] ?? [];
        $capture = $captures[0] ?? [];
        if (($order['id'] ?? '') !== $attempt->order_id || ($order['status'] ?? '') !== 'COMPLETED'
            || count($order['purchase_units'] ?? []) !== 1 || count($captures) !== 1
            || ($unit['reference_id'] ?? '') !== $attempt->id
            || (isset($unit['custom_id']) && $unit['custom_id'] !== self::reference($attempt))
            || (isset($unit['invoice_id']) && $unit['invoice_id'] !== self::invoice($attempt))
            || (isset($unit['payee']['merchant_id']) && $unit['payee']['merchant_id'] !== $attempt->expected_payee)
            || ($capture['status'] ?? '') !== 'COMPLETED' || ($capture['final_capture'] ?? null) !== true
            || ($capture['amount']['currency_code'] ?? '') !== $attempt->expected_currency
            || PaymentAmount::normalize((string) ($capture['amount']['value'] ?? ''), $attempt->expected_currency) !== $attempt->expected_amount
            || (isset($capture['custom_id']) && $capture['custom_id'] !== self::reference($attempt))
            || (isset($capture['invoice_id']) && $capture['invoice_id'] !== self::invoice($attempt))
            || (!empty($attempt->capture_id) && $capture['id'] !== $attempt->capture_id)) {
            throw new \DomainException('PayPal capture proof mismatch.');
        }
        PayPalOrdersClient::checkId((string) ($capture['id'] ?? ''));

        return $capture;
    }

    /** Minimal financial evidence. No payer addresses, access tokens, links or capabilities. */
    public static function evidence(object $attempt, array $order, string $source, bool $verified): string
    {
        $unit = $order['purchase_units'][0] ?? [];
        $captures = [];
        foreach (array_slice((array) ($unit['payments']['captures'] ?? []), 0, 20) as $capture) {
            $captures[] = ['id' => (string) ($capture['id'] ?? ''), 'status' => (string) ($capture['status'] ?? ''),
                'amount' => (string) ($capture['amount']['value'] ?? ''), 'currency' => (string) ($capture['amount']['currency_code'] ?? ''),
                'final_capture' => ($capture['final_capture'] ?? null) === true, 'provider_time' => (string) ($capture['create_time'] ?? '')];
        }
        return json_encode(['verified' => $verified, 'source' => $source, 'attempt_id' => $attempt->id,
            'order_id' => (string) ($order['id'] ?? ''), 'status' => (string) ($order['status'] ?? ''),
            'reference_id' => (string) ($unit['reference_id'] ?? ''), 'payee' => (string) ($unit['payee']['merchant_id'] ?? $attempt->expected_payee),
            'captures' => $captures, 'observed_at' => PaymentAttemptRepository::now()], JSON_THROW_ON_ERROR);
    }

    public static function hasDurableCapture(object $attempt): bool
    {
        $evidence = json_decode((string) ($attempt->provider_evidence ?? ''), true);
        if (!is_array($evidence) || !is_array($evidence['captures'] ?? null)) {
            return false;
        }
        $capture = $evidence['captures'][0] ?? [];
        if (!is_array($capture)) {
            return false;
        }
        try {
            $amount = PaymentAmount::normalize((string) ($capture['amount'] ?? ''), (string) $attempt->expected_currency);
        } catch (\DomainException) {
            return false;
        }
        return ($attempt->provider_status ?? '') === 'COMPLETED' && !empty($attempt->captured_at)
            && !empty($attempt->capture_id) && ($evidence['verified'] ?? false) === true
            && ($evidence['attempt_id'] ?? '') === $attempt->id && ($evidence['order_id'] ?? '') === $attempt->order_id
            && ($evidence['reference_id'] ?? '') === $attempt->id && ($evidence['payee'] ?? '') === $attempt->expected_payee
            && count($evidence['captures'] ?? []) === 1 && ($capture['id'] ?? '') === $attempt->capture_id
            && ($capture['status'] ?? '') === 'COMPLETED' && ($capture['final_capture'] ?? false) === true
            && ($capture['currency'] ?? '') === $attempt->expected_currency
            && $amount === $attempt->expected_amount;
    }
}
