<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

namespace Joomla\Module\CopyMyPage\Navbar\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Cache\Controller\OutputController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

/**
 * Helper class to prepare data for the CopyMyPage Navbar module.
 */
class NavbarHelper
{
    /**
     * Get default parameters as an associative array.
     *
     * @return array
     */
    public function getParams(): array
    {
        return [
            'logo' => 'media/com_copymypage/images/logo/logo-cmp-1.png',
        ];
    }

    /**
     * Get a list of the menu items (core-like).
     *
     * @param   Registry                 $params  The module options.
     * @param   CMSApplicationInterface  $app     The application
     *
     * @return  array
     */
    public function getItems(Registry $params, CMSApplicationInterface $app): array
    {
        $menu = $app->getMenu();
        $base = $this->getBaseItem($params, $app);

        $levels = $app->getIdentity()->getAuthorisedViewLevels();
        asort($levels);

        $menutype = (string) $params->get('menutype', 'copymypage');

        // Core-like cache key, but include menutype explicitly for safety.
        $cacheKey = 'navbar_items'
            . $params
            . '|' . $menutype
            . '|' . implode(',', $levels)
            . '.' . (int) ($base->id ?? 0);

        /** @var OutputController $cache */
        $cache = Factory::getContainer()
            ->get(CacheControllerFactoryInterface::class)
            ->createCacheController('output', ['defaultgroup' => 'mod_copymypage_navbar']);

        if ($cache->contains($cacheKey)) {
            $cached = $cache->get($cacheKey);

            return \is_array($cached) ? $cached : [];
        }

        $path    = (isset($base->tree) && \is_array($base->tree)) ? array_map('intval', $base->tree) : [];
        $start   = (int) $params->get('startLevel', 1);
        $end     = (int) $params->get('endLevel', 0);
        $showAll = (int) $params->get('showAllChildren', 1);

        $items         = $menu->getItems('menutype', $menutype);
        $hiddenParents = [];
        $lastIndex     = 0;

        if (!$items) {
            $cache->store([], $cacheKey);

            return [];
        }

        $inputVars = $app->getInput()->getArray();

        foreach ($items as $i => $item) {
            $item->parent = false;
            $itemParams   = $item->getParams();

            // Flag previous item as parent when this item is its child and is visible.
            if (
                isset($items[$lastIndex])
                && (int) $items[$lastIndex]->id === (int) $item->parent_id
                && (int) $itemParams->get('menu_show', 1) === 1
            ) {
                $items[$lastIndex]->parent = true;
            }

            $itemLevel    = (int) $item->level;
            $parentId     = (int) $item->parent_id;
            $treeStartRef = ($start > 1) ? (int) ($item->tree[$start - 2] ?? 0) : 0;

            // Level filtering (core-like).
            if (
                ($start && $start > $itemLevel)
                || ($end && $itemLevel > $end)
                || (!$showAll && $itemLevel > 1 && !\in_array($parentId, $path, true))
                || ($start > 1 && ($treeStartRef === 0 || !\in_array($treeStartRef, $path, true)))
            ) {
                unset($items[$i]);
                continue;
            }

            // Exclude item with "exclude from menu modules" or hidden parent.
            if ((int) $itemParams->get('menu_show', 1) === 0 || \in_array($parentId, $hiddenParents, true)) {
                $hiddenParents[] = (int) $item->id;
                unset($items[$i]);
                continue;
            }

            // "Current" state based on input vars vs item query.
            $item->current = true;

            foreach ($item->query as $key => $value) {
                if (!isset($inputVars[$key]) || $inputVars[$key] !== $value) {
                    $item->current = false;
                    break;
                }
            }

            // Tree flags for templating.
            $item->deeper     = false;
            $item->shallower  = false;
            $item->level_diff = 0;

            if (isset($items[$lastIndex])) {
                $items[$lastIndex]->deeper     = ($itemLevel > (int) $items[$lastIndex]->level);
                $items[$lastIndex]->shallower  = ($itemLevel < (int) $items[$lastIndex]->level);
                $items[$lastIndex]->level_diff = ((int) $items[$lastIndex]->level - $itemLevel);
            }

            $lastIndex    = $i;
            $item->active = false;
            $item->flink  = (string) $item->link;

            // Build link (core-like).
            switch ($item->type) {
                case 'separator':
                case 'heading':
                    // No link modification required here; templates may override href.
                    break;

                case 'url':
                    if (
                        str_starts_with((string) $item->link, 'index.php?')
                        && !str_contains((string) $item->link, 'Itemid=')
                    ) {
                        $item->flink = $item->link . '&Itemid=' . (int) $item->id;
                    }
                    break;

                case 'alias':
                    $item->flink = 'index.php?Itemid=' . (int) $itemParams->get('aliasoptions');

                    if (Multilanguage::isEnabled()) {
                        $newItem = $app->getMenu()->getItem((int) $itemParams->get('aliasoptions'));

                        if ($newItem !== null && $newItem->language && $newItem->language !== '*') {
                            $item->flink .= '&lang=' . $newItem->language;
                        }
                    }
                    break;

                default:
                    $item->flink = 'index.php?Itemid=' . (int) $item->id;
                    break;
            }

            // Route (core-like) — BUT keep pure anchors untouched.
            if (str_starts_with((string) $item->flink, '#')) {
                // keep as-is
            } elseif (str_contains((string) $item->flink, 'index.php?') && strcasecmp(substr((string) $item->flink, 0, 4), 'http')) {
                $item->flink = Route::_($item->flink, true, $itemParams->get('secure'));
            } else {
                $item->flink = Route::_($item->flink);
            }

            // Prevent double encoding (core behavior).
            $item->title          = htmlspecialchars((string) $item->title, ENT_COMPAT, 'UTF-8', false);
            $item->menu_icon      = htmlspecialchars((string) $itemParams->get('menu_icon_css', ''), ENT_COMPAT, 'UTF-8', false);
            $item->anchor_css     = htmlspecialchars((string) $itemParams->get('menu-anchor_css', ''), ENT_COMPAT, 'UTF-8', false);
            $item->anchor_title   = htmlspecialchars((string) $itemParams->get('menu-anchor_title', ''), ENT_COMPAT, 'UTF-8', false);
            $item->anchor_rel     = htmlspecialchars((string) $itemParams->get('menu-anchor_rel', ''), ENT_COMPAT, 'UTF-8', false);
            $item->menu_image     = htmlspecialchars((string) $itemParams->get('menu_image', ''), ENT_COMPAT, 'UTF-8', false);
            $item->menu_image_css = htmlspecialchars((string) $itemParams->get('menu_image_css', ''), ENT_COMPAT, 'UTF-8', false);
        }

        // Close flags for last item (core-like).
        if (isset($items[$lastIndex])) {
            $startLevel = ($start ?: 1);

            $items[$lastIndex]->deeper     = ($startLevel > (int) $items[$lastIndex]->level);
            $items[$lastIndex]->shallower  = ($startLevel < (int) $items[$lastIndex]->level);
            $items[$lastIndex]->level_diff = ((int) $items[$lastIndex]->level - $startLevel);
        }

        $items = array_values($items);

        $cache->store($items, $cacheKey);

        return $items;
    }

    /**
     * Get base menu item.
     *
     * @param   Registry                 $params  The module options.
     * @param   CMSApplicationInterface  $app     The application
     *
     * @return  object
     */
    public function getBaseItem(Registry $params, CMSApplicationInterface $app): object
    {
        if ($params->get('base')) {
            $base = $app->getMenu()->getItem((int) $params->get('base'));
        } else {
            $base = false;
        }

        if (!$base) {
            $base = $this->getActiveItem($app);
        }

        return $base;
    }

    /**
     * Get active menu item.
     *
     * @param   CMSApplicationInterface  $app  The application
     *
     * @return  object
     */
    public function getActiveItem(CMSApplicationInterface $app): object
    {
        $menu = $app->getMenu();

        return $menu->getActive() ?: $this->getDefaultItem($app);
    }

    /**
     * Get default menu item (home page) for current language.
     *
     * @param   CMSApplicationInterface  $app  The application
     *
     * @return  object
     */
    public function getDefaultItem(CMSApplicationInterface $app): object
    {
        $menu = $app->getMenu();

        if (Multilanguage::isEnabled()) {
            return $menu->getDefault($app->getLanguage()->getTag());
        }

        return $menu->getDefault();
    }
}
