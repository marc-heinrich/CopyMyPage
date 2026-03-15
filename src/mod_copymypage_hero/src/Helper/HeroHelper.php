<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.6
 */

namespace Joomla\Module\CopyMyPage\Hero\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * Helper class to prepare hero data for the CopyMyPage Hero module.
 *
 * The module is conceptually a hero module for the start page. The current
 * first layout variant is a slideshow, but more hero variants can be added later.
 */
final class HeroHelper
{
    /**
     * Get the slideshow items for the current hero output.
     *
     * @param  object    $module  The module object.
     * @param  Registry  $params  The module parameters.
     *
     * @return array<int, object>
     */
    public function getSlides(object $module, Registry $params): array
    {
        $basePath = rtrim(Uri::root(true), '/') . '/modules/mod_copymypage_hero/images';

        return [
            (object) [
                'src'           => $basePath . '/slide-1.webp',
                'alt'           => 'CopyMyPage hero image 1',
                'headline'      => 'CopyMyPage Hero',
                'subline'       => 'Default slideshow-based hero variant for the start page.',
                'isLazy'        => false,
                'fetchPriority' => 'high',
                'width'         => 1920,
                'height'        => 1280,
            ],
            (object) [
                'src'           => $basePath . '/slide-2.webp',
                'alt'           => 'CopyMyPage hero image 2',
                'headline'      => 'Second slide',
                'subline'       => 'Additional placeholder content for the hero slideshow.',
                'isLazy'        => true,
                'fetchPriority' => 'low',
                'width'         => 1920,
                'height'        => 1281,
            ],
            (object) [
                'src'           => $basePath . '/slide-3.webp',
                'alt'           => 'CopyMyPage hero image 3',
                'headline'      => 'Third slide',
                'subline'       => 'Prepared as a future-ready hero module variant foundation.',
                'isLazy'        => true,
                'fetchPriority' => 'low',
                'width'         => 1920,
                'height'        => 1280,
            ],
            (object) [
                'src'           => $basePath . '/slide-4.webp',
                'alt'           => 'CopyMyPage hero image 4',
                'headline'      => 'Fourth slide',
                'subline'       => 'Static demo slide for the first hero implementation.',
                'isLazy'        => true,
                'fetchPriority' => 'low',
                'width'         => 1920,
                'height'        => 1440,
            ],
        ];
    }

    /**
     * Get the slideshow options for the current hero slideshow variant.
     *
     * @param  object    $module  The module object.
     * @param  Registry  $params  The module parameters.
     *
     * @return string
     */
    public function getSlideshowOptions(object $module, Registry $params): string
    {
        return 'ratio: false; animation: fade; autoplay: true; draggable: true';
    }
}
