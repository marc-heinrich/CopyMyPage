<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

namespace Joomla\Module\CopyMyPage\Slideshow\Site\Helper;

\defined('_JEXEC') or die;

/**
 * Helper class to prepare slideshow data for the CopyMyPage Slideshow module.
 *
 * For dev0.0.4 all slide definitions and slideshow options are static.
 * Later, this class will read the configuration from the module parameters
 * and/or the database.
 */
class SlideshowHelper
{
    /**
     * Get the slideshow items for the module output.
     *
     * @param  object                     $module  The module object.
     * @param  \Joomla\Registry\Registry  $params  The module parameters.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getSlides($module, $params): array
    {
        // Base path for the slideshow images (adjust as needed).
        $basePath = 'images/copymypage/module/mod_copymypage_slideshow';

        // Static slide definitions for dev0.0.4.
        // Later, this structure will be built from $params.
        $slides = [
            [
                'src'      => $basePath . '/slide-1.webp',
                'alt'      => 'CopyMyPage slideshow image 1',
                'headline' => 'CopyMyPage Slideshow',
                'subline'  => 'Hero slide (static definition in dev0.0.4).',
                // First slide: visible on initial load, no lazy loading.
                'is_lazy'  => false,
            ],
            [
                'src'      => $basePath . '/slide-2.webp',
                'alt'      => 'CopyMyPage slideshow image 2',
                'headline' => 'Second slide',
                'subline'  => 'Additional content for testing the slideshow.',
                'is_lazy'  => true,
            ],
            [
                'src'      => $basePath . '/slide-3.webp',
                'alt'      => 'CopyMyPage slideshow image 3',
                'headline' => 'Third slide',
                'subline'  => 'Placeholder slide, will later be fed from params.',
                'is_lazy'  => true,
            ],
            [
                'src'      => $basePath . '/slide-4.webp',
                'alt'      => 'CopyMyPage slideshow image 4',
                'headline' => 'Fourth slide',
                'subline'  => 'Static dev0.0.4 demo slide.',
                'is_lazy'  => true,
            ],
        ];

        return $slides;
    }

    /**
     * Get the slideshow options for the uk-slideshow attribute.
     *
     * For dev0.0.4 this is a static string. Later it will be built from $params.
     *
     * @param  object                     $module  The module object.
     * @param  \Joomla\Registry\Registry  $params  The module parameters.
     *
     * @return string
     */
    public static function getOptions($module, $params): string
    {
        // Static default options for UIKit slideshow.
        // Example: full-width, 16:9 ratio, fade animation, autoplay enabled.
        $options = 'ratio: false; animation: fade; autoplay: true; draggable: true';

        return $options;
    }
}
