<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.14
 */

namespace Joomla\Component\CopyMyPage\Site\Helper\Helpers;

\defined('_JEXEC') or die;

use Joomla\Registry\Registry as JoomlaRegistry;

/**
 * Builds template design-token overrides from CopyMyPage template params.
 */
final class TemplateTokenHelper
{
    /**
     * @var array<string, array{token: string, default: string, rgb?: string}>
     */
    private const DESIGN_COLOR_TOKENS = [
        'designColor' => [
            'token'   => '--cmp-color',
            'default' => '#04cbd0',
            'rgb'     => '--cmp-color-rgb',
        ],
        'designColorStrong' => [
            'token'   => '--cmp-color-strong',
            'default' => '#00979c',
        ],
        'designColorSoft' => [
            'token'   => '--cmp-color-soft',
            'default' => '#c4f4f5',
        ],
        'designColorLogoDark' => [
            'token'   => '--cmp-color-logo-dark',
            'default' => '#061225',
            'rgb'     => '--cmp-color-logo-dark-rgb',
        ],
        'designColorLogoLight' => [
            'token'   => '--cmp-color-logo-light',
            'default' => '#ffffff',
            'rgb'     => '--cmp-color-logo-light-rgb',
        ],
        'designColorSurface' => [
            'token'   => '--cmp-color-surface',
            'default' => '#f5f7fb',
            'rgb'     => '--cmp-color-surface-rgb',
        ],
        'designColorMuted' => [
            'token'   => '--cmp-color-muted',
            'default' => '#d5dde8',
        ],
        'designColorBackgroundDefault' => [
            'token'   => '--cmp-color-background-default',
            'default' => '#ffffff',
        ],
        'designColorTextDefault' => [
            'token'   => '--cmp-color-text-default',
            'default' => '#333333',
        ],
        'designColorTextMuted' => [
            'token'   => '--cmp-color-text-muted',
            'default' => '#6c757d',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const FONT_FAMILY_OPTIONS = [
        'system'      => 'var(--cmp-font-family-system)',
        'monaSans'    => 'var(--cmp-font-family-google-mona-sans), var(--cmp-font-family-system)',
        'openSans'    => 'var(--cmp-font-family-google-open-sans), var(--cmp-font-family-system)',
        'fingerPaint' => 'var(--cmp-font-family-google-finger-paint), var(--cmp-font-family-system)',
        'body'        => 'var(--cmp-font-family-body)',
    ];

    /**
     * @var array<string, array{token: string, default: string}>
     */
    private const TYPOGRAPHY_TOKENS = [
        'typographyBodyFont' => [
            'token'   => '--cmp-font-family-body',
            'default' => 'monaSans',
        ],
        'typographyUiFont' => [
            'token'   => '--cmp-font-family-ui',
            'default' => 'body',
        ],
        'typographyAccentFont' => [
            'token'   => '--cmp-font-family-display-accent',
            'default' => 'fingerPaint',
        ],
        'typographyAltFont' => [
            'token'   => '--cmp-font-family-body-alt',
            'default' => 'openSans',
        ],
    ];

    /**
     * Build the complete :root CSS block used by the template inline style.
     *
     * @param   JoomlaRegistry|array<string, mixed>  $params        Template style parameters.
     * @param   int                                  $headerOffset  Current header offset in pixels.
     *
     * @return  string
     */
    public function buildRootTokenStyle(JoomlaRegistry|array $params, int $headerOffset = 64): string
    {
        $rootTokenStyles = [
            '    /* copymypage tokens */',
            "    --cmp-header-offset: {$headerOffset}px;",
        ];

        foreach (self::TYPOGRAPHY_TOKENS as $paramName => $definition) {
            $fontKey = (string) $this->getParam($params, $paramName, $definition['default']);

            if (!isset(self::FONT_FAMILY_OPTIONS[$fontKey])) {
                $fontKey = $definition['default'];
            }

            $rootTokenStyles[] = '    ' . $definition['token'] . ': ' . self::FONT_FAMILY_OPTIONS[$fontKey] . ';';
        }

        foreach (self::DESIGN_COLOR_TOKENS as $paramName => $definition) {
            $color             = $this->normalizeHexColor($this->getParam($params, $paramName, $definition['default']), $definition['default']);
            $rootTokenStyles[] = '    ' . $definition['token'] . ': ' . $color . ';';

            if (isset($definition['rgb'])) {
                $rootTokenStyles[] = '    ' . $definition['rgb'] . ': ' . $this->hexToRgb($color) . ';';
            }
        }

        return ":root {\n" . implode("\n", $rootTokenStyles) . "\n}";
    }

    /**
     * @param   JoomlaRegistry|array<string, mixed>  $params
     * @param   string                               $key
     * @param   mixed                                $default
     *
     * @return  mixed
     */
    private function getParam(JoomlaRegistry|array $params, string $key, mixed $default): mixed
    {
        if ($params instanceof JoomlaRegistry) {
            return $params->get($key, $default);
        }

        return $params[$key] ?? $default;
    }

    /**
     * @param   mixed   $value
     * @param   string  $fallback
     *
     * @return  string
     */
    private function normalizeHexColor(mixed $value, string $fallback): string
    {
        $color = strtolower(trim((string) $value));

        if ($color === '') {
            $color = $fallback;
        }

        if ($color !== '' && $color[0] !== '#') {
            $color = '#' . $color;
        }

        if (!preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
            $color = $fallback;

            if ($color !== '' && $color[0] !== '#') {
                $color = '#' . $color;
            }
        }

        return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $color) ? strtolower($color) : '#000000';
    }

    /**
     * Convert a normalized HEX color to an RGB token value.
     *
     * @param   string  $color
     *
     * @return  string
     */
    private function hexToRgb(string $color): string
    {
        $hex = ltrim($color, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return implode(', ', [
            (string) hexdec(substr($hex, 0, 2)),
            (string) hexdec(substr($hex, 2, 2)),
            (string) hexdec(substr($hex, 4, 2)),
        ]);
    }
}
