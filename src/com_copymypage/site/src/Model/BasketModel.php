<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Component\CopyMyPage\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Basket model for the CopyMyPage component.
 *
 * J2Commerce remains an optional, manually installed dependency until the
 * CopyMyPage backend can own extension setup from milestone 0.1.0 onward.
 */
class BasketModel extends BaseDatabaseModel
{
    private const J2COMMERCE_OPTION = 'com_j2commerce';

    /**
     * Cached J2Commerce availability status.
     *
     * @var array<string, mixed>|null
     */
    private ?array $j2CommerceStatus = null;

    /**
     * Cached basket payload.
     *
     * @var array<string, mixed>|null
     */
    private ?array $basket = null;

    /**
     * Check the optional integration before any J2Commerce class is resolved.
     *
     * @return array{available: bool, code: string, messageKey: string}
     */
    public function getJ2CommerceStatus(): array
    {
        if ($this->j2CommerceStatus !== null) {
            return $this->j2CommerceStatus;
        }

        try {
            if (!ComponentHelper::isInstalled(self::J2COMMERCE_OPTION)) {
                return $this->j2CommerceStatus = [
                    'available'  => false,
                    'code'       => 'not-installed',
                    'messageKey' => 'COM_COPYMYPAGE_BASKET_ERROR_NOT_INSTALLED',
                ];
            }

            if (!ComponentHelper::isEnabled(self::J2COMMERCE_OPTION)) {
                return $this->j2CommerceStatus = [
                    'available'  => false,
                    'code'       => 'disabled',
                    'messageKey' => 'COM_COPYMYPAGE_BASKET_ERROR_DISABLED',
                ];
            }
        } catch (\Throwable $e) {
            $this->logIntegrationError($e);

            return $this->j2CommerceStatus = $this->getUnavailableStatus();
        }

        return $this->j2CommerceStatus = [
            'available'  => true,
            'code'       => 'available',
            'messageKey' => '',
        ];
    }

    /**
     * Load the current session basket through J2Commerce's canonical site model.
     *
     * @return array<string, mixed>
     */
    public function getBasket(): array
    {
        if ($this->basket !== null) {
            return $this->basket;
        }

        // This guard deliberately precedes bootComponent(), model creation and
        // every reference to J2Commerce runtime classes.
        $status = $this->getJ2CommerceStatus();
        $basket = $this->getEmptyBasket($status);

        if (!$status['available']) {
            return $this->basket = $basket;
        }

        try {
            $app       = Factory::getApplication();
            $component = $app->bootComponent(self::J2COMMERCE_OPTION);

            if (!\method_exists($component, 'getMVCFactory')) {
                throw new \RuntimeException('J2Commerce does not expose an MVC factory.');
            }

            $model = $component->getMVCFactory()->createModel(
                'Carts',
                'Site',
                ['ignore_request' => true]
            );

            if (!\is_object($model)
                || !\method_exists($model, 'getItems')
                || !\method_exists($model, 'getOrder')
                || !\method_exists($model, 'getCheckoutUrl')
                || !\method_exists($model, 'getCurrency')) {
                throw new \RuntimeException('The J2Commerce carts model is unavailable or incomplete.');
            }

            $items = $model->getItems();

            if (\method_exists($model, 'validateShippingSelection')) {
                $model->validateShippingSelection();
            }

            $order = $model->getOrder();

            if (\is_object($order) && \method_exists($order, 'getItems')) {
                $processedItems = $order->getItems();

                if (\is_array($processedItems)) {
                    $items = $processedItems;
                }
            }

            $helperClass = '\\J2Commerce\\Component\\J2commerce\\Administrator\\Helper\\J2CommerceHelper';

            if (!\class_exists($helperClass)) {
                throw new \RuntimeException('The J2Commerce helper class is unavailable.');
            }

            $config = $helperClass::config();

            $basket = [
                'status'         => $status,
                'items'          => \is_array($items) ? array_values($items) : [],
                'order'          => \is_object($order) ? $order : null,
                'currency'       => $model->getCurrency(),
                'platform'       => $helperClass::platform(),
                'checkoutUrl'    => (string) $model->getCheckoutUrl(),
                'displayOptions' => $this->getDisplayOptions($config),
            ];
        } catch (\Throwable $e) {
            $this->logIntegrationError($e);
            $basket = $this->getEmptyBasket($this->getUnavailableStatus());
        }

        return $this->basket = $basket;
    }

    /**
     * Build a predictable empty payload for unavailable and empty states.
     *
     * @param   array<string, mixed>  $status  Integration status.
     *
     * @return array<string, mixed>
     */
    private function getEmptyBasket(array $status): array
    {
        return [
            'status'         => $status,
            'items'          => [],
            'order'          => null,
            'currency'       => null,
            'platform'       => null,
            'checkoutUrl'    => '',
            'displayOptions' => [
                'checkoutPriceDisplay' => 0,
                'showPriceField'       => true,
                'showQuantityField'    => true,
                'showSku'              => true,
                'showThumbCart'        => true,
            ],
        ];
    }

    /**
     * Normalize the J2Commerce cart presentation switches used by the template.
     *
     * @param   object  $config  J2Commerce configuration helper.
     *
     * @return array<string, bool|int>
     */
    private function getDisplayOptions(object $config): array
    {
        if (!\method_exists($config, 'get')) {
            return $this->getEmptyBasket($this->getUnavailableStatus())['displayOptions'];
        }

        return [
            'checkoutPriceDisplay' => (int) $config->get('checkout_price_display_options', 0),
            'showPriceField'       => (int) $config->get('show_price_field', 1) === 1,
            'showQuantityField'    => (int) $config->get('show_qty_field', 1) === 1,
            'showSku'              => (int) $config->get('show_sku', 1) === 1,
            'showThumbCart'        => (int) $config->get('show_thumb_cart', 1) === 1,
        ];
    }

    /**
     * Return the localized runtime-error state.
     *
     * @return array{available: bool, code: string, messageKey: string}
     */
    private function getUnavailableStatus(): array
    {
        return [
            'available'  => false,
            'code'       => 'unavailable',
            'messageKey' => 'COM_COPYMYPAGE_BASKET_ERROR_UNAVAILABLE',
        ];
    }

    /**
     * Record integration details without exposing them in the storefront.
     */
    private function logIntegrationError(\Throwable $error): void
    {
        Log::add(
            'J2Commerce basket integration failed: ' . $error->getMessage(),
            Log::ERROR,
            'com_copymypage.basket'
        );
    }
}
