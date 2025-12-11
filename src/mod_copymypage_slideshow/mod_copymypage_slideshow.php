<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Module\CopyMyPage\Slideshow\Site\Helper\SlideshowHelper;

/** @var \Joomla\Registry\Registry $params */
/** @var object $module */

// Retrieve slideshow data from the helper.
$slides  = SlideshowHelper::getSlides($module, $params);
$options = SlideshowHelper::getOptions($module, $params);

// Use the PreloadManager to preload the first (non-lazy) slide image.
if (!empty($slides)) {
    $firstSlide = $slides[0];

    if (!empty($firstSlide['src']) && (empty($firstSlide['is_lazy']) || $firstSlide['is_lazy'] === false)) {
        $preloadManager = Factory::getApplication()->getDocument()->getPreloadManager();

        $preloadManager->preload(
            $firstSlide['src'],
            ['as' => 'image']
        );
    }
}

// Load the layout.
require ModuleHelper::getLayoutPath('mod_copymypage_slideshow', $params->get('layout', 'default'));
