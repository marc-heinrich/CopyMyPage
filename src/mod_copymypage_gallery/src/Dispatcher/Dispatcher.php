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
    protected string $baseLayout = 'sigplus_preview';

    /**
     * Runs the dispatcher.
     *
     * @return void
     */
    public function dispatch(): void
    {
        $this->loadLanguage();

        $displayData = $this->getBaseLayoutData();

        if ($displayData === false) {
            return;
        }

        if (!$this->hasValidSlotPosition($displayData)) {
            echo $this->renderWarnings();

            return;
        }

        $this->populateGalleryData($displayData);

        $baseLayout    = $this->resolveBaseLayout();
        $layoutVariant = strtolower(trim((string) ($displayData['cfg']['layoutVariant'] ?? $baseLayout)));
        $layout        = $this->resolveLayout($layoutVariant, $baseLayout);
        $displayData['warning'] = $this->renderWarnings();

        if ($displayData['warning'] !== '') {
            $displayData['hint'] = '';
        }

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
     * @return  string
     */
    protected function resolveBaseLayout(): string
    {
        $layoutPrefix = strtolower(trim($this->layoutPrefix));
        $baseLayout   = strtolower(trim($this->baseLayout));

        if ($baseLayout === '') {
            return $layoutPrefix;
        }

        if ($layoutPrefix !== '' && !str_starts_with($baseLayout, $layoutPrefix . '_')) {
            return $layoutPrefix . '_' . $baseLayout;
        }

        return $baseLayout;
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
            $this->queueInvalidLayoutWarning();

            return $baseLayout;
        }

        $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_gallery', $layoutVariant);

        if (!is_file($layoutPath) || basename($layoutPath, '.php') !== $layoutVariant) {
            $this->queueInvalidLayoutWarning();

            return $baseLayout;
        }

        return $layoutVariant;
    }

    /**
     * Check whether the current module instance is published in the expected system slot.
     *
     * @param   array<string, mixed>  $displayData  Prepared display data.
     *
     * @return  bool
     */
    protected function hasValidSlotPosition(array $displayData): bool
    {
        $slot         = strtolower(trim((string) ($displayData['module']->position ?? '')));
        $expectedSlot = strtolower(trim($this->layoutPrefix));

        if ($slot === $expectedSlot) {
            return true;
        }

        $this->queueInvalidLayoutWarning();

        return false;
    }

    /**
     * Render collected warnings via the shared system layout.
     *
     * @return  string
     */
    protected function renderWarnings(): string
    {
        if ($this->warnings === []) {
            return '';
        }

        return LayoutHelper::render(
            'copymypage.system.warning',
            ['messages' => $this->warnings]
        );
    }

    /**
     * Add the gallery layout/slot warning once per render cycle.
     *
     * @return  void
     */
    protected function queueInvalidLayoutWarning(): void
    {
        if ($this->warnings !== []) {
            return;
        }

        $modulesUrl = Route::link('administrator', 'index.php?option=com_modules&view=modules');

        $this->warnings[] = [
            'info' => Text::_('MOD_COPYMYPAGE_GALLERY'),
            'desc' => Text::sprintf('MOD_COPYMYPAGE_GALLERY_ALERT_INVALID_POSITION', $modulesUrl),
        ];
    }

    /**
     * Apply rendered warnings or empty-state hints after all data sources are resolved.
     *
     * @param   array<string, mixed>  $displayData  Prepared display data.
     *
     * @return  void
     */
    protected function applyFeedback(array &$displayData): void
    {
        $displayData['warning'] = '';
        $displayData['hint']    = '';

        if ($this->warnings !== []) {
            $displayData['warning'] = $this->renderWarnings();

            return;
        }

        $list = \is_array($displayData['list'] ?? null)
            ? $displayData['list']
            : [];

        if ($list !== []) {
            return;
        }

        $displayData['hint'] = LayoutHelper::render(
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

    /**
     * Prepare the raw display data before slot and layout validation.
     *
     * @return array<string, mixed>|false
     */
    protected function getBaseLayoutData(): array|false
    {
        $data = parent::getLayoutData();

        $data['cfg'] = ($data['params'] instanceof \Joomla\Registry\Registry)
            ? $data['params']->toArray()
            : [];
        $data['list']    = [];
        $data['filters'] = [];
        $data['warning'] = '';
        $data['hint']    = '';

        return $data;
    }

    /**
     * Populate gallery-specific data after the module position has been validated.
     *
     * @param   array<string, mixed>  $displayData  Prepared display data.
     *
     * @return  void
     */
    protected function populateGalleryData(array &$displayData): void
    {
        $helper = $this->getHelperFactory()->getHelper('GalleryHelper');

        $sigplusPlugin   = $helper->getSigplusPlugin();

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
            $displayData['list']    = $helper->getSigplusModules();
            $displayData['filters'] = $helper->listUnique($displayData['list']);
        }

        $this->applyFeedback($displayData);
    }
}
