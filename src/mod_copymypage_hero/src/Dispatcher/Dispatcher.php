<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.5
 */

namespace Joomla\Module\CopyMyPage\Hero\Site\Dispatcher;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Helper\ModuleHelper;

/**
 * Dispatcher class for mod_copymypage_hero.
 *
 * Resolves a base hero layout and optional hero layout variants.
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Runs the dispatcher.
     *
     * @return void
     */
    public function dispatch(): void
    {
        $this->loadLanguage();

        $displayData = $this->getLayoutData();

        // Stop when display data is false.
        if ($displayData === false) {
            return;
        }

        $baseLayout    = 'hero';
        $layoutVariant = strtolower(trim((string) ($displayData['cfg']['layoutVariant'] ?? 'default')));
        $layout        = $baseLayout;

        if (
            $layoutVariant !== ''
            && $layoutVariant !== 'default'
            && str_starts_with($layoutVariant, $baseLayout . '_')
        ) {
            $layout = $layoutVariant;
        }

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
             * @var array<string, mixed>      $cfg
             * @var array<int, object>        $slides
             * @var string                    $slideshowOptions
             * @var string                    $moduleclass_sfx
             */

            $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_hero', $layout);

            if (!is_file($layoutPath)) {
                $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_hero', $fallbackLayout);
            }

            require $layoutPath;
        };

        $loader($displayData, $layout, $baseLayout);
    }

    /**
     * Returns the layout data.
     *
     * @return array<string, mixed>|false
     */
    protected function getLayoutData(): array|false
    {
        $data   = parent::getLayoutData();
        $helper = $this->getHelperFactory()->getHelper('HeroHelper');

        $data['cfg'] = ($data['params'] instanceof \Joomla\Registry\Registry)
            ? $data['params']->toArray()
            : [];
        $data['slides']           = $helper->getSlides($data['module'], $data['params']);
        $data['slideshowOptions'] = $helper->getSlideshowOptions($data['module'], $data['params']);

        // Preload the first (non-lazy) slide image via Joomla's PreloadManager.
        if (!empty($data['slides'])) {
            $firstSlide = $data['slides'][0];

            if (
                \is_object($firstSlide)
                && !empty($firstSlide->src)
                && (empty($firstSlide->isLazy) || $firstSlide->isLazy === false)
            ) {
                $preloadManager = Factory::getApplication()->getDocument()->getPreloadManager();
                $preloadManager->preload($firstSlide->src, ['as' => 'image']);
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
