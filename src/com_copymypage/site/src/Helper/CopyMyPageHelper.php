<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
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
     * @return  bool  True if the request is for com_copymypage & onepage view, otherwise false.
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
}
