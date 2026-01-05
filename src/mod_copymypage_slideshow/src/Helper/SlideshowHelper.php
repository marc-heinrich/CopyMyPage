<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

namespace Joomla\Module\CopyMyPage\Slideshow\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

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
     * @param  object    $module  The module object.
     * @param  Registry  $params  The module parameters.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getSlides(object $module, Registry $params): array
    {
        $basePath = rtrim(Uri::root(true), '/') . '/images/copymypage/module/mod_copymypage_slideshow';

        return [
            [
                'src'      => $basePath . '/slide-1.webp',
                'alt'      => 'CopyMyPage slideshow image 1',
                'headline' => 'CopyMyPage Slideshow',
                'subline'  => 'Hero slide (static definition in dev0.0.4).',
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
    }

    /**
     * Get the slideshow options for the uk-slideshow attribute.
     *
     * @param  object    $module  The module object.
     * @param  Registry  $params  The module parameters.
     *
     * @return string
     */
    public static function getOptions(object $module, Registry $params): string
    {
        return 'ratio: false; animation: fade; autoplay: true; draggable: true';
    }
}
