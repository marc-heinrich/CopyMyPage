<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Site\View\Orderreview;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\CopyMyPage\Site\Service\OrderCheckoutService;
use Joomla\Component\CopyMyPage\Site\Service\OrderReviewService;

/**
 * Guarded order summary and final confirmation for Step 4.
 */
final class HtmlView extends BaseHtmlView
{
    protected bool $blocked = true;

    protected array $cart = [];

    protected array $customer = [];

    protected string $customerDataUrl = '';

    protected array $items = [];

    protected float $baseTotal = 0.0;

    protected string $baseTotalFormatted = '';

    protected string $checkoutAction = '';

    protected array $checkoutIssues = [];

    protected bool $checkoutReady = false;

    protected string $checkoutSignature = '';

    protected string $currency = '';

    protected int $expectedRevision = 0;

    protected array $paymentProviders = [];

    protected bool $paymentRequired = false;

    protected array $terms = [];

    public function display($tpl = null): void
    {
        try {
            $container = Factory::getContainer();
            $state     = $container->get(OrderReviewService::class)->getViewState();
            $state     = array_replace(
                $state,
                $container->get(OrderCheckoutService::class)->getViewState($state)
            );
            $this->blocked = (bool) ($state['blocked'] ?? true);
            $this->cart = \is_array($state['cart'] ?? null) ? $state['cart'] : [];
            $this->customer = \is_array($state['customer'] ?? null) ? $state['customer'] : [];
            $this->customerDataUrl = (string) ($state['customerDataUrl'] ?? '');
            $this->items = \is_array($state['items'] ?? null) ? $state['items'] : [];
            $this->baseTotal = max(0.0, (float) ($state['baseTotal'] ?? 0.0));
            $this->baseTotalFormatted = (string) ($state['baseTotalFormatted'] ?? '');
            $this->checkoutAction = (string) ($state['checkoutAction'] ?? '');
            $this->checkoutIssues = \is_array($state['checkoutIssues'] ?? null)
                ? $state['checkoutIssues']
                : [];
            $this->checkoutReady = (bool) ($state['checkoutReady'] ?? false);
            $this->checkoutSignature = (string) ($state['checkoutSignature'] ?? '');
            $this->currency = (string) ($state['currency'] ?? '');
            $this->expectedRevision = max(0, (int) ($state['expectedRevision'] ?? 0));
            $this->paymentProviders = \is_array($state['paymentProviders'] ?? null)
                ? $state['paymentProviders']
                : [];
            $this->paymentRequired = (bool) ($state['paymentRequired'] ?? false);
            $this->terms = \is_array($state['terms'] ?? null) ? $state['terms'] : [];
        } catch (\Throwable $exception) {
            Log::add(
                'CopyMyPage order-review view failed (' . $exception::class . ').',
                Log::ERROR,
                'com_copymypage'
            );
            throw new GenericDataException(
                Text::_('COM_COPYMYPAGE_ORDER_REVIEW_BLOCKED_MESSAGE'),
                500,
                $exception
            );
        }

        $this->prepareDocument();

        $wa = $this->document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_copymypage');
        $wa->useScript('copymypage.order-review');

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
        $this->document->setTitle(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_TITLE'));
        $this->document->setDescription(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_INTRO'));
        $this->document->setMetaData('robots', 'noindex, nofollow');
    }
}
