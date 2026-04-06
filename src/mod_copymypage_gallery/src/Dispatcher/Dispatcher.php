<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.10
 */

namespace Joomla\Module\CopyMyPage\Gallery\Site\Dispatcher;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/**
 * Dispatcher class for mod_copymypage_gallery.
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
    protected string $layoutPrefix = 'gallery';

    /**
     * Base layout used as safe fallback.
     *
     * @var string
     */
    protected string $baseLayout = 'gallery_sigplus_preview';

    /**
     * Runs the dispatcher.
     *
     * @return void
     */
    public function dispatch(): void
    {
        $this->loadLanguage();

        $displayData = $this->getLayoutData();

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
             * @var array<string, mixed>      $cfg
             * @var array<int, object>        $list
             * @var array<int, string>        $filters
             * @var string                    $warning
             * @var string                    $hint
             */

            require ModuleHelper::getLayoutPath('mod_copymypage_gallery', $layout);
        };

        $loader($displayData, $layout);
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
     * Resolves the requested layout variant to an existing gallery layout.
     *
     * @param   string  $layoutVariant  Requested layout variant from module params.
     * @param   string  $baseLayout     Existing fallback layout for this module instance.
     *
     * @return  string
     */
    protected function resolveLayout(string $layoutVariant, string $baseLayout): string
    {
        $layoutPrefix = strtolower(trim($this->layoutPrefix));

        if ($layoutVariant === '' || $layoutVariant === 'default') {
            return $baseLayout;
        }

        if ($layoutPrefix !== '' && !str_starts_with($layoutVariant, $layoutPrefix . '_')) {
            return $baseLayout;
        }

        $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_gallery', $layoutVariant);

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
        $helper = $this->getHelperFactory()->getHelper('GalleryHelper');

        $data['cfg'] = ($data['params'] instanceof \Joomla\Registry\Registry)
            ? $data['params']->toArray()
            : [];

        $sigplusPlugin   = $helper->getSigplusPlugin();
        $data['list']    = [];
        $data['filters'] = [];

        if ($sigplusPlugin === null) {
            $this->warnings[] = [
                'info' => Text::_('MOD_COPYMYPAGE_GALLERY_MSG_NOSIGPLUS_INFO'),
                'desc' => Text::_('MOD_COPYMYPAGE_GALLERY_MSG_NOSIGPLUS_DESC'),
            ];
        } elseif (!$helper->isSigplusAvailable($sigplusPlugin)) {
            $pluginLink = Route::link(
                'administrator',
                'index.php?option=com_plugins&task=plugin.edit&extension_id=' . (int) $sigplusPlugin->id
            );

            $this->warnings[] = [
                'info' => Text::_('MOD_COPYMYPAGE_GALLERY_MSG_SIGPLUS_DISABLED_INFO'),
                'desc' => Text::sprintf('MOD_COPYMYPAGE_GALLERY_MSG_SIGPLUS_DISABLED_DESC', $pluginLink),
            ];
        } else {
            $data['list']    = $helper->getSigplusModules();
            $data['filters'] = $helper->listUnique($data['list']);
        }

        $data['warning'] = '';
        $data['hint']    = '';

        if (!empty($this->warnings)) {
            $data['warning'] = LayoutHelper::render(
                'copymypage.system.warning',
                ['messages' => $this->warnings]
            );
        } elseif ($data['list'] === []) {
            $data['hint'] = LayoutHelper::render(
                'copymypage.system.hint',
                [
                    'messages' => [
                        [
                            'info' => Text::_('MOD_COPYMYPAGE_GALLERY_HINT_INFO'),
                            'desc' => Text::_('MOD_COPYMYPAGE_GALLERY_HINT_DESC'),
                        ],
                    ],
                ]
            );
        }

        return $data;
    }
}
