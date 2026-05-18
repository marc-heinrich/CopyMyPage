<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.14
 */

namespace Joomla\Component\CopyMyPage\Administrator\Router\Rules;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;
use Joomla\Component\CopyMyPage\Site\Service\Router;

/**
 * Custom router rules for CopyMyPage onepage and gallery routing.
 */
final class CopyMyPageRules extends MenuRules
{
    /**
     * Query parameter used for server-visible onepage section URLs.
     *
     * @var  string
     */
    private const ONEPAGE_SECTION_PARAM = 'section';

    /**
     * Stable SEF prefix for onepage section URLs.
     *
     * @var  string
     */
    private const ONEPAGE_SECTION_PREFIX = 'section';

    /**
     * Append CopyMyPage specific values as SEF segments.
     *
     * Examples:
     * `/section/gallery.html`
     * `/component/copymypage/gallery/215/21.html`
     *
     * @param   array  $query     The vars that should be converted.
     * @param   array  $segments  The URL segments to create.
     *
     * @return  void
     */
    public function build(&$query, &$segments): void
    {
        if (!$this->router instanceof Router || !$this->isSefEnabled()) {
            return;
        }

        $this->buildOnepageSection($query, $segments);
        $this->buildGalleryImageCount($query, $segments);
    }

    /**
     * Parse CopyMyPage specific SEF segments back into query vars.
     *
     * @param   array  $segments  The URL segments to parse.
     * @param   array  $vars      The vars that result from the segments.
     *
     * @return  void
     */
    public function parse(&$segments, &$vars): void
    {
        if (!$this->router instanceof Router) {
            return;
        }

        $this->parseOnepageSection($segments, $vars);
        $this->parseGalleryImageCount($segments, $vars);
    }

    /**
     * Append the requested onepage section as its own SEF segment.
     *
     * @param   array  $query     The current router query.
     * @param   array  $segments  Already built URL segments.
     *
     * @return  void
     */
    private function buildOnepageSection(array &$query, array &$segments): void
    {
        if (!array_key_exists(self::ONEPAGE_SECTION_PARAM, $query) || !$this->isOnepageBuildContext($query, $segments)) {
            return;
        }

        $section = CopyMyPageHelper::normalizeOnepageSection((string) $query[self::ONEPAGE_SECTION_PARAM]);

        if ($section === '') {
            return;
        }

        $segments[] = self::ONEPAGE_SECTION_PREFIX;
        $segments[] = $section;

        unset($query[self::ONEPAGE_SECTION_PARAM]);
    }

    /**
     * Append the gallery image count as its own SEF segment.
     *
     * @param   array  $query     The current router query.
     * @param   array  $segments  Already built URL segments.
     *
     * @return  void
     */
    private function buildGalleryImageCount(array &$query, array &$segments): void
    {
        if (!array_key_exists('imageCount', $query) || !$this->isGalleryBuildContext($query, $segments)) {
            return;
        }

        $segments[] = (string) max(0, (int) $query['imageCount']);

        unset($query['imageCount']);
    }

    /**
     * Parse the first onepage section segment back into the query vars.
     *
     * @param   array  $segments  The URL segments to parse.
     * @param   array  $vars      The vars that result from the segments.
     *
     * @return  void
     */
    private function parseOnepageSection(array &$segments, array &$vars): void
    {
        if (
            !$this->isOnepageParseContext($vars)
            || !isset($segments[0], $segments[1])
            || trim((string) $segments[0], '/') !== self::ONEPAGE_SECTION_PREFIX
        ) {
            return;
        }

        $section = CopyMyPageHelper::normalizeOnepageSection((string) $segments[1]);

        if ($section === '') {
            return;
        }

        $vars[self::ONEPAGE_SECTION_PARAM] = $section;

        array_shift($segments);
        array_shift($segments);
    }

    /**
     * Parse the trailing gallery image count segment back into the query vars.
     *
     * @param   array  $segments  The URL segments to parse.
     * @param   array  $vars      The vars that result from the segments.
     *
     * @return  void
     */
    private function parseGalleryImageCount(array &$segments, array &$vars): void
    {
        if (!$this->isGalleryParseContext($vars) || !isset($segments[0])) {
            return;
        }

        $segment = trim((string) $segments[0], '/');

        if ($segment === '' || !ctype_digit($segment)) {
            return;
        }

        $vars['imageCount'] = (int) $segment;

        array_shift($segments);
    }

    /**
     * Determine whether the current build cycle targets the onepage view.
     *
     * @param   array  $query     The current router query.
     * @param   array  $segments  Already built URL segments.
     *
     * @return  bool
     */
    private function isOnepageBuildContext(array $query, array $segments): bool
    {
        if (($query['view'] ?? '') === 'onepage') {
            return true;
        }

        if (($segments[0] ?? '') === 'onepage') {
            return true;
        }

        $itemId = (int) ($query['Itemid'] ?? 0);

        if ($itemId <= 0) {
            return false;
        }

        $item = $this->router->menu->getItem($itemId);

        return $item !== null
            && ($item->component ?? '') === 'com_copymypage'
            && ($item->query['view'] ?? '') === 'onepage';
    }

    /**
     * Determine whether the current parse cycle targets the onepage view.
     *
     * @param   array  $vars  The vars already resolved by previous rules.
     *
     * @return  bool
     */
    private function isOnepageParseContext(array &$vars): bool
    {
        $view = (string) ($vars['view'] ?? '');

        if ($view === 'onepage') {
            return true;
        }

        if ($view !== '') {
            return false;
        }

        $active = $this->router->menu->getActive();

        if (
            $active === null
            || ($active->component ?? '') !== 'com_copymypage'
            || ($active->query['view'] ?? '') !== 'onepage'
        ) {
            return false;
        }

        $vars['view'] = 'onepage';

        return true;
    }

    /**
     * Determine whether the current build cycle targets the gallery view.
     *
     * @param   array  $query     The current router query.
     * @param   array  $segments  Already built URL segments.
     *
     * @return  bool
     */
    private function isGalleryBuildContext(array $query, array $segments): bool
    {
        if (($query['view'] ?? '') === 'gallery') {
            return true;
        }

        if (($segments[0] ?? '') === 'gallery') {
            return true;
        }

        $itemId = (int) ($query['Itemid'] ?? 0);

        if ($itemId <= 0) {
            return false;
        }

        $item = $this->router->menu->getItem($itemId);

        return $item !== null
            && ($item->component ?? '') === 'com_copymypage'
            && ($item->query['view'] ?? '') === 'gallery';
    }

    /**
     * Determine whether the current parse cycle targets the gallery view.
     *
     * @param   array  $vars  The vars already resolved by previous rules.
     *
     * @return  bool
     */
    private function isGalleryParseContext(array &$vars): bool
    {
        $view = (string) ($vars['view'] ?? '');

        if ($view === 'gallery') {
            return true;
        }

        if ($view !== '') {
            return false;
        }

        $active = $this->router->menu->getActive();

        if (
            $active === null
            || ($active->component ?? '') !== 'com_copymypage'
            || ($active->query['view'] ?? '') !== 'gallery'
        ) {
            return false;
        }

        $vars['view'] = 'gallery';

        if (!isset($vars['id']) && isset($active->query['id'])) {
            $vars['id'] = (int) $active->query['id'];
        }

        return true;
    }

    /**
     * Check whether SEF routing is enabled for the current application.
     *
     * @return  bool
     */
    private function isSefEnabled(): bool
    {
        return (bool) $this->router->app->get('sef', 0);
    }
}
