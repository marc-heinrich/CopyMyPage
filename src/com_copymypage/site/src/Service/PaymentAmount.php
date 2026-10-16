<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

/** Canonical provider money; comparisons never use a floating-point tolerance. */
final class PaymentAmount
{
    private const CURRENCIES = ['AUD','BRL','CAD','CNY','CZK','DKK','EUR','HKD','HUF','ILS','JPY','MYR','MXN','TWD','NZD','NOK','PHP','PLN','GBP','SGD','SEK','CHF','THB','USD'];

    public static function normalize(string $amount, string $currency): string
    {
        if (!in_array($currency, self::CURRENCIES, true) || !preg_match('/^([0-9]{1,13})(?:\.([0-9]{1,2}))?$/D', $amount, $parts)) {
            throw new \DomainException('Invalid provider amount or currency.');
        }
        $whole = ltrim($parts[1], '0') ?: '0';
        $fraction = str_pad($parts[2] ?? '', 2, '0');
        if (in_array($currency, ['HUF','JPY','TWD'], true) && $fraction !== '00') {
            throw new \DomainException('Provider currency does not accept fractional amounts.');
        }

        return $whole . '.' . $fraction;
    }

    /** Native prices are stored as DOUBLE; freeze their existing two-decimal contract once. */
    public static function fromBooking(object $booking): string
    {
        $value = (float) ($booking->price ?? -1);
        if (!is_finite($value) || $value <= 0 || $value > 9999999999999.99) {
            throw new \DomainException('Invalid managed payment total.');
        }

        return self::normalize(number_format($value, 2, '.', ''), (string) $booking->currency);
    }

    public static function forApi(string $amount, string $currency): string
    {
        $amount = self::normalize($amount, $currency);

        return in_array($currency, ['HUF','JPY','TWD'], true) ? explode('.', $amount)[0] : $amount;
    }
}
