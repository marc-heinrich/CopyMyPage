<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.14
 */

namespace Joomla\Module\CopyMyPage\Hero\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Helper\MediaHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;
use Joomla\Registry\Registry;

/**
 * Helper class to prepare hero data for the CopyMyPage Hero module.
 */
final class HeroHelper
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
     * Build Open Graph compatible tag data for the hero section.
     *
     * @param   Registry      $params  The module params.
     * @param   object|null   $module  The published module row.
     * @param   string        $slot    The active system slot.
     * @param   string        $layout  Optional validated layout key from a dispatcher caller.
     *
     * @return  array<string, string>
     */
    public function getOGTags(Registry $params, ?object $module = null, string $slot = '', string $layout = ''): array
    {
        $config         = $params->toArray();
        $layout         = $this->resolveLayoutVariant($config, $layout, $slot);
        $slides         = $this->getSlides($config, $layout);
        $primaryMeta    = self::emptyOpenGraphMeta();
        $configuredMeta = $this->resolveConfiguredOpenGraphMeta($config);

        if ($slides !== []) {
            $primaryMeta = array_replace($primaryMeta, $this->resolvePrimarySlideMeta($slides));
        }

        if ($slides === [] && !self::hasOpenGraphContent($configuredMeta)) {
            return [];
        }

        $meta = self::mergeOpenGraphMeta($primaryMeta, $configuredMeta);

        if ($meta['title'] === '') {
            $moduleTitle   = self::htmlToPlainText((string) ($module->title ?? ''));
            $meta['title'] = $moduleTitle !== '' ? $moduleTitle : 'Hero';
        }

        if ($meta['twitterCard'] === '') {
            $meta['twitterCard'] = $meta['image'] !== '' ? 'summary_large_image' : 'summary';
        }

        return [
            'slot'        => 'hero',
            'label'       => 'Hero',
            'title'       => $meta['title'],
            'description' => $meta['description'],
            'image'       => $meta['image'],
            'imageWidth'  => $meta['imageWidth'],
            'imageHeight' => $meta['imageHeight'],
            'imageAlt'    => $meta['imageAlt'],
            'twitterCard' => $meta['twitterCard'],
        ];
    }

    /**
     * Get the slideshow items for the current hero output.
     *
     * Only valid configured slides are rendered. When no valid slide is stored,
     * the dispatcher renders the module hint instead of a fallback hero.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $layout  Validated layout key.
     *
     * @return  array<int, object>
     */
    public function getSlides(array $cfg, string $layout): array
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);

        return $this->getConfiguredSlides($layoutConfig);
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
        $interval     = (int) self::cfgString($layoutConfig, 'autoplayInterval', '16000');

        if (!\in_array($animation, ['fade', 'slide', 'push', 'pull'], true)) {
            $animation = 'fade';
        }

        $interval = max(7000, min(60000, $interval));

        return 'ratio: false; animation: ' . $animation . '; autoplay: ' . $autoplay
            . '; autoplay-interval: ' . $interval . '; draggable: ' . $draggable;
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
     * Resolve user-configured slideshow rows from the layout params.
     *
     * @param   array<string, mixed>  $layoutConfig  Layout-specific config bucket.
     *
     * @return  array<int, object>
     */
    private function getConfiguredSlides(array $layoutConfig): array
    {
        $rows = self::normalizeSubformRows($layoutConfig['slides'] ?? []);

        if ($rows === []) {
            return [];
        }

        $slides = [];

        foreach ($rows as $row) {
            $image = $this->resolveSlideImage(self::rowValue($row, 'image'));

            if ($image['src'] === '') {
                continue;
            }

            $position = \count($slides) + 1;
            $alt      = trim(self::rowString($row, 'alt'));

            if ($alt === '') {
                $alt = 'CopyMyPage hero image ' . $position;
            }

            $slides[] = (object) [
                'src'           => $image['src'],
                'alt'           => $alt,
                'headline'      => trim(self::rowString($row, 'headline')),
                'subline'       => trim(self::rowString($row, 'subline')),
                'isLazy'        => $position > 1,
                'fetchPriority' => $position === 1 ? 'high' : 'low',
                'width'         => $image['width'],
                'height'        => $image['height'],
            ];
        }

        return $slides;
    }

    /**
     * Normalize a stored Joomla subform value to a list of row arrays.
     *
     * @param   mixed  $rows  Stored subform value.
     *
     * @return  array<int, array<string, mixed>>
     */
    private static function normalizeSubformRows(mixed $rows): array
    {
        if ($rows instanceof Registry) {
            $rows = $rows->toArray();
        } elseif (\is_string($rows)) {
            $decoded = json_decode($rows, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }

            $rows = $decoded;
        } elseif (\is_object($rows)) {
            $rows = get_object_vars($rows);
        }

        if (!\is_array($rows) || $rows === []) {
            return [];
        }

        if (array_key_exists('image', $rows) || array_key_exists('headline', $rows) || array_key_exists('subline', $rows)) {
            return [$rows];
        }

        $normalized = [];

        foreach ($rows as $row) {
            if ($row instanceof Registry) {
                $row = $row->toArray();
            } elseif (\is_object($row)) {
                $row = get_object_vars($row);
            }

            if (\is_array($row)) {
                $normalized[] = $row;
            }
        }

        return $normalized;
    }

    /**
     * Read a field value from a normalized subform row.
     *
     * @param   array<string, mixed>  $row  Stored subform row.
     * @param   string                $key  Field key.
     *
     * @return  mixed
     */
    private static function rowValue(array $row, string $key): mixed
    {
        return $row[$key] ?? null;
    }

    /**
     * Read a string field from a normalized subform row.
     *
     * @param   array<string, mixed>  $row      Stored subform row.
     * @param   string                $key      Field key.
     * @param   string                $default  Fallback value.
     *
     * @return  string
     */
    private static function rowString(array $row, string $key, string $default = ''): string
    {
        return CopyMyPageHelper::toString(self::rowValue($row, $key), $default);
    }

    /**
     * Normalize a media field value to a public image source and dimensions.
     *
     * @param   mixed  $rawImage  Stored media field value.
     *
     * @return  array{src: string, width: int, height: int}
     */
    private function resolveSlideImage(mixed $rawImage): array
    {
        $raw = self::mediaFieldString($rawImage);

        if ($raw === '') {
            return ['src' => '', 'width' => 0, 'height' => 0];
        }

        $fragmentData = self::extractJoomlaImageFragmentData($raw);
        $clean        = trim((string) MediaHelper::getCleanMediaFieldValue($raw));

        if ($clean === '' && $fragmentData['path'] !== '') {
            $clean = $fragmentData['path'];
        }

        $src = self::normalizeMediaPath($clean);

        if ($src === '') {
            return ['src' => '', 'width' => 0, 'height' => 0];
        }

        $width  = CopyMyPageHelper::toInt($fragmentData['width'], 0, 0);
        $height = CopyMyPageHelper::toInt($fragmentData['height'], 0, 0);

        if (($width === 0 || $height === 0) && !preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $src) && !str_starts_with($src, 'data:')) {
            [$localWidth, $localHeight] = self::resolveLocalImageDimensions($src);
            $width  = $width > 0 ? $width : $localWidth;
            $height = $height > 0 ? $height : $localHeight;
        }

        return ['src' => $src, 'width' => $width, 'height' => $height];
    }

    /**
     * Extract a path-like string from possible media field value shapes.
     *
     * @param   mixed  $value  Raw media value.
     *
     * @return  string
     */
    private static function mediaFieldString(mixed $value): string
    {
        if (\is_string($value)) {
            $value = trim($value);

            if ($value !== '' && ($value[0] === '{' || $value[0] === '[')) {
                $decoded = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return self::mediaFieldString($decoded);
                }
            }

            return $value;
        }

        if ($value instanceof Registry) {
            $value = $value->toArray();
        } elseif (\is_object($value)) {
            $value = get_object_vars($value);
        }

        if (\is_array($value)) {
            foreach (['imagefile', 'image', 'file', 'src', 'url', 'path'] as $key) {
                if (array_key_exists($key, $value)) {
                    $candidate = self::mediaFieldString($value[$key]);

                    if ($candidate !== '') {
                        return $candidate;
                    }
                }
            }
        }

        return '';
    }

    /**
     * Resolve Joomla media adapter prefixes and local paths to frontend URLs.
     *
     * @param   string  $path  Clean media path.
     *
     * @return  string
     */
    private static function normalizeMediaPath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        if (preg_match('#^(?:https?:)?//#i', $path) || str_starts_with($path, 'data:')) {
            return $path;
        }

        if (preg_match('#^joomlaImage://local-([^/]+)/(.+)$#', $path, $matches) === 1) {
            $path = $matches[1] . '/' . $matches[2];
        } elseif (preg_match('#^local-([^:]+):/?(.*)$#', $path, $matches) === 1) {
            $path = $matches[1] . '/' . ltrim($matches[2], '/');
        }

        return ltrim($path, '/');
    }

    /**
     * Extract dimensions and fallback path from a Joomla image fragment.
     *
     * @param   string  $value  Stored media field value.
     *
     * @return  array{path: string, width: int, height: int}
     */
    private static function extractJoomlaImageFragmentData(string $value): array
    {
        $data = ['path' => '', 'width' => 0, 'height' => 0];
        $hash = strpos($value, '#');

        if ($hash === false) {
            return $data;
        }

        $fragment = substr($value, $hash + 1);

        if ($fragment === '') {
            return $data;
        }

        $parts = parse_url($fragment);

        if (!\is_array($parts)) {
            return $data;
        }

        if (($parts['scheme'] ?? '') === 'joomlaImage' && str_starts_with((string) ($parts['host'] ?? ''), 'local-')) {
            $adapter = substr((string) $parts['host'], 6);
            $path    = ltrim((string) ($parts['path'] ?? ''), '/');

            if ($adapter !== '' && $path !== '') {
                $data['path'] = $adapter . '/' . $path;
            }
        }

        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);

        $data['width']  = CopyMyPageHelper::toInt($query['width'] ?? null, 0, 0);
        $data['height'] = CopyMyPageHelper::toInt($query['height'] ?? null, 0, 0);

        return $data;
    }

    /**
     * Read intrinsic dimensions for local public image paths.
     *
     * @param   string  $src  Public local image path.
     *
     * @return  array{0: int, 1: int}
     */
    private static function resolveLocalImageDimensions(string $src): array
    {
        $path = parse_url($src, PHP_URL_PATH);

        if (!\is_string($path) || $path === '') {
            return [0, 0];
        }

        $absolutePath = JPATH_ROOT . '/' . ltrim($path, '/');

        if (!is_file($absolutePath)) {
            return [0, 0];
        }

        $size = @getimagesize($absolutePath);

        if (!\is_array($size)) {
            return [0, 0];
        }

        return [CopyMyPageHelper::toInt($size[0] ?? 0, 0, 0), CopyMyPageHelper::toInt($size[1] ?? 0, 0, 0)];
    }

    /**
     * Resolve the validated layout variant for hero metadata and rendering helpers.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $layout  Optional validated layout key.
     * @param   string                $slot    Optional slot name used as fallback prefix.
     *
     * @return  string
     */
    private function resolveLayoutVariant(array $cfg, string $layout = '', string $slot = ''): string
    {
        $layout       = self::normalizeLayoutKey($layout);
        $layoutPrefix = $this->layoutPrefix !== ''
            ? $this->layoutPrefix
            : self::normalizeLayoutKey($slot);

        if ($layout === '') {
            $layout = self::normalizeLayoutKey((string) ($cfg['layoutVariant'] ?? ''));
        }

        if ($layout === '' || $layout === 'default') {
            $layout = $this->resolveConfiguredLayout($cfg, $layoutPrefix);
        }

        if ($layout === '' || $layout === 'default') {
            return $this->defaultLayout;
        }

        if ($layoutPrefix !== '' && !str_starts_with($layout, $layoutPrefix . '_')) {
            return $this->defaultLayout;
        }

        return $layout;
    }

    /**
     * Infer a layout key from prefixed layout params when no explicit variant is stored.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $prefix  Slot/layout prefix.
     *
     * @return  string
     */
    private function resolveConfiguredLayout(array $cfg, string $prefix): string
    {
        $prefix = self::normalizeLayoutKey($prefix);

        if ($prefix === '') {
            return '';
        }

        foreach ($cfg as $key => $value) {
            if (!\is_string($key) || !str_starts_with($key, $prefix . '_')) {
                continue;
            }

            $parts = explode('_', $key, 3);

            if (\count($parts) >= 2 && $parts[1] !== '') {
                return $parts[0] . '_' . $parts[1];
            }
        }

        return '';
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
     * Build metadata from explicit Open Graph module params.
     *
     * @param   array<string, mixed>  $cfg  Flat module config array.
     *
     * @return  array<string, string>
     */
    private function resolveConfiguredOpenGraphMeta(array $cfg): array
    {
        $image       = $this->resolveSlideImage($cfg['og_image'] ?? '');
        $imageWidth  = CopyMyPageHelper::cfgInt($cfg, 'og_image_width', 0, 0);
        $imageHeight = CopyMyPageHelper::cfgInt($cfg, 'og_image_height', 0, 0);
        $twitterCard = strtolower(trim(self::cfgString($cfg, 'og_twitter_card')));

        if (!\in_array($twitterCard, ['summary', 'summary_large_image'], true)) {
            $twitterCard = '';
        }

        if ($imageWidth === 0) {
            $imageWidth = $image['width'];
        }

        if ($imageHeight === 0) {
            $imageHeight = $image['height'];
        }

        return [
            'title'       => self::htmlToPlainText(self::cfgString($cfg, 'og_title')),
            'description' => self::htmlToPlainText(self::cfgString($cfg, 'og_description')),
            'image'       => $this->toAbsoluteUrl($image['src']),
            'imageWidth'  => $imageWidth > 0 ? (string) $imageWidth : '',
            'imageHeight' => $imageHeight > 0 ? (string) $imageHeight : '',
            'imageAlt'    => trim(self::cfgString($cfg, 'og_image_alt')),
            'twitterCard' => $twitterCard,
        ];
    }

    /**
     * Return an empty metadata payload with all supported keys.
     *
     * @return  array<string, string>
     */
    private static function emptyOpenGraphMeta(): array
    {
        return [
            'title'       => '',
            'description' => '',
            'image'       => '',
            'imageWidth'  => '',
            'imageHeight' => '',
            'imageAlt'    => '',
            'twitterCard' => '',
        ];
    }

    /**
     * Check whether metadata contains enough content to describe a section.
     *
     * @param   array<string, string>  $meta  Metadata payload.
     *
     * @return  bool
     */
    private static function hasOpenGraphContent(array $meta): bool
    {
        return trim($meta['title'] ?? '') !== ''
            || trim($meta['description'] ?? '') !== ''
            || trim($meta['image'] ?? '') !== '';
    }

    /**
     * Merge non-empty explicit metadata values over fallback values.
     *
     * @param   array<string, string>  $fallback   Derived fallback metadata.
     * @param   array<string, string>  $overrides  Explicit module param metadata.
     *
     * @return  array<string, string>
     */
    private static function mergeOpenGraphMeta(array $fallback, array $overrides): array
    {
        $meta = array_replace(self::emptyOpenGraphMeta(), $fallback);

        foreach ($overrides as $key => $value) {
            if (!\is_string($key) || !\array_key_exists($key, $meta)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                $meta[$key] = $value;
            }
        }

        return $meta;
    }

    /**
     * Build a stable metadata payload from the first available hero slide.
     *
     * @param   array<int, object>  $slides  Prepared hero slides.
     *
     * @return  array<string, string>
     */
    private function resolvePrimarySlideMeta(array $slides): array
    {
        $slide    = isset($slides[0]) && \is_object($slides[0]) ? $slides[0] : null;
        $image    = $this->toAbsoluteUrl(trim((string) ($slide->src ?? '')));

        $title       = self::htmlToPlainText((string) ($slide->headline ?? ''));
        $imageWidth  = (int) ($slide->width ?? 0);
        $imageHeight = (int) ($slide->height ?? 0);

        if ($title === '') {
            $title = 'Hero';
        }

        return [
            'title'       => $title,
            'description' => self::htmlToPlainText((string) ($slide->subline ?? '')),
            'image'       => $image,
            'imageWidth'  => $imageWidth > 0 ? (string) $imageWidth : '',
            'imageHeight' => $imageHeight > 0 ? (string) $imageHeight : '',
            'imageAlt'    => trim((string) ($slide->alt ?? '')),
        ];
    }

    /**
     * Convert a hero asset path into an absolute URL.
     *
     * @param   string  $url  Relative, rooted or absolute URL.
     *
     * @return  string
     */
    private function toAbsoluteUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $root     = rtrim(Uri::root(), '/');
        $rootPath = rtrim((string) parse_url($root, PHP_URL_PATH), '/');
        $origin   = $root;

        if ($rootPath !== '' && $rootPath !== '/') {
            $origin = preg_replace('#' . preg_quote($rootPath, '#') . '$#', '', $root) ?? $root;
        }

        if (str_starts_with($url, '/')) {
            if ($rootPath !== '' && str_starts_with($url, $rootPath . '/')) {
                return rtrim($origin, '/') . $url;
            }

            return $root . $url;
        }

        return $root . '/' . ltrim($url, '/');
    }

    /**
     * Convert filtered editor HTML into compact plain text for metadata.
     *
     * @param   string  $html  Filtered HTML or plain text.
     *
     * @return  string
     */
    private static function htmlToPlainText(string $html): string
    {
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
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
