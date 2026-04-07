<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.10
 */

namespace Joomla\Module\CopyMyPage\Gallery\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;
use Joomla\Component\CopyMyPage\Site\Helper\Helpers\SigplusHelper;
use Joomla\Component\CopyMyPage\Site\Helper\Registry as CopyMyPageRegistry;
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
     * Resolves the shared Sigplus helper via the CopyMyPage registry.
     *
     * @return  SigplusHelper
     */
    private function getSigplusHelper(): SigplusHelper
    {
        $container = Factory::getContainer();
        $registry  = $container->has(CopyMyPageRegistry::class)
            ? $container->get(CopyMyPageRegistry::class)
            : new CopyMyPageRegistry();
        $handler = $registry->getService('sigplus');

        if (\is_string($handler)) {
            $handler = new $handler();
        }

        if (!$handler instanceof SigplusHelper) {
            throw new \RuntimeException('The CopyMyPage sigplus helper is not available.');
        }

        $handler->setDatabase($this->getDatabase());

        return $handler;
    }
}
