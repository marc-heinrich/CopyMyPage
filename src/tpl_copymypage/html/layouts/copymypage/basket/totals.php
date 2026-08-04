<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$order       = \is_object($displayData['order'] ?? null) ? $displayData['order'] : null;
$checkoutUrl = trim((string) ($displayData['checkoutUrl'] ?? ''));
$totals      = $order !== null && \method_exists($order, 'get_formatted_order_totals')
    ? $order->get_formatted_order_totals()
    : [];
$helperClass = '\\J2Commerce\\Component\\J2commerce\\Administrator\\Helper\\J2CommerceHelper';

if (!\is_array($totals)) {
    $totals = [];
}
?>
<div class="cart-totals-block cmp-basket-totals">
    <h2 class="cmp-basket-totals__title"><?php echo Text::_('COM_J2COMMERCE_CART_TOTALS'); ?></h2>

    <?php if ($totals !== []) : ?>
        <dl class="cmp-basket-totals__list">
            <?php foreach ($totals as $total) : ?>
                <div class="cmp-basket-totals__row">
                    <dt>
                        <?php
                        // Labels and optional links are formatted by J2Commerce and its plugins.
                        echo $total['label'] ?? '';

                        if (isset($total['link'])) {
                            echo $total['link'];
                        }
                        ?>
                    </dt>
                    <dd><?php echo $total['value'] ?? ''; ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>

    <?php if ($order !== null && $checkoutUrl !== '') : ?>
        <div class="cmp-basket-totals__checkout">
            <a
                class="uk-button uk-button-primary cmp-button cmp-button--primary cmp-basket-totals__checkout-button"
                href="<?php echo htmlspecialchars(Route::_($checkoutUrl), ENT_QUOTES, 'UTF-8'); ?>"
                target="_top"
            >
                <span uk-icon="icon: lock" aria-hidden="true"></span>
                <span><?php echo Text::_('COM_J2COMMERCE_PROCEED_TO_CHECKOUT'); ?></span>
                <span uk-icon="icon: chevron-right" aria-hidden="true"></span>
            </a>

            <?php if (\class_exists($helperClass)) : ?>
                <?php echo $helperClass::plugin()->eventWithHtml('AfterDisplayCheckoutButton', [$order]); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
