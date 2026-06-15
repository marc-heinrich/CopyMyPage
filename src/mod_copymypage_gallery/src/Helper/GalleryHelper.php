<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.14
 */

namespace Joomla\Module\CopyMyPage\Gallery\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\MediaHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;
use Joomla\Component\CopyMyPage\Site\Helper\Helpers\SigplusHelper;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Helper class for the CopyMyPage Gallery module.
 */
final class GalleryHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /**
     * Default preview image used when no explicit Open Graph image is configured.
     *
     * @var string
     */
    private const DEFAULT_OG_IMAGE = 'images/copymypage/module/mod_copymypage_gallery/2026/kinder/00_start_001.jpg';

    /**
     * Build Open Graph compatible tag data for the gallery section.
     *
     * @param   Registry      $params  The module params.
     * @param   object|null   $module  The published module row.
     * @param   string        $slot    The active system slot.
     *
     * @return  array<string, string>
     */
    public function getOGTags(Registry $params, ?object $module = null, string $slot = ''): array
    {
        $config         = $params->toArray();
        $layout         = strtolower(trim((string) ($config['layoutVariant'] ?? 'gallery_sigplus_preview')));
        $layout         = $layout !== '' && $layout !== 'default' ? $layout : 'gallery_sigplus_preview';
        $layoutConfig   = self::getLayoutConfig($config, $layout);
        $headline       = trim(self::cfgString($layoutConfig, 'headline', Text::_('MOD_COPYMYPAGE_GALLERY_PREVIEW_TITLE')));
        $lead           = trim(self::cfgString($layoutConfig, 'lead', Text::_('MOD_COPYMYPAGE_GALLERY_PREVIEW_DESC')));
        $configuredMeta = $this->resolveConfiguredOpenGraphMeta($config);
        $meta           = self::mergeOpenGraphMeta(
            [
                'title'       => self::htmlToPlainText($headline !== '' ? $headline : Text::_('MOD_COPYMYPAGE_GALLERY_PREVIEW_TITLE')),
                'description' => self::htmlToPlainText($lead !== '' ? $lead : Text::_('MOD_COPYMYPAGE_GALLERY_PREVIEW_DESC')),
                'image'       => $this->toAbsoluteUrl(self::DEFAULT_OG_IMAGE),
                'imageWidth'  => '',
                'imageHeight' => '',
                'imageAlt'    => Text::_('MOD_COPYMYPAGE_GALLERY_PREVIEW_TITLE'),
                'twitterCard' => 'summary_large_image',
            ],
            $configuredMeta
        );

        if ($meta['title'] === '') {
            $moduleTitle   = self::htmlToPlainText((string) ($module->title ?? ''));
            $meta['title'] = $moduleTitle !== '' ? $moduleTitle : Text::_('MOD_COPYMYPAGE_GALLERY_PREVIEW_TITLE');
        }

        if ($meta['twitterCard'] === '') {
            $meta['twitterCard'] = $meta['image'] !== '' ? 'summary_large_image' : 'summary';
        }

        return [
            'slot'        => 'gallery',
            'label'       => Text::_('MOD_COPYMYPAGE_GALLERY_PREVIEW_TITLE'),
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
     * Loads all published Sigplus site modules from #__modules.
     *
     * The module rows are enriched with decoded params and normalized gallery metadata
     * so later layouts can work with a prepared list instead of reparsing module params.
     *
     * @return array<int, object>
     */
    public function getSigplusModules(): array
    {
        $db  = $this->getDatabase();

        $module   = 'mod_sigplus';
        $clientId = 0;

        $query = $db->getQuery(true)
            ->select(
                [
                    $db->quoteName('m.id'),
                    $db->quoteName('m.title'),
                    $db->quoteName('m.module'),
                    $db->quoteName('m.position'),
                    $db->quoteName('m.content'),
                    $db->quoteName('m.showtitle'),
                    $db->quoteName('m.params'),
                    $db->quoteName('m.ordering'),
                ]
            )
            ->from($db->quoteName('#__modules', 'm'))
            ->where(
                [
                    $db->quoteName('m.published') . ' = 1',
                    $db->quoteName('m.module') . ' = :module',
                    $db->quoteName('m.client_id') . ' = :clientId',
                ]
            )
            ->order($db->quoteName('m.ordering') . ' ASC')
            ->order($db->quoteName('m.id') . ' ASC')
            ->bind(':module', $module, ParameterType::STRING)
            ->bind(':clientId', $clientId, ParameterType::INTEGER);

        $modules = $db->setQuery($query)->loadObjectList();

        if (!\is_array($modules) || $modules === []) {
            return [];
        }

        foreach ($modules as $index => $moduleRow) {
            if (!\is_object($moduleRow)) {
                unset($modules[$index]);

                continue;
            }

            $modules[$index] = $this->hydrateSigplusModule($moduleRow);
        }

        return array_values($modules);
    }

    /**
     * Loads the Sigplus content plugin row from #__extensions.
     *
     * @return object|null
     */
    public function getSigplusPlugin(): ?object
    {
        return $this->getSigplusHelper()->getPlugin();
    }

    /**
     * Checks whether the Sigplus content plugin exists and is enabled.
     *
     * @return bool
     */
    public function isSigplusAvailable(?object $sigplusPlugin = null): bool
    {
        return $this->getSigplusHelper()->isAvailable($sigplusPlugin);
    }

    /**
     * Counts image files directly inside a Sigplus gallery directory.
     *
     * @param  string  $moduleSource  Relative gallery source from the Sigplus module params.
     *
     * @return object|null
     */
    public function countImagesInDirectory(string $moduleSource): ?object
    {
        $moduleSource = $this->normalizeSource($moduleSource);

        if ($moduleSource === '') {
            return null;
        }

        $galleryPath = JPATH_ROOT . '/images/' . str_replace('/', DIRECTORY_SEPARATOR, $moduleSource);

        if (!is_dir($galleryPath)) {
            return (object) ['image_count' => 0];
        }

        try {
            $iterator = new \FilesystemIterator($galleryPath, \FilesystemIterator::SKIP_DOTS);
        } catch (\UnexpectedValueException) {
            return (object) ['image_count' => 0];
        }

        $imageCount = 0;

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            if (!\in_array(strtolower($fileInfo->getExtension()), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'], true)) {
                continue;
            }

            $imageCount++;
        }

        return (object) ['image_count' => $imageCount];
    }

    /**
     * Returns the unique filter labels for the current Sigplus module list.
     *
     * @param  array<int, object>  $list
     *
     * @return array<int, string>
     */
    public function listUnique(array $list): array
    {
        $filters = [];

        foreach ($list as $item) {
            if (!\is_object($item)) {
                continue;
            }

            $filter = trim((string) ($item->filter_label ?? ''));

            if ($filter === '') {
                continue;
            }

            $filters[] = $filter;
        }

        return array_values(array_unique($filters));
    }

    /**
     * Extract the layout-specific parameter subset from the flat module config.
     *
     * Example:
     * layout "gallery_sigplus_preview" turns
     * "gallery_sigplus_preview_showFilters" into "showFilters".
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
     * Build metadata from explicit Open Graph module params.
     *
     * @param   array<string, mixed>  $cfg  Flat module config array.
     *
     * @return  array<string, string>
     */
    private function resolveConfiguredOpenGraphMeta(array $cfg): array
    {
        $image       = $this->resolveOpenGraphImage($cfg['og_image'] ?? '');
        $imageWidth  = self::cfgInt($cfg, 'og_image_width', 0, 0);
        $imageHeight = self::cfgInt($cfg, 'og_image_height', 0, 0);
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
     * Merge non-empty explicit metadata values over fallback values.
     *
     * @param   array<string, string>  $fallback   Derived fallback metadata.
     * @param   array<string, string>  $overrides  Explicit module param metadata.
     *
     * @return  array<string, string>
     */
    private static function mergeOpenGraphMeta(array $fallback, array $overrides): array
    {
        $meta = array_replace(
            [
                'title'       => '',
                'description' => '',
                'image'       => '',
                'imageWidth'  => '',
                'imageHeight' => '',
                'imageAlt'    => '',
                'twitterCard' => '',
            ],
            $fallback
        );

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
     * Normalize a media field value to a public image source and dimensions.
     *
     * @param   mixed  $rawImage  Stored media field value.
     *
     * @return  array{src: string, width: int, height: int}
     */
    private function resolveOpenGraphImage(mixed $rawImage): array
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

        $width  = self::cfgInt($fragmentData, 'width', 0, 0);
        $height = self::cfgInt($fragmentData, 'height', 0, 0);

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

        return [
            CopyMyPageHelper::toInt($size[0] ?? 0, 0, 0),
            CopyMyPageHelper::toInt($size[1] ?? 0, 0, 0),
        ];
    }

    /**
     * Convert a gallery asset path into an absolute URL.
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
     * Adds normalized gallery metadata to one Sigplus module row.
     *
     * @param  object  $moduleRow
     *
     * @return object
     */
    private function hydrateSigplusModule(object $moduleRow): object
    {
        $moduleParams = new Registry((string) ($moduleRow->params ?? ''));
        $source       = $this->normalizeSource((string) $moduleParams->get('source', ''));
        $filterSeed   = (string) ($moduleParams->get('id') ?: ($moduleRow->title ?? ''));

        $moduleRow->params_registry = $moduleParams;
        $moduleRow->params_array    = $moduleParams->toArray();
        $moduleRow->gallery_source  = $source;
        $moduleRow->gallery_image   = trim((string) $moduleParams->get('settings', ''));
        $moduleRow->gallery_id      = trim((string) $moduleParams->get('id', ''));
        $moduleRow->filter_label    = self::getTitle($filterSeed);
        $moduleRow->filter_class    = self::getFilterClass($filterSeed);
        $moduleRow->sigplus_data    = $this->countImagesInDirectory($source);
        $moduleRow->image_count     = (int) (($moduleRow->sigplus_data->image_count ?? 0));

        return $moduleRow;
    }

    /**
     * Builds a normalized filter class from a gallery label.
     *
     * @param  string  $filter
     *
     * @return string
     */
    public static function getFilterClass(string $filter): string
    {
        $filter = self::getTitle($filter);
        $filter = preg_replace('/\s+/', '', strtolower($filter)) ?? '';

        return 'filter-' . $filter;
    }

    /**
     * Extracts the display title from a filter seed.
     *
     * If a Sigplus ID follows the legacy "Group-Detail" shape, only the first part
     * is used for grouping/filtering.
     *
     * @param  string  $title
     *
     * @return string
     */
    public static function getTitle(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            return '';
        }

        if (str_contains($title, '-')) {
            $parts = explode('-', $title, 2);

            return trim((string) ($parts[0] ?? ''));
        }

        return $title;
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

    /**
     * Normalizes a Sigplus source path for DB matching.
     *
     * @param  string  $source
     *
     * @return string
     */
    private function normalizeSource(string $source): string
    {
        $source = str_replace('\\', '/', trim($source));

        return trim($source, '/');
    }

    /**
     * Resolves the shared Sigplus helper via the root DI container.
     *
     * @return  SigplusHelper
     */
    private function getSigplusHelper(): SigplusHelper
    {
        $handler = Factory::getContainer()->get(SigplusHelper::class);

        if (!$handler instanceof SigplusHelper) {
            throw new \RuntimeException('The CopyMyPage sigplus helper is not available.');
        }

        return $handler;
    }

}
