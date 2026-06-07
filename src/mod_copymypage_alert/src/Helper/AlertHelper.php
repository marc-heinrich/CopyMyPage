<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.14
 */

namespace Joomla\Module\CopyMyPage\Alert\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;

/**
 * Helper class for the CopyMyPage Alert module.
 */
final class AlertHelper
{
    /**
     * Dispatcher-provided fallback layout for the current module context.
     *
     * @var string
     */
    private string $defaultLayout = '';

    /**
     * Dispatcher-provided layout prefix for the current system slot.
     *
     * @var string
     */
    private string $layoutPrefix = '';

    /**
     * Supported UI tone keys for the alert bar.
     *
     * @var array<int, string>
     */
    private const STYLE_KEYS = ['default', 'primary', 'success', 'warning', 'danger', 'maintenance'];

    /**
     * Supported color configuration modes.
     *
     * @var array<int, string>
     */
    private const COLOR_MODES = ['preset', 'custom'];

    /**
     * Supported display modes.
     *
     * @var array<int, string>
     */
    private const DISPLAY_MODES = ['static', 'ticker'];

    /**
     * Supported dismissal persistence modes.
     *
     * @var array<int, string>
     */
    private const DISMISS_BEHAVIORS = ['none', 'session', 'cookie'];

    /**
     * Set the layout context resolved by the module dispatcher.
     *
     * @param   string  $defaultLayout  Validated fallback layout key.
     * @param   string  $layoutPrefix   Expected layout prefix for the slot.
     *
     * @return  void
     */
    public function setLayoutContext(string $defaultLayout, string $layoutPrefix = ''): void
    {
        $this->defaultLayout = self::normalizeLayoutKey($defaultLayout);
        $this->layoutPrefix  = self::normalizeLayoutKey($layoutPrefix);
    }

    /**
     * Get the prepared alert payload for the active layout.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $layout  Validated layout key.
     *
     * @return  object
     */
    public function getNotice(array $cfg, string $layout): object
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);
        $style        = $this->resolveOption(
            self::cfgString($layoutConfig, 'style', 'warning'),
            self::STYLE_KEYS,
            'warning'
        );
        $colorMode    = $this->resolveOption(
            self::cfgString($layoutConfig, 'colorMode', 'preset'),
            self::COLOR_MODES,
            'preset'
        );
        $backgroundColor = $colorMode === 'custom'
            ? self::normalizeHexColor(self::cfgString($layoutConfig, 'backgroundColor', ''))
            : '';
        $textColor       = $colorMode === 'custom'
            ? self::normalizeHexColor(self::cfgString($layoutConfig, 'textColor', ''))
            : '';
        $label        = trim(self::cfgString($layoutConfig, 'label', Text::_('MOD_COPYMYPAGE_ALERT_DEFAULT_LABEL')));
        $message      = trim(self::cfgString($layoutConfig, 'message', Text::_('MOD_COPYMYPAGE_ALERT_DEFAULT_MESSAGE')));
        $ctaUrl       = trim(self::cfgString($layoutConfig, 'ctaUrl', ''));
        $ctaLabel     = trim(self::cfgString($layoutConfig, 'ctaLabel', ''));
        $ctaTarget    = $this->resolveOption(
            self::cfgString($layoutConfig, 'ctaTarget', '_self'),
            ['_self', '_blank'],
            '_self'
        );
        $displayMode  = $this->resolveOption(
            self::cfgString($layoutConfig, 'displayMode', 'static'),
            self::DISPLAY_MODES,
            'static'
        );
        $dismissBehavior = $this->resolveOption(
            self::cfgString($layoutConfig, 'dismissBehavior', 'session'),
            self::DISMISS_BEHAVIORS,
            'session'
        );
        $dismissKey = trim(self::cfgString($layoutConfig, 'dismissKey', ''));

        if ($message === '') {
            $message = Text::_('MOD_COPYMYPAGE_ALERT_DEFAULT_MESSAGE');
        }

        if ($ctaUrl !== '' && $ctaLabel === '') {
            $ctaLabel = Text::_('MOD_COPYMYPAGE_ALERT_DEFAULT_CTA');
        }

        return (object) [
            'style'           => $style,
            'colorMode'       => $colorMode,
            'backgroundColor' => $backgroundColor,
            'textColor'       => $textColor,
            'label'           => $label,
            'message'         => $message,
            'ctaLabel'        => $ctaLabel,
            'ctaUrl'          => $ctaUrl,
            'ctaTarget'       => $ctaTarget,
            'displayMode'     => $displayMode,
            'tickerDuration'  => self::cfgInt($layoutConfig, 'tickerDuration', 28, 8, 120),
            'showClose'       => self::cfgBool($layoutConfig, 'showClose', true),
            'dismissBehavior' => $dismissBehavior,
            'dismissKey'      => $this->resolveDismissKey(
                $dismissKey,
                $style,
                $colorMode,
                $backgroundColor,
                $textColor,
                $label,
                $message,
                $ctaUrl
            ),
            'cookieDays'      => self::cfgInt($layoutConfig, 'cookieDays', 7, 1, 365),
        ];
    }

    /**
     * Extract the layout-specific parameter subset from the flat module config.
     *
     * Example:
     * layout "alert_bar" turns "alert_bar_message" into "message".
     *
     * @param   array<string, mixed>  $cfg     Flat config array.
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
     * Typed array getter (bool) for template-side layout config usage.
     *
     * @param   array<string, mixed>  $cfg      Config bucket.
     * @param   string                $key      Array key.
     * @param   bool                  $default  Default value.
     *
     * @return  bool
     */
    public static function cfgBool(array $cfg, string $key, bool $default = false): bool
    {
        return CopyMyPageHelper::cfgBool($cfg, $key, $default);
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
     * Resolve an option value against a whitelist.
     *
     * @param   string              $value    Raw value.
     * @param   array<int, string>  $allowed  Allowed values.
     * @param   string              $default  Fallback value.
     *
     * @return  string
     */
    private function resolveOption(string $value, array $allowed, string $default): string
    {
        $value = strtolower(trim($value));

        return \in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * Build a stable dismissal key, auto-versioned by alert content when no key is configured.
     *
     * @param   string  $configuredKey  Optional backend-provided key.
     * @param   string  $style          Alert style token.
     * @param   string  $colorMode      Alert color mode token.
     * @param   string  $backgroundColor Custom background color.
     * @param   string  $textColor      Custom text color.
     * @param   string  $label          Alert label.
     * @param   string  $message        Alert message.
     * @param   string  $ctaUrl         Optional action URL.
     *
     * @return  string
     */
    private function resolveDismissKey(
        string $configuredKey,
        string $style,
        string $colorMode,
        string $backgroundColor,
        string $textColor,
        string $label,
        string $message,
        string $ctaUrl
    ): string {
        $configuredKey = trim($configuredKey);

        if ($configuredKey !== '') {
            return $configuredKey;
        }

        $colorSignature = $colorMode === 'custom'
            ? '|' . $colorMode . '|' . $backgroundColor . '|' . $textColor
            : '';

        return 'alert-' . substr(sha1($style . $colorSignature . '|' . $label . '|' . strip_tags($message) . '|' . $ctaUrl), 0, 12);
    }

    /**
     * Normalize backend color values to a safe hex color string.
     *
     * @param   string  $value  Raw backend color value.
     *
     * @return  string
     */
    private static function normalizeHexColor(string $value): string
    {
        $value = strtolower(trim($value));

        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : '';
    }

    /**
     * Normalize a layout or prefix token.
     *
     * @param   string  $layout  Raw layout token.
     *
     * @return  string
     */
    private static function normalizeLayoutKey(string $layout): string
    {
        return strtolower(trim($layout));
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
