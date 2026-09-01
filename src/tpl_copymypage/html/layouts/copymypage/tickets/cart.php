<?php
/**
 * @package     Joomla.Site
 * @subpackage  Layouts.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$escape         = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$validAttribute = static function (mixed $value): string {
    $value = strtolower(trim((string) $value));

    return preg_match('/^data-[a-z0-9-]+$/', $value) ? $value : '';
};
$validFieldName = static function (mixed $value): string {
    $value = trim((string) $value);

    return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $value) ? $value : '';
};
$cart       = \is_array($displayData['cart'] ?? null) ? $displayData['cart'] : [];
$attributes = [];

foreach ((array) ($displayData['markupAttributes'] ?? []) as $key => $attribute) {
    $attributes[$key] = $validAttribute($attribute);
}

$formFieldNames = \is_array($displayData['formFieldNames'] ?? null)
    ? $displayData['formFieldNames']
    : [];
$revisionField  = $validFieldName($formFieldNames['expectedCartRevision'] ?? '');
$cartRevision   = max(0, (int) ($cart['cartRevision'] ?? 0));

$idPrefix     = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($displayData['idPrefix'] ?? 'cmp-ticket-cart')));
$idPrefix     = $idPrefix !== '' ? $idPrefix : 'cmp-ticket-cart';
$showTitle    = !\array_key_exists('showTitle', $displayData) || !empty($displayData['showTitle']);
$titleId      = $idPrefix . '-title';
$title        = Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CART_TITLE');
$removeAction = Route::_('index.php?option=com_copymypage&task=ticketcart.remove');
$clearAction  = Route::_('index.php?option=com_copymypage&task=ticketcart.clear');
$ticketSelectionUrl = Route::_('index.php?option=com_copymypage&view=ticketselection');
$showTicketSelectionBack = !empty($displayData['showTicketSelectionBack']);
$cartItems    = \is_array($cart['items'] ?? null) ? $cart['items'] : [];
$cartActive   = !empty($cart['active']) && $cartItems !== [];
$secondsLeft  = max(0, (int) ($cart['secondsLeft'] ?? 0));
$minutesLeft  = max(1, (int) ceil($secondsLeft / 60));
?>
<aside
    class="cmp-ticket-cart"
    <?php if ($showTitle) : ?>
        aria-labelledby="<?php echo $escape($titleId); ?>"
    <?php else : ?>
        aria-label="<?php echo $escape($title); ?>"
    <?php endif; ?>
    <?php if (($attributes['root'] ?? '') !== '') : ?>
        <?php echo $attributes['root']; ?>="1"
    <?php endif; ?>
    <?php if (($attributes['cart'] ?? '') !== '') : ?>
        <?php echo $attributes['cart']; ?>="1"
    <?php endif; ?>
>
    <?php if ($showTitle) : ?>
        <h2 id="<?php echo $escape($titleId); ?>" class="cmp-ticket-cart__title">
            <?php echo $escape($title); ?>
        </h2>
    <?php endif; ?>
    <p
        class="cmp-ticket-cart__empty"
        role="status"
        <?php echo $cartActive ? ' hidden' : ''; ?>
        <?php if (($attributes['cartEmpty'] ?? '') !== '') : ?>
            <?php echo $attributes['cartEmpty']; ?>
        <?php endif; ?>
    >
        <?php echo $escape(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CART_EMPTY')); ?>
    </p>

    <ul
        class="cmp-ticket-cart__items"
        <?php if (($attributes['cartItems'] ?? '') !== '') : ?>
            <?php echo $attributes['cartItems']; ?>
        <?php endif; ?>
    >
        <?php foreach ($cartItems as $cartItem) : ?>
            <?php $cartEventId = max(0, (int) ($cartItem['eventId'] ?? 0)); ?>
            <li class="cmp-ticket-cart-item">
                <div class="cmp-ticket-cart-item__copy">
                    <strong><?php echo $escape($cartItem['title'] ?? ''); ?></strong>
                    <small><?php echo $escape($cartItem['dateLabel'] ?? ''); ?></small>
                    <?php foreach ((array) ($cartItem['prices'] ?? []) as $cartPrice) : ?>
                        <span>
                            <?php echo $escape(Text::sprintf(
                                'COM_COPYMYPAGE_TICKET_SELECTION_CART_LINE',
                                (int) ($cartPrice['quantity'] ?? 0),
                                $cartPrice['label'] ?? '',
                                $cartPrice['lineFormatted'] ?? ''
                            )); ?>
                        </span>
                    <?php endforeach; ?>
                    <?php if (trim((string) ($cartItem['selectedSeatsLabel'] ?? '')) !== '') : ?>
                        <span class="cmp-ticket-cart-item__seat-count">
                            <?php echo $escape($cartItem['selectedSeatsLabel']); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (empty($cartItem['continuable'])
                        && trim((string) ($cartItem['statusLabel'] ?? '')) !== '') : ?>
                        <small class="cmp-ticket-cart-item__status">
                            <?php echo $escape($cartItem['statusLabel']); ?>
                        </small>
                    <?php endif; ?>
                </div>
                <form
                    action="<?php echo $escape($removeAction); ?>"
                    method="post"
                    <?php if (($attributes['removeForm'] ?? '') !== '') : ?>
                        <?php echo $attributes['removeForm']; ?>
                    <?php endif; ?>
                >
                    <input type="hidden" name="event_id" value="<?php echo $cartEventId; ?>">
                    <input type="hidden" name="return_view" value="basket">
                    <?php if ($revisionField !== '') : ?>
                        <input
                            type="hidden"
                            name="<?php echo $escape($revisionField); ?>"
                            value="<?php echo $cartRevision; ?>"
                            <?php if (($attributes['revisionField'] ?? '') !== '') : ?>
                                <?php echo $attributes['revisionField']; ?>
                            <?php endif; ?>
                        >
                    <?php endif; ?>
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <button
                        class="uk-button uk-button-danger cmp-button cmp-button--danger cmp-button--icon cmp-ticket-cart-item__remove"
                        type="submit"
                        aria-label="<?php echo $escape(Text::sprintf(
                            'COM_COPYMYPAGE_TICKET_SELECTION_CART_REMOVE_ARIA',
                            $cartItem['title'] ?? ''
                        )); ?>"
                    >
                        <span uk-icon="icon: trash" aria-hidden="true"></span>
                    </button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>

    <div
        class="cmp-ticket-cart__summary"
        <?php echo $cartActive ? '' : ' hidden'; ?>
        <?php if (($attributes['cartSummary'] ?? '') !== '') : ?>
            <?php echo $attributes['cartSummary']; ?>
        <?php endif; ?>
    >
        <p class="cmp-ticket-cart__expiry">
            <span uk-icon="icon: future" aria-hidden="true"></span>
            <span><?php echo $escape(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CART_EXPIRES')); ?></span>
            <time
                datetime="<?php echo $escape($cart['expiresAt'] ?? ''); ?>"
                <?php if (($attributes['cartExpiry'] ?? '') !== '') : ?>
                    <?php echo $attributes['cartExpiry']; ?>
                <?php endif; ?>
            >
                <?php echo $escape(Text::plural(
                    'COM_COPYMYPAGE_TICKET_SELECTION_CART_MINUTES',
                    $minutesLeft
                )); ?>
            </time>
        </p>
        <p class="cmp-ticket-cart__total">
            <span><?php echo $escape(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CART_TOTAL')); ?></span>
            <strong
                <?php if (($attributes['cartTotal'] ?? '') !== '') : ?>
                    <?php echo $attributes['cartTotal']; ?>
                <?php endif; ?>
            >
                <?php echo $escape($cart['totalFormatted'] ?? ''); ?>
            </strong>
        </p>
        <div class="cmp-ticket-cart__actions">
            <form
                action="<?php echo $escape($clearAction); ?>"
                method="post"
                <?php if (($attributes['clearForm'] ?? '') !== '') : ?>
                    <?php echo $attributes['clearForm']; ?>
                <?php endif; ?>
            >
                <input type="hidden" name="return_view" value="basket">
                <?php if ($revisionField !== '') : ?>
                    <input
                        type="hidden"
                        name="<?php echo $escape($revisionField); ?>"
                        value="<?php echo $cartRevision; ?>"
                        <?php if (($attributes['revisionField'] ?? '') !== '') : ?>
                            <?php echo $attributes['revisionField']; ?>
                        <?php endif; ?>
                    >
                <?php endif; ?>
                <?php echo HTMLHelper::_('form.token'); ?>
                <button class="uk-button uk-button-danger cmp-button cmp-button--danger" type="submit">
                    <span uk-icon="icon: trash" aria-hidden="true"></span>
                    <span><?php echo $escape(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CART_CLEAR')); ?></span>
                </button>
            </form>
            <?php if ($showTicketSelectionBack) : ?>
                <a
                    class="uk-button uk-button-default cmp-button cmp-button--secondary cmp-ticket-cart__back"
                    href="<?php echo $escape($ticketSelectionUrl); ?>"
                    target="_top"
                >
                    <span uk-icon="icon: chevron-left" aria-hidden="true"></span>
                    <span><?php echo $escape(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_CART_BACK')); ?></span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</aside>
