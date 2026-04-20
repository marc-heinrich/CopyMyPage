<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.10
 */

namespace Joomla\Module\CopyMyPage\Hero\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;
use Joomla\Registry\Registry;

/**
 * Helper class to prepare hero data for the CopyMyPage Hero module.
 */
final class HeroHelper
{
    /**
     * Build Open Graph compatible tag data for the hero section.
     *
     * @param   Registry      $params  The module params.
     * @param   object|null   $module  The published module row.
     * @param   string        $slot    The active system slot.
     *
     * @return  array<string, string>
     */
    public function getOGTags(Registry $params, ?object $module = null, string $slot = ''): array
    {
        return [
            'slot'        => 'hero',
            'label'       => 'Hero',
            'title'       => 'Fernbreitenbach Helau',
            'description' => 'Willkommen auf der Website des Fernbreiterbacher Carneval-Vereins',
            'image'       => rtrim(Uri::root(), '/') . '/modules/mod_copymypage_hero/images/slide_1.jpg',
            'twitterCard' => 'summary_large_image',
        ];
    }

    /**
     * Get the slideshow items for the current hero output.
     *
     * Default slide content stays in the module so the template can render a full
     * fallback hero even before custom layout params are introduced.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $layout  Validated layout key.
     *
     * @return  array<int, object>
     */
    public function getSlides(array $cfg, string $layout): array
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);
        $basePath     = rtrim(Uri::root(true), '/') . '/modules/mod_copymypage_hero/images';
        $imageRoot    = JPATH_ROOT . '/modules/mod_copymypage_hero/images';
        $slides       = [];

        foreach ($this->getDefaultSlides() as $index => $slide) {
            $slideNumber  = $index + 1;
            $filename     = trim((string) ($slide['file'] ?? ''));
            $absolutePath = $imageRoot . '/' . $filename;

            if ($filename === '' || !is_file($absolutePath)) {
                continue;
            }

            $slides[] = (object) [
                'src'           => $basePath . '/' . $filename,
                'alt'           => trim(self::cfgString($layoutConfig, 'slide_' . $slideNumber . '_alt', (string) ($slide['alt'] ?? ''))),
                'headline'      => trim(self::cfgString($layoutConfig, 'slide_' . $slideNumber . '_headline', (string) ($slide['headline'] ?? ''))),
                'subline'       => trim(self::cfgString($layoutConfig, 'slide_' . $slideNumber . '_subline', (string) ($slide['subline'] ?? ''))),
                'isLazy'        => (bool) ($slide['isLazy'] ?? true),
                'fetchPriority' => trim((string) ($slide['fetchPriority'] ?? 'low')),
                'width'         => (int) ($slide['width'] ?? 0),
                'height'        => (int) ($slide['height'] ?? 0),
            ];
        }

        return $slides;
    }

    /**
     * Get the UIkit slideshow options for the current hero layout.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $layout  Validated layout key.
     *
     * @return  string
     */
    public function getSlideshowOptions(array $cfg, string $layout): string
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);
        $animation    = strtolower(trim(self::cfgString($layoutConfig, 'animation', 'fade')));
        $autoplay     = self::cfgBool($layoutConfig, 'autoplay', true) ? 'true' : 'false';
        $draggable    = self::cfgBool($layoutConfig, 'draggable', true) ? 'true' : 'false';

        if (!\in_array($animation, ['fade', 'slide', 'push', 'pull'], true)) {
            $animation = 'fade';
        }

        return 'ratio: false; animation: ' . $animation . '; autoplay: ' . $autoplay . '; draggable: ' . $draggable;
    }

    /**
     * Extract the layout-specific parameter subset from the flat module config.
     *
     * Example:
     * layout "hero_slideshow" turns
     * "hero_slideshow_autoplay" into "autoplay".
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
     * Default slide dataset for the initial hero slideshow layout.
     *
     * @return  array<int, array<string, mixed>>
     */
    private function getDefaultSlides(): array
    {
        return [
            [
                'file'          => 'slide_1.jpg',
                'alt'           => 'CopyMyPage hero image 1',
                'headline'      => 'Fernbreitenbach Helau',
                'subline'       => 'Willkommen auf der Website des Fernbreiterbacher Carneval-Vereins',
                'isLazy'        => false,
                'fetchPriority' => 'high',
                'width'         => 1920,
                'height'        => 1280,
            ],
            [
                'file'          => 'slide_2.jpg',
                'alt'           => 'CopyMyPage hero image 2',
                'headline'      => 'Feiern, lachen, leben - Carneval verbindet!',
                'subline'       => '',
                'isLazy'        => true,
                'fetchPriority' => 'low',
                'width'         => 1920,
                'height'        => 1280,
            ],
            [
                'file'          => 'slide_3.jpg',
                'alt'           => 'CopyMyPage hero image 3',
                'headline'      => 'Junge Jecken, grosser Spass - Wir machen den Carneval von morgen!',
                'subline'       => '',
                'isLazy'        => true,
                'fetchPriority' => 'low',
                'width'         => 1920,
                'height'        => 1280,
            ],
        ];
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
