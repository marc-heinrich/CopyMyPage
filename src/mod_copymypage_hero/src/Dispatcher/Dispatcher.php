<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.10
 */

namespace Joomla\Module\CopyMyPage\Hero\Site\Dispatcher;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;

/**
 * Dispatcher class for mod_copymypage_hero.
 *
 * Resolves a base hero layout and optional hero layout variants.
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Collected warning messages for the current module render cycle.
     *
     * @var array<int, array<string, string>>
     */
    protected array $warnings = [];

    /**
     * Fixed layout prefix for this system slot.
     *
     * @var string
     */
    protected string $layoutPrefix = 'hero';

    /**
     * Base layout used as safe fallback.
     *
     * @var string
     */
    protected string $baseLayout = 'hero_slideshow';

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

        $baseLayout    = $this->resolveBaseLayout($displayData);
        $layoutVariant = strtolower(trim((string) ($displayData['cfg']['layoutVariant'] ?? $baseLayout)));
        $layout        = $this->resolveLayout($layoutVariant, $baseLayout);

        $loader = static function (array $displayData, string $layout): void {
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
            require ModuleHelper::getLayoutPath('mod_copymypage_hero', $layout);
        };

        $loader($displayData, $layout);
    }

    /**
     * Load module and shared CopyMyPage UI languages.
     *
     * @return  void
     */
    protected function loadLanguage(): void
    {
        parent::loadLanguage();

        CopyMyPageHelper::loadSharedUiLanguages($this->app->getLanguage());
    }

    /**
     * Resolves the base layout for this module instance.
     *
     * @param   array<string, mixed>  $displayData  Prepared display data.
     *
     * @return  string
     */
    protected function resolveBaseLayout(array $displayData): string
    {
        return $this->baseLayout;
    }

    /**
     * Resolves the requested layout variant to an existing hero layout.
     *
     * @param   string  $layoutVariant  Requested layout variant from module params.
     * @param   string  $baseLayout     Existing fallback layout for this module instance.
     *
     * @return  string
     */
    protected function resolveLayout(string $layoutVariant, string $baseLayout): string
    {
        $layoutPrefix = strtolower(trim($this->layoutPrefix));

        if ($layoutVariant === '') {
            return $baseLayout;
        }

        if ($layoutPrefix !== '' && !str_starts_with($layoutVariant, $layoutPrefix . '_')) {
            return $baseLayout;
        }

        $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_hero', $layoutVariant);

        if (!is_file($layoutPath) || basename($layoutPath, '.php') !== $layoutVariant) {
            return $baseLayout;
        }

        return $layoutVariant;
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

        // Core-style escaping.
        $data['moduleclass_sfx'] = htmlspecialchars(
            (string) $data['params']->get('moduleclass_sfx', ''),
            ENT_COMPAT,
            'UTF-8'
        );

        return $data;
    }
}
