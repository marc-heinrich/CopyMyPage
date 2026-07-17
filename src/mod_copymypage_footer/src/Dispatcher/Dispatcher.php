<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Module\CopyMyPage\Footer\Site\Dispatcher;

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
 * Dispatcher for mod_copymypage_footer.
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /** @var array<int, array<string, string>> */
    protected array $warnings = [];

    protected string $layoutPrefix = 'footer';

    protected string $baseLayout = 'default';

    /**
     * Validate the system slot and layout before preparing footer data.
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

        $helper = $this->getHelperFactory()->getHelper('FooterHelper');

        $displayData['footerHelper'] = $helper;
        $displayData['items']        = $helper->getItems();
        $displayData['warning']      = $this->renderWarnings();

        $loader = static function (array $displayData, string $layout): void {
            if (!\array_key_exists('displayData', $displayData)) {
                extract($displayData);
                unset($displayData);
            } else {
                extract($displayData);
            }

            require ModuleHelper::getLayoutPath('mod_copymypage_footer', $layout);
        };

        $loader($displayData, $layout);
    }

    /**
     * Load module and shared CopyMyPage UI languages.
     */
    protected function loadLanguage(): void
    {
        parent::loadLanguage();

        CopyMyPageHelper::loadSharedUiLanguages($this->app->getLanguage());
    }

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

        $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_footer', $layoutVariant);

        if (!is_file($layoutPath) || basename($layoutPath, '.php') !== $layoutVariant) {
            $this->queueInvalidLayoutWarning();

            return $baseLayout;
        }

        return $layoutVariant;
    }

    /**
     * Ensure the module is published in the template's fixed footer slot.
     *
     * @param array<string, mixed> $displayData Prepared display data.
     */
    protected function hasValidSlotPosition(array $displayData): bool
    {
        $slot = strtolower(trim((string) ($displayData['module']->position ?? '')));

        if ($slot === $this->layoutPrefix) {
            return true;
        }

        $this->queueInvalidLayoutWarning();

        return false;
    }

    protected function renderWarnings(): string
    {
        if ($this->warnings === []) {
            return '';
        }

        return LayoutHelper::render('copymypage.system.warning', ['messages' => $this->warnings]);
    }

    protected function queueInvalidLayoutWarning(): void
    {
        if ($this->warnings !== []) {
            return;
        }

        $modulesUrl = Route::link('administrator', 'index.php?option=com_modules&view=modules');

        $this->warnings[] = [
            'info' => Text::_('MOD_COPYMYPAGE_FOOTER'),
            'desc' => Text::sprintf('MOD_COPYMYPAGE_FOOTER_ALERT_INVALID_POSITION', $modulesUrl),
        ];
    }

    /**
     * @return array<string, mixed>|false
     */
    protected function getBaseLayoutData(): array|false
    {
        $data = parent::getLayoutData();

        if ($data === false) {
            return false;
        }

        $data['cfg'] = ($data['params'] instanceof \Joomla\Registry\Registry)
            ? $data['params']->toArray()
            : [];
        $data['footerHelper'] = null;
        $data['items']        = [];
        $data['warning']      = '';

        return $data;
    }
}
