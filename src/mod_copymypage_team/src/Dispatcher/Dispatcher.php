<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.14
 */

namespace Joomla\Module\CopyMyPage\Team\Site\Dispatcher;

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
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;

/**
 * Dispatcher class for mod_copymypage_team.
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
    protected string $layoutPrefix = 'team';

    /**
     * Base layout used as safe fallback.
     *
     * @var string
     */
    protected string $baseLayout = 'cards';

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

        $baseLayout    = $this->resolveBaseLayout();
        $layoutVariant = strtolower(trim((string) ($displayData['cfg']['layoutVariant'] ?? $baseLayout)));
        $layout        = $this->resolveLayout($layoutVariant, $baseLayout);

        $this->populateTeamData($displayData, $layout, $baseLayout);

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
             * @var \stdClass                                      $module
             * @var \Joomla\Registry\Registry                      $params
             * @var \Joomla\CMS\Application\CMSApplicationInterface $app
             * @var array<string, mixed>                           $cfg
             * @var string                                         $eyebrow
             * @var string                                         $headline
             * @var string                                         $lead
             * @var array<int, object>                             $items
             * @var string                                         $warning
             * @var string                                         $hint
             * @var \Joomla\Module\CopyMyPage\Team\Site\Helper\TeamHelper|null $teamHelper
             */

            require ModuleHelper::getLayoutPath('mod_copymypage_team', $layout);
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
     * Resolves the requested layout variant to an existing team layout.
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

        $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_team', $layoutVariant);

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
     * Add the team layout/slot warning once per render cycle.
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
            'info' => Text::_('MOD_COPYMYPAGE_TEAM'),
            'desc' => Text::sprintf('MOD_COPYMYPAGE_TEAM_ALERT_INVALID_POSITION', $modulesUrl),
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

        $items = \is_array($displayData['items'] ?? null)
            ? $displayData['items']
            : [];

        if ($items !== []) {
            return;
        }

        $displayData['hint'] = LayoutHelper::render(
            'copymypage.system.hint',
            [
                'messages' => [
                    [
                        'info' => Text::_('MOD_COPYMYPAGE_TEAM_HINT_INFO'),
                        'desc' => Text::_('MOD_COPYMYPAGE_TEAM_HINT_DESC'),
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
        $data['teamHelper'] = null;
        $data['eyebrow']    = '';
        $data['headline']   = '';
        $data['lead']       = '';
        $data['items']      = [];
        $data['warning']    = '';
        $data['hint']       = '';

        return $data;
    }

    /**
     * Populate team-specific data after slot and layout validation.
     *
     * @param   array<string, mixed>  $displayData  Prepared display data.
     * @param   string                $layout       Validated layout key.
     * @param   string                $baseLayout   Resolved fallback layout key.
     *
     * @return  void
     */
    protected function populateTeamData(array &$displayData, string $layout, string $baseLayout): void
    {
        $helper = $this->getHelperFactory()->getHelper('TeamHelper');

        if (method_exists($helper, 'setLayoutContext')) {
            $helper->setLayoutContext($baseLayout, $this->layoutPrefix);
        }

        $displayData['teamHelper'] = $helper;
        $displayData['eyebrow']    = $helper->getEyebrow($displayData['cfg'], $layout);
        $displayData['headline']   = $helper->getHeadline($displayData['cfg'], $layout);
        $displayData['lead']       = $helper->getLead($displayData['cfg'], $layout);
        $displayData['items']      = $helper->getItems($displayData['cfg'], $layout);

        $this->applyFeedback($displayData);
    }
}
