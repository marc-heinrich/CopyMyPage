<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/** @var \Joomla\Component\CopyMyPage\Site\View\Orderreview\HtmlView $this */

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$introKey = $this->blocked
    ? 'COM_COPYMYPAGE_ORDER_REVIEW_BLOCKED_MESSAGE'
    : 'COM_COPYMYPAGE_ORDER_REVIEW_INTRO';
$customer  = $this->customer;
$fullName  = trim((string) ($customer['firstName'] ?? '') . ' ' . (string) ($customer['lastName'] ?? ''));
$streetLine = trim((string) ($customer['street'] ?? '') . ' ' . (string) ($customer['houseNumber'] ?? ''));
$cityLine   = trim((string) ($customer['postcode'] ?? '') . ' ' . (string) ($customer['city'] ?? ''));
$providerCount = \count($this->paymentProviders);
$singleProvider = $providerCount === 1 ? $this->paymentProviders[0] : null;
$displayTotal   = \is_array($singleProvider)
    ? (string) ($singleProvider['totalFormatted'] ?? $this->baseTotalFormatted)
    : $this->baseTotalFormatted;
$totalLabelKey = $this->paymentRequired && $providerCount > 1
    ? 'COM_COPYMYPAGE_ORDER_REVIEW_TICKET_SUBTOTAL'
    : 'COM_COPYMYPAGE_TICKET_SELECTION_CART_TOTAL';
$canSubmit = !$this->blocked
    && $this->checkoutReady
    && $this->checkoutAction !== ''
    && $this->checkoutSignature !== '';
$requiresTermsAcceptance = !$this->blocked && $this->terms !== [];
$submitDisabled = !$canSubmit || $requiresTermsAcceptance;
$buttonKey = $this->blocked
    ? 'COM_COPYMYPAGE_ORDER_REVIEW_CONTINUE'
    : ($this->paymentRequired
        ? 'COM_COPYMYPAGE_ORDER_REVIEW_ORDER_BUTTON'
        : 'COM_COPYMYPAGE_ORDER_REVIEW_ORDER_FREE_BUTTON');
?>
<div class="cmp-customer-data cmp-order-review">
    <div class="uk-container">
        <?php echo LayoutHelper::render(
            'copymypage.tickets.steps',
            [
                'activeStep' => 4,
                'totalSteps' => 5,
            ]
        ); ?>

        <header class="cmp-customer-data__header">
            <h1 class="cmp-customer-data__title">
                <?php echo $escape(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_TITLE')); ?>
            </h1>
            <p class="cmp-customer-data__intro">
                <?php echo $escape(Text::_($introKey)); ?>
            </p>
        </header>

        <form
            class="cmp-form cmp-order-review__form"
            action="<?php echo $escape($this->checkoutAction); ?>"
            method="post"
            data-cmp-order-review-form
        >
        <section
            class="cmp-customer-data__blocked cmp-order-review__status<?php echo $this->blocked ? ' cmp-order-review__status--blocked' : ''; ?>"
            <?php echo $this->blocked ? 'role="alert" aria-labelledby="cmp-order-review-status-title"' : ''; ?>
        >
            <?php if ($this->blocked) : ?>
                <h2 id="cmp-order-review-status-title">
                    <?php echo $escape(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_BLOCKED_TITLE')); ?>
                </h2>
            <?php else : ?>
                <div class="cmp-order-review__content">
                    <section class="cmp-order-review__section" aria-labelledby="cmp-order-review-tickets-title">
                        <h3 id="cmp-order-review-tickets-title" class="cmp-order-review__section-title">
                            <span class="cmp-order-review__section-icons" aria-hidden="true">
                                <span
                                    class="cmp-order-review__section-icon cmp-order-review__section-icon--ticket"
                                ></span>
                                <span
                                    class="cmp-order-review__section-icon cmp-order-review__section-icon--seat"
                                ></span>
                            </span>
                            <span><?php echo $escape(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_TICKETS_TITLE')); ?></span>
                        </h3>

                        <ul class="cmp-order-review__events">
                            <?php foreach ($this->items as $item) : ?>
                                <li class="cmp-order-review__event">
                                    <header class="cmp-order-review__event-header">
                                        <h4><?php echo $escape($item['title'] ?? ''); ?></h4>
                                        <?php if ((string) ($item['dateLabel'] ?? '') !== '') : ?>
                                            <time<?php echo (string) ($item['dateTime'] ?? '') !== '' ? ' datetime="' . $escape($item['dateTime']) . '"' : ''; ?>>
                                                <?php echo $escape($item['dateLabel']); ?>
                                            </time>
                                        <?php endif; ?>
                                    </header>

                                    <ul class="cmp-order-review__prices">
                                        <?php foreach ((array) ($item['prices'] ?? []) as $price) : ?>
                                            <li class="cmp-order-review__price">
                                                <span class="cmp-order-review__price-copy">
                                                    <span>
                                                        <?php echo $escape(Text::sprintf(
                                                            'COM_COPYMYPAGE_ORDER_REVIEW_PRICE_LINE',
                                                            (int) ($price['quantity'] ?? 0),
                                                            (string) ($price['label'] ?? '')
                                                        )); ?>
                                                    </span>
                                                    <small>
                                                        <?php echo $escape(Text::sprintf(
                                                            'COM_COPYMYPAGE_ORDER_REVIEW_UNIT_PRICE',
                                                            (string) ($price['unitFormatted'] ?? '')
                                                        )); ?>
                                                    </small>
                                                </span>
                                                <strong class="uk-badge cmp-order-review__price-badge">
                                                    <?php echo $escape($price['lineFormatted'] ?? ''); ?>
                                                </strong>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>

                                    <div class="cmp-order-review__seats">
                                        <h5><?php echo $escape(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_SEATS_TITLE')); ?></h5>
                                        <ul>
                                            <?php foreach ((array) ($item['seats'] ?? []) as $seat) : ?>
                                                <li><?php echo $escape($seat['label'] ?? ''); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>

                    <section class="cmp-order-review__section" aria-labelledby="cmp-order-review-billing-title">
                        <h3 id="cmp-order-review-billing-title">
                            <?php echo $escape(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_BILLING_TITLE')); ?>
                        </h3>

                        <address class="cmp-order-review__billing">
                            <div class="cmp-order-review__address">
                                <strong><?php echo $escape($fullName); ?></strong>
                                <span><?php echo $escape($streetLine); ?></span>
                                <span><?php echo $escape($cityLine); ?></span>
                                <?php if ((string) ($customer['regionName'] ?? '') !== '') : ?>
                                    <span><?php echo $escape($customer['regionName']); ?></span>
                                <?php endif; ?>
                                <span><?php echo $escape($customer['countryName'] ?? ''); ?></span>
                            </div>

                            <dl class="cmp-order-review__contact">
                                <div>
                                    <dt><?php echo $escape(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_EMAIL')); ?></dt>
                                    <dd><?php echo $escape($customer['email'] ?? ''); ?></dd>
                                </div>
                                <?php if ((string) ($customer['telephone'] ?? '') !== '') : ?>
                                    <div>
                                        <dt><?php echo $escape(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_PHONE')); ?></dt>
                                        <dd><?php echo $escape($customer['telephone']); ?></dd>
                                    </div>
                                <?php endif; ?>
                            </dl>
                        </address>
                    </section>

                    <section
                        class="cmp-order-review__section cmp-order-review__section--checkout"
                        aria-labelledby="cmp-order-review-payment-title"
                    >
                        <h3 id="cmp-order-review-payment-title">
                            <?php echo $escape(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_PAYMENT_TITLE')); ?>
                        </h3>

                        <?php if ($this->paymentRequired) : ?>
                            <p id="cmp-order-review-payment-intro" class="cmp-order-review__section-intro">
                                <?php echo $escape(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_PAYMENT_INTRO')); ?>
                            </p>

                            <?php if ($this->paymentProviders !== []) : ?>
                                <fieldset
                                    class="cmp-order-review__payment-options"
                                    aria-describedby="cmp-order-review-payment-intro"
                                >
                                    <legend class="visually-hidden">
                                        <?php echo $escape(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_PAYMENT_OPTIONS_LABEL')); ?>
                                    </legend>

                                    <?php foreach ($this->paymentProviders as $index => $provider) : ?>
                                        <?php
                                        $providerId = 'cmp-order-review-provider-' . $index;
                                        $description = trim((string) ($provider['description'] ?? ''));
                                        $fee = max(0.0, (float) ($provider['fee'] ?? 0.0));
                                        ?>
                                        <label class="cmp-order-review__payment-option" for="<?php echo $escape($providerId); ?>">
                                            <input
                                                id="<?php echo $escape($providerId); ?>"
                                                class="form-check-input cmp-order-review__payment-input"
                                                type="radio"
                                                name="payment_provider"
                                                value="<?php echo $escape($provider['id'] ?? ''); ?>"
                                                required
                                                <?php echo $providerCount === 1 ? 'checked' : ''; ?>
                                            >
                                            <span class="cmp-order-review__payment-copy">
                                                <strong><?php echo $escape($provider['label'] ?? ''); ?></strong>
                                                <?php if ($description !== '') : ?>
                                                    <small><?php echo $escape($description); ?></small>
                                                <?php endif; ?>
                                                <?php if ($fee > 0) : ?>
                                                    <small>
                                                        <?php echo $escape(Text::sprintf(
                                                            'COM_COPYMYPAGE_ORDER_REVIEW_PAYMENT_FEE',
                                                            (string) ($provider['feeFormatted'] ?? '')
                                                        )); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </span>
                                            <span class="cmp-order-review__payment-total">
                                                <span><?php echo $escape(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_PAYMENT_METHOD_TOTAL')); ?></span>
                                                <strong><?php echo $escape($provider['totalFormatted'] ?? ''); ?></strong>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            <?php endif; ?>
                        <?php else : ?>
                            <p class="cmp-order-review__free-payment">
                                <span uk-icon="icon: check" aria-hidden="true"></span>
                                <span><?php echo $escape(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_FREE_PAYMENT_TEXT')); ?></span>
                            </p>
                        <?php endif; ?>
                    </section>

                    <section
                        class="cmp-order-review__section cmp-order-review__section--checkout"
                        aria-labelledby="cmp-order-review-conditions-title"
                    >
                        <h3 id="cmp-order-review-conditions-title">
                            <?php echo $escape(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_CONDITIONS_TITLE')); ?>
                        </h3>

                        <?php if ($this->terms !== []) : ?>
                            <div
                                id="cmp-order-review-terms-details"
                                class="form-check cmp-order-review__terms-details cmp-order-review__terms-consent"
                            >
                                <input
                                    id="cmp-order-review-terms"
                                    class="form-check-input"
                                    type="checkbox"
                                    name="terms_accepted"
                                    value="1"
                                    required
                                    data-cmp-order-review-terms
                                    aria-labelledby="cmp-order-review-terms-copy"
                                >
                                <div id="cmp-order-review-terms-copy" class="cmp-order-review__terms-content">
                                    <label class="form-check-label" for="cmp-order-review-terms">
                                        <?php echo $escape(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_TERMS_ACCEPT')); ?>
                                    </label>
                                    <span class="cmp-order-review__terms-links">
                                        <?php $lastTermKey = \array_key_last($this->terms); ?>
                                        <?php foreach ($this->terms as $termKey => $term) : ?>
                                            <a
                                                href="<?php echo $escape($term['url'] ?? ''); ?>"
                                                aria-haspopup="dialog"
                                                data-cmp-content-drawer="terms"
                                                data-cmp-drawer-title="<?php echo $escape($term['title'] ?? ''); ?>"
                                                data-cmp-drawer-transport="fragment"
                                            >
                                                <?php echo $escape(\count($this->terms) === 1
                                                    ? Text::_('COM_COPYMYPAGE_ORDER_REVIEW_TERMS_LINKS_LABEL')
                                                    : ($term['title'] ?? ''))
                                                    . ($termKey === $lastTermKey ? '.' : ''); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <?php if ($this->checkoutIssues !== []) : ?>
                    <div
                        id="cmp-order-review-issues"
                        class="cmp-order-review__issues"
                        role="alert"
                        aria-labelledby="cmp-order-review-issues-title"
                    >
                        <strong id="cmp-order-review-issues-title">
                            <?php echo $escape(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_UNAVAILABLE_TITLE')); ?>
                        </strong>
                        <ul>
                            <?php foreach ($this->checkoutIssues as $issue) : ?>
                                <li><?php echo $escape($issue); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <footer class="cmp-order-review__total">
                    <span><?php echo $escape(Text::_($totalLabelKey)); ?></span>
                    <strong><?php echo $escape($displayTotal); ?></strong>
                </footer>

                <p id="cmp-order-review-payment-note" class="cmp-order-review__payment-note">
                    <?php echo $escape(Text::_($this->paymentRequired
                        ? 'COM_COPYMYPAGE_ORDER_REVIEW_PAYMENT_NOTE'
                        : 'COM_COPYMYPAGE_ORDER_REVIEW_ORDER_FREE_NOTE')); ?>
                </p>
            <?php endif; ?>
        </section>

        <nav
            class="cmp-customer-data__navigation cmp-order-review__navigation"
            aria-label="<?php echo $escape(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_NAVIGATION_LABEL')); ?>"
        >
            <a
                class="uk-button uk-button-default cmp-button cmp-button--secondary cmp-button--back cmp-customer-data__back"
                href="<?php echo $escape($this->customerDataUrl); ?>"
            >
                <span uk-icon="icon: chevron-left" aria-hidden="true"></span>
                <span><?php echo $escape(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_BACK')); ?></span>
            </a>

            <button
                class="uk-button uk-button-primary cmp-button cmp-button--primary cmp-order-review__continue"
                type="submit"
                data-cmp-order-review-continue
                data-cmp-order-review-ready="<?php echo $canSubmit ? 'true' : 'false'; ?>"
                aria-disabled="<?php echo $submitDisabled ? 'true' : 'false'; ?>"
                <?php echo $submitDisabled ? 'disabled' : ''; ?>
                <?php echo !$this->blocked ? 'aria-describedby="cmp-order-review-payment-note' . ($this->checkoutIssues !== [] ? ' cmp-order-review-issues' : '') . '"' : ''; ?>
            >
                <span uk-icon="icon: lock" aria-hidden="true"></span>
                <span><?php echo $escape(Text::_($buttonKey)); ?></span>
            </button>
        </nav>

        <input type="hidden" name="expectedCartRevision" value="<?php echo $this->expectedRevision; ?>">
        <input type="hidden" name="checkout_signature" value="<?php echo $escape($this->checkoutSignature); ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    </div>
</div>
