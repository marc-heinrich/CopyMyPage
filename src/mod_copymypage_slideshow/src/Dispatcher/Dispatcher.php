<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

namespace Joomla\Module\CopyMyPage\Slideshow\Site\Dispatcher;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Helper\ModuleHelper;

/**
 * Dispatcher class for mod_copymypage_slideshow.
 *
 * Currently uses a single layout ("default"). Future layout variants can be added later.
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Runs the dispatcher.
     *
     * @return void
     */
    public function dispatch()
    {
        $this->loadLanguage();

        $displayData = $this->getLayoutData();

        // Stop when display data is false.
        if ($displayData === false) {
            return;
        }

        $layout = (string) $displayData['params']->get('layout', 'default');

        // Execute the layout without the module context (core pattern).
        $loader = static function (array $displayData, string $layout, string $fallbackLayout): void {
            // If $displayData doesn't exist in extracted data, unset the variable.
            if (!\array_key_exists('displayData', $displayData)) {
                extract($displayData);
                unset($displayData);
            } else {
                extract($displayData);
            }

            /**
             * Extracted variables
             * -----------------
             * @var \stdClass                 $module
             * @var \Joomla\Registry\Registry $params
             * @var \Joomla\CMS\Application\CMSApplicationInterface $app
             * @var array<int, array<string, mixed>> $slides
             * @var string                   $options
             * @var string                   $moduleclass_sfx
             */

            $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_slideshow', $layout);

            if (!is_file($layoutPath)) {
                $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_slideshow', $fallbackLayout);
            }

            require $layoutPath;
        };

        $loader($displayData, $layout, 'default');
    }

    /**
     * Returns the layout data.
     *
     * @return array<string, mixed>|false
     */
    protected function getLayoutData()
    {
        $data   = parent::getLayoutData();
        $helper = $this->getHelperFactory()->getHelper('SlideshowHelper');

        $data['slides']  = $helper::getSlides($data['module'], $data['params']);
        $data['options'] = $helper::getOptions($data['module'], $data['params']);

        // Preload the first (non-lazy) slide image via Joomla's PreloadManager.
        if (!empty($data['slides'])) {
            $firstSlide = $data['slides'][0];

            if (!empty($firstSlide['src']) && (empty($firstSlide['is_lazy']) || $firstSlide['is_lazy'] === false)) {
                $preloadManager = Factory::getApplication()->getDocument()->getPreloadManager();
                $preloadManager->preload($firstSlide['src'], ['as' => 'image']);
            }
        }

        // Core-style escaping.
        $data['moduleclass_sfx'] = htmlspecialchars(
            (string) $data['params']->get('moduleclass_sfx', ''),
            ENT_COMPAT,
            'UTF-8'
        );

        return $data;
    }
}
