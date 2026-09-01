<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Site\View\Customerdata;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\CopyMyPage\Site\Service\CustomerDataService;

/**
 * Guarded customer and invoice-data form.
 */
final class HtmlView extends BaseHtmlView
{
    protected bool $accountCreated = false;

    protected bool $accountExpanded = false;

    protected ?Form $accountForm = null;

    protected bool $blocked = true;

    protected bool $captchaEnabled = false;

    protected int $cartRevision = 0;

    protected ?Form $form = null;

    protected bool $guest = true;

    protected ?Form $loginForm = null;

    protected bool $loginModeActive = false;

    /** @var array<string, string> */
    protected array $formFieldNames = [];

    /** @var array<string, string> */
    protected array $markupAttributes = [];

    protected bool $showAccountOption = false;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        try {
            $service = Factory::getContainer()->get(CustomerDataService::class);
            $state   = $service->getViewState();
        } catch (\Throwable $exception) {
            Log::add(
                'CopyMyPage customer-data view failed (' . $exception::class . ').',
                Log::ERROR,
                'com_copymypage'
            );
            throw new GenericDataException(
                Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ERROR_FORM'),
                500,
                $exception
            );
        }

        $this->accountCreated    = (bool) ($state['accountCreated'] ?? false);
        $this->accountExpanded   = (bool) ($state['accountExpanded'] ?? false);
        $this->accountForm       = $state['accountForm'] instanceof Form ? $state['accountForm'] : null;
        $this->blocked           = (bool) ($state['blocked'] ?? true);
        $this->captchaEnabled    = (bool) ($state['captchaEnabled'] ?? false);
        $this->cartRevision      = max(0, (int) ($state['cartRevision'] ?? 0));
        $this->form              = $state['form'] instanceof Form ? $state['form'] : null;
        $this->guest             = (bool) ($state['guest'] ?? true);
        $this->loginForm         = $state['loginForm'] instanceof Form ? $state['loginForm'] : null;
        $this->loginModeActive   = (bool) ($state['loginModeActive'] ?? false);
        $this->showAccountOption = (bool) ($state['showAccountOption'] ?? false);
        $this->formFieldNames    = $service->getFormFieldNames();
        $this->markupAttributes  = $service->getMarkupAttributes();

        $this->prepareDocument();

        $wa = $this->document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_copymypage');
        $wa->useStyle('joomla.fontawesome');
        $wa->useScript('copymypage.customer-data');

        parent::display($tpl);
    }

    private function prepareDocument(): void
    {
        $app = Factory::getApplication();
        $app->allowCache(false);
        $app->setHeader(
            'Cache-Control',
            'no-store, no-cache, must-revalidate, private, max-age=0',
            true
        );
        $app->setHeader('Pragma', 'no-cache', true);
        $this->document->setTitle(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_TITLE'));
        $this->document->setDescription(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_META_DESCRIPTION'));
        $this->document->setMetaData('robots', 'noindex, nofollow');
    }
}
