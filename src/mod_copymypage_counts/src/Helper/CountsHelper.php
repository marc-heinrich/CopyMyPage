<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.16
 */

namespace Joomla\Module\CopyMyPage\Counts\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;

/**
 * Helper class for the CopyMyPage Counts module.
 */
final class CountsHelper
{
    /**
     * Supported UIkit icon names for the counters.
     *
     * @var array<int, string>
     */
    private const ICONS = ['users', 'calendar', 'star', 'happy', 'world', 'bolt'];

    /**
     * Parameter groups supported by the counts layout.
     *
     * @var array<int, string>
     */
    private const COUNTER_KEYS = ['members', 'founded', 'events', 'guests'];

    /**
     * Get the configured counters for the active counts layout.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $layout  Validated layout key.
     *
     * @return  array<int, object>|null
     */
    public function getItems(array $cfg, string $layout): ?array
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);
        $items        = [];

        foreach (self::COUNTER_KEYS as $key) {
            $label = trim(self::cfgString($layoutConfig, $key . '_label'));

            if ($label === '') {
                continue;
            }

            $items[] = (object) [
                'key'      => $key,
                'label'    => Text::_($label),
                'value'    => self::cfgInt($layoutConfig, $key . '_value', 0, 0),
                'start'    => 0,
                'duration' => self::cfgInt($layoutConfig, $key . '_duration', 0, 0, 30),
                'icon'     => $this->normalizeIcon(self::cfgString($layoutConfig, $key . '_icon')),
            ];
        }

        return $items !== [] ? $items : null;
    }

    /**
     * Extract the layout-specific parameter subset from the flat module config.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $layout  Validated layout key.
     *
     * @return  array<string, mixed>
     */
    public static function getLayoutConfig(array $cfg, string $layout): array
    {
        $layout = strtolower(trim($layout));

        if ($layout === '') {
            return [];
        }

        return self::extractPrefixedConfig($cfg, $layout . '_');
    }

    /**
     * Typed array getter (string) for template-side layout config usage.
     *
     * @param   array<string, mixed>  $cfg      Config bucket.
     * @param   string                $key      Array key.
     * @param   string                $default  Default value.
     *
     * @return  string
     */
    public static function cfgString(array $cfg, string $key, string $default = ''): string
    {
        return CopyMyPageHelper::cfgString($cfg, $key, $default);
    }

    /**
     * Typed array getter (int) for template-side layout config usage.
     *
     * @param   array<string, mixed>  $cfg      Config bucket.
     * @param   string                $key      Array key.
     * @param   int                   $default  Default value.
     * @param   int|null              $min      Optional minimum.
     * @param   int|null              $max      Optional maximum.
     *
     * @return  int
     */
    public static function cfgInt(array $cfg, string $key, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        return CopyMyPageHelper::cfgInt($cfg, $key, $default, $min, $max);
    }

    /**
     * Normalize a configured UIkit icon token.
     *
     * @param   string  $icon  Raw configured icon.
     *
     * @return  string
     */
    private function normalizeIcon(string $icon): string
    {
        $icon = strtolower(trim($icon));

        return \in_array($icon, self::ICONS, true) ? $icon : 'star';
    }

    /**
     * Extract a prefixed subset from a flat config array.
     *
     * @param   array<string, mixed>  $cfg          Flat config array.
     * @param   string                $prefix       Prefix to match.
     * @param   bool                  $stripPrefix  Remove the prefix from returned keys.
     *
     * @return  array<string, mixed>
     */
    private static function extractPrefixedConfig(array $cfg, string $prefix, bool $stripPrefix = true): array
    {
        $prefix = trim($prefix);

        if ($prefix === '') {
            return [];
        }

        $result = [];

        foreach ($cfg as $key => $value) {
            if (!\is_string($key) || !str_starts_with($key, $prefix)) {
                continue;
            }

            $targetKey = $stripPrefix ? substr($key, strlen($prefix)) : $key;

            if (!\is_string($targetKey) || $targetKey === '') {
                continue;
            }

            $result[$targetKey] = $value;
        }

        return $result;
    }
}
