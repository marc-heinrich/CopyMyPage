<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.9
 */

namespace Joomla\Component\CopyMyPage\Administrator\Router\Rules;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\Component\CopyMyPage\Site\Service\Router;

/**
 * Custom router rules for CopyMyPage gallery routing.
 */
final class CopyMyPageRules extends MenuRules
{
    /**
     * Append the gallery image count as its own SEF segment.
     *
     * Example:
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

        if (!array_key_exists('imageCount', $query) || !$this->isGalleryBuildContext($query, $segments)) {
            return;
        }

        $segments[] = (string) max(0, (int) $query['imageCount']);

        unset($query['imageCount']);
    }

    /**
     * Parse the trailing gallery image count segment back into the query vars.
     *
     * @param   array  $segments  The URL segments to parse.
     * @param   array  $vars      The vars that result from the segments.
     *
     * @return  void
     */
    public function parse(&$segments, &$vars): void
    {
        if (!$this->router instanceof Router || !$this->isGalleryParseContext($vars)) {
            return;
        }

        if (!isset($segments[0])) {
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
        if (($vars['view'] ?? '') === 'gallery') {
            return true;
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
