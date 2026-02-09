<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.5
 */

namespace Joomla\Component\CopyMyPage\Site\Helper;

\defined('_JEXEC') or die;

/**
 * Static helper for CopyMyPage view related utilities.
 */
abstract class CopyMyPageHelper
{
    /**
     * Check whether the current request targets the CopyMyPage onepage view.
     *
     * @param   string  $option  The component option (e.g. 'com_copymypage').
     * @param   string  $view    The view name (e.g. 'onepage').
     *
     * @return  bool    True if the request is for com_copymypage & onepage view, otherwise false.
     */
    public static function isOnepage(string $option = '', string $view = ''): bool
    {
        return $option === 'com_copymypage' && $view === 'onepage';
    }

    /**
     * Convert a simple selector (".class" or "#id") into a plain token ("class" / "id").
     *
     * @param   string  $selector  The selector value.
     *
     * @return  string  The sanitized token (without leading "." or "#").
     */
    public static function selectorToToken(string $selector): string
    {
        $selector = trim($selector);

        if ($selector === '') {
            return '';
        }

        // Remove leading dot or hash.
        $token = ltrim($selector, '.#');

        // Keep only valid token chars.
        $token = preg_replace('/[^A-Za-z0-9_-]/', '', $token) ?? '';

        return $token;
    }

    /**
     * Normalize a value into a boolean (handles "1"/"0", 1/0, true/false, "true"/"false", "yes"/"no", etc.).
     *
     * @param   mixed  $value
     * @param   bool   $default
     *
     * @return  bool
     */
    public static function toBool(mixed $value, bool $default = false): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_int($value) || \is_float($value)) {
            return (int) $value === 1;
        }

        if (\is_string($value)) {
            $v = strtolower(trim($value));

            if ($v === '1' || $v === 'true' || $v === 'yes' || $v === 'on') {
                return true;
            }

            if ($v === '0' || $v === 'false' || $v === 'no' || $v === 'off' || $v === '') {
                return false;
            }
        }

        return $default;
    }

    /**
     * Normalize a value into an integer with optional bounds.
     *
     * @param   mixed      $value
     * @param   int        $default
     * @param   int|null   $min
     * @param   int|null   $max
     *
     * @return  int
     */
    public static function toInt(mixed $value, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        if (\is_int($value)) {
            $int = $value;
        } elseif (\is_numeric($value)) {
            $int = (int) $value;
        } else {
            $int = $default;
        }

        if ($min !== null && $int < $min) {
            $int = $min;
        }

        if ($max !== null && $int > $max) {
            $int = $max;
        }

        return $int;
    }

    /**
     * Normalize a value into a string.
     *
     * @param   mixed   $value
     * @param   string  $default
     *
     * @return  string
     */
    public static function toString(mixed $value, string $default = ''): string
    {
        if (\is_string($value)) {
            return $value;
        }

        if (\is_numeric($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * Typed array getter (string).
     *
     * @param   array<string, mixed> $cfg
     * @param   string               $key
     * @param   string               $default
     *
     * @return  string
     */
    public static function cfgString(array $cfg, string $key, string $default = ''): string
    {
        return self::toString($cfg[$key] ?? null, $default);
    }

    /**
     * Typed array getter (bool).
     *
     * @param   array<string, mixed> $cfg
     * @param   string               $key
     * @param   bool                 $default
     *
     * @return  bool
     */
    public static function cfgBool(array $cfg, string $key, bool $default = false): bool
    {
        return self::toBool($cfg[$key] ?? null, $default);
    }

    /**
     * Typed array getter (int) with optional bounds.
     *
     * @param   array<string, mixed> $cfg
     * @param   string               $key
     * @param   int                  $default
     * @param   int|null             $min
     * @param   int|null             $max
     *
     * @return  int
     */
    public static function cfgInt(array $cfg, string $key, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        return self::toInt($cfg[$key] ?? null, $default, $min, $max);
    }

    /**
     * Normalize a value into a CSS length token.
     *
     * Allowed units are px, rem, em, vw, vh, vmin, vmax and optionally %.
     * If a pure number is provided, it is interpreted as "%" (when allowed) or "px".
     *
     * @param   mixed   $value
     * @param   string  $default
     * @param   bool    $allowPercent
     *
     * @return  string
     */
    public static function toCssLength(mixed $value, string $default = '0px', bool $allowPercent = false): string
    {
        $raw = strtolower(trim(self::toString($value, '')));

        if ($raw === '') {
            return $default;
        }

        if (preg_match('/^\d+(?:\.\d+)?$/', $raw) === 1) {
            return $allowPercent ? ($raw . '%') : ($raw . 'px');
        }

        $pattern = $allowPercent
            ? '/^\d+(?:\.\d+)?(?:%|px|rem|em|vw|vh|vmin|vmax)$/'
            : '/^\d+(?:\.\d+)?(?:px|rem|em|vw|vh|vmin|vmax)$/';

        if (preg_match($pattern, $raw) !== 1) {
            return $default;
        }

        return $raw;
    }

    /**
     * Typed array getter (CSS length).
     *
     * @param   array<string, mixed> $cfg
     * @param   string               $key
     * @param   string               $default
     * @param   bool                 $allowPercent
     *
     * @return  string
     */
    public static function cfgCssLength(array $cfg, string $key, string $default = '0px', bool $allowPercent = false): string
    {
        return self::toCssLength($cfg[$key] ?? null, $default, $allowPercent);
    }
}
