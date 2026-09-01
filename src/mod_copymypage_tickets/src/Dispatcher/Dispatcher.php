<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Module\CopyMyPage\Tickets\Site\Dispatcher;

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
 * Dispatcher for mod_copymypage_tickets.
 */
final class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Collected warning messages for the current render cycle.
     *
     * @var array<int, array<string, string>>
     */
    private array $warnings = [];

    /**
     * Fixed system position and layout prefix.
     */
    private string $layoutPrefix = 'tickets';

    /**
     * Safe layout fallback.
     */
    private string $baseLayout = 'default';

    /**
     * Render the module through the validated layout contract.
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

        $this->populateTicketsData($displayData, $layout, $baseLayout);

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

            require ModuleHelper::getLayoutPath('mod_copymypage_tickets', $layout);
        };

        $loader($displayData, $layout);
    }

    /**
     * Load the module and shared CopyMyPage UI language strings.
     */
    protected function loadLanguage(): void
    {
        parent::loadLanguage();

        CopyMyPageHelper::loadSharedUiLanguages($this->app->getLanguage());
    }

    /**
     * Resolve the prefixed fallback layout.
     */
    private function resolveBaseLayout(): string
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
     * Resolve a requested layout without allowing arbitrary template paths.
     */
    private function resolveLayout(string $layoutVariant, string $baseLayout): string
    {
        $layoutPrefix = strtolower(trim($this->layoutPrefix));

        if ($layoutVariant === '' || $layoutVariant === 'default') {
            return $baseLayout;
        }

        if ($layoutPrefix !== '' && !str_starts_with($layoutVariant, $layoutPrefix . '_')) {
            $this->queueInvalidLayoutWarning();

            return $baseLayout;
        }

        $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_tickets', $layoutVariant);

        if (!is_file($layoutPath) || basename($layoutPath, '.php') !== $layoutVariant) {
            $this->queueInvalidLayoutWarning();

            return $baseLayout;
        }

        return $layoutVariant;
    }

    /**
     * Enforce the landing-page system position expected by CopyMyPage.
     *
     * @param   array<string, mixed>  $displayData  Prepared display data.
     */
    private function hasValidSlotPosition(array $displayData): bool
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
     * Render collected warnings through the shared CopyMyPage layout.
     */
    private function renderWarnings(): string
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
     * Queue the layout/position warning once.
     */
    private function queueInvalidLayoutWarning(): void
    {
        if ($this->warnings !== []) {
            return;
        }

        $modulesUrl = Route::link('administrator', 'index.php?option=com_modules&view=modules');

        $this->warnings[] = [
            'info' => Text::_('MOD_COPYMYPAGE_TICKETS'),
            'desc' => Text::sprintf('MOD_COPYMYPAGE_TICKETS_ALERT_INVALID_POSITION', $modulesUrl),
        ];
    }

    /**
     * Queue a safe dependency/data warning without exposing implementation details.
     */
    private function queueDataWarning(): void
    {
        if ($this->warnings !== []) {
            return;
        }

        $this->warnings[] = [
            'info' => Text::_('MOD_COPYMYPAGE_TICKETS'),
            'desc' => Text::_('MOD_COPYMYPAGE_TICKETS_ALERT_DATA_UNAVAILABLE'),
        ];
    }

    /**
     * Add either a warning or the public empty-state hint.
     *
     * @param   array<string, mixed>  $displayData  Prepared display data.
     */
    private function applyFeedback(array &$displayData): void
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
                        'info' => Text::_('MOD_COPYMYPAGE_TICKETS_HINT_INFO'),
                        'desc' => Text::_('MOD_COPYMYPAGE_TICKETS_HINT_DESC'),
                    ],
                ],
            ]
        );
    }

    /**
     * Prepare the common layout variables.
     *
     * @return array<string, mixed>|false
     */
    private function getBaseLayoutData(): array|false
    {
        $data = parent::getLayoutData();

        $data['cfg'] = ($data['params'] instanceof \Joomla\Registry\Registry)
            ? $data['params']->toArray()
            : [];
        $data['ticketsHelper']   = null;
        $data['clientConfig']    = [];
        $data['markupAttributes'] = [];
        $data['markupClasses']    = [];
        $data['eyebrow']         = '';
        $data['headline']        = '';
        $data['lead']            = '';
        $data['items']           = [];
        $data['warning']         = '';
        $data['hint']            = '';

        return $data;
    }

    /**
     * Resolve DPCalendar data and presentation settings through the module helper.
     *
     * @param   array<string, mixed>  $displayData  Prepared display data.
     * @param   string                $layout       Validated layout key.
     * @param   string                $baseLayout   Validated fallback layout key.
     */
    private function populateTicketsData(array &$displayData, string $layout, string $baseLayout): void
    {
        try {
            $helper = $this->getHelperFactory()->getHelper('TicketsHelper');

            if (method_exists($helper, 'setLayoutContext')) {
                $helper->setLayoutContext($baseLayout, $this->layoutPrefix);
            }

            $displayData['ticketsHelper']    = $helper;
            $displayData['clientConfig']     = $helper->getClientConfig();
            $displayData['markupAttributes'] = $helper->getMarkupAttributes();
            $displayData['markupClasses']    = $helper->getMarkupClasses();
            $displayData['eyebrow']          = $helper->getEyebrow($displayData['cfg'], $layout);
            $displayData['headline']         = $helper->getHeadline($displayData['cfg'], $layout);
            $displayData['lead']             = $helper->getLead($displayData['cfg'], $layout);
            $displayData['items']            = $helper->getItems($displayData['cfg'], $layout);
        } catch (\Throwable) {
            $this->queueDataWarning();
        }

        $this->applyFeedback($displayData);
    }
}
