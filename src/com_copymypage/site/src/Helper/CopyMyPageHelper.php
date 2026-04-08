<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.10
 */

namespace Joomla\Component\CopyMyPage\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Language\Language;

/**
 * Static helper for CopyMyPage view related utilities.
 */
abstract class CopyMyPageHelper
{
    /**
     * Load additional language packs used by shared CopyMyPage UI elements.
     *
     * @param   Language  $language  The active site language object.
     *
     * @return  void
     */
    public static function loadSharedUiLanguages(Language $language): void
    {
        $language->load('com_users', JPATH_SITE, null, true);
        $language->load('com_users', JPATH_ADMINISTRATOR, null, true);
        $language->load('com_contact', JPATH_SITE, null, true);
        $language->load('com_contact', JPATH_ADMINISTRATOR, null, true);
    }

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
     * Resolve the fixed CopyMyPage slot that should be treated as active for the current view.
     *
     * @param   string  $option  The component option (e.g. 'com_copymypage').
     * @param   string  $view    The view name (e.g. 'gallery').
     *
     * @return  string  The active slot token (for example 'gallery'), or an empty string.
     */
    public static function resolveActiveSlot(string $option = '', string $view = ''): string
    {
        return match (true) {
            $option === 'com_copymypage' && $view === 'gallery' => 'gallery',
            default => '',
        };
    }

    /**
     * Extract a normalized hash token from a menu link.
     *
     * Supports plain hash links like "#gallery" and routed URLs like "/de/#gallery".
     *
     * @param   string  $link  The menu link to inspect.
     *
     * @return  string  The normalized hash token without the leading "#".
     */
    public static function extractHashToken(string $link): string
    {
        $link = trim($link);

        if ($link === '') {
            return '';
        }

        $hashPosition = strpos($link, '#');

        if ($hashPosition === false) {
            return '';
        }

        $token = substr($link, $hashPosition + 1);

        return strtolower(trim($token, " \t\n\r\0\x0B/"));
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
     * Add a custom meta tag with a property attribute if no matching property exists yet.
     *
     * @param   HtmlDocument  $document  The target HTML document.
     * @param   string        $property  The meta property name (for example "og:type").
     * @param   string        $content   The meta content value.
     *
     * @return  void
     */
    public static function addMetaPropertyIfMissing(HtmlDocument $document, string $property, string $content): void
    {
        $property = trim($property);
        $content  = trim($content);

        if ($property === '' || $content === '') {
            return;
        }

        $headData = $document->getHeadData();

        foreach (($headData['custom'] ?? []) as $tag) {
            if (preg_match('/^<meta\b.*\bproperty="' . preg_quote($property, '/') . '".*>$/', $tag)) {
                return;
            }
        }

        $document->addCustomTag(
            '<meta property="' . htmlspecialchars($property, ENT_QUOTES, 'UTF-8')
            . '" content="' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '" />'
        );
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
        } elseif (\is_string($value) && preg_match('/^-?\d+(?:\.\d+)?/', trim($value), $matches) === 1) {
            // Accept legacy stored values like "50px" or "80%" and normalize them to integers.
            $int = (int) $matches[0];
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
