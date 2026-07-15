<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.16
 */

namespace Joomla\Module\CopyMyPage\Contact\Site\Dispatcher;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;

/**
 * Dispatcher class for mod_copymypage_contact.
 */
final class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Collected warning messages for the current module render cycle.
     *
     * @var array<int, array<string, string>>
     */
    private array $warnings = [];

    /**
     * Fixed layout prefix and system slot.
     *
     * @var string
     */
    private string $layoutPrefix = 'contact';

    /**
     * Base layout used as safe fallback.
     *
     * @var string
     */
    private string $baseLayout = 'default';

    /**
     * Runs the dispatcher.
     *
     * @return  void
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

        $this->populateContactData($displayData, $layout, $baseLayout);

        $displayData['warning'] = $this->renderWarnings();

        $loader = static function (array $displayData, string $layout): void {
            if (!\array_key_exists('displayData', $displayData)) {
                extract($displayData);
                unset($displayData);
            } else {
                extract($displayData);
            }

            require ModuleHelper::getLayoutPath('mod_copymypage_contact', $layout);
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
     * Resolve the complete base layout key.
     *
     * @return  string
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
     * Resolve and validate the requested layout.
     *
     * @param   string  $layoutVariant  Requested layout.
     * @param   string  $baseLayout     Safe fallback layout.
     *
     * @return  string
     */
    private function resolveLayout(string $layoutVariant, string $baseLayout): string
    {
        $layoutPrefix = strtolower(trim($this->layoutPrefix));

        if ($layoutVariant === '' || $layoutVariant === 'default') {
            return $baseLayout;
        }

        if ($layoutPrefix !== '' && !str_starts_with($layoutVariant, $layoutPrefix . '_')) {
            $this->queueWarning(
                Text::_('MOD_COPYMYPAGE_CONTACT'),
                Text::sprintf('MOD_COPYMYPAGE_CONTACT_ALERT_INVALID_POSITION', $this->getModulesUrl())
            );

            return $baseLayout;
        }

        $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_contact', $layoutVariant);

        if (!is_file($layoutPath) || basename($layoutPath, '.php') !== $layoutVariant) {
            $this->queueWarning(
                Text::_('MOD_COPYMYPAGE_CONTACT'),
                Text::sprintf('MOD_COPYMYPAGE_CONTACT_ALERT_INVALID_POSITION', $this->getModulesUrl())
            );

            return $baseLayout;
        }

        return $layoutVariant;
    }

    /**
     * Validate that this system module is published in the contact slot.
     *
     * @param   array<string, mixed>  $displayData  Prepared display data.
     *
     * @return  bool
     */
    private function hasValidSlotPosition(array $displayData): bool
    {
        $slot = strtolower(trim((string) ($displayData['module']->position ?? '')));

        if ($slot === $this->layoutPrefix) {
            return true;
        }

        $this->queueWarning(
            Text::_('MOD_COPYMYPAGE_CONTACT'),
            Text::sprintf('MOD_COPYMYPAGE_CONTACT_ALERT_INVALID_POSITION', $this->getModulesUrl())
        );

        return false;
    }

    /**
     * Prepare raw layout data.
     *
     * @return  array<string, mixed>|false
     */
    private function getBaseLayoutData(): array|false
    {
        $data = parent::getLayoutData();

        if ($data === false) {
            return false;
        }

        $data['cfg']           = $data['params'] instanceof \Joomla\Registry\Registry
            ? $data['params']->toArray()
            : [];
        $data['contactHelper'] = null;
        $data['layout']        = '';
        $data['eyebrow']       = '';
        $data['headline']      = '';
        $data['lead']          = '';
        $data['infoItems']     = [];
        $data['mapUrl']        = '';
        $data['mapTitle']      = '';
        $data['form']          = null;
        $data['showCopy']      = false;
        $data['warning']       = '';

        return $data;
    }

    /**
     * Populate contact-specific display data.
     *
     * @param   array<string, mixed>  $displayData  Prepared display data.
     * @param   string                $layout       Validated layout.
     * @param   string                $baseLayout   Safe fallback layout.
     *
     * @return  void
     */
    private function populateContactData(array &$displayData, string $layout, string $baseLayout): void
    {
        $helper = $this->getHelperFactory()->getHelper('ContactHelper');

        if (method_exists($helper, 'setLayoutContext')) {
            $helper->setLayoutContext($baseLayout, $this->layoutPrefix);
        }

        $displayData['contactHelper'] = $helper;
        $displayData['layout']        = $layout;
        $displayData['eyebrow']       = $helper->getEyebrow($displayData['cfg'], $layout);
        $displayData['headline']      = $helper->getHeadline($displayData['cfg'], $layout);
        $displayData['lead']          = $helper->getLead($displayData['cfg'], $layout);
        $displayData['infoItems']     = $helper->getInfoItems($displayData['cfg'], $layout, $this->app);
        $displayData['mapUrl']        = $helper->getMapUrl($displayData['cfg'], $layout);
        $displayData['mapTitle']      = $helper->getMapTitle($displayData['cfg'], $layout);
        $displayData['showCopy']      = $helper->getShowCopy($displayData['cfg'], $layout);
        $this->checkRuntimeRequirements($displayData, $helper, $layout);

        if ($this->warnings === []) {
            $displayData['form'] = $helper->getContactForm(
                $displayData['params'],
                $this->app,
                (int) ($displayData['module']->id ?? 0)
            );

            if (!$displayData['form']) {
                $this->queueWarning(
                    Text::_('MOD_COPYMYPAGE_CONTACT'),
                    Text::_('MOD_COPYMYPAGE_CONTACT_ALERT_FORM_UNAVAILABLE')
                );
            }
        }
    }

    /**
     * Check runtime dependencies before rendering the form.
     *
     * @param   array<string, mixed>  $displayData  Prepared display data.
     * @param   object                $helper       Contact helper.
     * @param   string                $layout       Validated layout.
     *
     * @return  void
     */
    private function checkRuntimeRequirements(array $displayData, object $helper, string $layout): void
    {
        if (!$this->app->get('mailonline', 1)) {
            $configUrl = Route::link('administrator', 'index.php?option=com_config#page-server');
            $this->queueWarning(
                Text::_('MOD_COPYMYPAGE_CONTACT_ALERT_MAIL_DISABLED_INFO'),
                Text::sprintf('MOD_COPYMYPAGE_CONTACT_ALERT_MAIL_DISABLED_DESC', $configUrl)
            );
        }

        if (!PluginHelper::isEnabled('content', 'confirmconsent')) {
            $pluginsUrl = Route::link('administrator', 'index.php?option=com_plugins&view=plugins');
            $this->queueWarning(
                Text::_('MOD_COPYMYPAGE_CONTACT_ALERT_CONSENT_DISABLED_INFO'),
                Text::sprintf('MOD_COPYMYPAGE_CONTACT_ALERT_CONSENT_DISABLED_DESC', $pluginsUrl)
            );
        }

        $recipient = $helper->getRecipientEmail($displayData['cfg'], $layout, $this->app);

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->queueWarning(
                Text::_('MOD_COPYMYPAGE_CONTACT_ALERT_RECIPIENT_MISSING_INFO'),
                Text::sprintf('MOD_COPYMYPAGE_CONTACT_ALERT_RECIPIENT_MISSING_DESC', $this->getModulesUrl())
            );
        }

        if (!is_file(JPATH_SITE . '/components/com_copymypage/src/Controller/ContactController.php')) {
            $this->queueWarning(
                Text::_('MOD_COPYMYPAGE_CONTACT_ALERT_CONTROLLER_MISSING_INFO'),
                Text::_('MOD_COPYMYPAGE_CONTACT_ALERT_CONTROLLER_MISSING_DESC')
            );
        }
    }

    /**
     * Add one warning message.
     *
     * @param   string  $info  Warning heading.
     * @param   string  $desc  Warning description.
     *
     * @return  void
     */
    private function queueWarning(string $info, string $desc): void
    {
        $this->warnings[] = [
            'info' => $info,
            'desc' => $desc,
        ];
    }

    /**
     * Render warnings through the shared CopyMyPage layout.
     *
     * @return  string
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
     * Return the administrator module manager URL.
     *
     * @return  string
     */
    private function getModulesUrl(): string
    {
        return Route::link('administrator', 'index.php?option=com_modules&view=modules');
    }
}
