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

use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry as JoomlaRegistry;

/**
 * Builds normalized preloader configuration from CopyMyPage template params.
 */
final class PreloaderHelper
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_TYPES = ['dots', 'ring', 'bars', 'logo', 'pulse'];

    /**
     * Build normalized preloader config for the template.
     *
     * @param   JoomlaRegistry|array<string, mixed>  $params        Template style parameters.
     * @param   string                               $templateName  Active template name.
     *
     * @return  array{enabled: bool, id: string, type: string, text: string, logo: string, logoUrl: string}
     */
    public function getConfig(JoomlaRegistry|array $params, string $templateName): array
    {
        $type = strtolower(trim((string) $this->getParam($params, 'preloaderType', 'logo')));

        if (!\in_array($type, self::ALLOWED_TYPES, true)) {
            $type = 'logo';
        }

        $logo = trim((string) $this->getParam($params, 'preloaderLogo', ''));

        if ($logo === '') {
            $logo = 'media/com_' . $templateName . '/images/logo/logo-cmp-preloader.png';
        }

        return [
            'enabled' => (bool) $this->getParam($params, 'preloaderEnabled', 1),
            'id'      => (string) $this->getParam($params, 'preloaderId', 'cmp-preloader'),
            'type'    => $type,
            'text'    => trim((string) $this->getParam($params, 'preloaderText', '')),
            'logo'    => $logo,
            'logoUrl' => $this->toUrl($logo),
        ];
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
     * Convert a media path into a browser URL.
     *
     * @param   string  $path  Raw media path or absolute URL.
     *
     * @return  string
     */
    private function toUrl(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $path) || str_starts_with($path, 'data:')) {
            return $path;
        }

        return Uri::root() . ltrim($path, '/');
    }
}
