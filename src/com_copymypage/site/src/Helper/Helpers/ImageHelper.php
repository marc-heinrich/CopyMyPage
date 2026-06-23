<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.15
 */

namespace Joomla\Component\CopyMyPage\Site\Helper\Helpers;

\defined('_JEXEC') or die;

use Joomla\CMS\Helper\MediaHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * Shared image normalization and responsive variant helper for CopyMyPage.
 */
final class ImageHelper
{
    /**
     * Normalize a Joomla media field value to a public image source and dimensions.
     *
     * @param   mixed  $rawImage  Stored media field value.
     *
     * @return  array{src: string, width: int, height: int}
     */
    public function resolveMediaImage(mixed $rawImage): array
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

        $width  = self::toPositiveInt($fragmentData['width']);
        $height = self::toPositiveInt($fragmentData['height']);

        if (($width === 0 || $height === 0) && self::isLocalSource($src)) {
            [$localWidth, $localHeight] = self::resolveLocalImageDimensions($src);
            $width  = $width > 0 ? $width : $localWidth;
            $height = $height > 0 ? $height : $localHeight;
        }

        return ['src' => $src, 'width' => $width, 'height' => $height];
    }

    /**
     * Build picture/source data from generated same-name image variants.
     *
     * Variants follow the `<name>-<width>.<extension>` convention. An optional
     * preferred subdirectory can be checked before the source image directory.
     *
     * @param   string          $src                    Normalized image path or URL.
     * @param   array<int, int> $variantWidths          Candidate variant widths.
     * @param   string          $sizes                  Browser sizing hint.
     * @param   string          $preferredSubdirectory  Optional sibling directory.
     *
     * @return  array{
     *     src: string,
     *     srcset: string,
     *     webpSrcset: string,
     *     avifSrcset: string,
     *     sizes: string,
     *     width: int,
     *     height: int
     * }
     */
    public function buildResponsiveImageData(
        string $src,
        array $variantWidths,
        string $sizes = '',
        string $preferredSubdirectory = ''
    ): array {
        $data = [
            'src'          => '',
            'srcset'       => '',
            'webpSrcset'   => '',
            'avifSrcset'   => '',
            'sizes'        => trim($sizes),
            'width'        => 0,
            'height'       => 0,
        ];
        $src = trim($src);

        if ($src === '') {
            return $data;
        }

        if (!self::isLocalSource($src)) {
            $data['src'] = $src;

            return $data;
        }

        $publicPath = self::resolvePublicPath($src);

        if ($publicPath === '' || !self::isLocalImageFile($publicPath)) {
            return $data;
        }

        [$width, $height] = self::resolveLocalImageDimensions($publicPath);

        $data['src']    = $this->toAbsoluteUrl($publicPath);
        $data['width']  = $width;
        $data['height'] = $height;

        $extension = strtolower((string) pathinfo($publicPath, PATHINFO_EXTENSION));

        if ($extension === '') {
            return $data;
        }

        $variantWidths        = self::normalizeVariantWidths($variantWidths);
        $srcsetEntries        = [];
        $webpSrcsetEntries    = [];
        $avifSrcsetEntries    = [];
        $hasIntrinsicVariant  = false;
        $preferredSubdirectory = trim(str_replace('\\', '/', $preferredSubdirectory), '/');

        foreach ($variantWidths as $variantWidth) {
            if ($width > 0 && $variantWidth > $width) {
                continue;
            }

            $fallbackPath = self::findImageVariantPath(
                $publicPath,
                $variantWidth,
                $extension,
                $preferredSubdirectory
            );
            $webpPath = self::findImageVariantPath(
                $publicPath,
                $variantWidth,
                'webp',
                $preferredSubdirectory
            );
            $avifPath = self::findImageVariantPath(
                $publicPath,
                $variantWidth,
                'avif',
                $preferredSubdirectory
            );

            if ($fallbackPath !== '') {
                $fallbackUrl     = $this->toAbsoluteUrl($fallbackPath);
                $srcsetEntries[] = $fallbackUrl . ' ' . $variantWidth . 'w';

                if ($width > 0 && $variantWidth === $width) {
                    $data['src']         = $fallbackUrl;
                    $hasIntrinsicVariant = true;
                }
            }

            if ($webpPath !== '') {
                $webpSrcsetEntries[] = $this->toAbsoluteUrl($webpPath) . ' ' . $variantWidth . 'w';
            }

            if ($avifPath !== '') {
                $avifSrcsetEntries[] = $this->toAbsoluteUrl($avifPath) . ' ' . $variantWidth . 'w';
            }
        }

        if ($width > 0 && !$hasIntrinsicVariant) {
            $srcsetEntries[] = $this->toAbsoluteUrl($publicPath) . ' ' . $width . 'w';
        }

        $data['srcset']     = implode(', ', array_unique($srcsetEntries));
        $data['webpSrcset'] = implode(', ', array_unique($webpSrcsetEntries));
        $data['avifSrcset'] = implode(', ', array_unique($avifSrcsetEntries));

        return $data;
    }

    /**
     * Convert a CopyMyPage asset path into an absolute URL.
     */
    public function toAbsoluteUrl(string $url): string
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
     * Extract a path-like string from possible media field value shapes.
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
     * Resolve Joomla media adapter prefixes and local paths to public paths.
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
     * @return array{path: string, width: int, height: int}
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

        $data['width']  = self::toPositiveInt($query['width'] ?? 0);
        $data['height'] = self::toPositiveInt($query['height'] ?? 0);

        return $data;
    }

    /**
     * Normalize and sort responsive widths.
     *
     * @param   array<int, int>  $variantWidths
     *
     * @return  array<int, int>
     */
    private static function normalizeVariantWidths(array $variantWidths): array
    {
        $widths = [];

        foreach ($variantWidths as $variantWidth) {
            $variantWidth = self::toPositiveInt($variantWidth);

            if ($variantWidth > 0) {
                $widths[] = $variantWidth;
            }
        }

        $widths = array_values(array_unique($widths));
        sort($widths, SORT_NUMERIC);

        return $widths;
    }

    /**
     * Build a same-directory responsive image variant path.
     */
    private static function buildImageVariantPath(string $publicPath, int $width, string $extension): string
    {
        $variantPath = preg_replace(
            '/\.[^.]+$/',
            '-' . $width . '.' . strtolower(trim($extension, '.')),
            $publicPath
        );

        return \is_string($variantPath) ? $variantPath : $publicPath;
    }

    /**
     * Locate a generated variant in the preferred or source directory.
     */
    private static function findImageVariantPath(
        string $publicPath,
        int $width,
        string $extension,
        string $preferredSubdirectory = ''
    ): string {
        $sameDirectoryPath = self::buildImageVariantPath($publicPath, $width, $extension);
        $directory         = str_replace('\\', '/', (string) pathinfo($publicPath, PATHINFO_DIRNAME));
        $candidates        = [];

        if (
            $preferredSubdirectory !== ''
            && strcasecmp((string) basename($directory), $preferredSubdirectory) !== 0
        ) {
            $directoryPrefix = $directory !== '' && $directory !== '.' ? rtrim($directory, '/') . '/' : '';
            $candidates[]     = $directoryPrefix . $preferredSubdirectory . '/' . basename($sameDirectoryPath);
        }

        $candidates[] = $sameDirectoryPath;

        foreach ($candidates as $candidate) {
            if (self::isLocalImageFile($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Read intrinsic dimensions for a local image path.
     *
     * @return array{0: int, 1: int}
     */
    private static function resolveLocalImageDimensions(string $src): array
    {
        $publicPath = self::resolvePublicPath($src);
        $file       = self::resolveLocalFile($publicPath);

        if ($file === '') {
            return [0, 0];
        }

        $size = @getimagesize($file);

        if (!\is_array($size)) {
            return [0, 0];
        }

        return [
            self::toPositiveInt($size[0] ?? 0),
            self::toPositiveInt($size[1] ?? 0),
        ];
    }

    /**
     * Resolve a local or same-site URL to a Joomla-root-relative public path.
     */
    private static function resolvePublicPath(string $src): string
    {
        $path = parse_url($src, PHP_URL_PATH);

        if (!\is_string($path) || $path === '') {
            return '';
        }

        $rootPath = rtrim(Uri::root(true), '/');

        if ($rootPath !== '' && $rootPath !== '/' && str_starts_with($path, $rootPath . '/')) {
            $path = substr($path, strlen($rootPath));
        }

        $path = ltrim(rawurldecode(str_replace('\\', '/', $path)), '/');

        if ($path === '' || preg_match('#(?:^|/)\.\.(?:/|$)#', $path) === 1) {
            return '';
        }

        return $path;
    }

    /**
     * Check whether an image path resolves inside the Joomla root.
     */
    private static function isLocalImageFile(string $publicPath): bool
    {
        return self::resolveLocalFile($publicPath) !== '';
    }

    /**
     * Resolve and validate a public path against the Joomla document root.
     */
    private static function resolveLocalFile(string $publicPath): string
    {
        $publicPath = ltrim(str_replace('\\', '/', trim($publicPath)), '/');

        if ($publicPath === '' || preg_match('#(?:^|/)\.\.(?:/|$)#', $publicPath) === 1) {
            return '';
        }

        $candidate = JPATH_ROOT . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $publicPath);
        $file = realpath($candidate);
        $root = realpath(JPATH_ROOT);

        if ($file === false || $root === false || !is_file($file)) {
            return '';
        }

        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (strncasecmp($file, $rootPrefix, strlen($rootPrefix)) !== 0) {
            return '';
        }

        return $file;
    }

    /**
     * Determine whether a source can be resolved against the local Joomla root.
     */
    private static function isLocalSource(string $src): bool
    {
        if (str_starts_with($src, 'data:') || str_starts_with($src, '//')) {
            return false;
        }

        $scheme = strtolower((string) parse_url($src, PHP_URL_SCHEME));

        if ($scheme === '') {
            return true;
        }

        if (!\in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $sourceHost = (string) parse_url($src, PHP_URL_HOST);
        $siteHost   = (string) parse_url(Uri::root(), PHP_URL_HOST);

        return $sourceHost !== '' && $siteHost !== '' && strcasecmp($sourceHost, $siteHost) === 0;
    }

    /**
     * Normalize a value into a positive integer.
     */
    private static function toPositiveInt(mixed $value): int
    {
        if (\is_int($value)) {
            return max(0, $value);
        }

        if (\is_float($value) || (\is_string($value) && is_numeric(trim($value)))) {
            return max(0, (int) $value);
        }

        return 0;
    }
}
