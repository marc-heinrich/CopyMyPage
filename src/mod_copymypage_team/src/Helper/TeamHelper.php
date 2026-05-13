<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.14
 */

namespace Joomla\Module\CopyMyPage\Team\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;
use Joomla\Registry\Registry;

/**
 * Helper class for the CopyMyPage Team module.
 */
final class TeamHelper
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
     * Default slot token used by the team module.
     *
     * @var string
     */
    private const DEFAULT_SLOT = 'team';

    /**
     * Default image width used by the placeholder dataset.
     *
     * @var int
     */
    private const DEFAULT_IMAGE_WIDTH = 1600;

    /**
     * Default image height used by the placeholder dataset.
     *
     * @var int
     */
    private const DEFAULT_IMAGE_HEIGHT = 900;

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
     * Build Open Graph compatible tag data for the team section.
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
        $config       = $params->toArray();
        $layout       = $this->resolveLayoutVariant($config, $layout, $slot);
        $items        = $this->getItems($config, $layout);
        $primaryMeta  = $this->resolvePrimaryItemMeta($items);
        $resolvedSlot = trim($slot) !== '' ? strtolower(trim($slot)) : self::DEFAULT_SLOT;

        return [
            'slot'        => $resolvedSlot,
            'label'       => Text::_('MOD_COPYMYPAGE_TEAM_OG_LABEL'),
            'title'       => $this->getHeadline($config, $layout),
            'description' => $this->getLead($config, $layout),
            'image'       => $primaryMeta['image'],
            'imageWidth'  => $primaryMeta['imageWidth'],
            'imageHeight' => $primaryMeta['imageHeight'],
            'imageAlt'    => $primaryMeta['imageAlt'],
            'twitterCard' => 'summary_large_image',
        ];
    }

    /**
     * Get the optional eyebrow text for the active layout.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $layout  Validated layout key.
     *
     * @return  string
     */
    public function getEyebrow(array $cfg, string $layout): string
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);

        return trim(self::cfgString($layoutConfig, 'eyebrow', Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_EYEBROW')));
    }

    /**
     * Get the headline text for the active layout.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $layout  Validated layout key.
     *
     * @return  string
     */
    public function getHeadline(array $cfg, string $layout): string
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);
        $headline     = trim(self::cfgString($layoutConfig, 'headline', Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_HEADLINE')));

        return $headline !== '' ? $headline : Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_HEADLINE');
    }

    /**
     * Get the lead text for the active layout.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $layout  Validated layout key.
     *
     * @return  string
     */
    public function getLead(array $cfg, string $layout): string
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);
        $lead         = trim(self::cfgString($layoutConfig, 'lead', Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_LEAD')));

        return $lead !== '' ? $lead : Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_LEAD');
    }

    /**
     * Get the prepared placeholder team members for the active layout.
     *
     * @param   array<string, mixed>  $cfg     Flat module config array.
     * @param   string                $layout  Validated layout key.
     *
     * @return  array<int, object>
     */
    public function getItems(array $cfg, string $layout): array
    {
        $layoutConfig = self::getLayoutConfig($cfg, $layout);
        $maxItems     = self::cfgInt($layoutConfig, 'maxItems', 3, 1, 12);
        $items        = [];
        $defaultImage = $this->getPlaceholderImageUrl();
        $defaultAlt   = Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_IMAGE_ALT');

        foreach (array_slice($this->getDefaultItems(), 0, $maxItems) as $item) {
            $items[] = (object) [
                'name'        => trim((string) ($item['name'] ?? '')),
                'role'        => trim((string) ($item['role'] ?? '')),
                'description' => trim((string) ($item['description'] ?? '')),
                'image'       => $this->toAbsoluteUrl(trim((string) ($item['image'] ?? $defaultImage))),
                'imageAlt'    => trim((string) ($item['imageAlt'] ?? $defaultAlt)),
                'imageWidth'  => (int) ($item['imageWidth'] ?? self::DEFAULT_IMAGE_WIDTH),
                'imageHeight' => (int) ($item['imageHeight'] ?? self::DEFAULT_IMAGE_HEIGHT),
                'social'      => $this->normalizeSocialLinks($item['social'] ?? []),
            ];
        }

        return $items;
    }

    /**
     * Extract the layout-specific parameter subset from the flat module config.
     *
     * Example:
     * layout "team_cards" turns "team_cards_headline" into "headline".
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
     * Resolve the layout variant for metadata and rendering helpers.
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
     * Build a stable metadata payload from the first available team item.
     *
     * @param   array<int, object>  $items  Prepared team items.
     *
     * @return  array<string, string>
     */
    private function resolvePrimaryItemMeta(array $items): array
    {
        $item        = isset($items[0]) && \is_object($items[0]) ? $items[0] : null;
        $placeholder = $this->getPlaceholderImageUrl();
        $image       = $this->toAbsoluteUrl(trim((string) ($item->image ?? $placeholder)));
        $imageWidth  = (int) ($item->imageWidth ?? self::DEFAULT_IMAGE_WIDTH);
        $imageHeight = (int) ($item->imageHeight ?? self::DEFAULT_IMAGE_HEIGHT);
        $imageAlt    = trim((string) ($item->imageAlt ?? Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_IMAGE_ALT')));

        return [
            'image'       => $image,
            'imageWidth'  => $imageWidth > 0 ? (string) $imageWidth : '',
            'imageHeight' => $imageHeight > 0 ? (string) $imageHeight : '',
            'imageAlt'    => $imageAlt,
        ];
    }

    /**
     * Return the placeholder dataset for the team cards.
     *
     * @return  array<int, array<string, mixed>>
     */
    private function getDefaultItems(): array
    {
        return [
            [
                'name'        => Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_ITEM_1_NAME'),
                'role'        => Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_ITEM_1_ROLE'),
                'description' => Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_ITEM_1_DESC'),
            ],
            [
                'name'        => Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_ITEM_2_NAME'),
                'role'        => Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_ITEM_2_ROLE'),
                'description' => Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_ITEM_2_DESC'),
            ],
            [
                'name'        => Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_ITEM_3_NAME'),
                'role'        => Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_ITEM_3_ROLE'),
                'description' => Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_ITEM_3_DESC'),
            ],
        ];
    }

    /**
     * Normalize optional social links.
     *
     * @param   mixed  $links  Raw link list.
     *
     * @return  array<int, array{url: string, label: string, icon: string}>
     */
    private function normalizeSocialLinks(mixed $links): array
    {
        if (!\is_array($links)) {
            return [];
        }

        $normalized = [];

        foreach ($links as $link) {
            if (!\is_array($link)) {
                continue;
            }

            $url = trim((string) ($link['url'] ?? ''));

            if ($url === '') {
                continue;
            }

            $normalized[] = [
                'url'   => $url,
                'label' => trim((string) ($link['label'] ?? Text::_('MOD_COPYMYPAGE_TEAM_SOCIAL_LINK'))),
                'icon'  => trim((string) ($link['icon'] ?? 'link')),
            ];
        }

        return $normalized;
    }

    /**
     * Get the absolute URL for the bundled placeholder image.
     *
     * @return  string
     */
    private function getPlaceholderImageUrl(): string
    {
        return rtrim(Uri::root(), '/') . '/modules/mod_copymypage_team/images/placeholder.svg';
    }

    /**
     * Convert a team asset path into an absolute URL.
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
