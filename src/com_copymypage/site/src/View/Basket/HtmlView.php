<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Component\CopyMyPage\Site\View\Basket;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * HTML view for the CopyMyPage basket drawer and fallback page.
 */
class HtmlView extends BaseHtmlView
{
    /** @var array<string, mixed> */
    protected array $integrationStatus = [];

    /** @var array<int, object> */
    protected array $items = [];

    protected ?object $order = null;

    protected ?object $currency = null;

    protected ?object $platform = null;

    protected string $checkoutUrl = '';

    /** @var array<string, bool|int> */
    protected array $displayOptions = [];

    protected string $statusMessage = '';

    /**
     * Display the basket after checking the optional integration first.
     *
     * @param   string|null  $tpl  Template name.
     */
    public function display($tpl = null): void
    {
        // No J2Commerce language, class, component or model is touched before
        // this installed/enabled check has completed.
        $this->integrationStatus = (array) $this->get('J2CommerceStatus');
        $this->preparePrivateDocument();

        if (empty($this->integrationStatus['available'])) {
            $this->statusMessage = Text::_((string) ($this->integrationStatus['messageKey'] ?? ''));
            parent::display($tpl);

            return;
        }

        $app      = Factory::getApplication();
        $language = $app->getLanguage();

        if (!$language->load('com_j2commerce', JPATH_SITE . '/components/com_j2commerce')) {
            $language->load('com_j2commerce', JPATH_SITE);
        }

        $basket = (array) $this->get('Basket');
        $this->integrationStatus = (array) ($basket['status'] ?? $this->integrationStatus);

        if (empty($this->integrationStatus['available'])) {
            $this->statusMessage = Text::_((string) ($this->integrationStatus['messageKey'] ?? ''));
            parent::display($tpl);

            return;
        }

        $this->items          = \is_array($basket['items'] ?? null) ? $basket['items'] : [];
        $this->order          = \is_object($basket['order'] ?? null) ? $basket['order'] : null;
        $this->currency       = \is_object($basket['currency'] ?? null) ? $basket['currency'] : null;
        $this->platform       = \is_object($basket['platform'] ?? null) ? $basket['platform'] : null;
        $this->checkoutUrl    = trim((string) ($basket['checkoutUrl'] ?? ''));
        $this->displayOptions = \is_array($basket['displayOptions'] ?? null)
            ? $basket['displayOptions']
            : [];

        if ($this->items !== []) {
            $this->registerCartAssets();
        }

        parent::display($tpl);
    }

    /**
     * Keep session-specific basket output out of search indexes and caches.
     */
    private function preparePrivateDocument(): void
    {
        $app = Factory::getApplication();

        $app->allowCache(false);
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0', true);
        $app->setHeader('Pragma', 'no-cache', true);

        $this->document->setMetaData('robots', 'noindex, nofollow');
        $this->document->setTitle(Text::_('COM_COPYMYPAGE_BASKET_TITLE'));
    }

    /**
     * Reuse J2Commerce's official quantity and removal AJAX implementation.
     */
    private function registerCartAssets(): void
    {
        $wa = $this->document->getWebAssetManager();

        $wa->registerAndUseScript(
            'com_j2commerce.cart-ajax',
            'media/com_j2commerce/js/site/cart-ajax.js',
            [],
            ['defer' => true],
            ['core']
        );

        $this->document->addScriptOptions('j2commerce.cart', [
            'csrfToken' => Session::getFormToken(),
            'baseUrl'   => Route::_('index.php', false),
            'strings'   => [
                'errorUpdating'   => Text::_('COM_J2COMMERCE_ERROR_UPDATING_CART'),
                'errorRemoving'   => Text::_('COM_J2COMMERCE_ERROR_REMOVING_ITEM'),
                'emptyCart'       => Text::_('COM_COPYMYPAGE_BASKET_COMING_SOON'),
                'confirmClearCart'=> Text::_('COM_J2COMMERCE_CONFIRM_CLEAR_CART'),
            ],
        ]);
    }
}
