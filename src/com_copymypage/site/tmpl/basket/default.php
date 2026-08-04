<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/** @var \Joomla\Component\CopyMyPage\Site\View\Basket\HtmlView $this */

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$titleId = 'cmp-basket-title';
?>
<section class="cmp-basket" aria-labelledby="<?php echo $escape($titleId); ?>">
    <h1 id="<?php echo $escape($titleId); ?>" class="cmp-basket__title">
        <?php echo $escape(Text::_('COM_COPYMYPAGE_BASKET_TITLE')); ?>
    </h1>

    <?php if (empty($this->integrationStatus['available'])) : ?>
        <div class="cmp-basket__notice" role="status">
            <span class="cmp-basket__notice-icon" uk-icon="icon: warning" aria-hidden="true"></span>
            <p><?php echo $escape($this->statusMessage); ?></p>
        </div>
    <?php else : ?>
        <div class="j2commerce-cart cmp-basket__cart">
            <?php if ($this->items === []) : ?>
                <div class="cmp-basket__empty" role="status">
                    <span class="cmp-basket__empty-icon" uk-icon="icon: cart; ratio: 1.35" aria-hidden="true"></span>
                    <p class="cart-no-items"><?php echo $escape(Text::_('COM_COPYMYPAGE_BASKET_COMING_SOON')); ?></p>
                </div>
            <?php else : ?>
                <?php
                $showQuantityField    = (bool) ($this->displayOptions['showQuantityField'] ?? true);
                $showPriceField       = (bool) ($this->displayOptions['showPriceField'] ?? true);
                $showSku              = (bool) ($this->displayOptions['showSku'] ?? true);
                $showThumbCart        = (bool) ($this->displayOptions['showThumbCart'] ?? true);
                $checkoutPriceDisplay = (int) ($this->displayOptions['checkoutPriceDisplay'] ?? 0);
                ?>
                <ul class="cmp-basket__items">
                    <?php foreach ($this->items as $item) : ?>
                        <?php
                        $cartItemId = (int) ($item->cartitem_id ?? $item->j2commerce_cartitem_id ?? 0);
                        $currentQty = max(1, (int) ($item->orderitem_quantity ?? $item->product_qty ?? 1));
                        $minQty     = max(1, (int) ($item->min_sale_qty ?? 1));
                        $maxQty     = max(0, (int) ($item->max_sale_qty ?? 0));
                        $thumbImage = '';
                        $backOrderText = '';

                        if ($this->platform !== null && \method_exists($this->platform, 'getRegistry')) {
                            try {
                                $itemParams   = $this->platform->getRegistry($item->orderitem_params ?? '{}');
                                $rawThumbnail = trim((string) $itemParams->get('thumb_image', ''));
                                $backOrderText = trim((string) $itemParams->get('back_order_item', ''));

                                if ($rawThumbnail !== '' && \method_exists($this->platform, 'getImagePath')) {
                                    $cleanImage = HTMLHelper::_(
                                        'cleanImageURL',
                                        $this->platform->getImagePath($rawThumbnail)
                                    );
                                    $thumbImage = trim((string) ($cleanImage->url ?? ''));
                                }
                            } catch (\Throwable) {
                                $thumbImage = '';
                            }
                        }

                        $unitPrice = '';
                        $lineTotal = '';
                        $discountInfo = null;

                        if ($this->order !== null && $this->currency !== null) {
                            if ($showPriceField && \method_exists($this->order, 'get_formatted_lineitem_price')) {
                                $unitPrice = (string) $this->currency->format(
                                    $this->order->get_formatted_lineitem_price($item, $checkoutPriceDisplay)
                                );
                            }

                            if (\method_exists($this->order, 'get_formatted_lineitem_total')) {
                                $lineTotal = (string) $this->currency->format(
                                    $this->order->get_formatted_lineitem_total($item, $checkoutPriceDisplay)
                                );
                            }

                            if (\method_exists($this->order, 'get_lineitem_discount_info')) {
                                $discountInfo = $this->order->get_lineitem_discount_info(
                                    $item,
                                    $checkoutPriceDisplay
                                );
                            }
                        }
                        ?>
                        <li
                            class="j2commerce-cart-item cmp-basket-item"
                            data-cartitem-id="<?php echo $cartItemId; ?>"
                        >
                            <article class="cmp-basket-item__body">
                                <?php if ($showThumbCart && $thumbImage !== '') : ?>
                                    <div class="cmp-basket-item__media cart-thumb-image">
                                        <img
                                            src="<?php echo $escape($thumbImage); ?>"
                                            alt="<?php echo $escape($item->orderitem_name ?? ''); ?>"
                                            width="96"
                                            height="96"
                                            loading="lazy"
                                        >
                                    </div>
                                <?php endif; ?>

                                <div class="cmp-basket-item__details">
                                    <h2 class="cmp-basket-item__name">
                                        <?php echo $escape($item->orderitem_name ?? ''); ?>
                                    </h2>

                                    <?php if ($showSku && !empty($item->orderitem_sku)) : ?>
                                        <p class="cmp-basket-item__sku">
                                            <span><?php echo $escape(Text::_('COM_J2COMMERCE_CART_LINE_ITEM_SKU')); ?>:</span>
                                            <strong><?php echo $escape($item->orderitem_sku); ?></strong>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($item->orderitemattributes)) : ?>
                                        <div class="cmp-basket-item__attributes">
                                            <?php
                                            // J2Commerce's canonical layout owns and escapes its attribute markup.
                                            echo LayoutHelper::render(
                                                'orderitem.attributes',
                                                [
                                                    'attributes' => $item->orderitemattributes,
                                                    'item'       => $item,
                                                    'context'    => 'cart',
                                                    'variant'    => 'full',
                                                    'framework'  => 'uikit',
                                                ],
                                                JPATH_ROOT . '/components/com_j2commerce/layouts'
                                            );
                                            ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($unitPrice !== '') : ?>
                                        <p class="cmp-basket-item__unit-price">
                                            <span><?php echo $escape(Text::_('COM_J2COMMERCE_CART_LINE_ITEM_UNIT_PRICE')); ?>:</span>
                                            <strong><?php echo $escape($unitPrice); ?></strong>
                                        </p>
                                    <?php endif; ?>

                                    <?php if ($backOrderText !== '') : ?>
                                        <p class="cmp-basket-item__backorder">
                                            <?php echo $escape(Text::_($backOrderText)); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <div class="cmp-basket-item__actions">
                                    <?php if ($showQuantityField && $cartItemId > 0) : ?>
                                        <div
                                            class="j2commerce-qty-controls cmp-basket-quantity"
                                            data-cartitem-id="<?php echo $cartItemId; ?>"
                                            data-min-qty="<?php echo $minQty; ?>"
                                            data-max-qty="<?php echo $maxQty; ?>"
                                        >
                                            <button
                                                type="button"
                                                class="uk-button uk-button-default cmp-button cmp-button--secondary cmp-button--icon j2commerce-qty-minus"
                                                aria-label="<?php echo $escape(Text::_('COM_J2COMMERCE_DECREASE_QUANTITY')); ?>"
                                                <?php echo $currentQty <= $minQty ? ' disabled' : ''; ?>
                                            >
                                                <span uk-icon="icon: minus" aria-hidden="true"></span>
                                            </button>
                                            <input
                                                class="uk-input j2commerce-qty-input cmp-basket-quantity__input"
                                                type="text"
                                                name="qty[<?php echo $cartItemId; ?>]"
                                                value="<?php echo $currentQty; ?>"
                                                min="<?php echo $minQty; ?>"
                                                <?php echo $maxQty > 0 ? ' max="' . $maxQty . '"' : ''; ?>
                                                step="1"
                                                pattern="[0-9]*"
                                                inputmode="numeric"
                                                aria-label="<?php echo $escape(Text::_('COM_J2COMMERCE_CART_LINE_ITEM_QUANTITY')); ?>"
                                            >
                                            <button
                                                type="button"
                                                class="uk-button uk-button-default cmp-button cmp-button--secondary cmp-button--icon j2commerce-qty-plus"
                                                aria-label="<?php echo $escape(Text::_('COM_J2COMMERCE_INCREASE_QUANTITY')); ?>"
                                                <?php echo $maxQty > 0 && $currentQty >= $maxQty ? ' disabled' : ''; ?>
                                            >
                                                <span uk-icon="icon: plus" aria-hidden="true"></span>
                                            </button>
                                        </div>
                                    <?php else : ?>
                                        <input
                                            type="hidden"
                                            name="qty[<?php echo $cartItemId; ?>]"
                                            value="<?php echo $currentQty; ?>"
                                        >
                                    <?php endif; ?>

                                    <div class="cart-line-subtotal cmp-basket-item__total" aria-live="polite">
                                        <?php if (\is_object($discountInfo)
                                            && !empty($discountInfo->original_price)
                                            && $discountInfo->original_price > $discountInfo->final_price) : ?>
                                            <span class="line-total-original">
                                                <?php echo $escape($this->currency->format($discountInfo->original_price)); ?>
                                            </span>
                                            <span class="line-total-value"><?php echo $escape($this->currency->format($discountInfo->final_price)); ?></span>
                                        <?php else : ?>
                                            <span class="line-total-value"><?php echo $escape($lineTotal); ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($cartItemId > 0) : ?>
                                        <button
                                            type="button"
                                            class="uk-button uk-button-danger cmp-button cmp-button--danger cmp-button--icon j2commerce-remove-ajax cmp-basket-item__remove"
                                            data-cartitem-id="<?php echo $cartItemId; ?>"
                                            title="<?php echo $escape(Text::_('COM_J2COMMERCE_REMOVE')); ?>"
                                            aria-label="<?php echo $escape(Text::_('COM_J2COMMERCE_REMOVE')); ?>"
                                        >
                                            <span uk-icon="icon: trash" aria-hidden="true"></span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </article>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php
                echo LayoutHelper::render(
                    'copymypage.basket.totals',
                    [
                        'order'       => $this->order,
                        'checkoutUrl' => $this->checkoutUrl,
                    ]
                );
                ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
