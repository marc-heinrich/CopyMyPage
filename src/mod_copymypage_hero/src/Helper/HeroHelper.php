<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.7
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
                'src'           => $basePath . '/slide_1.jpg',
                'alt'           => 'CopyMyPage hero image 1',
                'headline'      => 'Fernbreitenbach Helau',
                'subline'       => 'Willkommen auf der Website des Fernbreiterbacher Carneval-Vereins',
                'isLazy'        => false,
                'fetchPriority' => 'high',
                'width'         => 1920,
                'height'        => 1280,
            ],
            (object) [
                'src'           => $basePath . '/slide_2.jpg',
                'alt'           => 'CopyMyPage hero image 2',
                'headline'      => 'Feiern, lachen, leben – Carneval verbindet!',
                'subline'       => 'ohne',
                'isLazy'        => true,
                'fetchPriority' => 'low',
                'width'         => 1920,
                'height'        => 1280,
            ],
            (object) [
                'src'           => $basePath . '/slide_3.jpg',
                'alt'           => 'CopyMyPage hero image 3',
                'headline'      => 'Junge Jecken, großer Spaß – Wir machen den Carneval von morgen!',
                'subline'       => 'ohne',
                'isLazy'        => true,
                'fetchPriority' => 'low',
                'width'         => 1920,
                'height'        => 1280,
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
